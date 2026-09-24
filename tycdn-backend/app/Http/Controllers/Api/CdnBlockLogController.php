<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CdnBlockLogController extends Controller
{
    use ReportsCdnflyFailures;

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
            $upstream = $cdnfly->exportUserBlackIps($request->user(), $resource, $query);
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
