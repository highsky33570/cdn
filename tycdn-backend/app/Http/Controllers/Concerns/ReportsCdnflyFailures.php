<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * One way to report an upstream CDNfly failure.
 *
 * Two things were wrong with the flat `502 + 'CDNfly 通讯失败'` this replaces.
 *
 * 1. Cloudflare sits in front of console.tycdn.org and *intercepts* an origin
 *    502, discarding the body and serving its own page:
 *
 *        {"title":"Error 502: Bad gateway",
 *         "detail":"The origin web server returned an invalid or incomplete
 *                   response ... the origin is overloaded or misconfigured"}
 *
 *    So the operator was told their own server was broken, when in fact the
 *    server was fine and CDNfly had rejected the request. The real message
 *    never left the building. 500 is passed through intact, which matters far
 *    more here than 502 being the more literally correct gateway status.
 *
 * 2. Even when the body did survive, "CDNfly 通讯失败" says nothing. The
 *    exception already carries CDNfly's own short message — a missing region, a
 *    duplicate name, an expired licence all look identical without it.
 *
 * These routes are admin-only, so surfacing the upstream text leaks nothing an
 * operator cannot already read in the CDNfly panel.
 */
trait ReportsCdnflyFailures
{
    protected function cdnflyFailure(\Throwable $e, ?string $context = null): JsonResponse
    {
        $upstream = trim($e->getMessage());

        Log::warning('CDNfly request failed', [
            'context' => $context,
            'error' => $upstream,
            'exception' => $e::class,
        ]);

        return response()->json([
            'ok' => false,
            // Kept as `message` because every caller in the console reads that key.
            'message' => $upstream !== ''
                ? 'CDNfly 通讯失败：'.$upstream
                : 'CDNfly 通讯失败',
            'upstream_error' => $upstream,
            // Names the operation that failed, so a page firing several requests
            // at once can say which one broke.
            'context' => $context,
        ], 500);
    }
}
