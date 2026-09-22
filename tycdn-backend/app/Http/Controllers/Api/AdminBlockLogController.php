<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBlockLogController extends Controller
{
    use ReportsCdnflyFailures;

    public function unlock(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*' => ['required', 'array:site_id,ip'],
            'items.*.site_id' => ['required', 'integer', 'min:0'],
            'items.*.ip' => ['sometimes', 'required', 'ip'],
        ]);
        $jobs = array_map(static function (array $item): array {
            $data = ['site_id' => (int) $item['site_id'], 'key1' => 'site_id'];
            if (isset($item['ip'])) {
                $data['ip'] = $item['ip'];
            }

            return ['type' => 'unlock_ip', 'data' => $data];
        }, $validated['items']);

        try {
            return response()->json(['ok' => true, 'data' => $cdnfly->proxyAdminRequest('POST', '/v1/jobs', $jobs)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function export(Request $request, string $resource, CdnflyApiService $cdnfly): StreamedResponse|JsonResponse
    {
        $query = $request->validate([
            'ip' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'filter_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start' => ['required_with:end', 'integer', 'min:0'],
            'end' => ['required_with:start', 'integer', 'gte:start'],
        ]);

        try {
            $upstream = $cdnfly->exportAdminBlackIps($resource, $query);
            $body = $upstream->toPsrResponse()->getBody();

            return response()->streamDownload(function () use ($body): void {
                try {
                    while (! $body->eof()) {
                        echo $body->read(65536);
                    }
                } finally {
                    $body->close();
                }
            }, $resource === 'blackip' ? 'black_ip.txt' : 'history_black_ip.txt', [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
