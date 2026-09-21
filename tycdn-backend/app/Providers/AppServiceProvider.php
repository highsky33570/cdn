<?php

namespace App\Providers;

use App\Listeners\InvalidateSessionsOnPasswordReset;
use App\Listeners\SyncCdnflyOnVerified;
use App\Listeners\ThrottleOutboundMail;
use App\Support\CdnflyEncrypter;
use App\Support\EmailVerificationSignature;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CdnflyEncrypter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureEmailVerificationLinks();
        $this->configurePasswordResetLinks();
        $this->configureConsoleRateLimiting();

        Event::listen(Verified::class, SyncCdnflyOnVerified::class);
        Event::listen(PasswordResetEvent::class, InvalidateSessionsOnPasswordReset::class);
        Event::listen(MessageSending::class, ThrottleOutboundMail::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureConsoleRateLimiting(): void
    {
        RateLimiter::for('admin-api', function (Request $request) {
            $operation = $request->isMethodSafe() ? 'reads' : 'writes';
            $user = $request->user()?->getAuthIdentifier() ?? $request->ip();

            $default = $operation === 'reads' ? 600 : 120;

            return Limit::perMinute(max(1, (int) config("rate_limits.admin_{$operation}_per_minute", $default)))
                ->by($operation.':'.$user);
        });

        // Named keys prevent nested limits from incrementing the same counter.
        // Keep record IDs out of the action key so bulk edits share one budget.
        foreach ([10, 20, 30, 60] as $attempts) {
            RateLimiter::for('admin-write-'.$attempts, function (Request $request) use ($attempts) {
                if ($request->isMethodSafe()) {
                    return Limit::none();
                }

                $route = $request->route();
                $action = implode('|', [
                    $request->method(),
                    $route->uri(),
                    $route->parameter('resource', ''),
                    $route->parameter('kind', ''),
                ]);

                return Limit::perMinute($attempts)
                    ->by($request->user()->getAuthIdentifier().'|'.$action);
            });
        }
    }

    protected function configureEmailVerificationLinks(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable): string {
            $backendBaseUrl = rtrim((string) config('app.url'), '/');
            $userId = $notifiable->getKey();
            $emailHash = sha1($notifiable->getEmailForVerification());
            $expires = now()->addMinutes(config('auth.verification.expire', 60))->getTimestamp();
            $token = EmailVerificationSignature::make($userId, $emailHash, $expires);
            $verificationPath = route('api.auth.verify-email', [
                'id' => $userId,
                'hash' => $emailHash,
            ], false);
            $signedUrl = $backendBaseUrl.'/'.ltrim($verificationPath, '/').'?'.http_build_query([
                'expires' => $expires,
                'token' => $token,
            ]);

            return $signedUrl;
        });
    }

    protected function configurePasswordResetLinks(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $frontendBaseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $frontendBaseUrl.'/reset-password/'.$token.'?'.http_build_query([
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
