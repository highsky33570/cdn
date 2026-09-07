<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\CdnflyRequestGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdnSiteController extends Controller
{
    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->cdnfly->listSites(
            $request->user(),
            CdnflyRequestGuard::rejectPrivilegedFields($request->query()),
        );

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $data = $this->cdnfly->getSite($request->user(), $id);

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->cdnfly->createSite(
            $request->user(),
            CdnflyRequestGuard::rejectPrivilegedFields($request->all()),
        );

        return response()->json(['ok' => true, 'data' => $data], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->cdnfly->updateSite(
            $request->user(),
            $id,
            CdnflyRequestGuard::rejectPrivilegedFields($request->all()),
        );

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $this->cdnfly->deleteSite($request->user(), $id);

        return response()->json(['ok' => true, 'data' => $data]);
    }
}
