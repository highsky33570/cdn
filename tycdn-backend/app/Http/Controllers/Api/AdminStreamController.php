<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStreamController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllStreams($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function setEnabled(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'enable' => ['required', 'integer', 'in:0,1'],
        ]);

        try {
            $result = $this->cdnfly->adminUpdateStream($id, $validated);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminUpdateStream($id, ['enable' => 0]);
            $this->cdnfly->adminDeleteStream($id);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->validateStream($request, true);

        try {
            $result = $this->cdnfly->adminCreateStream($request->all());

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->validateStream($request, false);

        try {
            $result = $this->cdnfly->adminUpdateStream($id, $request->all());

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function groups(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listStreamGroups($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    private function validateStream(Request $request, bool $creating): void
    {
        $presence = $creating ? 'required' : 'sometimes';
        $request->validate([
            'uid' => [$presence, 'integer', 'min:1'],
            'user_package' => [$presence, 'integer', 'min:1'],
            'listen' => [$presence, 'array', 'min:1'],
            'listen.*.port' => ['required', 'integer', 'between:1,65535'],
            'listen.*.protocol' => ['required', 'in:tcp,udp'],
            'backend' => [$presence, 'array', 'min:1'],
            'backend.*.addr' => ['required', 'string'],
            'backend.*.weight' => ['required', 'integer', 'min:1'],
            'backend.*.state' => ['required', 'in:up,down,backup'],
            'backend_port' => [$presence, 'integer', 'between:1,65535'],
            'enable' => ['sometimes', 'integer', 'in:0,1'],
            'proxy_protocol' => ['sometimes', 'integer', 'in:0,1'],
            'balance_way' => ['sometimes', 'in:rr,ip_hash'],
            'conn_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminCreateStreamGroup($request->all());

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminUpdateStreamGroup($id, $request->all());

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyGroup(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminDeleteStreamGroup($id);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
