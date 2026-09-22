<?php

use App\Http\Controllers\Api\AdminAccessLogController;
use App\Http\Controllers\Api\AdminBlockLogController;
use App\Http\Controllers\Api\AdminCcController;
use App\Http\Controllers\Api\AdminConfigController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminDnsController;
use App\Http\Controllers\Api\AdminFinanceController;
use App\Http\Controllers\Api\AdminMonitorController;
use App\Http\Controllers\Api\AdminNodeController;
use App\Http\Controllers\Api\AdminRecoveryController;
use App\Http\Controllers\Api\AdminSiteController;
use App\Http\Controllers\Api\AdminStreamController;
use App\Http\Controllers\Api\AdminWafLogController;
use App\Http\Controllers\Api\AdminWorkspaceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CdnApiKeyController;
use App\Http\Controllers\Api\CdnCertController;
use App\Http\Controllers\Api\CdnLogDownloadController;
use App\Http\Controllers\Api\CdnProxyController;
use App\Http\Controllers\Api\CdnSiteController;
use App\Http\Controllers\Api\EpusdtController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\PublicNetworkController;
use App\Http\Controllers\Api\ServiceInstanceController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'message' => 'Laravel API is running',
    ]);
});

Route::get('/products', [ProductCatalogController::class, 'index']);

// 公开的网络概况（在线边缘节点数 + 分布），营销站用，服务端算好并缓存
Route::get('/network', [PublicNetworkController::class, 'index']);

// ─── 认证（替代 Flask backend） ─────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:two-factor');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('throttle:6,1')
        ->name('api.auth.verify-email');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:verification-notice');
        Route::post('/retry-api-key', [AuthController::class, 'retryApiKey'])
            ->middleware('throttle:api-key-sync');
    });
});

// Key management must remain reachable after a key is disabled.
Route::match(['GET', 'POST', 'PUT', 'DELETE'], '/cdn/account/api-key', [CdnApiKeyController::class, 'handle'])
    ->middleware(['auth:sanctum', 'verified', 'throttle:20,1']);

// ─── CDN 用户端接口（需登录 + 需有 CDNfly API key） ──────
Route::middleware(['auth:sanctum', 'verified', 'cdnfly.apikey'])->prefix('cdn')->group(function () {
    // 站点管理
    Route::apiResource('sites', CdnSiteController::class);

    // 证书管理
    Route::apiResource('certs', CdnCertController::class);

    Route::get('access-log-downloads/{id}', CdnLogDownloadController::class)->whereNumber('id');

    // 通用代理（ACL、DNS API、站点分组等）
    Route::any('proxy/{path}', [CdnProxyController::class, 'handle'])->where('path', '.*');
});

