<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CdnLogDownloadController extends Controller
{
    use ReportsCdnflyFailures;

    public function __invoke(Request $request, int $id, CdnflyApiService $cdnfly): StreamedResponse|JsonResponse
    {
        try {
            // Upstream authenticates the owner of this completed download job.
            $upstream = $cdnfly->downloadUserAccessLog($request->user(), $id);
            $body = $upstream->toPsrResponse()->getBody();

            return response()->streamDownload(function () use ($body): void {
                try {
                    while (! $body->eof()) {
                        echo $body->read(65536);
                    }
                } finally {
                    $body->close();
                }
            }, "access-log-{$id}.gz", ['Content-Type' => 'application/gzip']);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
