<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            // The public auth screens (login, verify-email, password reset) live
            // on the Vue portal, a different origin. The console needs the origin
            // to link to them with a real <a>: an Inertia visit to another origin
            // dies on CORS rather than navigating.
            'portal_url' => rtrim((string) config('app.frontend_url', ''), '/'),
            'cdnfly' => [
                'outbound_enabled' => (bool) config('services.cdnfly.outbound_enabled', true),
                'docs_url' => 'https://doc.cdnfly.com',
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
