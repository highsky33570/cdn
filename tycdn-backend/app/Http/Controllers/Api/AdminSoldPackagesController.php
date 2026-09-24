<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSoldPackagesController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $query = $request->validate(['to_package' => ['sometimes', 'integer', 'min:1']]);
        try {
            return response()->json(['ok' => true, 'data' => $this->cdnfly->proxyAdminRequest('GET', "/v1/user-packages/{$id}", $query)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function usage(int $id): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->cdnfly->proxyAdminRequest('GET', "/v1/user-package/{$id}/usage")]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function addUpgrade(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate(['package_up' => ['required', 'integer', 'min:1'], 'amount' => ['required', 'integer', 'min:1']]);
        try {
            return response()->json(['ok' => true, 'data' => $this->cdnfly->proxyAdminRequest('POST', "/v1/user-package/{$id}/upgrades", $payload)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
