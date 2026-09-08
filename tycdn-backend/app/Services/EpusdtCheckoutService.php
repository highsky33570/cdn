<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\OrderType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class EpusdtCheckoutService
{
    /**
     * @return array<string, array<int, string>>
     */
    public function checkoutRules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'order_type' => ['nullable', 'string', 'max:20'],
            'billing_cycle' => ['nullable', 'string', 'in:month,quarter,year,monthly,quarterly,yearly'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'service_instance_id' => ['nullable', 'integer'],
            'fiat_currency' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z]{3,10}$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{order: Order, gateway: array<string, mixed>}
     */
    public function createForUser(User $user, array $validated): array
    {
        $product = Product::query()
            ->whereKey($validated['product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $orderType = $this->normalizeOrderType((string) ($validated['order_type'] ?? 'new'));
        $quantity = max(1, (int) ($validated['quantity'] ?? 1));
        $billingCycle = $this->normalizeBillingCycle((string) ($validated['billing_cycle'] ?? 'monthly'));
        $quote = app(ProductPricingService::class)->quote($product, $billingCycle, $quantity);
        $serviceInstance = $this->resolveTargetServiceInstance($user, $product, $validated, $orderType);

        if (($quote['total_amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'product_id' => '套餐价格配置无效',
            ]);
        }

        $fiatAmount = (float) $quote['total_amount'];
        $fiatCurrency = strtoupper((string) ($quote['currency'] ?: ($validated['fiat_currency'] ?? config('services.epusdt.default_currency', 'USD'))));
        $token = strtolower((string) config('services.epusdt.default_token', 'usdt'));
        $network = strtoupper((string) config('services.epusdt.default_network', 'TRON'));

        $order = Order::create([
            'order_no' => 'TY'.Str::ulid(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_type' => $orderType,
            'billing_cycle' => $billingCycle,
            'quantity' => $quantity,
            'target_service_instance_id' => $serviceInstance?->id,
            'fiat_amount' => $fiatAmount,
            'fiat_currency' => $fiatCurrency,
            'fx_rate_snapshot' => null,
            'amount_usdt' => 0,
            'pay_address' => '',
            'pay_currency' => strtoupper($token),
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(max(1, (int) config('services.epusdt.order_ttl_minutes', 30))),
            'gateway_provider' => 'epusdt',
            'gateway_amount' => $fiatAmount,
            'gateway_currency' => strtolower($fiatCurrency),
            'gateway_status' => 'pending',
        ]);

        return $this->openGatewayTransaction($order, $user, $fiatAmount, $fiatCurrency, $token, $network);
    }

    /**
     * Top up the customer's CDNfly balance.
     *
     * Distinct from a package purchase: no product, no billing cycle, and the
     * amount is chosen by the customer rather than derived from a price list.
     * Settlement is handled by CdnflyProvisionService, which credits the balance
     * instead of provisioning.
     *
     * @return array{order: Order, gateway: array<string, mixed>}
     */
    public function createRechargeForUser(User $user, float $amount, ?string $currency = null): array
    {
        $min = (float) config('services.epusdt.recharge_min', 1);
        $max = (float) config('services.epusdt.recharge_max', 10000);

        if ($amount < $min || $amount > $max) {
            throw ValidationException::withMessages([
                'amount' => "充值金额需在 {$min} 至 {$max} 之间。",
            ]);
        }

        // A recharge can only land somewhere if the account exists upstream.
        if (! $user->cdnfly_user_id) {
            throw ValidationException::withMessages([
                'amount' => 'CDNfly 账号尚未开通，无法充值，请先完成账号同步。',
            ]);
        }

        $fiatAmount = round($amount, 2);
        $fiatCurrency = strtoupper((string) ($currency ?: config('services.epusdt.default_currency', 'USD')));
        $token = strtolower((string) config('services.epusdt.default_token', 'usdt'));
        $network = strtoupper((string) config('services.epusdt.default_network', 'TRON'));

        $order = Order::create([
            'order_no' => 'TY'.Str::ulid(),
            'user_id' => $user->id,
            'product_id' => null,
            'order_type' => OrderType::RECHARGE,
            'billing_cycle' => null,
            'quantity' => 1,
            'target_service_instance_id' => null,
            'fiat_amount' => $fiatAmount,
            'fiat_currency' => $fiatCurrency,
            'fx_rate_snapshot' => null,
            'amount_usdt' => 0,
            'pay_address' => '',
            'pay_currency' => strtoupper($token),
            'status' => OrderStatus::PENDING,
            'expire_at' => now()->addMinutes(max(1, (int) config('services.epusdt.order_ttl_minutes', 30))),
            'gateway_provider' => 'epusdt',
            'gateway_amount' => $fiatAmount,
            'gateway_currency' => strtolower($fiatCurrency),
            'gateway_status' => 'pending',
        ]);

        return $this->openGatewayTransaction($order, $user, $fiatAmount, $fiatCurrency, $token, $network);
    }

    /**
     * Ask EPUSDT for a payment address and record what it returns.
     *
     * Shared by both order kinds so they cannot drift apart — in particular the
     * failure path: the order row must exist first (the gateway is given its
     * order_no), but a gateway failure used to leave it stranded as `pending`
     * with no payment URL, undismissable in the UI and polled by reconciliation
     * forever. Cancel it and surface a 503 instead. Reconciliation still covers
     * the case where the gateway created the transaction but the response never
     * reached us.
     *
     * @return array{order: Order, gateway: array<string, mixed>}
     */
    private function openGatewayTransaction(
        Order $order,
        User $user,
        float $fiatAmount,
        string $fiatCurrency,
        string $token,
        string $network,
    ): array {
        try {
            $gateway = app(EpusdtService::class)->createTransaction([
                'order_id' => $order->order_no,
                'amount' => $fiatAmount,
                'notify_url' => config('services.epusdt.notify_url'),
                'redirect_url' => config('services.epusdt.redirect_url'),
                'currency' => $fiatCurrency,
                'token' => $token,
                'network' => $network,
            ]);
        } catch (\Throwable $e) {
            $order->update([
                'status' => OrderStatus::CANCELLED,
                'gateway_status' => 'create_failed',
                'cancelled_at' => now(),
            ]);

            Log::warning('epusdt checkout creation failed; order cancelled', [
                'order_no' => $order->order_no,
                'order_type' => $order->order_type,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new ServiceUnavailableHttpException(null, '支付网关暂时不可用，请稍后重试。', $e);
        }

        $data = $gateway['data'];
        $paymentUrl = $data['payment_url'] ?? ($data['checkout_url'] ?? null);
        $gatewayExpireAt = isset($data['expiration_time']) && is_numeric($data['expiration_time'])
            ? now()->setTimestamp((int) $data['expiration_time'])
            : null;

        $order->update([
            'gateway_trade_id' => $data['trade_id'] ?? null,
            'gateway_request_id' => $gateway['request_id'] ?? null,
            'gateway_amount' => $data['amount'] ?? $fiatAmount,
            'gateway_currency' => $data['currency'] ?? strtolower($fiatCurrency),
            'gateway_actual_amount' => $data['actual_amount'] ?? null,
            'gateway_payment_url' => $paymentUrl,
            'gateway_token' => $data['token'] ?? $token,
            'gateway_status' => 'pending',
            'gateway_expired_at' => $gatewayExpireAt,
            'pay_address' => $data['receive_address'] ?? null,
            'pay_currency' => strtoupper((string) ($data['token'] ?? $token)),
            'amount_usdt' => $data['actual_amount'] ?? 0,
        ]);

        return [
            'order' => $order->fresh(['product']),
            'gateway' => $gateway,
        ];
    }

    /**
     * @param  array{order: Order, gateway: array<string, mixed>}  $checkout
     * @return array<string, mixed>
     */
    public function checkoutResponse(array $checkout): array
    {
        $order = $checkout['order'];
        $gateway = $checkout['gateway'];
        $data = $gateway['data'] ?? [];

        return [
            'order_no' => $order->order_no,
            'order_id' => $order->id,
            'product_id' => $order->product_id,
            'product_name' => $order->product?->name,
            'status' => $order->status,
            'gateway_provider' => $order->gateway_provider,
            'trade_id' => $data['trade_id'] ?? null,
            'amount' => $data['amount'] ?? $order->gateway_amount,
            'currency' => $data['currency'] ?? $order->gateway_currency,
            'actual_amount' => $data['actual_amount'] ?? $order->gateway_actual_amount,
            'receive_address' => $data['receive_address'] ?? $order->pay_address,
            'token' => $data['token'] ?? $order->gateway_token,
            'network' => strtoupper((string) config('services.epusdt.default_network', 'TRON')),
            'expiration_time' => $data['expiration_time'] ?? optional($order->gateway_expired_at)->timestamp,
            'payment_url' => $data['payment_url'] ?? ($data['checkout_url'] ?? $order->gateway_payment_url),
            'request_id' => $gateway['request_id'] ?? $order->gateway_request_id,
        ];
    }

    private function normalizeBillingCycle(string $value): string
    {
        return match (strtolower(trim($value))) {
            'month', 'monthly', '' => 'monthly',
            'quarter', 'quarterly' => 'quarterly',
            'year', 'yearly' => 'yearly',
            default => 'monthly',
        };
    }

    private function normalizeOrderType(string $value): string
    {
        return strtolower(trim($value)) === 'renew' ? 'renew' : 'new';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveTargetServiceInstance(
        User $user,
        Product $product,
        array $validated,
        string $orderType,
    ): ?ServiceInstance {
        $serviceInstanceId = isset($validated['service_instance_id'])
            ? (int) $validated['service_instance_id']
            : 0;

        if ($orderType !== 'renew') {
            return null;
        }

        if ($serviceInstanceId <= 0) {
            throw ValidationException::withMessages([
                'service_instance_id' => '续费订单必须指定服务实例。',
            ]);
        }

        return ServiceInstance::query()
            ->whereKey($serviceInstanceId)
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
    }
}
