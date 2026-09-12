<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceInstance;
use App\Services\CdnflyApiService;
use App\Support\OrderStatus;
use App\Support\QueryHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFinanceController extends Controller
{
    use ReportsCdnflyFailures;

    private const FORBIDDEN_FIELDS = [
        'user_id', 'uid', 'owner_id', 'role', 'is_admin',
        'cdnfly_user_id', 'api_key', 'api_secret', 'apikey', 'apisecret',
        'api-key', 'api-secret', 'access_token', 'access-token',
        'authorization', 'token', 'password',
    ];

    private const VALID_DURATIONS = ['month', 'quarter', 'year'];

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $orders = Order::query()
            ->with(['user:id,name,email', 'product:id,name,slug'])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $escaped = QueryHelper::escapeLike($search);
                $q->where(function ($q) use ($search, $escaped): void {
                    $q->where('order_no', 'like', "%{$escaped}%")
                        ->orWhere('id', $search);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $order->user_id,
                'user_name' => $order->user?->name,
                'user_email' => $order->user?->email,
                'product_id' => $order->product_id,
                'product_name' => $order->product?->name,
                'product_slug' => $order->product?->slug,
                'product_snapshot' => $order->product_snapshot,
                'order_type' => $order->order_type,
                'billing_cycle' => $order->billing_cycle,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'gateway_status' => $order->gateway_status,
                'amount_usdt' => (string) $order->amount_usdt,
                'amount_cny' => (string) ($order->amount_cny ?? ''),
                'epusdt_order_id' => $order->epusdt_order_id,
                'epusdt_trade_id' => $order->epusdt_trade_id,
                'expire_at' => $order->expire_at,
                'paid_at' => $order->paid_at,
                'provisioned_at' => $order->provisioned_at,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);

        return response()->json(['ok' => true, 'data' => $orders]);
    }

    /**
     * GET /api/admin/finance/summary
     *
     * The finance page opened onto three empty tables and answered nothing.
     * These are the numbers an operator actually arrives wanting, computed in
     * the database rather than by counting rows on the current page.
     *
     * Revenue counts paid and provisioned orders: money has changed hands in
     * both, and excluding provisioned would under-report every completed sale.
     */
    public function summary(): JsonResponse
    {
        // Money has changed hands from PAID onward; PROVISIONING and
        // ACTIVE are later stages of the same sale, not separate ones.
        $earned = [OrderStatus::PAID, OrderStatus::PROVISIONING, OrderStatus::ACTIVE];

        $revenue = fn (?Carbon $since) => (float) Order::query()
            ->whereIn('status', $earned)
            ->when($since, fn ($q) => $q->where('paid_at', '>=', $since))
            ->sum('amount_usdt');

        return response()->json([
            'ok' => true,
            'data' => [
                'revenue_total' => $revenue(null),
                'revenue_month' => $revenue(Carbon::now()->startOfMonth()),
                'orders_total' => Order::query()->count(),
                'orders_pending' => Order::query()->where('status', OrderStatus::PENDING)->count(),
                // Money taken but nothing delivered — the one number worth
                // acting on today, so it is surfaced on its own.
                'orders_failed' => Order::query()->where('status', OrderStatus::FAILED)->count(),
                'services_active' => ServiceInstance::query()->where('status', 'active')->count(),
                'services_total' => ServiceInstance::query()->count(),
            ],
        ]);
    }

    public function services(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $services = ServiceInstance::query()
            ->with(['user:id,name,email', 'order:id,order_no', 'product:id,name,slug'])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $escaped = QueryHelper::escapeLike($search);
                $q->where(function ($q) use ($search, $escaped): void {
                    $q->where('service_name', 'like', "%{$escaped}%")
                        ->orWhere('cdnfly_service_id', 'like', "%{$escaped}%")
                        ->orWhere('id', $search)
                        ->orWhereHas('order', fn ($q) => $q->where('order_no', 'like', "%{$escaped}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ServiceInstance $si): array => [
                'id' => $si->id,
                'user_id' => $si->user_id,
                'user_name' => $si->user?->name,
                'user_email' => $si->user?->email,
                'order_no' => $si->order?->order_no,
                'source_order_id' => $si->source_order_id,
                'product_id' => $si->product_id,
                'product_name' => $si->product?->name,
                'product_slug' => $si->product?->slug,
                'service_name' => $si->service_name,
                'cdnfly_user_id' => $si->cdnfly_user_id,
                'cdnfly_service_id' => $si->cdnfly_service_id,
                'status' => $si->status,
                'error_message' => $si->extra['last_error'] ?? null,
                'opened_at' => $si->opened_at,
                'expired_at' => $si->expired_at,
                'created_at' => $si->created_at,
                'updated_at' => $si->updated_at,
            ]);

        return response()->json(['ok' => true, 'data' => $services]);
    }

    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                OrderStatus::PENDING,
                OrderStatus::PAID,
                OrderStatus::PROVISIONING,
                OrderStatus::ACTIVE,
                OrderStatus::FAILED,
                OrderStatus::EXPIRED,
                OrderStatus::CANCELLED,
            ])],
        ]);

        $order->status = $validated['status'];
        $order->save();

        return response()->json(['ok' => true, 'message' => '订单状态已更新']);
    }

    public function userPackages(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listUserPackages($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function storeUserPackage(Request $request): JsonResponse
    {
        $request->validate([
            'uid' => ['required', 'integer', 'min:1'],
            'package' => ['required', 'integer', 'min:1'],
            'duration' => ['required', 'string', Rule::in(self::VALID_DURATIONS)],
            'name' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $data = $this->cdnfly->adminCreateUserPackage($request->only(['uid', 'package', 'duration', 'name', 'coupon_code']));

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updateUserPackage(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->adminUpdateUserPackage($id, $this->sanitizePayload($request->all()));

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyUserPackage(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->adminDeleteUserPackage($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function listUserPackageUpgrades(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->adminListUserPackageUpgrades($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function addUserPackageUpgrade(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'package_up_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $data = $this->cdnfly->adminAddUserPackageUpgrade($id, $request->only(['package_up_id']));

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function removeUserPackageUpgrade(int $id, int $upgradeId): JsonResponse
    {
        try {
            $data = $this->cdnfly->adminDeleteUserPackageUpgrade($id, $upgradeId);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function listPackageGroups(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listPackageGroups($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function storePackageGroup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $data = $this->cdnfly->createPackageGroup($this->sanitizePayload($request->all()));

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updatePackageGroup(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->updatePackageGroup($id, $this->sanitizePayload($request->all()));

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyPackageGroup(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deletePackageGroup($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function listPackageUps(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listPackageUps($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function storePackageUp(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $data = $this->cdnfly->createPackageUp($this->sanitizePayload($request->all()));

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updatePackageUp(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->updatePackageUp($id, $this->sanitizePayload($request->all()));

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyPackageUp(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deletePackageUp($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    private function sanitizePayload(array $data): array
    {
        $normalized = array_map(
            fn (string $f): string => strtolower(str_replace(['-', '_'], '', $f)),
            self::FORBIDDEN_FIELDS,
        );

        foreach (array_keys($data) as $key) {
            if (! is_string($key)) {
                unset($data[$key]);

                continue;
            }

            $norm = strtolower(str_replace(['-', '_'], '', $key));

            if (in_array($norm, $normalized, true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
