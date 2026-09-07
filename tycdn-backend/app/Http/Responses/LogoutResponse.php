<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $url = rtrim((string) config('app.frontend_url', 'http://127.0.0.1:5177'), '/').'/login';

        // Inertia::location() 返回 409 + X-Inertia-Location 头，
        // Inertia 客户端收到后会执行 window.location.href 全页面跳转，
        // 从而正确跳到外部 URL（前端 5177）。
        return Inertia::location($url);
    }
}
