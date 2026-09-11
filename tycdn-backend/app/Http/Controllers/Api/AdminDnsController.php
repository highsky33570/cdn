<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDnsController extends Controller
{
    use ReportsCdnflyFailures;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listAllDnsApis($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'auth' => ['required', 'array'],
            'des' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->cdnfly->adminCreateDnsApi($validated);

            return response()->json(['ok' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string'],
            'auth' => ['sometimes', 'array'],
            'des' => ['nullable', 'string', 'max:500'],
            'enable' => ['sometimes', 'integer', 'in:0,1'],
        ]);

        try {
            $result = $this->cdnfly->adminUpdateDnsApi($id, $validated);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->cdnfly->adminDeleteDnsApi($id);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    // ─── 全局 DNS 设置 ────────────────────────────────────────
    // The master calls this simply "DNS". It is what 请先设置DNS refers to,
    // and it is NOT the DNS API credential above: that one is a per-user ACME
    // credential for issuing certificates. Without this the master refuses to
    // accept a CNAME domain and never generates any DNS lines.

    public function dnsSettingShow(): JsonResponse
    {
        try {
            $data = $this->cdnfly->getDnsSetting();

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function dnsSettingUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dns' => ['required', 'string', 'in:aliyun,huaweicloud,dns_la,dnspod_cn,dnspod_com,dnsdotcom,cloudflare'],
            'id' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:500'],
            // The master rejects a TTL below its provider minimum; 60 is the
            // lowest any of the supported providers accepts.
            'ttl' => ['required', 'integer', 'min:60', 'max:86400'],
            'weight_on' => ['required', 'integer', 'in:0,1'],
        ]);

        try {
            $data = $this->cdnfly->saveDnsSetting($validated);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
    // ─── CNAME domains ───────────────────────────────────────
    // Admin scope, unlike the DNS API credentials above: these live next to
    // /v1/nodes in the master and are keyed with the master api-key, so they
    // go through here rather than the per-user proxy.

    public function cnameIndex(Request $request): JsonResponse
    {
        try {
            $data = $this->cdnfly->listCnameDomains($request->query());

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function cnameStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'des' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->cdnfly->createCnameDomain($validated + ['des' => '']);

            return response()->json(['ok' => true, 'data' => $result], 201);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function cnameUpdate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'des' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->cdnfly->updateCnameDomain($id, $validated + ['des' => '']);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function cnameDestroy(string $id): JsonResponse
    {
        // Comma-separated ids, matching the panel's multi-select delete.
        $ids = array_filter(array_map('trim', explode(',', $id)), 'is_numeric');

        if ($ids === []) {
            return response()->json(['ok' => false, 'message' => '缺少有效的域名 ID'], 422);
        }

        try {
            $this->cdnfly->deleteCnameDomains(array_values($ids));

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
