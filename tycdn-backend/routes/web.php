<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// ─── 跨端口会话桥接 ─────────────────────────────────
// 前端 SPA (5177) 登录后通过签名链接跳转到此路由，
// 在 8013 端建立真正的 web session，然后 redirect 到控制台。
Route::get('auth/bridge', function (Request $request) {
    if (! $request->hasValidSignature()) {
        $fallback = rtrim((string) config('app.frontend_url', 'http://127.0.0.1:5177'), '/').'/login';

        return redirect($fallback);
    }

    $user = User::find($request->query('user'));

    if (! $user) {
        abort(404);
    }

    Auth::login($user, $request->boolean('remember'));
    $request->session()->regenerate();

    $redirect = (string) $request->query('redirect', '/console');

    if (! str_starts_with($redirect, '/')) {
        $redirect = '/console';
    }

    return redirect($redirect);
})->name('auth.bridge');

Route::middleware('guest')->group(function () {
    Route::post('forgot-password', [ApiAuthController::class, 'forgotPassword'])
        ->middleware('throttle:forgot-password')
        ->name('password.email');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'console/Index')->name('dashboard');
    Route::inertia('console', 'console/Index')->name('console');

    $consoleModuleRoutes = [
        ['uri' => 'console/sites', 'name' => 'console.sites', 'module' => 'sites'],
        ['uri' => 'console/site-groups', 'name' => 'console.site-groups', 'module' => 'site-groups'],
        ['uri' => 'console/certificates', 'name' => 'console.certificates', 'module' => 'certificates'],
        ['uri' => 'console/dnsapis', 'name' => 'console.dnsapis', 'module' => 'dnsapis'],
        ['uri' => 'console/cache', 'name' => 'console.cache', 'module' => 'cache'],
        ['uri' => 'console/cache/jobs', 'name' => 'console.cache.jobs', 'module' => 'cache-jobs'],
        ['uri' => 'console/security/acls', 'name' => 'console.security.acls', 'module' => 'security-acls'],
        ['uri' => 'console/security/cc', 'name' => 'console.security.cc', 'module' => 'security-cc'],
        ['uri' => 'console/security/blackip', 'name' => 'console.security.blackip', 'module' => 'security-blackip'],
        ['uri' => 'console/analytics/realtime', 'name' => 'console.analytics.realtime', 'module' => 'analytics-realtime'],
        ['uri' => 'console/analytics/top', 'name' => 'console.analytics.top', 'module' => 'analytics-top'],
        ['uri' => 'console/analytics/logs', 'name' => 'console.analytics.logs', 'module' => 'analytics-logs'],
        ['uri' => 'console/analytics/usage', 'name' => 'console.analytics.usage', 'module' => 'analytics-usage'],
        ['uri' => 'console/streams', 'name' => 'console.streams', 'module' => 'streams'],
        ['uri' => 'console/streams/analytics', 'name' => 'console.streams.analytics', 'module' => 'streams-analytics'],
        ['uri' => 'console/billing/packages', 'name' => 'console.billing.packages', 'module' => 'billing-packages'],
        ['uri' => 'console/billing/subscriptions', 'name' => 'console.billing.subscriptions', 'module' => 'billing-subscriptions'],
        ['uri' => 'console/billing/traffic-packs', 'name' => 'console.billing.traffic-packs', 'module' => 'billing-traffic-packs'],
        ['uri' => 'console/billing/usage', 'name' => 'console.billing.usage', 'module' => 'billing-usage'],
        ['uri' => 'console/billing/orders', 'name' => 'console.billing.orders', 'module' => 'billing-orders'],
        ['uri' => 'console/messages', 'name' => 'console.messages', 'module' => 'messages'],
        ['uri' => 'console/messages/subscriptions', 'name' => 'console.messages.subscriptions', 'module' => 'message-subscriptions'],
        ['uri' => 'console/account/profile', 'name' => 'console.account.profile', 'module' => 'account-profile'],
        ['uri' => 'console/account/certification', 'name' => 'console.account.certification', 'module' => 'account-certification'],
        ['uri' => 'console/account/api-key', 'name' => 'console.account.api-key', 'module' => 'account-api-key'],
        ['uri' => 'console/account/login-logs', 'name' => 'console.account.login-logs', 'module' => 'account-login-logs'],
        ['uri' => 'console/admin', 'name' => 'console.admin', 'module' => 'admin-overview'],
        ['uri' => 'console/admin/users', 'name' => 'console.admin.users', 'module' => 'admin-users'],
        ['uri' => 'console/admin/packages', 'name' => 'console.admin.packages', 'module' => 'admin-packages'],
        ['uri' => 'console/admin/sites', 'name' => 'console.admin.sites', 'module' => 'admin-sites'],
        ['uri' => 'console/admin/nodes', 'name' => 'console.admin.nodes', 'module' => 'admin-nodes'],
        ['uri' => 'console/admin/dns', 'name' => 'console.admin.dns', 'module' => 'admin-dns'],
        ['uri' => 'console/admin/streams', 'name' => 'console.admin.streams', 'module' => 'admin-streams'],
        ['uri' => 'console/admin/finance', 'name' => 'console.admin.finance', 'module' => 'admin-finance'],
        ['uri' => 'console/admin/monitoring', 'name' => 'console.admin.monitoring', 'module' => 'admin-monitoring'],
        ['uri' => 'console/admin/settings', 'name' => 'console.admin.settings', 'module' => 'admin-settings'],
        ['uri' => 'console/admin/security', 'name' => 'console.admin.security', 'module' => 'admin-security'],
    ];

    $consoleModuleRoutes[] = ['uri' => 'console/admin/maintenance', 'name' => 'console.admin.maintenance', 'module' => 'admin-maintenance'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/node-monitoring', 'name' => 'console.admin.node-monitoring', 'module' => 'admin-node-monitoring'];
    // 已售套餐 and 服务实例 are tabs on console/admin/finance (AdminFinance.vue);
    // deep-link a tab with ?tab=packages / ?tab=services. One route, three tabs.
    $consoleModuleRoutes[] = ['uri' => 'console/admin/package-groups', 'name' => 'console.admin.package-groups', 'module' => 'admin-package-groups'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/package-upgrades', 'name' => 'console.admin.package-upgrades', 'module' => 'admin-package-upgrades'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/certificates', 'name' => 'console.admin.certificates', 'module' => 'admin-certificates'];
    // 待接入节点 and 区域节点组线路 are tabs on console/admin/nodes (AdminNodes.vue),
    // not separate pages — one route drives all three via its in-page tab bar.
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/l2-configs', 'name' => 'console.admin.workspace.l2-configs', 'module' => 'admin-workspace-l2-configs'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/l2-conds', 'name' => 'console.admin.workspace.l2-conds', 'module' => 'admin-workspace-l2-conds'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/l2-nodes', 'name' => 'console.admin.workspace.l2-nodes', 'module' => 'admin-workspace-l2-nodes'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/traffic-packages', 'name' => 'console.admin.workspace.traffic-packages', 'module' => 'admin-workspace-traffic-packages'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/user-traffic-packages', 'name' => 'console.admin.workspace.user-traffic-packages', 'module' => 'admin-workspace-user-traffic-packages'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/discounts', 'name' => 'console.admin.workspace.discounts', 'module' => 'admin-workspace-discounts'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/coupons', 'name' => 'console.admin.workspace.coupons', 'module' => 'admin-workspace-coupons'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/coupon-historys', 'name' => 'console.admin.workspace.coupon-historys', 'module' => 'admin-workspace-coupon-historys'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/messages', 'name' => 'console.admin.workspace.messages', 'module' => 'admin-workspace-messages'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/tasks', 'name' => 'console.admin.workspace.tasks', 'module' => 'admin-workspace-tasks'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/attack-log', 'name' => 'console.admin.workspace.attack-log', 'module' => 'admin-workspace-attack-log'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/blackip', 'name' => 'console.admin.workspace.blackip', 'module' => 'admin-workspace-blackip'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/history-blackip', 'name' => 'console.admin.workspace.history-blackip', 'module' => 'admin-workspace-history-blackip'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/node-ip-log', 'name' => 'console.admin.workspace.node-ip-log', 'module' => 'admin-workspace-node-ip-log'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/package-monitor', 'name' => 'console.admin.workspace.package-monitor', 'module' => 'admin-workspace-package-monitor'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/system', 'name' => 'console.admin.config.system', 'module' => 'admin-config-system'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/account', 'name' => 'console.admin.config.account', 'module' => 'admin-config-account'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/payment', 'name' => 'console.admin.config.payment', 'module' => 'admin-config-payment'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/mail', 'name' => 'console.admin.config.mail', 'module' => 'admin-config-mail'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/notifications', 'name' => 'console.admin.config.notifications', 'module' => 'admin-config-notifications'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/cleanup', 'name' => 'console.admin.config.cleanup', 'module' => 'admin-config-cleanup'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/firewall', 'name' => 'console.admin.config.firewall', 'module' => 'admin-config-firewall'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/nginx', 'name' => 'console.admin.config.nginx', 'module' => 'admin-config-nginx'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/resources', 'name' => 'console.admin.config.resources', 'module' => 'admin-config-resources'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/defaults', 'name' => 'console.admin.config.defaults', 'module' => 'admin-config-defaults'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/stream-defaults', 'name' => 'console.admin.config.stream-defaults', 'module' => 'admin-config-stream-defaults'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/errors', 'name' => 'console.admin.config.errors', 'module' => 'admin-config-errors'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/config/node-monitor', 'name' => 'console.admin.config.node-monitor', 'module' => 'admin-config-node-monitor'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/analytics/realtime', 'name' => 'console.admin.analytics.realtime', 'module' => 'admin-analytics-realtime'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/analytics/top', 'name' => 'console.admin.analytics.top', 'module' => 'admin-analytics-top'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/analytics/logs', 'name' => 'console.admin.analytics.logs', 'module' => 'admin-analytics-logs'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/analytics/usage', 'name' => 'console.admin.analytics.usage', 'module' => 'admin-analytics-usage'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/streams/analytics', 'name' => 'console.admin.streams.analytics', 'module' => 'admin-streams-analytics'];
    $consoleModuleRoutes[] = ['uri' => 'console/streams/defaults', 'name' => 'console.streams.defaults', 'module' => 'stream-defaults'];

    $consoleModuleRoutes[] = ['uri' => 'console/streams/groups', 'name' => 'console.streams.groups', 'module' => 'stream-groups'];
    $consoleModuleRoutes[] = ['uri' => 'console/admin/workspace/stream-groups', 'name' => 'console.admin.workspace.stream-groups', 'module' => 'admin-workspace-stream-groups'];
    foreach ($consoleModuleRoutes as $route) {
        $consoleRoute = Route::inertia($route['uri'], 'console/Module', [
            'moduleKey' => $route['module'],
        ]);

        if (str_starts_with($route['uri'], 'console/admin')) {
            $consoleRoute->middleware('admin');
        }

        $consoleRoute->name($route['name']);
    }

    Route::get('console/admin/sites/{site}', function (string $site) {
        return Inertia::render('console/SiteDetail', ['siteId' => $site, 'scope' => 'admin']);
    })->whereNumber('site')->middleware('admin')->name('console.admin.sites.edit');

    Route::get('console/sites/{site}', function (string $site) {
        return Inertia::render('console/SiteDetail', [
            'siteId' => $site,
        ]);
    })->where('site', '[A-Za-z0-9._-]+')->name('console.sites.show');

    Route::get('console/streams/{stream}', function (string $stream) {
        return Inertia::render('console/StreamDetail', [
            'streamId' => $stream,
        ]);
    })->where('stream', '[0-9]+')->name('console.streams.show');
});

require __DIR__.'/settings.php';
