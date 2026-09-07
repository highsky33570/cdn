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

    foreach ($consoleModuleRoutes as $route) {
        $consoleRoute = Route::inertia($route['uri'], 'console/Module', [
            'moduleKey' => $route['module'],
        ]);

        if (str_starts_with($route['uri'], 'console/admin')) {
            $consoleRoute->middleware('admin');
        }

        $consoleRoute->name($route['name']);
    }

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
