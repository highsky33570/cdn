<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceInstanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('limit', 20), 1), 100);
        $search = trim((string) $request->input('search', ''));

        $instances = ServiceInstance::query()
            ->with(['product:id,name,slug', 'order:id,order_no,gateway_provider,status'])
            ->where('user_id', $user->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('service_name', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_no', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = $instances->getCollection()
            ->map(fn (ServiceInstance $instance): array => [
                'id' => $instance->id,
                'status' => $instance->status,
                'service_name' => $instance->service_name,
                'product_id' => $instance->product_id,
                'product_name' => $instance->product?->name,
                'product_slug' => $instance->product?->slug,
                'order_no' => $instance->order?->order_no,
                'order_status' => $instance->order?->status,
                'gateway_provider' => $instance->order?->gateway_provider,
                'cdnfly_user_id' => $instance->cdnfly_user_id,
                'cdnfly_service_id' => $instance->cdnfly_service_id,
                'opened_at' => optional($instance->opened_at)?->toIso8601String(),
                'expired_at' => optional($instance->expired_at)?->toIso8601String(),
                'queue_stage' => data_get($instance->extra, 'queue_stage'),
                'queue_code' => data_get($instance->extra, 'queue_code'),
                'queue_reason' => data_get($instance->extra, 'queue_reason'),
                'failure_stage' => data_get($instance->extra, 'failure_stage'),
                'failure_code' => data_get($instance->extra, 'failure_code'),
                'last_error' => data_get($instance->extra, 'last_error'),
                'last_attempt_at' => data_get($instance->extra, 'last_attempt_at'),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'total' => $instances->total(),
                'page' => $instances->currentPage(),
                'per_page' => $instances->perPage(),
            ],
        ]);
    }
}
