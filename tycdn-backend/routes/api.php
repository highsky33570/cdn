<?php

use App\Http\Controllers\Api\AdminConfigController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminDnsController;
use App\Http\Controllers\Api\AdminFinanceController;
use App\Http\Controllers\Api\AdminMonitorController;
use App\Http\Controllers\Api\AdminNodeController;
use App\Http\Controllers\Api\AdminSiteController;
use App\Http\Controllers\Api\AdminStreamController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CdnCertController;
use App\Http\Controllers\Api\CdnProxyController;
use App\Http\Controllers\Api\CdnSiteController;
use App\Http\Controllers\Api\EpusdtController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\ServiceInstanceController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'message' => 'Laravel API is running',
    ]);
});

Route::get('/products', [ProductCatalogController::class, 'index']);

// ─── 认证（替代 Flask backend） ─────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:two-factor');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
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

// ─── CDN 用户端接口（需登录 + 需有 CDNfly API key） ──────
Route::middleware(['auth:sanctum', 'verified', 'cdnfly.apikey'])->prefix('cdn')->group(function () {
    // 站点管理
    Route::apiResource('sites', CdnSiteController::class);

    // 证书管理
    Route::apiResource('certs', CdnCertController::class);

    // 通用代理（ACL、DNS API、站点分组等）
    Route::any('proxy/{path}', [CdnProxyController::class, 'handle'])->where('path', '.*');
});

