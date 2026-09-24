<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPackageUpgradeController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    private function forward(string $method, string $path, array $payload = []): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->cdnfly->proxyAdminRequest($method, $path, $payload)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function show(int $id): JsonResponse
    {
        return $this->forward('GET', "/v1/package-ups/{$id}");
    }

    public function batchStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct'], 'enable' => ['required', 'integer', 'in:0,1']]);

        return $this->forward('PUT', '/v1/package-ups', array_map(fn ($id) => ['id' => (int) $id, 'enable' => (int) $data['enable']], $data['ids']));
    }

    public function batchDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct']]);

        return $this->forward('DELETE', '/v1/package-ups/'.implode(',', array_map('intval', $data['ids'])));
    }

    public function sold(Request $request): JsonResponse
    {
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50'], 'uid' => ['sometimes', 'integer', 'min:1'], 'package_up' => ['sometimes', 'integer', 'min:1']]);

        return $this->forward('GET', '/v1/user-package-ups', $data);
    }

    public function soldShow(int $id): JsonResponse
    {
        return $this->forward('GET', "/v1/user-package-ups/{$id}");
    }

    public function soldUpdate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1']]);

        return $this->forward('PUT', "/v1/user-package-ups/{$id}", $data);
    }

    public function soldDelete(int $id): JsonResponse
    {
        return $this->forward('DELETE', "/v1/user-package-ups/{$id}");
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate(['uid' => ['required', 'integer', 'min:1'], 'package_up' => ['required', 'integer', 'min:1'], 'user_package' => ['required', 'integer', 'min:1'], 'amount' => ['required', 'integer', 'min:1']]);

        return $this->forward('POST', '/v1/user-package-ups', $data);
    }
}
