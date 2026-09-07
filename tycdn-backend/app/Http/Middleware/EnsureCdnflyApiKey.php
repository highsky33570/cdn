<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCdnflyApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasCdnflyApiKey()) {
            return response()->json([
                'ok' => false,
                'message' => 'CDNfly API 密钥尚未开通，请联系管理员或重新注册',
            ], 403);
        }

        return $next($request);
    }
}