// ─── 管理端接口（需登录 + admin 角色） ──────────────────
// 整体限流 60 次/分钟；写入型操作（创建/修改/删除）单独 20 次/分钟
Route::middleware(['auth:sanctum', 'verified', 'admin', 'throttle:60,1'])->prefix('admin')->group(function () {
    // 管理概览
    Route::get('/overview', [AdminController::class, 'overview']);

    // 用户管理
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::post('/users', [AdminController::class, 'storeUser'])->middleware('throttle:20,1');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->middleware('throttle:20,1');
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->middleware('throttle:10,1');
    Route::post('/users/{id}/sync-api-key', [AdminController::class, 'syncUserApiKey'])->middleware('throttle:10,1');
    Route::post('/users/{id}/recharge', [AdminController::class, 'rechargeUser'])->middleware('throttle:10,1');

    // 套餐管理（CDNfly）
    Route::get('/package-options', [AdminController::class, 'packageOptions']);
    Route::get('/packages', [AdminController::class, 'listPackages']);
    Route::get('/packages/{id}', [AdminController::class, 'showPackage']);
    Route::post('/packages', [AdminController::class, 'createPackage'])->middleware('throttle:20,1');
    // 门户商品：套餐实际的对外售价，与 CDNfly 内部价格无关
    Route::get('/package-products', [AdminController::class, 'packageProducts']);
    Route::put('/package-products/{packageId}', [AdminController::class, 'updatePackageProduct'])->middleware('throttle:30,1');
    Route::put('/packages/batch', [AdminController::class, 'batchUpdatePackages'])->middleware('throttle:10,1');
    Route::put('/packages/{id}', [AdminController::class, 'updatePackage'])->middleware('throttle:20,1');
    Route::delete('/packages/{id}', [AdminController::class, 'deletePackage'])->middleware('throttle:10,1');

    // 用户套餐
    Route::get('/user-packages', [AdminController::class, 'listUserPackages']);

    // 节点管理
    Route::get('/nodes', [AdminNodeController::class, 'index']);
    Route::get('/node-install-command', [AdminNodeController::class, 'installCommand']);
    Route::get('/pending-nodes', [AdminNodeController::class, 'pending']);
    Route::delete('/pending-nodes/{id}', [AdminNodeController::class, 'destroyPending'])->middleware('throttle:10,1');
    Route::post('/nodes', [AdminNodeController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/nodes/{id}', [AdminNodeController::class, 'show']);
    Route::put('/nodes/{id}/enable', [AdminNodeController::class, 'setEnabled'])->middleware('throttle:20,1');
    Route::put('/nodes/{id}', [AdminNodeController::class, 'update'])->middleware('throttle:20,1');
    Route::delete('/nodes/{id}', [AdminNodeController::class, 'destroy'])->middleware('throttle:10,1');
    // 节点组（线路）：套餐必须挂在某个节点组上，所以这里必须可写，
    // 否则只能在 CDNfly 面板里建组，控制台就无法独立完成套餐配置。
    Route::get('/node-groups', [AdminNodeController::class, 'nodeGroups']);
    Route::post('/node-groups', [AdminNodeController::class, 'storeNodeGroup'])->middleware('throttle:20,1');
    Route::put('/node-groups/{id}', [AdminNodeController::class, 'updateNodeGroup'])->middleware('throttle:20,1');
    Route::delete('/node-groups/{id}', [AdminNodeController::class, 'destroyNodeGroup'])->middleware('throttle:10,1');
    // 区域和线路同理：区域 → 节点组 → 套餐，缺任何一环都无法在控制台里配出可售套餐。
    Route::get('/regions', [AdminNodeController::class, 'regions']);
    Route::post('/regions', [AdminNodeController::class, 'storeRegion'])->middleware('throttle:20,1');
    Route::put('/regions/{id}', [AdminNodeController::class, 'updateRegion'])->middleware('throttle:20,1');
    Route::delete('/regions/{id}', [AdminNodeController::class, 'destroyRegion'])->middleware('throttle:10,1');
    // 线路不是可创建的对象：DNS 线路定义在系统配置里，POST /v1/lines 是把节点 IP
    // 绑定到某个节点组的某条线路上。详见 CdnflyApiService::assignLines()。
    Route::get('/lines', [AdminNodeController::class, 'lines']);
    Route::get('/dns-lines', [AdminNodeController::class, 'dnsLines']);
    Route::post('/lines', [AdminNodeController::class, 'storeLine'])->middleware('throttle:20,1');
    Route::delete('/lines/{id}', [AdminNodeController::class, 'destroyLine'])->where('id', '[0-9,]+')->middleware('throttle:10,1');

    // 全部网站（管理端）
    Route::get('/sites', [AdminSiteController::class, 'index']);
    Route::post('/sites', [AdminSiteController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/sites/{id}', [AdminSiteController::class, 'show']);
    Route::put('/sites/{id}', [AdminSiteController::class, 'update'])->middleware('throttle:20,1');
    Route::delete('/sites/{id}', [AdminSiteController::class, 'destroy'])->middleware('throttle:10,1');
    Route::put('/sites/{id}/enable', [AdminSiteController::class, 'setEnabled'])->middleware('throttle:20,1');
    Route::get('/all-certs', [AdminSiteController::class, 'certs']);
    Route::post('/all-certs', [AdminSiteController::class, 'storeCert'])->middleware('throttle:20,1');
    Route::put('/all-certs/{id}', [AdminSiteController::class, 'updateCert'])->middleware('throttle:20,1');
    Route::delete('/all-certs/{id}', [AdminSiteController::class, 'destroyCert'])->middleware('throttle:10,1');
    Route::get('/all-acls', [AdminSiteController::class, 'acls']);

    // 四层转发（管理端）
    Route::get('/streams', [AdminStreamController::class, 'index']);
    Route::post('/streams', [AdminStreamController::class, 'store'])->middleware('throttle:20,1');
    Route::put('/streams/{id}', [AdminStreamController::class, 'update'])->middleware('throttle:20,1');
    Route::put('/streams/{id}/enable', [AdminStreamController::class, 'setEnabled'])->middleware('throttle:20,1');
    Route::delete('/streams/{id}', [AdminStreamController::class, 'destroy'])->middleware('throttle:10,1');
    Route::get('/stream-groups', [AdminStreamController::class, 'groups']);
    Route::post('/stream-groups', [AdminStreamController::class, 'storeGroup'])->middleware('throttle:20,1');
    Route::put('/stream-groups/{id}', [AdminStreamController::class, 'updateGroup'])->middleware('throttle:20,1');
    Route::delete('/stream-groups/{id}', [AdminStreamController::class, 'destroyGroup'])->middleware('throttle:10,1');

    // DNS 管理
    Route::get('/dns-apis', [AdminDnsController::class, 'index']);
    Route::post('/dns-apis', [AdminDnsController::class, 'store'])->middleware('throttle:20,1');
    Route::put('/dns-apis/{id}', [AdminDnsController::class, 'update'])->middleware('throttle:20,1');
    Route::delete('/dns-apis/{id}', [AdminDnsController::class, 'destroy'])->middleware('throttle:10,1');

    // 全局 DNS 设置：主控口中的「请先设置DNS」，与上面的 DNS API 凭据无关
    Route::get('/dns-setting', [AdminDnsController::class, 'dnsSettingShow']);
    Route::put('/dns-setting', [AdminDnsController::class, 'dnsSettingUpdate'])->middleware('throttle:20,1');
    // CNAME 域名：主控在生成 DNS 线路前要求至少存在一个
    Route::get('/cname-domains', [AdminDnsController::class, 'cnameIndex']);
    Route::post('/cname-domains', [AdminDnsController::class, 'cnameStore'])->middleware('throttle:20,1');
    Route::put('/cname-domains/{id}', [AdminDnsController::class, 'cnameUpdate'])->middleware('throttle:20,1');
    Route::delete('/cname-domains/{id}', [AdminDnsController::class, 'cnameDestroy'])->middleware('throttle:10,1');
    // ACL 规则管理
    Route::post('/acls', [AdminSiteController::class, 'storeAcl'])->middleware('throttle:20,1');
    Route::put('/acls/{id}', [AdminSiteController::class, 'updateAcl'])->middleware('throttle:20,1');
    Route::delete('/acls/{id}', [AdminSiteController::class, 'destroyAcl'])->middleware('throttle:10,1');

    // 财务管理
    Route::get('/finance/summary', [AdminFinanceController::class, 'summary']);
    Route::get('/orders', [AdminFinanceController::class, 'index']);
    Route::put('/orders/{order}/status', [AdminFinanceController::class, 'updateOrderStatus']);
    Route::get('/services', [AdminFinanceController::class, 'services']);
    Route::get('/cdnfly-user-packages', [AdminFinanceController::class, 'userPackages']);
    Route::post('/cdnfly-user-packages', [AdminFinanceController::class, 'storeUserPackage'])->middleware('throttle:20,1');
    Route::put('/cdnfly-user-packages/{id}', [AdminFinanceController::class, 'updateUserPackage'])->middleware('throttle:20,1');
    Route::delete('/cdnfly-user-packages/{id}', [AdminFinanceController::class, 'destroyUserPackage'])->middleware('throttle:10,1');
    Route::get('/cdnfly-user-packages/{id}/upgrades', [AdminFinanceController::class, 'listUserPackageUpgrades']);
    Route::post('/cdnfly-user-packages/{id}/upgrades', [AdminFinanceController::class, 'addUserPackageUpgrade'])->middleware('throttle:20,1');
    Route::delete('/cdnfly-user-packages/{id}/upgrades/{upgradeId}', [AdminFinanceController::class, 'removeUserPackageUpgrade'])->middleware('throttle:10,1');

    // 套餐组管理
    Route::get('/package-groups', [AdminFinanceController::class, 'listPackageGroups']);
    Route::post('/package-groups', [AdminFinanceController::class, 'storePackageGroup'])->middleware('throttle:20,1');
    Route::put('/package-groups/{id}', [AdminFinanceController::class, 'updatePackageGroup'])->middleware('throttle:20,1');
    Route::delete('/package-groups/{id}', [AdminFinanceController::class, 'destroyPackageGroup'])->middleware('throttle:10,1');

    // 升级包管理
    Route::get('/package-ups', [AdminFinanceController::class, 'listPackageUps']);
    Route::post('/package-ups', [AdminFinanceController::class, 'storePackageUp'])->middleware('throttle:20,1');
    Route::put('/package-ups/{id}', [AdminFinanceController::class, 'updatePackageUp'])->middleware('throttle:20,1');
    Route::delete('/package-ups/{id}', [AdminFinanceController::class, 'destroyPackageUp'])->middleware('throttle:10,1');

    // 监控日志
    Route::get('/logs/login', [AdminMonitorController::class, 'loginLogs']);
    Route::get('/logs/op', [AdminMonitorController::class, 'opLogs']);
    Route::get('/monitor/site-realtime', [AdminMonitorController::class, 'siteRealtime']);
    Route::get('/monitor/stream-realtime', [AdminMonitorController::class, 'streamRealtime']);

    // 系统配置
    Route::get('/configs', [AdminConfigController::class, 'index']);
    // 单项 upsert：CDNfly 用 作用域+类型+名称 定位一条配置，行里没有 id。
    Route::put('/configs', [AdminConfigController::class, 'update'])->middleware('throttle:20,1');
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
