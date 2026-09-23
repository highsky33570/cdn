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

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $this->cdnfly->proxyAdminRequest('GET', '/v1/streams/'.$id)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function defaults(Request $request, ?int $id = null): JsonResponse
    {
        $payload = [];
        if ($request->isMethod('GET')) {
            $payload = $request->query();
            $payload['type'] = 'stream';
        } elseif (! $request->isMethod('DELETE')) {
            $payload = $request->validate([
                'uid' => ['required', 'integer', 'min:1'],
                'name' => ['required', 'in:listen_protocol,balance_way,proxy_protocol'],
                'value' => ['required', 'string'],
                'scope_name' => ['required', 'in:global,group'],
                'scope_id' => ['required', 'integer', 'min:0'],
            ]);
            $allowed = ['listen_protocol' => ['tcp', 'udp'], 'balance_way' => ['rr', 'ip_hash'], 'proxy_protocol' => ['0', '1']];
            abort_unless(in_array($payload['value'], $allowed[$payload['name']], true), 422, 'Invalid default value');
            abort_if($payload['scope_name'] === 'group' && $payload['scope_id'] < 1, 422, 'Select a stream group');
            if ($payload['scope_name'] === 'global') {
                $payload['scope_id'] = 0;
            }
            $payload['type'] = 'stream';
        }
        try {
            if ($id !== null) {
                $response = $this->cdnfly->proxyAdminRequest('GET', '/v1/user-configs', ['type' => 'stream', 'limit' => 0]);
                $record = collect($response['data'] ?? [])->first(fn ($row) => (int) ($row['id'] ?? 0) === $id) ?? [];
                if (($record['type'] ?? null) !== 'stream') {
                    return response()->json(['ok' => false, 'message' => 'Stream default not found'], 404);
                }
                if (isset($payload['uid']) && (int) $record['uid'] !== (int) $payload['uid']) {
                    return response()->json(['ok' => false, 'message' => 'Cannot change the setting owner'], 422);
                }
            }
            if (($payload['scope_name'] ?? '') === 'group') {
                $response = $this->cdnfly->proxyAdminRequest('GET', '/v1/stream-groups/'.$payload['scope_id']);
                $group = $response['data'] ?? $response;
                if ((int) ($group['uid'] ?? 0) !== (int) $payload['uid']) {
                    return response()->json(['ok' => false, 'message' => 'Select a group owned by this user'], 422);
                }
            }
            $data = $this->cdnfly->proxyAdminRequest($request->method(), '/v1/user-configs'.($id === null ? '' : '/'.$id), $payload);

            return response()->json(['ok' => true, 'data' => $data]);
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
        $validated = $request->validate([
            'uid' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'des' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $result = $this->cdnfly->adminCreateStreamGroup($validated);

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
