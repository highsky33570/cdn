<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBalanceController extends Controller
{
    use ReportsCdnflyFailures;

    public function store(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $input = $request->validate([
            'uid' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:add,reduce'],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'des' => ['nullable', 'string'],
        ]);

        try {
            // The picker returns master user IDs, not local portal user IDs.
            // Preserve the decimal string required by the native recharge API.
            $result = $cdnfly->proxyAdminRequest('POST', '/v1/user/'.((int) $input['uid']).'/recharge', [
                'type' => $input['type'],
                'amount' => $input['amount'],
                'des' => $input['des'] ?? '',
            ]);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
