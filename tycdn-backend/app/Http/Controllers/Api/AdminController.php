<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Services\CdnflyAccountService;
use App\Services\CdnflyApiService;
use App\Services\PackageProductLinker;
use App\Services\PackageSpecResolver;
use App\Support\QueryHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    use ReportsCdnflyFailures;

    private const ADMIN_PROXY_BLOCKED_PREFIXES = [
        '/v1/api-key',
        '/v1/login',
        '/v1/users',
        '/v1/user',
        '/v1/nodes',
        '/v1/configs',
    ];

    private const PRIVILEGED_PROXY_FIELDS = [
        'user_id',
        'role',
        'status',
        'type',
        'is_admin',
        'cdnfly_user_id',
    ];

    private const FORBIDDEN_PACKAGE_FIELDS = [
        'user_id',
        'uid',
        'owner_id',
        'role',
        'is_admin',
        'cdnfly_user_id',
        'api_key',
        'api_secret',
        'apikey',
        'apisecret',
        'api-key',
        'api-secret',
        'access_token',
        'access-token',
        'authorization',
        'token',
        'password',
    ];

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
        private readonly CdnflyAccountService $accounts,
        private readonly PackageProductLinker $productLinker,
        private readonly PackageSpecResolver $specs,
    ) {}

    /**
     * GET /api/admin/overview
     */
    public function overview(): JsonResponse
    {
        $usersTotal = User::query()->count();
        $adminTotal = User::query()->where('role', 'admin')->count();
        $cdnflyMapped = User::query()->whereNotNull('cdnfly_user_id')->count();
        $apiKeyReady = User::query()
            ->whereNotNull('cdnfly_api_key')
            ->whereNotNull('cdnfly_api_secret')
            ->count();

        $orderCounts = $this->statusCounts(Order::class);
        $serviceCounts = $this->statusCounts(ServiceInstance::class);

        return response()->json([
            'ok' => true,
            'data' => [
                'metrics' => [
                    'users_total' => $usersTotal,
                    'admins_total' => $adminTotal,
                    'cdnfly_mapped_users' => $cdnflyMapped,
                    'api_key_ready_users' => $apiKeyReady,
                    'orders_total' => Order::query()->count(),
                    'orders_pending' => (int) ($orderCounts['pending'] ?? 0),
                    'orders_paid' => (int) ($orderCounts['paid'] ?? 0),
                    'services_total' => ServiceInstance::query()->count(),
                    'services_active' => (int) ($serviceCounts['active'] ?? 0),
                    'services_failed' => (int) ($serviceCounts['failed'] ?? 0),
                    'active_products' => Product::query()->where('is_active', true)->count(),
                ],
                'cdnfly' => $this->cdnflyOverviewTotals(),
                'recent_users' => User::query()
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (User $user): array => $this->userRecord($user))
                    ->values(),
                'recent_orders' => Order::query()
                    ->latest()
                    ->limit(6)
                    ->get([
                        'id',
                        'order_no',
                        'user_id',
                        'status',
                        'amount_usdt',
                        'gateway_status',
                        'created_at',
                    ])
                    ->map(fn (Order $order): array => [
                        'id' => $order->id,
                        'order_no' => $order->order_no,
                        'user_id' => $order->user_id,
                        'status' => $order->status,
                        'gateway_status' => $order->gateway_status,
                        'amount_usdt' => (string) $order->amount_usdt,
                        'created_at' => $order->created_at,
                    ])
                    ->values(),
                'alerts' => $this->adminOverviewAlerts($usersTotal, $cdnflyMapped, $apiKeyReady, $orderCounts, $serviceCounts),
            ],
        ]);
    }

    /**
     * GET /api/admin/users
     */
    public function listUsers(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));
        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
                'email_verified_at',
                'cdnfly_user_id',
                'cdnfly_api_key',
                'cdnfly_api_secret',
                'cdnfly_synced_at',
                'created_at',
                'updated_at',
            ])
            ->withCount(['orders', 'serviceInstances'])
            ->when($search !== '', function ($query) use ($search): void {
                $escaped = QueryHelper::escapeLike($search);
                $query->where(function ($query) use ($search, $escaped): void {
                    $query
                        ->where('name', 'like', "%{$escaped}%")
                        ->orWhere('email', 'like', "%{$escaped}%")
                        ->orWhere('id', $search)
                        ->orWhere('cdnfly_user_id', $search);
                });
            })
            ->when(in_array($role, ['admin', 'user'], true), fn ($query) => $query->where('role', $role))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => $this->userRecord($user));

        return response()->json(['ok' => true, 'data' => $users]);
    }

    /**
     * POST /api/admin/users
     */
    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            // Same policy as public registration; min:8 let admins create accounts
            // far weaker than users could create for themselves.
            'password' => ['required', 'string', PasswordRule::default()],
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
            ]);

            $user->sendEmailVerificationNotification();

            return $user;
        });

        try {
            $cdnflyResult = $this->cdnfly->createCdnflyUser(
                $validated['name'],
                $validated['email'],
                $validated['password'],
            );
            $cdnflyUserId = $cdnflyResult['cdnfly_user_id'];
            $user->update(['cdnfly_user_id' => $cdnflyUserId]);

            $this->cdnfly->updateCdnflyUser($cdnflyUserId, [
                'cert_verified' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CDNfly user creation failed, local user created without mapping', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->userRecord($user->fresh()),
        ], 201);
    }

    /**
     * DELETE /api/admin/users/{id}
     */
    public function destroyUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id) {
            return response()->json([
                'ok' => false,
                'message' => '不能删除当前登录的管理员自己',
            ], 422);
        }

        if ($user->role === 'admin' && User::query()->where('role', 'admin')->count() <= 1) {
            return response()->json([
                'ok' => false,
                'message' => '至少需要保留一个管理员账号',
            ], 422);
        }

        if ($user->cdnfly_user_id) {
            try {
                $this->cdnfly->deleteCdnflyUser($user->cdnfly_user_id);
            } catch (\Throwable $e) {
                Log::warning('CDNfly user deletion failed', [
                    'user_id' => $user->id,
                    'cdnfly_user_id' => $user->cdnfly_user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $user->delete();

        return response()->json(['ok' => true], 200);
    }

    /**
     * PUT /api/admin/users/{id}
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'cdnfly_user_id' => ['nullable', 'integer', 'min:1'],
            'email_verified' => ['required', 'boolean'],
        ]);

        $newRole = (string) $validated['role'];
        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id && $newRole !== 'admin') {
            return response()->json([
                'ok' => false,
                'message' => '不能取消当前登录管理员自己的管理员权限',
            ], 422);
        }

        if ($user->role === 'admin' && $newRole !== 'admin' && User::query()->where('role', 'admin')->count() <= 1) {
            return response()->json([
                'ok' => false,
                'message' => '至少需要保留一个管理员账号',
            ], 422);
        }

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $newRole,
            'cdnfly_user_id' => $validated['cdnfly_user_id'] ?? null,
            'email_verified_at' => $validated['email_verified'] ? ($user->email_verified_at ?? now()) : null,
        ])->save();

        return response()->json([
            'ok' => true,
            'data' => $this->userRecord($user->fresh()),
        ]);
    }

    /**
     * POST /api/admin/users/{id}/sync-api-key
     */
    public function syncUserApiKey(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        try {
            // Also creates the CDNfly account when it is missing entirely, which is
            // the state a failed verification-time sync leaves behind.
            $outcome = $this->accounts->ensureAccount($user);

            return response()->json([
                'ok' => true,
                'message' => $outcome === 'synced' ? 'API 密钥同步成功' : 'API 密钥开通成功',
            ]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * POST /api/admin/users/{id}/recharge
     */
    public function rechargeUser(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $user = User::findOrFail($id);

        if (! $user->cdnfly_user_id) {
            return response()->json([
                'ok' => false,
                'message' => '该用户尚未在 CDNfly 创建账号',
            ], 422);
        }

        try {
            $result = $this->cdnfly->rechargeUser($user->cdnfly_user_id, $validated['amount']);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * GET /api/admin/packages
     */
    public function listPackages(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listPackages($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * GET /api/admin/package-options
     */
    public function packageOptions(): JsonResponse
    {
        $cnameDomains = $this->optionRecords($this->safeCdnflyOptionList(
            fn () => $this->cdnfly->listCnameDomains(['page' => 1, 'limit' => 500]),
        ));

        if ($cnameDomains === []) {
            $cnameDomains = $this->configuredCnameDomainOptions();
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'regions' => $this->optionRecords($this->safeCdnflyOptionList(
                    fn () => $this->cdnfly->listRegions(['page' => 1, 'limit' => 500]),
                )),
                'node_groups' => $this->optionRecords($this->safeCdnflyOptionList(
                    fn () => $this->cdnfly->listNodeGroups(['page' => 1, 'limit' => 500]),
                )),
                'package_groups' => $this->optionRecords($this->safeCdnflyOptionList(
                    fn () => $this->cdnfly->listPackageGroups(['page' => 1, 'limit' => 500]),
                )),
                'cname_domains' => $cnameDomains,
            ],
        ]);
    }

    /**
     * GET /api/admin/packages/{id}
     */
    public function showPackage(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->getPackage($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * POST /api/admin/packages
     */
    public function createPackage(Request $request): JsonResponse
    {
        $input = $request->all();

        // The portal half never goes to CDNfly — it would be rejected as an
        // unknown field, and it describes what *we* sell, not what CDNfly runs.
        $portal = $this->validatedPortalProduct($request);
        unset($input['portal']);

        $payload = $this->sanitizePackagePayload($input, true);

        try {
            $data = $this->cdnfly->createPackage($payload);
            $this->specs->forget();
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }

        $response = ['ok' => true, 'data' => ['package' => $data]];

        if ($portal === null) {
            return response()->json($response, 201);
        }

        $packageId = $this->extractPackageId($data);

        // The package exists in CDNfly either way. Say so plainly rather than
        // reporting a flat failure that invites a duplicate retry.
        if ($packageId === null) {
            $response['data']['portal_warning'] = 'CDNfly 套餐已创建，但未能取得套餐 ID，门户商品需手动关联。';

            return response()->json($response, 201);
        }

        try {
            $product = $this->productLinker->link($packageId, $portal);
            $response['data']['product'] = $product->only(['id', 'slug', 'name', 'price_monthly', 'currency']);
        } catch (\Throwable $e) {
            Log::error('portal product link failed', [
                'cdnfly_package_id' => $packageId,
                'error' => $e->getMessage(),
            ]);

            $response['data']['portal_warning'] = 'CDNfly 套餐已创建（ID '.$packageId.'），但门户商品写入失败：'.$e->getMessage();
        }

        return response()->json($response, 201);
    }

    /**
     * GET /api/admin/package-products
     *
     * The portal products behind the CDNfly packages, keyed by CDNfly package
     * id so the package list can show what each one is actually sold as — and
     * so a package with no product is visible as such rather than failing at
     * checkout much later.
     */
    public function packageProducts(): JsonResponse
    {
        $rows = ProductCdnflyMapping::query()
            ->whereNotNull('cdnfly_plan_id')
            ->get()
            ->keyBy(fn (ProductCdnflyMapping $mapping): string => (string) $mapping->cdnfly_plan_id);

        $products = Product::query()
            ->whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $data = [];

        foreach ($rows as $planId => $mapping) {
            $product = $products->get($mapping->product_id);

            if (! $product) {
                continue;
            }

            $data[$planId] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price_monthly' => (float) $product->price_monthly,
                'price_quarterly' => (float) $product->price_quarterly,
                'price_yearly' => (float) $product->price_yearly,
                'currency' => $product->currency,
                'is_active' => (bool) $product->is_active,
                'sort_order' => (int) $product->sort_order,
                'features' => $product->features ?? [],
            ];
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * PUT /api/admin/package-products/{packageId}
     *
     * Edit what a package sells for, or attach a product to a package that has
     * none. Kept separate from updatePackage: this touches only our database,
     * so a CDNfly outage must not stop a price change.
     */
    public function updatePackageProduct(Request $request, int $packageId): JsonResponse
    {
        $portal = $this->validatedPortalProduct($request);

        if ($portal === null) {
            throw ValidationException::withMessages([
                'portal' => '缺少门户商品字段',
            ]);
        }

        $product = $this->productLinker->link($packageId, $portal);

        return response()->json([
            'ok' => true,
            'data' => $product->only([
                'id', 'slug', 'name', 'price_monthly', 'price_quarterly',
                'price_yearly', 'currency', 'is_active', 'sort_order',
            ]),
        ]);
    }

    /**
     * The portal-side product, or null when the admin chose not to sell this
     * package through the portal.
     *
     * @return array<string, mixed>|null
     */
    private function validatedPortalProduct(Request $request): ?array
    {
        if (! $request->filled('portal')) {
            return null;
        }

        $validated = $request->validate([
            'portal.name' => ['required', 'string', 'max:100'],
            'portal.slug' => ['nullable', 'string', 'max:100'],
            'portal.description' => ['nullable', 'string', 'max:1000'],
            'portal.price_monthly' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'portal.price_quarterly' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'portal.price_yearly' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'portal.currency' => ['nullable', 'string', 'size:3'],
            'portal.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'portal.is_active' => ['nullable', 'boolean'],
            'portal.features' => ['nullable', 'array', 'max:20'],
            'portal.features.*' => ['string', 'max:100'],
        ]);

        return $validated['portal'];
    }

    /**
     * CDNfly is inconsistent about where a created record's id lands, so probe
     * the shapes it actually uses rather than assuming one.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractPackageId(array $data): ?int
    {
        $id = data_get($data, 'data.id')
            ?? data_get($data, 'data.0.id')
            ?? data_get($data, 'id')
            ?? (is_numeric(data_get($data, 'data')) ? data_get($data, 'data') : null);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * PUT /api/admin/packages/batch
     */
    public function batchUpdatePackages(Request $request): JsonResponse
    {
        $request->validate([
            'packages' => ['required', 'array', 'min:1', 'max:50'],
            'packages.*' => ['required', 'array'],
            'packages.*.id' => ['required', 'integer', 'min:1'],
        ]);

        $updates = [];

        foreach ($request->input('packages', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $packageId = (int) $item['id'];
            $updates[] = [
                'id' => $packageId,
                'payload' => $this->sanitizePackagePayload(
                    array_diff_key($item, ['id' => true]),
                    false,
                ),
            ];
        }

        $packages = array_map(
            fn (array $update): array => ['id' => $update['id'], ...$update['payload']],
            $updates,
        );

        try {
            $data = $this->cdnfly->batchUpdatePackages($packages);
            $this->specs->forget();

            return response()->json([
                'ok' => true,
                'data' => [
                    'raw' => $data,
                    'updated' => $packages,
                    'failed' => [],
                    'updated_count' => count($packages),
                    'failed_count' => 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * PUT /api/admin/packages/{id}
     */
    public function updatePackage(Request $request, int $id): JsonResponse
    {
        $payload = $this->sanitizePackagePayload($request->all(), false);

        try {
            $data = $this->cdnfly->updatePackage($id, $payload);
            $this->specs->forget();

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/admin/packages/{id}
     */
    public function deletePackage(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deletePackage($id);
            $this->specs->forget();

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * GET /api/admin/user-packages
     */
    public function listUserPackages(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listUserPackages($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * ANY /api/admin/proxy/{path}
     */
    public function proxy(Request $request, string $path): JsonResponse
    {
        $path = '/'.ltrim($path, '/');
        $method = $request->method();

        if (! str_starts_with($path, '/v1/')) {
            return response()->json([
                'ok' => false,
                'message' => '不允许代理此路径',
            ], 403);
        }

        if ($this->isBlockedAdminProxyPath($request, $path)) {
            return response()->json([
                'ok' => false,
                'message' => '管理员代理已拦截敏感路径',
            ], 403);
        }

        try {
            $data = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
                ? $request->query()
                : $request->all();
            $data = $this->rejectPrivilegedProxyFields($data);

            Log::info('CDNfly admin proxy request', [
                'admin_user_id' => $request->user()?->id,
                'method' => $method,
                'path' => $path,
                'ip' => $request->ip(),
            ]);

            $result = $this->cdnfly->proxyAdminRequest($method, $path, $data);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(string $modelClass): array
    {
        return $modelClass::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function userRecord(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified' => $user->email_verified_at !== null,
            'email_verified_at' => $user->email_verified_at,
            'cdnfly_user_id' => $user->cdnfly_user_id,
            'cdnfly_synced_at' => $user->cdnfly_synced_at,
            'has_api_key' => $user->hasCdnflyApiKey(),
            'orders_count' => (int) ($user->getAttribute('orders_count') ?? 0),
            'service_instances_count' => (int) ($user->getAttribute('service_instances_count') ?? 0),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * @param  array<string, int>  $orderCounts
     * @param  array<string, int>  $serviceCounts
     * @return array<int, array{level: string, title: string, detail: string}>
     */
    private function adminOverviewAlerts(
        int $usersTotal,
        int $cdnflyMapped,
        int $apiKeyReady,
        array $orderCounts,
        array $serviceCounts,
    ): array {
        $alerts = [];

        if ($usersTotal > $cdnflyMapped) {
            $alerts[] = [
                'level' => 'warning',
                'title' => '用户未同步 CDNfly',
                'detail' => ($usersTotal - $cdnflyMapped).' 个本地用户缺少 CDNfly 用户 ID',
            ];
        }

        if ($cdnflyMapped > $apiKeyReady) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'API Key 未就绪',
                'detail' => ($cdnflyMapped - $apiKeyReady).' 个已映射用户还没有本地 API Key',
            ];
        }

        if (($orderCounts['pending'] ?? 0) > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => '存在待支付订单',
                'detail' => $orderCounts['pending'].' 个订单仍在等待支付确认',
            ];
        }

        if (($serviceCounts['failed'] ?? 0) > 0) {
            $alerts[] = [
                'level' => 'danger',
                'title' => '存在开通失败服务',
                'detail' => $serviceCounts['failed'].' 个服务实例处于 failed 状态',
            ];
        }

        return $alerts;
    }

    private function safeCdnflyTotal(callable $loader): ?int
    {
        try {
            $payload = $loader();

            if (! is_array($payload)) {
                return null;
            }

            foreach ([
                'total',
                'count',
                'data.total',
                'data.count',
                'meta.total',
                'pagination.total',
            ] as $key) {
                $value = data_get($payload, $key);

                if (is_numeric($value)) {
                    return (int) $value;
                }
            }

            return count($this->recordList($payload));
        } catch (\Throwable $e) {
            Log::warning('CDNfly admin overview load failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function cdnflyOverviewTotals(): array
    {
        $totalQuery = ['page' => 1, 'limit' => 1];

        return [
            'users_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listUsers($totalQuery),
            ),
            'packages_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listPackages($totalQuery),
            ),
            'user_packages_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listUserPackages($totalQuery),
            ),
            'nodes_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listNodes($totalQuery),
            ),
            'pending_nodes_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listPendingNodes($totalQuery),
            ),
            'sites_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listAllSites($totalQuery),
            ),
            'streams_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listAllStreams($totalQuery),
            ),
            'certs_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listAllCerts($totalQuery),
            ),
            'acls_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listAllAcls($totalQuery),
            ),
            'dns_apis_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listAllDnsApis($totalQuery),
            ),
            'regions_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listRegions($totalQuery),
            ),
            'node_groups_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listNodeGroups($totalQuery),
            ),
            'package_groups_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listPackageGroups($totalQuery),
            ),
            'cname_domains_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listCnameDomains($totalQuery),
            ),
            'stream_groups_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listStreamGroups($totalQuery),
            ),
            'lines_total' => $this->safeCdnflyTotal(
                fn () => $this->cdnfly->listLines($totalQuery),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safeCdnflyOptionList(callable $loader): array
    {
        try {
            $data = $loader();

            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Log::warning('CDNfly package option load failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array{id: int|string, name: string}>
     */
    private function optionRecords(array $payload): array
    {
        $records = $this->recordList($payload);
        $options = [];

        foreach ($records as $record) {
            if (! isset($record['id'])) {
                continue;
            }

            $id = $record['id'];

            if (! is_int($id) && ! is_string($id)) {
                continue;
            }

            $name = $record['name']
                ?? $record['title']
                ?? $record['region_name']
                ?? $record['domain']
                ?? $record['hostname']
                ?? $record['value']
                ?? $record['cname']
                ?? $record['cname_domain']
                ?? $record['cname_domain_name']
                ?? $record['cname_hostname']
                ?? $id;

            if (! is_scalar($name)) {
                $name = $id;
            }

            $options[] = [
                'id' => $id,
                'name' => (string) $name,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{id: int|string, name: string}>
     */
    private function configuredCnameDomainOptions(): array
    {
        $raw = (string) config('services.cdnfly.cname_domain_options', '');
        $options = [];

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            $parts = preg_split('/[:=]/', $entry, 2);
            $id = trim($parts[0] ?? '');
            $name = trim($parts[1] ?? $id);

            if ($id === '' || $name === '') {
                continue;
            }

            $options[] = [
                'id' => ctype_digit($id) ? (int) $id : $id,
                'name' => $name,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recordList(array $payload): array
    {
        foreach (['data', 'items', 'list', 'rows', 'records'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_array($value) && array_is_list($value)) {
                return array_values(array_filter($value, 'is_array'));
            }

            if (is_array($value)) {
                $nested = $this->recordList($value);

                if ($nested !== []) {
                    return $nested;
                }
            }
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizePackagePayload(array $data, bool $creating): array
    {
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data = array_merge($data, $data['payload']);
        }

        unset($data['payload'], $data['id']);

        foreach ($data as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw ValidationException::withMessages([
                    'package' => '套餐字段格式不正确',
                ]);
            }

            if ($this->isForbiddenPackageField($key)) {
                throw ValidationException::withMessages([
                    $key => '套餐接口不允许提交用户、权限或密钥字段',
                ]);
            }

            if (is_array($value)) {
                $this->rejectForbiddenPackageFields($value, $key);
            }
        }

        if ($data === []) {
            throw ValidationException::withMessages([
                'package' => '请至少提交一个套餐字段',
            ]);
        }

        if ($creating) {
            foreach ([
                'name' => '新增基础套餐需要套餐名称',
                'region_id' => '新增基础套餐需要区域 ID',
                'node_group_id' => '新增基础套餐需要线路组 ID',
                'month_price' => '新增基础套餐需要月付价格',
                'quarter_price' => '新增基础套餐需要季付价格',
                'year_price' => '新增基础套餐需要年付价格',
                'groups' => '新增基础套餐需要所属套餐组',
                // CDNfly rejects a package with no CNAME domain as
                // 「无法找到此cname域名」, which reads like a lookup failure
                // rather than a missing field.
                'cname_domain' => '新增基础套餐需要 CNAME 域名',
            ] as $key => $message) {
                if (! array_key_exists($key, $data) || $data[$key] === '') {
                    throw ValidationException::withMessages([
                        $key => $message,
                    ]);
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function rejectForbiddenPackageFields(array $data, string $parentKey = 'package'): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isForbiddenPackageField($key)) {
                throw ValidationException::withMessages([
                    $parentKey => '套餐接口不允许提交用户、权限或密钥字段',
                ]);
            }

            if (is_array($value)) {
                $this->rejectForbiddenPackageFields($value, $parentKey);
            }
        }
    }

    private function isForbiddenPackageField(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach (self::FORBIDDEN_PACKAGE_FIELDS as $field) {
            if ($normalized === strtolower(str_replace(['-', '_'], '', $field))) {
                return true;
            }
        }

        return false;
    }

    private function isBlockedAdminProxyPath(Request $request, string $path): bool
    {
        foreach (self::ADMIN_PROXY_BLOCKED_PREFIXES as $prefix) {
            if ($this->pathMatchesSegmentPrefix($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function pathMatchesSegmentPrefix(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rejectPrivilegedProxyFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::PRIVILEGED_PROXY_FIELDS, true)) {
                throw ValidationException::withMessages([
                    'proxy' => '代理请求包含不允许透传的字段',
                ]);
            }

            if (is_array($value)) {
                $this->rejectPrivilegedProxyFields($value);
            }
        }

        return $data;
    }
}
