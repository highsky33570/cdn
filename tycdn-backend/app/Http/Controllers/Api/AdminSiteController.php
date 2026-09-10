<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSiteController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllSites($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->getAdminSite($id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_package' => ['required', 'integer', 'min:1'],
            'domain' => ['required', 'string', 'max:255'],
            'backend' => ['required', 'array', 'min:1'],
            'backend.*.addr' => ['required', 'string', 'max:255'],
            'backend.*.weight' => ['sometimes', 'integer', 'min:1'],
            'backend.*.state' => ['sometimes', 'string', 'in:up,down'],
            'groups' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $data = $this->cdnfly->createAdminSite($validated);

            return response()->json(['ok' => true, 'data' => $data], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_package' => ['sometimes', 'integer', 'min:1'],
            'domain' => ['sometimes', 'string', 'max:255'],
            'backend' => ['sometimes', 'array', 'min:1'],
            'backend.*.addr' => ['required_with:backend', 'string', 'max:255'],
            'backend.*.weight' => ['sometimes', 'integer', 'min:1'],
            'backend.*.state' => ['sometimes', 'string', 'in:up,down'],
            'groups' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated === []) {
            return response()->json(['ok' => false, 'message' => '没有可更新的字段'], 422);
        }

        try {
            $data = $this->cdnfly->updateAdminSite($id, $validated);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $data = $this->cdnfly->deleteAdminSite((string) $id);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function setEnabled(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'enable' => ['required', 'boolean'],
        ]);

        try {
            $data = $this->cdnfly->updateAdminSite($id, [
                'status' => ((bool) $validated['enable']) ? 1 : 0,
            ]);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function certs(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllCerts($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function storeCert(Request $request): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminCreateCert($request->all());

            return response()->json(['ok' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updateCert(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->cdnfly->adminUpdateCert($id, $request->all());

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyCert(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminDeleteCert($id);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function acls(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllAcls($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    // ─── Admin ACL CRUD ────────────────────────────────────
    //
    // /v1/waf-rules exists only at user scope in CDNfly v6, so every one of these
    // acts as the rule's owner via an SSO token rather than as the panel. The
    // owner's CDNfly user id is therefore required on all three, including
    // update and delete, where it was previously not asked for at all.
    public function storeAcl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'default_action' => ['required', 'string', 'in:reject,allow'],
            'data' => ['required', 'array'],
            'des' => ['nullable', 'string', 'max:500'],
            'enable' => ['nullable', 'integer', 'in:0,1'],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->cdnfly->adminCreateAcl((int) $validated['user_id'], $validated);

            return response()->json(['ok' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function updateAcl(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'default_action' => ['sometimes', 'string', 'in:reject,allow'],
            'data' => ['sometimes', 'array'],
            'des' => ['nullable', 'string', 'max:500'],
            'enable' => ['sometimes', 'integer', 'in:0,1'],
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->cdnfly->adminUpdateAcl((int) $validated['user_id'], $id, $validated);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroyAcl(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->cdnfly->adminDeleteAcl((int) $validated['user_id'], $id);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
