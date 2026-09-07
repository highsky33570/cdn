<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\CdnflyRequestGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdnCertController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->cdnfly->listCerts(
            $request->user(),
            CdnflyRequestGuard::rejectPrivilegedFields($request->query()),
        );

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $data = $this->cdnfly->getCert($request->user(), $id);

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->cdnfly->createCert(
            $request->user(),
            CdnflyRequestGuard::rejectPrivilegedFields($request->all()),
        );

        return response()->json(['ok' => true, 'data' => $data], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->cdnfly->updateCert(
            $request->user(),
            $id,
            CdnflyRequestGuard::rejectPrivilegedFields($request->all()),
        );

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $this->cdnfly->deleteCert($request->user(), $id);

        return response()->json(['ok' => true, 'data' => $data]);
    }
}
