<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AdminNginxController extends Controller
{
    use ReportsCdnflyFailures;

    private const FIELDS = ['worker_processes', 'worker_connections', 'worker_rlimit_nofile', 'worker_shutdown_timeout', 'logs_dir', 'http.gzip_comp_level', 'http.gzip_http_version', 'http.gzip_min_length', 'http.gzip_vary', 'http.server_addr_outgoing', 'http.proxy_request_buffering', 'http.proxy_buffering', 'http.proxy_cache_dir', 'http.proxy_cache_max_size', 'http.proxy_cache_methods', 'http.proxy_http_version', 'http.proxy_max_temp_file_size', 'http.proxy_next_upstream', 'http.proxy_connect_timeout', 'http.proxy_send_timeout', 'http.proxy_read_timeout', 'http.client_body_buffer_size', 'http.server', 'http.client_max_body_size', 'http.default_type', 'http.keepalive_requests', 'http.keepalive_timeout', 'http.log_not_found', 'http.server_tokens', 'http.large_client_header_buffers', 'http.server_names_hash_max_size', 'http.server_names_hash_bucket_size', 'stream.proxy_connect_timeout', 'stream.proxy_timeout'];

    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    private function path(string $scope = 'global', int $id = 0): string
    {
        return '/v1/configs/'.$scope.'-'.$id.'-nginx_config-nginx-config-file';
    }

    private function decode(mixed $value): array
    {
        $config = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        if (! is_array($config) || (count($config) && array_is_list($config))) {
            throw new \RuntimeException('Invalid nginx configuration');
        }
        foreach (['http', 'stream'] as $section) {
            if (isset($config[$section]) && (! is_array($config[$section]) || (count($config[$section]) && array_is_list($config[$section])))) {
                throw new \RuntimeException('Invalid nginx configuration section');
            }
        }

        return $config;
    }

    public function show(): JsonResponse
    {
        try {
            $response = $this->cdnfly->proxyAdminRequest('GET', $this->path());

            return response()->json(['ok' => true, 'data' => $this->mask($this->decode($response['data']['value'] ?? null))]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function overrides(Request $request): JsonResponse
    {
        $query = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'between:1,100']]);
        try {
            $response = $this->cdnfly->proxyAdminRequest('GET', '/v1/configs', $query + ['type' => 'nginx_config', 'name' => 'nginx-config-file', 'scope_name' => 'region,node']);
            foreach ($response['data'] ?? [] as $i => $row) {
                $response['data'][$i]['value'] = json_encode((object) $this->mask($this->decode($row['value'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return response()->json(['ok' => true, 'data' => $response]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, string $scope = 'global', int $id = 0): JsonResponse
    {
        abort_unless(in_array($scope, ['global', 'node', 'region'], true) && ($scope === 'global' ? $id === 0 : $id > 0), 404);
        $input = $request->validate(['patch' => ['present', 'array'], 'remove' => ['sometimes', 'array'], 'remove.*' => ['string', 'in:'.implode(',', self::FIELDS)], 'creating' => ['sometimes', 'boolean']]);
        if (array_diff(array_keys($input['patch']), self::FIELDS) || ($scope === 'global' && (! empty($input['remove']) || ! $input['patch']))) {
            throw ValidationException::withMessages(['patch' => '配置项无效']);
        }
        $patch = [];
        foreach ($input['patch'] as $path => $value) {
            $patch[$path] = $this->validateValue($path, $value);
        }
        if (array_intersect(array_keys($patch), $input['remove'] ?? [])) {
            throw ValidationException::withMessages(['remove' => '不能同时修改和移除同一配置项']);
        }
        try {
            if ($scope === 'global') {
                $response = $this->cdnfly->proxyAdminRequest('GET', $this->path());
                $current = $this->decode($response['data']['value'] ?? null);
            } else {
                $response = $this->cdnfly->proxyAdminRequest('GET', '/v1/configs', ['type' => 'nginx_config', 'name' => 'nginx-config-file', 'scope_name' => $scope, 'scope_id' => $id]);
                $row = collect($response['data'] ?? [])->first(fn ($row) => ($row['scope_name'] ?? '') === $scope && (int) ($row['scope_id'] ?? 0) === $id);
                if (($input['creating'] ?? false) && $row) {
                    return response()->json(['ok' => false, 'message' => '该节点或区域已有配置，请编辑现有设置。'], 409);
                }
                if (! ($input['creating'] ?? false) && ! $row) {
                    return response()->json(['ok' => false, 'message' => '配置已不存在，请刷新列表。'], 404);
                }
                $current = $row ? $this->decode($row['value']) : [];
            }
            foreach ($patch as $path => $value) {
                Arr::set($current, $path, ConfigSecrets::restore($value, Arr::get($current, $path)));
            }
            Arr::forget($current, $input['remove'] ?? []);
            foreach (['http', 'stream'] as $section) {
                if (isset($current[$section]) && ! $current[$section]) {
                    unset($current[$section]);
                }
            }
            if (! $current) {
                throw ValidationException::withMessages(['patch' => '请至少保留一个配置项，或删除该覆盖配置。']);
            }
            $this->cdnfly->proxyAdminRequest('PUT', $this->path($scope, $id), ['value' => json_encode((object) $current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

            return response()->json(['ok' => true, 'data' => $this->mask($current)]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    private function mask(array $config): array
    {
        $masked = ConfigSecrets::mask($config);
        // Known Nginx directives are not credentials (notably server_tokens).
        foreach (self::FIELDS as $path) {
            if (Arr::has($config, $path)) {
                Arr::set($masked, $path, Arr::get($config, $path));
            }
        }

        return $masked;
    }

    private function validateValue(string $path, mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages(['patch' => $path.' 配置值无效']);
        }
        $value = trim((string) $value);
        $rules = ['required', 'string', 'max:4096', 'not_regex:/[;{}\r\n\x00]/'];
        if (in_array($path, ['worker_connections', 'worker_rlimit_nofile', 'http.client_body_buffer_size', 'http.keepalive_requests', 'http.server_names_hash_max_size', 'http.server_names_hash_bucket_size'], true)) {
            $rules[] = 'regex:/^[1-9]\d*$/';
        } elseif ($path === 'worker_processes') {
            $rules[] = 'regex:/^(auto|[1-9]\d*)$/';
        } elseif ($path === 'http.gzip_comp_level') {
            $rules[] = 'in:1,2,3,4,5,6,7,8,9';
        } elseif (in_array($path, ['http.gzip_http_version', 'http.proxy_http_version'], true)) {
            $rules[] = 'in:1.0,1.1';
        } elseif ($path === 'http.server_addr_outgoing') {
            $rules[] = 'in:0,1';
        } elseif (in_array($path, ['http.gzip_vary', 'http.proxy_request_buffering', 'http.proxy_buffering', 'http.log_not_found', 'http.server_tokens'], true)) {
            $rules[] = 'in:on,off';
        }
        validator(['value' => $value], ['value' => $rules], [], ['value' => $path])->validate();

        return $value;
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
