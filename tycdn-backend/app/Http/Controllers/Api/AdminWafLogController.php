<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWafLogController extends Controller
{
    use ReportsCdnflyFailures;

    public function detail(string $id, CdnflyApiService $cdnfly): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest('GET', '/v1/monitor/site/attack-log/'.rawurlencode($id))]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function unlock(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $target = $request->validate(['site_id' => ['required', 'integer', 'min:0'], 'ip' => ['required', 'ip']]);
        $target['site_id'] = (int) $target['site_id'];
        $target['filter_name'] = 'waf_auto_block';
        try {
            $current = $cdnfly->proxyAdminRequest('GET', '/v1/monitor/site/blackip', [...$target, 'page' => 1, 'limit' => 1]);
            if ((int) ($current['count'] ?? 0) < 1) {
                return response()->json(['ok' => false, 'message' => '黑名单正在同步或已解锁，请稍后刷新'], 409);
            }

            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest('POST', '/v1/jobs', [
                ['type' => 'unlock_ip', 'data' => [...$target, 'key1' => 'site_id']],
            ])]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
