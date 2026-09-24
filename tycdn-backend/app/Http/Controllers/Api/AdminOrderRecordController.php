<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderRecordController extends Controller
{
    use ReportsCdnflyFailures;

    public function save(Request $request, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        $required = $id === null ? 'required' : 'sometimes';
        $data = $request->validate([
            'uid' => [$required, 'integer', 'min:0'],
            'type' => [$required, 'string', 'min:1'],
            'create_at' => [$required, 'date_format:Y-m-d H:i:s'],
            'pay_at' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'amount' => ['sometimes', 'integer'],
            'real_amount' => ['sometimes', 'integer'],
            'pay_type' => [$required, 'string', 'min:1'],
            'des' => ['sometimes', 'nullable', 'string'],
            'mch_order_no' => ['sometimes', 'nullable', 'string'],
            'transaction_id' => ['sometimes', 'nullable', 'string'],
            'state' => ['sometimes', 'in:已付款,未付款'],
        ]);
        foreach (['des', 'mch_order_no', 'transaction_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] ??= '';
            }
        }
        foreach (['uid', 'amount', 'real_amount'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (int) $data[$key];
            }
        }
        try {
            $result = $cdnfly->proxyAdminRequest($id === null ? 'POST' : 'PUT', '/v1/orders'.($id === null ? '' : '/'.$id), $data);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroy(int $id, CdnflyApiService $cdnfly): JsonResponse
    {
        try {
            $result = $cdnfly->proxyAdminRequest('DELETE', '/v1/orders/'.$id);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
