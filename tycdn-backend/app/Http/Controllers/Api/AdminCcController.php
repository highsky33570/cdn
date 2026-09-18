<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCcController extends Controller
{
    use ReportsCdnflyFailures;

    public function handle(Request $request, string $kind, CdnflyApiService $cdnfly, ?int $id = null): JsonResponse
    {
        $resource = match ($kind) {
            'matcher' => 'cc-matchs',
            'filter' => 'cc-filters',
            'rule' => 'cc-rules',
        };
        $path = '/v1/'.$resource.($id === null ? '' : '/'.$id);

        // Dedicated admin routes allow customer ownership; the customer proxy
        // correctly rejects uid and must retain that restriction.
        try {
            $data = $cdnfly->proxyAdminRequest($request->method(), $path,
                $request->isMethod('GET') ? $request->query() : $request->all());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
