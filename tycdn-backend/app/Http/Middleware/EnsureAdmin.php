<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                abort(Response::HTTP_FORBIDDEN, '权限不足，需要管理员权限');
            }

            return response()->json([
                'ok' => false,
                'message' => '权限不足，需要管理员权限',
            ], 403);
        }

        return $next($request);
    }
}
