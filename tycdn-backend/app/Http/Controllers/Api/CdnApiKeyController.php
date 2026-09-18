<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdnApiKeyController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ip' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reset' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('ip', $payload)) {
            $payload['ip'] ??= '';
        }
        if (array_key_exists('reset', $payload)) {
            $payload['reset'] = (bool) $payload['reset'];
        }

        $user = $request->user();
        if (! $user->cdnfly_user_id) {
            return response()->json(['ok' => false, 'message' => '请先同步 CDNfly 账户'], 422);
        }

        try {
            $data = $this->cdnfly->manageUserApiKey($user, $request->method(), $payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
