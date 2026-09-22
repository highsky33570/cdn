<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAccessLogController extends Controller
{
    use ReportsCdnflyFailures;

    public function jobs(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $query = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:300'],
        ]);

        try {
            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest('GET', '/v1/jobs', [
                ...$query, 'type' => 'down_http_access_log',
            ])]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function store(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $rules = [
            'start' => ['required', 'date_format:Y-m-d H:i:s'],
            'end' => ['required', 'date_format:Y-m-d H:i:s', 'after:start'],
            'server_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'node_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'uri_match_type' => ['sometimes', 'nullable', 'in:exact,prefix'],
            'cache_status' => ['sometimes', 'nullable', 'in:HIT,MISS'],
        ];
        foreach (['addr', 'req_uri', 'status', 'host', 'method', 'tls_fp', 'referer', 'country', 'province', 'isp'] as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'string', 'max:4096'];
        }
        $data = array_filter($request->validate($rules), static fn ($value) => $value !== null && $value !== '');
        foreach (['server_port', 'node_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        try {
            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest('POST', '/v1/jobs', [
                'type' => 'down_http_access_log', 'data' => $data,
            ])]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function detail(string $id, CdnflyApiService $cdnfly): JsonResponse
    {
        try {
            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest('GET', '/v1/monitor/site/access-log/'.rawurlencode($id))]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function download(int $id, CdnflyApiService $cdnfly): StreamedResponse|JsonResponse
    {
        try {
            $body = $cdnfly->downloadAdminAccessLog($id)->toPsrResponse()->getBody();

            return response()->streamDownload(function () use ($body): void {
                try {
                    while (! $body->eof()) {
                        echo $body->read(65536);
                    }
                } finally {
                    $body->close();
                }
            }, "access-log-{$id}.gz", ['Content-Type' => 'application/gzip', 'Cache-Control' => 'private, no-store']);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
