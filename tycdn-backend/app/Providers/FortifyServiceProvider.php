<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            LogoutResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Keep the existing "username or email" login habit while using Fortify.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $account = trim((string) ($request->input('account') ?: $request->input(Fortify::username())));
            $password = (string) $request->input('password', '');

            if ($account === '' || $password === '') {
                return null;
            }

            $user = User::query()
                ->where('email', $account)
                ->orWhere('name', $account)
                ->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        // Public auth screens live on the Vue portal, not here. These Fortify GET
        // routes only exist because Fortify registers them, so send visitors to the
        // portal instead of rendering the superseded starter-kit pages, which would
        // otherwise be a second login UI that bypasses the API captcha flow.
        //
        // Inertia::location, not redirect()->away: the portal is a different origin
        // (tycdn.org vs console.tycdn.org). A plain 302 to another origin is fine for
        // a browser navigation but fatal for an Inertia XHR — the browser blocks the
        // redirected request on CORS and the visit dies as a network error instead of
        // navigating. Inertia::location answers a 409 with X-Inertia-Location for XHR
        // visits, which the client turns into a full page load, and still falls back
        // to a normal away() redirect for ordinary requests.
        Fortify::loginView(fn () => Inertia::location($this->portalUrl('/login')));

        Fortify::registerView(fn () => Inertia::location($this->portalUrl('/register')));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::location($this->portalUrl('/forgot-password')));

        Fortify::resetPasswordView(function (Request $request) {
            $email = (string) $request->query('email', '');
            $url = $this->portalUrl('/reset-password/'.$request->route('token'));

            return Inertia::location($email === '' ? $url : $url.'?'.http_build_query(['email' => $email]));
        });

        Fortify::verifyEmailView(fn () => Inertia::location($this->portalUrl('/verify-email')));

        Fortify::twoFactorChallengeView(fn () => Inertia::location($this->portalUrl('/two-factor-challenge')));

        // Kept in-console: the user is already logged in, so bouncing them to the
        // portal login would be wrong. Guards /settings/security via password.confirm.
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function portalUrl(string $path = ''): string
    {
        return rtrim((string) config('app.frontend_url', 'http://127.0.0.1:5177'), '/').$path;
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $account = trim((string) ($request->input('account') ?: $request->input(Fortify::username())));
            $throttleKey = Str::transliterate(Str::lower($account).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('register', function (Request $request) {
            $email = trim((string) $request->input('email'));
            $name = trim((string) $request->input('name'));
            $throttleKey = Str::transliterate(Str::lower($email.'|'.$name).'|'.$request->ip());

            return Limit::perMinute(3)->by($throttleKey);
        });

        RateLimiter::for('verification-notice', function (Request $request) {
            $identifier = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());

            return Limit::perMinute(3)->by($identifier);
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = trim((string) $request->input('email'));
            $throttleKey = Str::transliterate(Str::lower($email.'|'.$request->ip()));

            return Limit::perMinute(3)->by($throttleKey);
        });

        RateLimiter::for('forgot-password-captcha', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->ip()));

            return Limit::perMinute(12)->by($throttleKey);
        });

        RateLimiter::for('api-key-sync', function (Request $request) {
            $identifier = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());

            return Limit::perMinute(3)->by($identifier);
        });
    }
}
