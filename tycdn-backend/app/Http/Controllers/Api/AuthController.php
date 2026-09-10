<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CdnflyAccountService;
use App\Services\CdnflyApiService;
use App\Services\RecaptchaService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\CompletePasswordReset;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class AuthController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
        private readonly CdnflyAccountService $accounts,
        private readonly RecaptchaService $recaptcha,
    ) {}

    /**
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fff}]+$/u', 'unique:users,name'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
            'captcha' => ['nullable', 'string'],
        ], [
            'name.unique' => '用户名已被使用',
            'email.unique' => '邮箱已被注册',
        ]);

        if (! $this->recaptcha->validate($validated['captcha'] ?? '')) {
            throw ValidationException::withMessages([
                'captcha' => '人机验证失败，请重试',
            ]);
        }

        try {
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => 'user',
                ]);

                $user->sendEmailVerificationNotification();

                return $user;
            });

            Auth::login($user);

            return response()->json([
                'ok' => true,
                'message' => '注册成功，请查收邮箱完成验证',
                'data' => [
                    'user' => $user->only(['id', 'name', 'email', 'role']),
                    'email_verified' => false,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('API register failed', [
                'email' => $validated['email'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => '注册失败，请稍后重试',
            ], 422);
        }
    }

    /**
     * GET /api/auth/verify-email/{id}/{hash}
     *
     * Signed email verification callback for the SPA flow.
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => '用户不存在',
            ], 404);
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'ok' => false,
                'message' => '验证链接无效',
            ], 403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $user->refresh();

        return response()->json([
            'ok' => true,
            'message' => '邮箱验证成功',
            'data' => [
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }

    /**
     * POST /api/auth/login
     *
     * Pure API authentication — no Fortify controller dependency.
     * Validates credentials, handles 2FA handoff, returns bridge URL.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
            'captcha' => ['nullable', 'string'],
            'redirect' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->recaptcha->validate($validated['captcha'] ?? '')) {
            throw ValidationException::withMessages([
                'captcha' => '人机验证失败，请重试',
            ]);
        }

        $account = trim($validated['account']);

        $user = User::query()
            ->where('email', $account)
            ->orWhere('name', $account)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'ok' => false,
                'message' => '账号或密码错误',
            ], 422);
        }

        // If 2FA is enabled and confirmed, hand off to the two-factor challenge flow.
        // Store the user ID in session so TwoFactorLoginRequest::challengedUser() can
        // retrieve it — this mirrors Fortify's RedirectIfTwoFactorAuthenticatable.
        if ($this->userHasTwoFactor($user)) {
            $request->session()->put('login.id', $user->getKey());
            $request->session()->put('login.remember', $request->boolean('remember'));

            return response()->json([
                'ok' => true,
                'message' => '需要进行两步验证',
                'data' => [
                    'two_factor' => true,
                ],
            ]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'ok' => true,
            'message' => '登录成功',
            'data' => [
                'two_factor' => false,
                'user' => $user->only(['id', 'name', 'email', 'role', 'cdnfly_user_id']),
                'email_verified' => $user->hasVerifiedEmail(),
                'has_api_key' => $user->hasCdnflyApiKey(),
                'redirect' => $this->makeBridgeUrl($user, $request->input('redirect')),
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'message' => '已退出登录',
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'ok' => true,
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role', 'cdnfly_user_id', 'cdnfly_synced_at']),
                'email_verified' => $user->hasVerifiedEmail(),
                'has_api_key' => $user->hasCdnflyApiKey(),
            ],
        ]);
    }

    /**
     * POST /api/auth/resend-verification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => true,
                'message' => '邮箱已验证',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'ok' => true,
            'message' => '验证邮件已重新发送',
        ]);
    }

    /**
     * POST /api/auth/retry-api-key
     */
    public function retryApiKey(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'ok' => false,
                'message' => '请先完成邮箱验证',
            ], 422);
        }

        if ($user->hasCdnflyApiKey()) {
            return response()->json([
                'ok' => true,
                'message' => 'API 密钥已存在',
            ]);
        }

        try {
            // Creates the upstream account too when verification-time sync failed,
            // which used to leave the user permanently stuck with no way out.
            $outcome = $this->accounts->ensureAccount($user);

            return response()->json([
                'ok' => true,
                'message' => $outcome === 'synced' ? 'API 密钥同步成功' : 'API 密钥开通成功',
            ]);
        } catch (\Throwable $e) {
            Log::warning('API key sync failed', [
                'user_id' => $user->id,
                'cdnfly_user_id' => $user->cdnfly_user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'API 密钥开通失败：'.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/auth/two-factor-challenge
     */
    public function twoFactorChallenge(TwoFactorLoginRequest $request): JsonResponse
    {
        $user = $request->challengedUser();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => '两步验证会话已过期，请重新登录',
            ], 422);
        }

        if ($code = $request->validRecoveryCode()) {
            $user->replaceRecoveryCode($code);
        } elseif (! $request->hasValidCode()) {
            return response()->json([
                'ok' => false,
                'message' => '验证码无效',
            ], 422);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'message' => '两步验证成功',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role', 'cdnfly_user_id']),
                'email_verified' => $user->hasVerifiedEmail(),
                'has_api_key' => $user->hasCdnflyApiKey(),
                'redirect' => $this->makeBridgeUrl($user, $request->input('redirect')),
            ],
        ]);
    }

    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
        ]);

        $status = Password::broker(config('fortify.passwords'))->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                app(ResetsUserPasswords::class)->reset($user, $request->all());
                app(CompletePasswordReset::class)(Auth::guard('web'), $user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'ok' => true,
                'message' => '密码重置成功',
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => __($status),
        ], 422);
    }

    /**
     * POST /api/auth/forgot-password
     *
     * Pure API endpoint for requesting a password reset link.
     * Always returns a success response regardless of whether the email exists,
     * to prevent email enumeration.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'captcha' => ['required', 'string'],
        ], [
            'email.required' => '请输入邮箱',
            'email.email' => '请输入正确的邮箱格式',
            'captcha.required' => '请完成人机验证',
        ]);

        if (! $this->recaptcha->validate($validated['captcha'])) {
            throw ValidationException::withMessages([
                'captcha' => '人机验证失败，请重试',
            ]);
        }

        Password::broker()->sendResetLink([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => '如果该邮箱存在账号，我们会发送重置密码邮件。',
        ]);
    }

    /**
     * Check whether a user has two-factor authentication enabled and confirmed.
     */
    private function userHasTwoFactor(User $user): bool
    {
        return ! empty($user->two_factor_secret)
            && ! is_null($user->two_factor_confirmed_at);
    }

    /**
     * Generate a short-lived signed URL that establishes a web session on the
     * Inertia console origin (8013) after the SPA login at the frontend origin
     * (5177).  This avoids the cross-port session cookie issue entirely.
     */
    private function makeBridgeUrl(User $user, ?string $redirect = null): string
    {
        $requested = is_string($redirect) ? trim($redirect) : '';

        $redirect = str_starts_with($requested, '/')
            ? $requested
            : $this->defaultHomeFor($user);

        return URL::temporarySignedRoute('auth.bridge', now()->addMinutes(2), [
            'user' => $user->id,
            'redirect' => $redirect,
        ]);
    }

    /**
     * Landing page for a fresh login, mirroring CDNfly's own split between
     * admin-home and home.
     *
     * /console is the *customer* overview: it proxies /v1/user/overview and so
     * needs the caller's own CDNfly key. An operator account has no reason to hold
     * one, so sending admins there greeted them with "CDNfly API 密钥尚未开通".
     * Operators belong on the admin console, which authenticates with the admin
     * key from config instead.
     *
     * An explicit ?redirect= (a checkout deep-link, say) always wins over this.
     */
    private function defaultHomeFor(User $user): string
    {
        return $user->isAdmin() ? '/console/admin' : '/console';
    }
}
