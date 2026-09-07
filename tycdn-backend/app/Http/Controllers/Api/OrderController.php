<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceInstance;
use App\Services\CdnflyProvisionService;
use App\Services\EpusdtCheckoutService;
use App\Support\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('limit', 20), 1), 100);
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', ''));

        $orders = Order::query()
            ->with(['product:id,name,slug', 'targetServiceInstance:id,status,service_name,opened_at,extra'])
            ->where('user_id', $user->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('order_no', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = $orders->getCollection()
            ->map(fn (Order $order): array => $this->formatOrder($order))
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'total' => $orders->total(),
                'page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
            ],
        ]);
    }

    public function store(Request $request, EpusdtCheckoutService $checkoutService): JsonResponse
    {
        $validated = $request->validate($checkoutService->checkoutRules());
        $checkout = $checkoutService->createForUser($request->user(), $validated);

        return response()->json([
            'ok' => true,
            'data' => $checkoutService->checkoutResponse($checkout),
        ], 201);
    }

    public function show(string $orderNo): JsonResponse
    {
        $order = $this->findOrderForRequest($orderNo);

        return response()->json([
            'ok' => true,
            'data' => $this->formatOrder($order->loadMissing('product:id,name,slug', 'targetServiceInstance:id,status,service_name,opened_at,extra')),
        ]);
    }

    public function provision(string $orderNo, CdnflyProvisionService $cdnflyProvisionService): JsonResponse
    {
        $order = $this->findOrderForRequest($orderNo);

        if (! in_array($order->status, [OrderStatus::PAID, OrderStatus::ACTIVE], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Only paid or active orders can be provisioned.',
                'data' => [
                    'status' => 'failed',
                    'failure_stage' => 'payment',
                    'failure_code' => 'order_not_paid',
                ],
            ], 422);
        }

        $result = $cdnflyProvisionService->provisionPaidOrder($order);

        return response()->json([
            'ok' => in_array($result['status'] ?? null, ['success', 'already_provisioned', 'already_processing_or_active', 'queued'], true),
            'data' => $result,
        ], ($result['status'] ?? null) === 'failed' ? 422 : 200);
    }

    private function findOrderForRequest(string $orderNo): Order
    {
        $user = request()->user();

        return Order::query()
            ->where('order_no', $orderNo)
            ->when(! $user?->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrder(Order $order): array
    {
        $serviceInstance = ServiceInstance::query()
            ->select(['id', 'status', 'service_name', 'opened_at', 'extra'])
            ->where('source_order_id', $order->id)
            ->first();

        if (! $serviceInstance && $order->targetServiceInstance) {
            $serviceInstance = $order->targetServiceInstance;
        }

        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'product_id' => $order->product_id,
            'product_name' => $order->product?->name,
            'product_slug' => $order->product?->slug,
            'order_type' => $order->order_type,
            'billing_cycle' => $order->billing_cycle,
            'quantity' => $order->quantity,
            'target_service_instance_id' => $order->target_service_instance_id,
            'fiat_amount' => $order->fiat_amount,
            'fiat_currency' => $order->fiat_currency,
            'gateway_provider' => $order->gateway_provider,
            'gateway_status' => $order->gateway_status,
            'gateway_payment_url' => $order->gateway_payment_url,
            'gateway_trade_id' => $order->gateway_trade_id,
            'gateway_request_id' => $order->gateway_request_id,
            'status' => $order->status,
            'pay_currency' => $order->pay_currency,
            'pay_address' => $order->pay_address,
            'actual_paid_amount' => $order->actual_paid_amount,
            'amount_usdt' => $order->amount_usdt,
            'created_at' => optional($order->created_at)?->toIso8601String(),
            'paid_at' => optional($order->paid_at)?->toIso8601String(),
            'provisioned_at' => optional($order->provisioned_at)?->toIso8601String(),
            'gateway_expired_at' => optional($order->gateway_expired_at)?->toIso8601String(),
            'service_instance' => $serviceInstance ? [
                'id' => $serviceInstance->id,
                'status' => $serviceInstance->status,
                'service_name' => $serviceInstance->service_name,
                'opened_at' => optional($serviceInstance->opened_at)?->toIso8601String(),
                'queue_stage' => data_get($serviceInstance->extra, 'queue_stage'),
                'queue_code' => data_get($serviceInstance->extra, 'queue_code'),
                'queue_reason' => data_get($serviceInstance->extra, 'queue_reason'),
                'failure_stage' => data_get($serviceInstance->extra, 'failure_stage'),
                'failure_code' => data_get($serviceInstance->extra, 'failure_code'),
                'last_error' => data_get($serviceInstance->extra, 'last_error'),
            ] : null,
        ];
    }
}
