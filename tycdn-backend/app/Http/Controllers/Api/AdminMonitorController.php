<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMonitorController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function loginLogs(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listLoginLogs($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function opLogs(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listOpLogs($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function siteRealtime(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->getSiteRealtimeMonitor($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function streamRealtime(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->getStreamRealtimeMonitor($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }
}
