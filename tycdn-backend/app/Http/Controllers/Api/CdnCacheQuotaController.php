<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CdnCacheQuotaController extends Controller
{
    public function __invoke(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $input = $request->validate([
            'type' => ['required', 'in:clean_url,clean_dir,pre_cache_url'],
            'start' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            // Read only these native quota settings with the customer's credentials.
            // Never expose a general configuration proxy or use the master account.
            $config = $cdnfly->proxyUserRequest($request->user(), 'GET', '/v1/configs/global-0-site-'.$input['type']);
            $jobs = $cdnfly->proxyUserRequest($request->user(), 'GET', '/v1/jobs', [
                'type' => $input['type'], 'start' => $input['start'], 'limit' => 1,
            ]);
            $value = $config['data']['value'] ?? null;
            $used = $jobs['count'] ?? null;

            return response()->json(['ok' => true, 'data' => [
                'total' => is_numeric($value) ? max(0, (int) $value) : null,
                'used' => is_numeric($used) ? max(0, (int) $used) : null,
            ]]);
        } catch (\Throwable $error) {
            Log::warning('Customer cache quota read failed', ['user_id' => $request->user()->id, 'message' => $error->getMessage()]);

            return response()->json(['ok' => false, 'message' => '暂时无法读取今日额度，请重试'], 502);
        }
    }
}
