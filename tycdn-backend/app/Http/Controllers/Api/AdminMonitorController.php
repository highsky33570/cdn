<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMonitorController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function loginLogs(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listLoginLogs($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function opLogs(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listOpLogs($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function backupLogs(Request $request): JsonResponse
    {
        $query = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        try {
            $data = $this->cdnfly->proxyAdminRequest('GET', '/v1/jobs', [...$query, 'type' => 'backup']);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function messageLogs(Request $request): JsonResponse
    {
        $query = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'uid' => ['sometimes', 'integer', 'min:1'], 'msg_id' => ['sometimes', 'integer', 'min:1'],
            'msg_type' => ['sometimes', 'string', 'max:100'], 'media' => ['sometimes', 'string', 'max:30'], 'state' => ['sometimes', 'string', 'max:30'],
        ]);
        try {
            $data = $this->cdnfly->proxyAdminRequest('GET', '/v1/log/msg-send', $query);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function siteRealtime(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->getSiteRealtimeMonitor($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function siteTop(Request $request): JsonResponse
    {
        return $this->ranking($request, 'site', 'top-domain');
    }

    public function streamTop(Request $request): JsonResponse
    {
        return $this->ranking($request, 'stream', 'top-ports');
    }

    private function ranking(Request $request, string $resource, string $type): JsonResponse
    {
        $params = $request->only(['start', 'end', 'recent_time', 'domain', 'port', 'server_port', 'uid']);
        $params['type'] = $type;
        if (empty($params['start']) || empty($params['end'])) {
            $params['recent_time'] ??= '30m';
        }

        try {
            $data = $this->cdnfly->proxyAdminRequest('GET', "/v1/monitor/{$resource}/top", $params);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function streamRealtime(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->getStreamRealtimeMonitor($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
