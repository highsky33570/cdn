<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\CdnflyProvisionService;
use App\Services\EpusdtCheckoutService;
use App\Services\EpusdtService;
use App\Support\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EpusdtController extends Controller
{
    public function create(Request $request, EpusdtCheckoutService $checkoutService)
    {
        $validated = $request->validate($checkoutService->checkoutRules());
        $checkout = $checkoutService->createForUser($request->user(), $validated);

        return response()->json([
            'ok' => true,
            'data' => $checkoutService->checkoutResponse($checkout),
        ], 201);
    }

    public function notify(Request $request, EpusdtService $epusdtService, CdnflyProvisionService $cdnflyProvisionService)
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        if (! is_array($payload) || ! $epusdtService->verifySignature($payload)) {
            return response('signature error', 401)->header('Content-Type', 'text/plain');
        }

        $validated = validator($payload, [
            'pid' => ['required', 'string', 'max:128'],
            'trade_id' => ['required', 'string', 'max:64'],
            'order_id' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric'],
            'actual_amount' => ['required', 'numeric'],
            'receive_address' => ['nullable', 'string', 'max:128'],
            'token' => ['required', 'string', 'max:32'],
            'block_transaction_id' => ['nullable', 'string', 'max:128'],
            'status' => ['required', Rule::in([1, 2, 3, '1', '2', '3'])],
            'signature' => ['required', 'string', 'size:32'],
        ])->validate();

        $order = Order::where('order_no', $validated['order_id'])->first();

        if (! $order) {
            return response('order not found', 404)->header('Content-Type', 'text/plain');
        }

        if ($mismatch = $this->epusdtOrderMismatch($order, $validated)) {
            return response($mismatch, 422)->header('Content-Type', 'text/plain');
        }

        // 防重放：只有「已结算」的订单（paid/provisioning/active/failed）才算重放。
        // 过期或取消的订单收到真实付款属于迟到付款，必须照常入账，否则等于收钱不发货。
        if ((int) $validated['status'] === 2 && ! OrderStatus::isSettleable($order->status)) {
            Log::info('epusdt notify replay ignored', [
                'order_no' => $order->order_no,
                'trade_id' => $validated['trade_id'],
                'order_status' => $order->status,
            ]);

            return response('ok', 200)->header('Content-Type', 'text/plain');
        }

        // 迟到付款：金额/地址/币种/trade_id 都已校验通过，照常结算并告警，方便人工核对。
        if ((int) $validated['status'] === 2 && $order->status !== OrderStatus::PENDING) {
            Log::warning('epusdt late payment settled on a non-pending order', [
                'order_no' => $order->order_no,
                'trade_id' => $validated['trade_id'],
                'previous_status' => $order->status,
                'expire_at' => optional($order->expire_at)?->toIso8601String(),
            ]);
        }

        $gatewayStatus = match ((int) $validated['status']) {
            1 => 'pending',
            2 => 'paid',
            3 => 'expired',
            default => 'unknown',
        };

        $order->update([
            'gateway_trade_id' => $validated['trade_id'],
            'gateway_amount' => $validated['amount'],
            'gateway_actual_amount' => $validated['actual_amount'],
            'gateway_token' => $validated['token'],
            'gateway_status' => $gatewayStatus,
            'gateway_notify_payload' => $validated,
            'pay_address' => $validated['receive_address'] ?? $order->pay_address,
        ]);

        if ((int) $validated['status'] === 3) {
            if ($order->status === OrderStatus::PENDING) {
                $order->update([
                    'status' => OrderStatus::EXPIRED,
                ]);
            }

            return response('ok', 200)->header('Content-Type', 'text/plain');
        }

        if ((int) $validated['status'] !== 2) {
            return response('ok', 200)->header('Content-Type', 'text/plain');
        }

        $shouldProvision = false;

        DB::transaction(function () use ($order, $validated, &$shouldProvision) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $txid = $validated['block_transaction_id'] ?? null;

            if ($mismatch = $this->epusdtOrderMismatch($lockedOrder, $validated)) {
                abort(response($mismatch, 422)->header('Content-Type', 'text/plain'));
            }

            // 事务内二次检查，防止并发重复入账；迟到付款（expired/cancelled）同样放行。
            if (! OrderStatus::isSettleable($lockedOrder->status)) {
                return;
            }

            if ($txid && ! PaymentTransaction::where('txid', $txid)->exists()) {
                PaymentTransaction::create([
                    'order_id' => $lockedOrder->id,
                    'chain' => 'TRON',
                    'token' => strtoupper($validated['token']),
                    'txid' => $txid,
                    'from_address' => '',
                    'to_address' => $validated['receive_address'] ?? $lockedOrder->pay_address,
                    'amount' => $validated['actual_amount'],
                    'block_timestamp' => now()->timestamp,
                    'confirmations' => 0,
                    'confirmed_at' => now(),
                    'processed_at' => now(),
                    'status' => 'confirmed',
                    'raw_payload' => $validated,
                ]);
            }

            $lockedOrder->update([
                'status' => OrderStatus::PAID,
                'actual_paid_amount' => $validated['actual_amount'],
                'paid_at' => now(),
                'amount_usdt' => $validated['actual_amount'],
                'pay_currency' => strtoupper($validated['token']),
                'cancelled_at' => null,
            ]);

            $shouldProvision = true;
        });

        if ($shouldProvision) {
            try {
                $result = $cdnflyProvisionService->provisionPaidOrder($order->fresh());
                Log::info('cdnfly provision result', [
                    'order_no' => $order->order_no,
                    'result' => $result,
                ]);
            } catch (\Throwable $e) {
                Log::error('cdnfly provisioning exception', [
                    'order_no' => $order->order_no,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response('ok', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function epusdtOrderMismatch(Order $order, array $validated): ?string
    {
        if ($order->gateway_provider !== 'epusdt') {
            return 'provider mismatch';
        }

        if ($order->gateway_trade_id !== null && $order->gateway_trade_id !== $validated['trade_id']) {
            return 'trade mismatch';
        }

        if ($order->gateway_amount !== null && $this->decimalString($order->gateway_amount, 4) !== $this->decimalString($validated['amount'], 4)) {
            return 'amount mismatch';
        }

        if ($order->gateway_actual_amount !== null && $this->decimalString($order->gateway_actual_amount, 6) !== $this->decimalString($validated['actual_amount'], 6)) {
            return 'actual amount mismatch';
        }

        $receiveAddress = $validated['receive_address'] ?? null;
        if ($order->pay_address !== null && $order->pay_address !== '' && $receiveAddress !== null && $receiveAddress !== '' && ! hash_equals(trim($order->pay_address), trim((string) $receiveAddress))) {
            return 'address mismatch';
        }

        $expectedToken = $order->gateway_token ?: $order->pay_currency;
        if ($expectedToken !== null && $expectedToken !== '' && ! $this->sameToken($expectedToken, $validated['token'])) {
            return 'token mismatch';
        }

        return null;
    }

    private function decimalString(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
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
}