// ─── 管理端接口（需登录 + admin 角色） ──────────────────
// 读取和写入按账号分别限流；操作级写入配额独立于监控请求。
Route::middleware(['auth:sanctum', 'verified', 'admin', 'throttle:admin-api'])->prefix('admin')->group(function () {
    // 管理概览
    Route::get('/overview', [AdminController::class, 'overview']);

    // 用户管理
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::post('/users', [AdminController::class, 'storeUser'])->middleware('throttle:admin-write-20');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->middleware('throttle:admin-write-20');
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->middleware('throttle:admin-write-10');
    Route::get('/users/{user}/mapping-preview', [AdminRecoveryController::class, 'mapping']);
    Route::put('/users/{user}/mapping', [AdminRecoveryController::class, 'bind'])->middleware('throttle:admin-write-10');
    Route::get('/services/{service}/recovery', [AdminRecoveryController::class, 'service']);
    Route::post('/users/{id}/sync-api-key', [AdminController::class, 'syncUserApiKey'])->middleware('throttle:admin-write-10');
    Route::post('/users/{id}/recharge', [AdminController::class, 'rechargeUser'])->middleware('throttle:admin-write-10');

    // 套餐管理（CDNfly）
    Route::get('/package-options', [AdminController::class, 'packageOptions']);
    Route::get('/packages', [AdminController::class, 'listPackages']);
    Route::get('/packages/{id}', [AdminController::class, 'showPackage']);
    Route::post('/packages', [AdminController::class, 'createPackage'])->middleware('throttle:admin-write-20');
    // 门户商品：套餐实际的对外售价，与 CDNfly 内部价格无关
    Route::get('/package-products', [AdminController::class, 'packageProducts']);
    Route::put('/package-products/{packageId}', [AdminController::class, 'updatePackageProduct'])->middleware('throttle:admin-write-30');
    Route::put('/packages/batch', [AdminController::class, 'batchUpdatePackages'])->middleware('throttle:admin-write-10');
    Route::put('/packages/{id}', [AdminController::class, 'updatePackage'])->middleware('throttle:admin-write-20');
    Route::delete('/packages/{id}', [AdminController::class, 'deletePackage'])->middleware('throttle:admin-write-10');

    // 用户套餐
    Route::get('/user-packages', [AdminController::class, 'listUserPackages']);

    // 节点管理
    Route::get('/nodes', [AdminNodeController::class, 'index']);
    Route::get('/node-install-command', [AdminNodeController::class, 'installCommand']);
    Route::get('/pending-nodes', [AdminNodeController::class, 'pending']);
    Route::delete('/pending-nodes/{id}', [AdminNodeController::class, 'destroyPending'])->middleware('throttle:admin-write-10');
    Route::post('/nodes', [AdminNodeController::class, 'store'])->middleware('throttle:admin-write-20');
    Route::get('/nodes/{id}', [AdminNodeController::class, 'show']);
    Route::put('/nodes/{id}/enable', [AdminNodeController::class, 'setEnabled'])->middleware('throttle:admin-write-20');
    // 子IP：一个节点的主 IP + 已登记的副 IP，以及登记 /29 里的备用 IP。
    Route::get('/nodes/{id}/ips', [AdminNodeController::class, 'nodeIps']);
    Route::post('/nodes/{id}/sub-ips', [AdminNodeController::class, 'storeSubIps'])->middleware('throttle:admin-write-20');
    Route::put('/nodes/{id}', [AdminNodeController::class, 'update'])->middleware('throttle:admin-write-20');
    Route::delete('/nodes/{id}', [AdminNodeController::class, 'destroy'])->middleware('throttle:admin-write-10');
    // 节点组（线路）：套餐必须挂在某个节点组上，所以这里必须可写，
    // 否则只能在 CDNfly 面板里建组，控制台就无法独立完成套餐配置。
    Route::get('/node-groups', [AdminNodeController::class, 'nodeGroups']);
    Route::post('/node-groups', [AdminNodeController::class, 'storeNodeGroup'])->middleware('throttle:admin-write-20');
    Route::put('/node-groups/{id}', [AdminNodeController::class, 'updateNodeGroup'])->middleware('throttle:admin-write-20');
    Route::delete('/node-groups/{id}', [AdminNodeController::class, 'destroyNodeGroup'])->middleware('throttle:admin-write-10');
    // 区域和线路同理：区域 → 节点组 → 套餐，缺任何一环都无法在控制台里配出可售套餐。
    Route::get('/regions', [AdminNodeController::class, 'regions']);
    Route::post('/regions', [AdminNodeController::class, 'storeRegion'])->middleware('throttle:admin-write-20');
    Route::put('/regions/{id}', [AdminNodeController::class, 'updateRegion'])->middleware('throttle:admin-write-20');
    Route::delete('/regions/{id}', [AdminNodeController::class, 'destroyRegion'])->middleware('throttle:admin-write-10');
    // 线路不是可创建的对象：DNS 线路定义在系统配置里，POST /v1/lines 是把节点 IP
    // 绑定到某个节点组的某条线路上。详见 CdnflyApiService::assignLines()。
    Route::get('/lines', [AdminNodeController::class, 'lines']);
    Route::get('/dns-lines', [AdminNodeController::class, 'dnsLines']);
    Route::post('/lines', [AdminNodeController::class, 'storeLine'])->middleware('throttle:admin-write-20');
    Route::delete('/lines/{id}', [AdminNodeController::class, 'destroyLine'])->where('id', '[0-9,]+')->middleware('throttle:admin-write-10');

    // 全部网站（管理端）
    Route::get('/sites', [AdminSiteController::class, 'index']);
    Route::post('/sites', [AdminSiteController::class, 'store'])->middleware('throttle:admin-write-20');
    Route::get('/sites/{id}', [AdminSiteController::class, 'show']);
    Route::put('/sites/{id}', [AdminSiteController::class, 'update'])->middleware('throttle:admin-write-20');
    Route::delete('/sites/{id}', [AdminSiteController::class, 'destroy'])->middleware('throttle:admin-write-10');
    Route::put('/sites/{id}/enable', [AdminSiteController::class, 'setEnabled'])->middleware('throttle:admin-write-20');
    // 申请免费证书并绑定：先建证书再回写 https_listen.cert
    Route::post('/sites/{id}/certificate', [AdminSiteController::class, 'applyCertificate'])->middleware('throttle:admin-write-10');
    Route::match(['GET', 'PUT'], '/sites/{id}/waf-rules', [AdminSiteController::class, 'wafRules'])->whereNumber('id')->middleware('throttle:admin-write-20');
    Route::get('/all-certs', [AdminSiteController::class, 'certs']);
    Route::post('/all-certs', [AdminSiteController::class, 'storeCert'])->middleware('throttle:admin-write-20');
    Route::put('/all-certs/{id}', [AdminSiteController::class, 'updateCert'])->middleware('throttle:admin-write-20');
    Route::delete('/all-certs/{id}', [AdminSiteController::class, 'destroyCert'])->middleware('throttle:admin-write-10');
    Route::get('/all-acls', [AdminSiteController::class, 'acls']);
    Route::match(['GET', 'POST'], '/cc/{kind}', [AdminCcController::class, 'handle'])
        ->whereIn('kind', ['matcher', 'filter', 'rule'])->middleware('throttle:admin-write-60');
    Route::match(['PUT', 'DELETE'], '/cc/{kind}/{id}', [AdminCcController::class, 'handle'])
        ->whereIn('kind', ['matcher', 'filter', 'rule'])->whereNumber('id')->middleware('throttle:admin-write-20');

    // 四层转发（管理端）
    Route::get('/streams', [AdminStreamController::class, 'index']);
    Route::post('/streams', [AdminStreamController::class, 'store'])->middleware('throttle:admin-write-20');
    Route::put('/streams/{id}', [AdminStreamController::class, 'update'])->middleware('throttle:admin-write-20');
    Route::put('/streams/{id}/enable', [AdminStreamController::class, 'setEnabled'])->middleware('throttle:admin-write-20');
    Route::delete('/streams/{id}', [AdminStreamController::class, 'destroy'])->middleware('throttle:admin-write-10');
    Route::get('/stream-groups', [AdminStreamController::class, 'groups']);
    Route::post('/stream-groups', [AdminStreamController::class, 'storeGroup'])->middleware('throttle:admin-write-20');
    Route::put('/stream-groups/{id}', [AdminStreamController::class, 'updateGroup'])->middleware('throttle:admin-write-20');
    Route::delete('/stream-groups/{id}', [AdminStreamController::class, 'destroyGroup'])->middleware('throttle:admin-write-10');

    // DNS 管理
    Route::get('/dns-apis', [AdminDnsController::class, 'index']);
    Route::post('/dns-apis', [AdminDnsController::class, 'store'])->middleware('throttle:admin-write-20');
    Route::put('/dns-apis/{id}', [AdminDnsController::class, 'update'])->middleware('throttle:admin-write-20');
    Route::delete('/dns-apis/{id}', [AdminDnsController::class, 'destroy'])->middleware('throttle:admin-write-10');

    // 全局 DNS 设置：主控口中的「请先设置DNS」，与上面的 DNS API 凭据无关
    Route::get('/dns-setting', [AdminDnsController::class, 'dnsSettingShow']);
    Route::put('/dns-setting', [AdminDnsController::class, 'dnsSettingUpdate'])->middleware('throttle:admin-write-20');
    // CNAME 域名：主控在生成 DNS 线路前要求至少存在一个
    Route::get('/cname-domains', [AdminDnsController::class, 'cnameIndex']);
    Route::post('/cname-domains', [AdminDnsController::class, 'cnameStore'])->middleware('throttle:admin-write-20');
    Route::put('/cname-domains/{id}', [AdminDnsController::class, 'cnameUpdate'])->middleware('throttle:admin-write-20');
    Route::delete('/cname-domains/{id}', [AdminDnsController::class, 'cnameDestroy'])->middleware('throttle:admin-write-10');
    // ACL 规则管理
    Route::post('/acls', [AdminSiteController::class, 'storeAcl'])->middleware('throttle:admin-write-20');
    Route::put('/acls/{id}', [AdminSiteController::class, 'updateAcl'])->middleware('throttle:admin-write-20');
    Route::delete('/acls/{id}', [AdminSiteController::class, 'destroyAcl'])->middleware('throttle:admin-write-10');

    // 财务管理
    Route::get('/finance/summary', [AdminFinanceController::class, 'summary']);
    Route::get('/orders', [AdminFinanceController::class, 'index']);
    Route::put('/orders/{order}/status', [AdminFinanceController::class, 'updateOrderStatus']);
    Route::get('/services', [AdminFinanceController::class, 'services']);
    Route::get('/cdnfly-user-packages', [AdminFinanceController::class, 'userPackages']);
    Route::post('/cdnfly-user-packages', [AdminFinanceController::class, 'storeUserPackage'])->middleware('throttle:admin-write-20');
    Route::put('/cdnfly-user-packages/{id}', [AdminFinanceController::class, 'updateUserPackage'])->middleware('throttle:admin-write-20');
    Route::delete('/cdnfly-user-packages/{id}', [AdminFinanceController::class, 'destroyUserPackage'])->middleware('throttle:admin-write-10');
    Route::get('/cdnfly-user-packages/{id}/upgrades', [AdminFinanceController::class, 'listUserPackageUpgrades']);
    Route::post('/cdnfly-user-packages/{id}/upgrades', [AdminFinanceController::class, 'addUserPackageUpgrade'])->middleware('throttle:admin-write-20');
    Route::delete('/cdnfly-user-packages/{id}/upgrades/{upgradeId}', [AdminFinanceController::class, 'removeUserPackageUpgrade'])->middleware('throttle:admin-write-10');

    // 套餐组管理
    Route::get('/package-groups', [AdminFinanceController::class, 'listPackageGroups']);
    Route::post('/package-groups', [AdminFinanceController::class, 'storePackageGroup'])->middleware('throttle:admin-write-20');
    Route::put('/package-groups/{id}', [AdminFinanceController::class, 'updatePackageGroup'])->middleware('throttle:admin-write-20');
    Route::delete('/package-groups/{id}', [AdminFinanceController::class, 'destroyPackageGroup'])->middleware('throttle:admin-write-10');

    // 升级包管理
    Route::get('/package-ups', [AdminFinanceController::class, 'listPackageUps']);
    Route::post('/package-ups', [AdminFinanceController::class, 'storePackageUp'])->middleware('throttle:admin-write-20');
    Route::put('/package-ups/{id}', [AdminFinanceController::class, 'updatePackageUp'])->middleware('throttle:admin-write-20');
    Route::delete('/package-ups/{id}', [AdminFinanceController::class, 'destroyPackageUp'])->middleware('throttle:admin-write-10');

    // 监控日志
    Route::get('/waf-logs/{id}', [AdminWafLogController::class, 'detail'])->where('id', '[A-Za-z0-9_-]+');
    Route::post('/waf-logs/unlock', [AdminWafLogController::class, 'unlock'])->middleware('throttle:admin-write-30');
    Route::get('/access-log-jobs', [AdminAccessLogController::class, 'jobs']);
    Route::post('/access-log-jobs', [AdminAccessLogController::class, 'store'])->middleware('throttle:admin-write-30');
    Route::get('/access-log-jobs/{id}/download', [AdminAccessLogController::class, 'download'])->whereNumber('id');
    Route::get('/access-logs/{id}', [AdminAccessLogController::class, 'detail'])->where('id', '[A-Za-z0-9_-]+');
    Route::get('/logs/login', [AdminMonitorController::class, 'loginLogs']);
    Route::get('/logs/op', [AdminMonitorController::class, 'opLogs']);
    Route::get('/monitor/site-realtime', [AdminMonitorController::class, 'siteRealtime']);
    Route::get('/monitor/stream-realtime', [AdminMonitorController::class, 'streamRealtime']);
    Route::get('/monitor/site-top', [AdminMonitorController::class, 'siteTop']);
    Route::get('/monitor/stream-top', [AdminMonitorController::class, 'streamTop']);

    Route::post('/workspace/blackip/unlock', [AdminBlockLogController::class, 'unlock'])->middleware('throttle:admin-write-30');
    Route::get('/workspace/{resource}/export', [AdminBlockLogController::class, 'export'])->whereIn('resource', ['blackip', 'history-blackip']);
    Route::match(['GET', 'POST', 'PUT', 'DELETE'], '/workspace/{resource}/{id?}', [AdminWorkspaceController::class, 'handle'])->whereNumber('id')->middleware('throttle:admin-write-30');

    // 系统配置
    Route::get('/configs', [AdminConfigController::class, 'index']);
    // 单项 upsert：CDNfly 用 作用域+类型+名称 定位一条配置，行里没有 id。
    Route::put('/configs', [AdminConfigController::class, 'update'])->middleware('throttle:admin-write-20');
    Route::get('/register-info', [AdminConfigController::class, 'registerInfo']);

    // 通用管理端代理
    Route::any('proxy/{path}', [AdminController::class, 'proxy'])->where('path', '.*');
});

// ─── 订单和支付 ─────────────────────────────────────
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/orders/recharge', [OrderController::class, 'recharge'])->middleware('throttle:10,1');
    Route::get('/orders/{orderNo}', [OrderController::class, 'show']);
    Route::post('/orders/{orderNo}/provision', [OrderController::class, 'provision'])->middleware('throttle:10,1');
    Route::get('/service-instances', [ServiceInstanceController::class, 'index']);
    Route::post('/payments/epusdt/create', [EpusdtController::class, 'create']);
});

Route::post('/payments/epusdt/notify', [EpusdtController::class, 'notify']);
