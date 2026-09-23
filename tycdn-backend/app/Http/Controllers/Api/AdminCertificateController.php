<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminCertificateController extends Controller
{
    use ReportsCdnflyFailures;

    public function users(Request $request, CdnflyApiService $cdnfly): JsonResponse
    {
        $query = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:255']]);
        try {
            $result = $cdnfly->listUsers(['search' => $query['search'] ?? '', 'type' => 2, 'limit' => 20]);
            $rows = array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'name', 'username', 'email'])), $result['data'] ?? []);

            return response()->json(['ok' => true, 'data' => ['data' => $rows]]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function show(int $id, CdnflyApiService $cdnfly): JsonResponse
    {
        try {
            $result = $cdnfly->proxyAdminRequest('GET', '/v1/certs/'.$id);
            $row = $result['data'] ?? $result;
            unset($row['key'], $row['cert']);

            return response()->json(['ok' => true, 'data' => $row]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function defaults(Request $request, int $uid, CdnflyApiService $cdnfly): JsonResponse
    {
        abort_if($uid < 1, 422);
        $input = $request->isMethod('PUT') ? $request->validate([
            'name' => ['required', 'in:cert_default_type,dnsapi'],
            'value' => ['present', 'nullable', 'string', 'max:50'],
        ]) : [];
        if (($input['name'] ?? '') === 'cert_default_type' && ! in_array($input['value'], ['system', 'lets', 'zerossl'], true)) {
            throw ValidationException::withMessages(['value' => '请选择有效的证书类型。']);
        }
        try {
            $result = $cdnfly->proxyAdminRequest('GET', '/v1/user-configs', ['uid' => $uid, 'type' => 'cert', 'limit' => 0]);
            $configs = array_values(array_filter($result['data'] ?? [], fn ($row) => in_array($row['name'] ?? '', ['cert_default_type', 'dnsapi'], true) && ($row['scope_name'] ?? 'global') === 'global' && (int) ($row['uid'] ?? $uid) === $uid && ($row['type'] ?? 'cert') === 'cert'));
            $dns = $cdnfly->proxyAdminRequest('GET', '/v1/dnsapis', ['uid' => $uid, 'limit' => 0]);
            $options = array_values(array_map(fn ($row) => array_intersect_key($row, array_flip(['id', 'name', 'type'])), array_filter($dns['data'] ?? [], fn ($row) => (int) ($row['uid'] ?? $uid) === $uid)));
            if ($request->isMethod('GET')) {
                return response()->json(['ok' => true, 'data' => ['configs' => $configs, 'dnsapis' => $options]]);
            }
            $name = $input['name'];
            $value = $input['value'] ?? '';
            if ($name === 'dnsapi' && $value !== '' && ! in_array($value, array_map(fn ($row) => (string) $row['id'], $options), true)) {
                throw ValidationException::withMessages(['value' => '请选择该用户可用的 DNS API。']);
            }
            $existing = collect($configs)->firstWhere('name', $name);
            if ($value === '' || ($name === 'cert_default_type' && $value === 'system')) {
                if ($existing) {
                    $cdnfly->proxyAdminRequest('DELETE', '/v1/user-configs/'.$existing['id']);
                }
            } else {
                $payload = ['name' => $name, 'value' => $value, 'type' => 'cert', 'scope_name' => 'global', 'scope_id' => 0];
                if ($existing) {
                    $cdnfly->proxyAdminRequest('PUT', '/v1/user-configs/'.$existing['id'], $payload);
                } else {
                    $cdnfly->proxyAdminRequest('POST', '/v1/user-configs', [...$payload, 'uid' => $uid]);
                }
            }

            return response()->json(['ok' => true]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
