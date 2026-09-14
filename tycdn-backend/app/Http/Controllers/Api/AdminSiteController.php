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
            // An admin creates a site on behalf of a user; without uid the
            // master resolves the caller as the owner and rejects the package
            // with 「指定的套餐不属于当前用户」.
            'uid' => ['required', 'integer', 'min:1'],
            'user_package' => ['required', 'integer', 'min:1'],
            'domain' => ['required', 'string', 'max:255'],
            // The origin port travels separately from the address.
            'backend_http_port' => ['sometimes', 'string', 'max:10'],
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

    /**
     * POST /api/admin/sites/{id}/certificate
     *
     * Issue a free certificate for the site and attach it.
     *
     * This is two calls, in the order the master's own panel makes them
     * (chunk-0871c1ec, applyCertRecur): create the cert, then point the site
     * at the id that comes back. Enabling an https listener without a cert is
     * rejected outright — there is no "just turn on HTTPS" shortcut.
     */
    public function applyCertificate(int $id): JsonResponse
    {
        try {
            $site = $this->siteRecord($this->cdnfly->getAdminSite($id));
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }

        $domain = trim((string) ($site['domain'] ?? ''));

        if ($domain === '') {
            return response()->json(['ok' => false, 'message' => '该网站没有域名，无法申请证书'], 422);
        }

        // The master skips both of these rather than reporting them, which
        // looks like success while nothing happens.
        if ((string) ($site['enable'] ?? '1') === '0') {
            return response()->json(['ok' => false, 'message' => '网站已停用，请先启用后再申请证书'], 422);
        }

        if ($this->hasHttpsListener($site)) {
            return response()->json(['ok' => false, 'message' => '该网站已开启 HTTPS，无需重复申请'], 422);
        }

        // Only the first hostname names the cert; the cert still covers every
        // domain on the site.
        $first = preg_split('/\\s+/', $domain)[0] ?? $domain;

        $payload = [
            'name' => $first.'免费证书',
            'des' => '一键申请',
            'domain' => $domain,
        ];

        if (isset($site['uid']) && (int) $site['uid'] > 0) {
            $payload['uid'] = (int) $site['uid'];
        }

        try {
            $created = $this->cdnfly->adminCreateCert($payload);
            $certId = $this->extractId($created);

            if ($certId === null) {
                return response()->json([
                    'ok' => false,
                    'message' => '证书已提交申请，但未能取得证书 ID，请稍后在证书列表中手动绑定。',
                ], 502);
            }

            $data = $this->cdnfly->updateAdminSite($id, ['https_listen' => ['cert' => $certId]]);

            return response()->json([
                'ok' => true,
                'data' => ['cert_id' => $certId, 'site' => $data],
            ]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    /**
     * CDNfly returns a single record under data, data.0, or at the root.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function siteRecord(array $payload): array
    {
        foreach (['data', 'data.0'] as $path) {
            $row = data_get($payload, $path);

            if (is_array($row) && isset($row['domain'])) {
                return $row;
            }
        }

        return $payload;
    }

    /**
     * The panel treats an empty object as "no https yet".
     *
     * @param  array<string, mixed>  $site
     */
    private function hasHttpsListener(array $site): bool
    {
        $listener = $site['https_listen'] ?? null;

        if (is_string($listener)) {
            return trim($listener) !== '' && trim($listener) !== '{}';
        }

        return is_array($listener) && $listener !== [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractId(array $payload): ?int
    {
        $id = data_get($payload, 'data.id')
            ?? data_get($payload, 'data.0.id')
            ?? data_get($payload, 'id')
            ?? (is_numeric(data_get($payload, 'data')) ? data_get($payload, 'data') : null);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
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
            // HTTPS. A listener without a certificate is rejected by the
            // master as 「https需要指定证书」, so cert is issued first and
            // attached here. Field names verified against its own panel
            // (chunk-0871c1ec).
            'https_listen' => ['sometimes', 'array'],
            'https_listen.port' => ['sometimes', 'string', 'max:50'],
            'https_listen.cert' => ['sometimes', 'integer', 'min:0'],
            'https_listen.hsts' => ['sometimes', 'integer', 'in:0,1'],
            'https_listen.http2' => ['sometimes', 'integer', 'in:0,1'],
            'https_listen.http3' => ['sometimes', 'integer', 'in:0,1'],
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
            // CDNfly names this enable, not status. Sending status left the
            // site disabled while the request reported success, which also
            // made the master silently skip certificate issuance.
            $data = $this->cdnfly->updateAdminSite($id, [
                'enable' => ((bool) $validated['enable']) ? 1 : 0,
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
