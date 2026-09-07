<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCdnflyApiKey;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'cdnfly.apikey' => EnsureCdnflyApiKey::class,
        ]);

        // The app container is only reachable through the nginx/LB proxy, so the
        // forwarded headers are trusted. Without this, $request->ip() is the proxy
        // address and HTTPS is not detected behind TLS termination.
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof AuthenticationException && ! $request->is('api/*') && ! $request->expectsJson()) {
                $frontendLogin = rtrim((string) config('app.frontend_url', 'http://127.0.0.1:5177'), '/').'/login';
                $intended = $request->getRequestUri();
                $target = $frontendLogin.'?redirect='.urlencode($intended);

                // Inertia XHR 收到跨域 302 会白屏，改用 Inertia::location() 做全页面跳转
                if ($request->header('X-Inertia')) {
                    return Inertia::location($target);
                }

                return redirect($target);
            }

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null;
            }

            $status = match (true) {
                $e instanceof AuthenticationException => Response::HTTP_UNAUTHORIZED,
                $e instanceof InvalidSignatureException => Response::HTTP_FORBIDDEN,
                $e instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
                $e instanceof TokenMismatchException => 419,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            $message = match (true) {
                $e instanceof InvalidSignatureException => '验证链接已过期或无效，请重新发送验证邮件。',
                $status === Response::HTTP_UNAUTHORIZED => '请先登录后再继续操作。',
                $status === Response::HTTP_FORBIDDEN => '当前账号没有权限执行该操作。',
                $status === Response::HTTP_NOT_FOUND => '请求的接口不存在。',
                $status === 419 => '登录状态已过期，请刷新后重试。',
                $status === Response::HTTP_TOO_MANY_REQUESTS => '请求过于频繁，请稍后再试。',
                // A deliberate HttpException (abort(), ServiceUnavailableHttpException)
                // carries a message we wrote and vetted, so show it. Anything else
                // reaching 5xx is an unexpected crash and must stay generic.
                in_array($status, [
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    Response::HTTP_BAD_GATEWAY,
                    Response::HTTP_SERVICE_UNAVAILABLE,
                    Response::HTTP_GATEWAY_TIMEOUT,
                ], true) => $e instanceof HttpExceptionInterface && $e->getMessage() !== ''
                    ? $e->getMessage()
                    : '服务器内部错误，请稍后重试。',
                default => $e->getMessage() !== '' ? $e->getMessage() : '请求失败',
            };

            if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                Log::error('Unhandled API exception', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                ]);
            }

            return response()->json([
                'ok' => false,
                'message' => $message,
            ], $status);
        });
    })->create();
