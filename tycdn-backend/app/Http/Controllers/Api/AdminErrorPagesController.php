<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminErrorPagesController extends Controller
{
    use ReportsCdnflyFailures;

    private const FIELDS = ['p456', 'p400', 'p403', 'waf_block', 'p502', 'p504', 'p513', 'p512', 'p514', 'host_not_found', 'access_ip_not_allow', 'p515'];

    private const REGIONAL = ['p400', 'p403', 'p502', 'p504', 'p513', 'p512', 'p514', 'host_not_found', 'access_ip_not_allow', 'p515'];

    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    private function path(string $scope = 'global', int $id = 0): string
    {
        return "/v1/configs/{$scope}-{$id}-error_page-error-page";
    }

    private function decode(mixed $value): array
    {
        $data = is_string($value) ? json_decode($value, false, 512, JSON_THROW_ON_ERROR) : $value;
        if (! is_object($data) && (! is_array($data) || array_is_list($data))) {
            throw new \RuntimeException('Invalid error-page configuration');
        }
        $data = (array) $data;
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data) && ! is_string($data[$field])) {
                throw new \RuntimeException('Invalid error-page content');
            }
        }

        return $data;
    }

    public function show(): JsonResponse
    {
        try {
            $result = $this->cdnfly->proxyAdminRequest('GET', $this->path());

            return response()->json(['ok' => true, 'data' => (object) ConfigSecrets::mask($this->decode($result['data']['value'] ?? null))]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function overrides(Request $request): JsonResponse
    {
        $query = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'between:1,100']]);
        try {
            $data = $this->cdnfly->proxyAdminRequest('GET', '/v1/configs', $query + ['type' => 'error_page', 'name' => 'error-page', 'scope_name' => 'region,node']);

            return response()->json(['ok' => true, 'data' => ConfigSecrets::mask($data)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, string $scope = 'global', int $id = 0): JsonResponse
    {
        abort_unless(in_array($scope, ['global', 'region', 'node'], true) && ($scope === 'global' ? $id === 0 : $id > 0), 404);
        $allowed = $scope === 'global' ? self::FIELDS : self::REGIONAL;
        // Preserve template whitespace instead of using the middleware-trimmed JSON bag.
        $raw = $request->isJson() ? json_decode($request->getContent(), true) : $request->all();
        $input = validator(is_array($raw) ? $raw : [], ['patch' => ['present', 'array'], 'patch.*' => ['nullable', 'string', 'max:200000'], 'remove' => ['sometimes', 'array'], 'remove.*' => ['string', 'in:'.implode(',', $allowed)], 'creating' => ['sometimes', 'boolean']])->validate();
        $patch = $input['patch'];
        $remove = $input['remove'] ?? [];
        if (array_diff(array_keys($patch), $allowed) || array_intersect(array_keys($patch), $remove) || ($scope === 'global' && ($remove || ! $patch))) {
            throw ValidationException::withMessages(['patch' => '配置项无效']);
        }
        foreach ($patch as $key => $value) {
            // Empty global HTML is valid; Laravel converts empty strings to null.
            $patch[$key] = $value ?? '';
            if ($scope !== 'global' && $patch[$key] === '') {
                throw ValidationException::withMessages(['patch' => '请输入页面内容，或移除此覆盖项']);
            }
        }
        try {
            if ($scope === 'global') {
                $result = $this->cdnfly->proxyAdminRequest('GET', $this->path());
                $current = $this->decode($result['data']['value'] ?? null);
            } else {
                $result = $this->cdnfly->proxyAdminRequest('GET', '/v1/configs', ['type' => 'error_page', 'name' => 'error-page', 'scope_name' => $scope, 'scope_id' => $id]);
                $row = collect($result['data'] ?? [])->first(fn ($row) => ($row['scope_name'] ?? '') === $scope && (int) ($row['scope_id'] ?? 0) === $id);
                if (($input['creating'] ?? false) && $row) {
                    return response()->json(['ok' => false, 'message' => '该节点或区域已有配置，请编辑现有设置。'], 409);
                }
                if (! ($input['creating'] ?? false) && ! $row) {
                    return response()->json(['ok' => false, 'message' => '配置已不存在，请刷新列表。'], 404);
                }
                $current = $row ? $this->decode($row['value']) : [];
            }
            foreach ($patch as $key => $value) {
                $current[$key] = ConfigSecrets::restore($value, $current[$key] ?? null);
            }
            foreach ($remove as $key) {
                unset($current[$key]);
            }
            if ($scope !== 'global' && ! $current) {
                throw ValidationException::withMessages(['patch' => '请至少保留一个配置项，或删除覆盖配置']);
            }
            $this->cdnfly->proxyAdminRequest('PUT', $this->path($scope, $id), ['value' => json_encode((object) $current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);

            return response()->json(['ok' => true]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function destroy(string $scope, int $id): JsonResponse
    {
        abort_unless(in_array($scope, ['node', 'region'], true) && $id > 0, 404);
        try {
            $this->cdnfly->proxyAdminRequest('DELETE', $this->path($scope, $id));

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
