<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ServiceInstance;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    public function __construct(
        private readonly EpusdtService $epusdtService,
        private readonly CdnflyProvisionService $cdnflyProvisionService,
    ) {}

    /**
     * @return array<string, int>
     */
    public function reconcile(): array
    {
        $summary = [
            'epusdt_orders_paid' => $this->reconcileEpusdtOrders(),
            'expired_orders' => $this->expireStalePendingOrders(),
            'provisioning_attempts' => $this->retryPaidProvisioning(),
        ];

        Log::info('payment reconciliation completed', $summary);

        return $summary;
    }

    private function reconcileEpusdtOrders(): int
    {
        if (trim((string) config('services.epusdt.query_order_path', '')) === '') {
            return 0;
        }

        $matched = 0;
        $lookbackMinutes = max(1, (int) config('services.epusdt.reconcile_lookback_minutes', 1440));

        Order::query()
            ->where('gateway_provider', 'epusdt')
            ->whereIn('status', OrderStatus::SETTLEABLE)
            ->where('expire_at', '>', now()->subMinutes($lookbackMinutes))
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$matched): void {
                foreach ($orders as $order) {
                    try {
                        $response = $this->epusdtService->queryTransaction($order->order_no);
                    } catch (\Throwable $e) {
                        Log::warning('epusdt reconciliation query failed', [
                            'order_no' => $order->order_no,
                            'error' => $e->getMessage(),
                        ]);

                        continue;
                    }

                    $data = is_array($response) ? ($response['data'] ?? null) : null;
                    if (! is_array($data) || (int) ($data['status'] ?? 0) !== 2) {
                        continue;
                    }

                    $matched += $this->markEpusdtOrderPaid($order, $data);
                }
            });

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function markEpusdtOrderPaid(Order $order, array $data): int
    {
        $responseOrderId = $this->scalarString($data['order_id'] ?? null);
        $responseTradeId = $this->scalarString($data['trade_id'] ?? null);

        if (($responseOrderId === null || $responseOrderId === '') && ($responseTradeId === null || $responseTradeId === '')) {
            Log::warning('epusdt reconciliation missing order identity', [
                'order_no' => $order->order_no,
            ]);

            return 0;
        }

        if ($responseOrderId !== null && $responseOrderId !== '' && $responseOrderId !== $order->order_no) {
            Log::warning('epusdt reconciliation order mismatch', [
                'order_no' => $order->order_no,
                'response_order_id' => $responseOrderId,
            ]);

            return 0;
        }

        if ($order->gateway_trade_id !== null && $responseTradeId !== $order->gateway_trade_id) {
            Log::warning('epusdt reconciliation trade mismatch', [
                'order_no' => $order->order_no,
                'expected_trade_id' => $order->gateway_trade_id,
                'response_trade_id' => $responseTradeId,
            ]);

            return 0;
        }

        $actualAmount = $data['actual_amount'] ?? null;
        if ($actualAmount === null || ! $this->sameDecimal($order->gateway_amount ?? $order->fiat_amount, $data['amount'] ?? null, 4)) {
            return 0;
        }

        if ($order->gateway_actual_amount !== null && ! $this->sameDecimal($order->gateway_actual_amount, $actualAmount, 6)) {
            Log::warning('epusdt reconciliation actual amount mismatch', [
                'order_no' => $order->order_no,
                'expected_actual_amount' => $order->gateway_actual_amount,
                'response_actual_amount' => $actualAmount,
            ]);

            return 0;
        }

        $responseAddress = $this->scalarString($data['receive_address'] ?? null);
        if ($order->pay_address !== null && $order->pay_address !== '' && $responseAddress !== $order->pay_address) {
            Log::warning('epusdt reconciliation address mismatch', [
                'order_no' => $order->order_no,
                'expected_address' => $order->pay_address,
                'response_address' => $responseAddress,
            ]);

            return 0;
        }

        $expectedToken = $this->scalarString($order->gateway_token ?: $order->pay_currency);
        $responseToken = $this->scalarString($data['token'] ?? null);
        if ($expectedToken !== null && $expectedToken !== '' && ($responseToken === null || ! $this->sameToken($expectedToken, $responseToken))) {
            Log::warning('epusdt reconciliation token mismatch', [
                'order_no' => $order->order_no,
                'expected_token' => $expectedToken,
                'response_token' => $responseToken,
            ]);

            return 0;
        }

        DB::transaction(function () use ($order, $data, $actualAmount, $responseTradeId): void {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! OrderStatus::isSettleable($lockedOrder->status)) {
                return;
            }

            if ($lockedOrder->status !== OrderStatus::PENDING) {
                Log::warning('epusdt reconciliation settled a late payment', [
                    'order_no' => $lockedOrder->order_no,
                    'previous_status' => $lockedOrder->status,
                ]);
            }

            $txid = is_string($data['block_transaction_id'] ?? null) ? $data['block_transaction_id'] : null;
            if ($txid && ! PaymentTransaction::where('txid', $txid)->exists()) {
                PaymentTransaction::create([
                    'order_id' => $lockedOrder->id,
                    'chain' => strtoupper((string) ($data['network'] ?? 'TRON')),
                    'token' => strtoupper((string) ($data['token'] ?? 'USDT')),
                    'txid' => $txid,
                    'from_address' => '',
                    'to_address' => (string) ($data['receive_address'] ?? $lockedOrder->pay_address),
                    'amount' => $actualAmount,
                    'block_timestamp' => now()->timestamp,
                    'confirmations' => 0,
                    'confirmed_at' => now(),
                    'processed_at' => now(),
                    'status' => 'confirmed',
                    'raw_payload' => [
                        'source' => 'epusdt_reconciliation',
                        'data' => $data,
                    ],
                ]);
            }

            $lockedOrder->update([
                'status' => OrderStatus::PAID,
                'actual_paid_amount' => $actualAmount,
                'paid_at' => now(),
                'amount_usdt' => $actualAmount,
                'pay_currency' => strtoupper((string) ($data['token'] ?? $lockedOrder->pay_currency)),
                'gateway_status' => 'paid',
                'gateway_trade_id' => $responseTradeId ?? $lockedOrder->gateway_trade_id,
                'gateway_actual_amount' => $actualAmount,
                'gateway_token' => $this->scalarString($data['token'] ?? null) ?? $lockedOrder->gateway_token,
                'gateway_notify_payload' => $data,
                'pay_address' => $this->scalarString($data['receive_address'] ?? null) ?? $lockedOrder->pay_address,
                'cancelled_at' => null,
            ]);
        });

        return 1;
    }

    private function expireStalePendingOrders(): int
    {
        return Order::query()
            ->where('status', OrderStatus::PENDING)
            ->where('expire_at', '<=', now())
            ->update([
                'status' => OrderStatus::EXPIRED,
                'gateway_status' => 'expired',
                'cancelled_at' => now(),
            ]);
    }

    private function retryPaidProvisioning(): int
    {
        $attempts = 0;
        $maxAttempts = max(1, (int) config('services.cdnfly.max_provision_attempts', 10));

        Order::query()
            ->where('status', OrderStatus::PAID)
            ->whereNull('provisioned_at')
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$attempts, $maxAttempts): void {
                foreach ($orders as $order) {
                    // Without a cap a permanently broken order (missing product
                    // mapping, revoked credentials) hammers CDNfly every 5 minutes
                    // forever and nobody is told. Stop, and say so loudly once.
                    $instance = ServiceInstance::query()
                        ->where('source_order_id', $order->id)
                        ->first();
                    $priorAttempts = (int) data_get($instance?->extra, 'provision_attempts', 0);

                    if ($priorAttempts >= $maxAttempts) {
                        continue;
                    }

                    $attempts++;
                    $result = $this->cdnflyProvisionService->provisionPaidOrder($order);

                    if (($result['status'] ?? null) === 'failed') {
                        Log::warning('paid order provisioning retry failed', [
                            'order_no' => $order->order_no,
                            'attempt' => $priorAttempts + 1,
                            'max_attempts' => $maxAttempts,
                            'failure_stage' => $result['failure_stage'] ?? 'unknown',
                            'failure_code' => $result['failure_code'] ?? 'unknown',
                            'reason' => $result['reason'] ?? null,
                        ]);
                    }

                    if (in_array($result['status'] ?? null, ['failed', 'queued'], true)
                        && $priorAttempts + 1 >= $maxAttempts) {
                        Log::error('paid order provisioning GAVE UP after max attempts; needs manual action', [
                            'order_no' => $order->order_no,
                            'user_id' => $order->user_id,
                            'product_id' => $order->product_id,
                            'attempts' => $priorAttempts + 1,
                            'reason' => $result['reason'] ?? null,
                        ]);
                    }
                }
            });

        return $attempts;
    }

    private function sameDecimal(mixed $left, mixed $right, int $scale): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return number_format((float) $left, $scale, '.', '') === number_format((float) $right, $scale, '.', '');
    }

    private function sameToken(mixed $expected, mixed $actual): bool
    {
        return $this->tokenSymbol($expected) === $this->tokenSymbol($actual);
    }

    private function tokenSymbol(mixed $value): string
    {
        $token = strtolower(trim((string) $value));
        $token = str_replace('-', '_', $token);

        return explode('_', $token, 2)[0];
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }
}
