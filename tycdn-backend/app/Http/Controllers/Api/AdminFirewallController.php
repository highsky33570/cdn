<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ReportsCdnflyFailures;
use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\ConfigSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminFirewallController extends Controller
{
    use ReportsCdnflyFailures;

    private const FIELDS = ['cc_enable', 'waf_enable', 'waf_resource_limits', 'block_time', 'white_time', 'default_block_way', 'tmp_white_total_limit', 'tmp_white_per_limit', 'custom_white', 'custom_black', 'ssl_handshake_limit', 'default_page_refuse', 'force_default_page_cc', 'auto_default_page_cc_qps', 'default_page_rule', 'icmp_drop', 'key', 'resolver', 'auto_delete_access_log', 'auto_switch', 'cc_img_url_type', 'cc_img_url', 'cc_img_url_interval', 'at_hour', 'slider_html', 'captcha_html', 'click_html', 'delay_jump_html', 'rotate_html', 'easy_click_html', 'easy_slider_html', 'log', 'well_known_auto_protect_count', 'internal_qps', 'internal_res_block_time', 'internal_rule', 'ipset_auto_enable', 'site_block_qps'];

    public function __construct(private readonly CdnflyApiService $cdnfly) {}

    private function path(string $scope = 'global', int $id = 0): string
    {
        return '/v1/configs/'.$scope.'-'.$id.'-openresty_config-openresty-config';
    }

    private function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        if (! is_array($decoded) || (array_is_list($decoded) && count($decoded))) {
            throw new \RuntimeException('Invalid firewall configuration');
        }

        return $decoded;
    }

    private function mask(array $value): array
    {
        if (! empty($value['key'])) {
            $value['key'] = ConfigSecrets::MASK;
        }

        return ConfigSecrets::mask($value);
    }

    public function show(): JsonResponse
    {
        try {
            $response = $this->cdnfly->proxyAdminRequest('GET', $this->path());
            $config = $this->decode($response['data']['value'] ?? null);

            return response()->json(['ok' => true, 'data' => $this->mask($config)]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function overrides(Request $request): JsonResponse
    {
        $query = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'limit' => ['sometimes', 'integer', 'between:1,100']]);
        try {
            $response = $this->cdnfly->proxyAdminRequest('GET', '/v1/configs', $query + ['type' => 'openresty_config', 'name' => 'openresty-config', 'scope_name' => 'region,node']);
            foreach ($response['data'] ?? [] as $i => $row) {
                $response['data'][$i]['value'] = json_encode($this->mask($this->decode($row['value'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return response()->json(['ok' => true, 'data' => $response]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    public function update(Request $request, string $scope = 'global', int $id = 0): JsonResponse
    {
        abort_unless(in_array($scope, ['global', 'node', 'region'], true) && ($scope === 'global' ? $id === 0 : $id > 0), 404);
        $input = $request->validate([
            'patch' => ['required', 'array:'.implode(',', self::FIELDS)],
            'remove' => ['sometimes', 'array'],
            'remove.*' => ['required', 'string', 'in:'.implode(',', self::FIELDS)],
            'creating' => ['sometimes', 'boolean'],
        ]);
        abort_if($scope === 'global' && ! empty($input['remove']), 422);
        try {
            if ($scope === 'global') {
                $response = $this->cdnfly->proxyAdminRequest('GET', $this->path());
                $current = $this->decode($response['data']['value'] ?? null);
            } else {
                $response = $this->cdnfly->proxyAdminRequest('GET', '/v1/configs', ['type' => 'openresty_config', 'name' => 'openresty-config', 'scope_name' => $scope, 'scope_id' => $id]);
                $row = collect($response['data'] ?? [])->first(fn ($row) => ($row['scope_name'] ?? '') === $scope && (int) ($row['scope_id'] ?? 0) === $id);
                if (($input['creating'] ?? false) && $row) {
                    return response()->json(['ok' => false, 'message' => '该节点或区域已有配置，请编辑现有设置。'], 409);
                }
                $current = $row ? $this->decode($row['value']) : [];
            }
            $patch = ConfigSecrets::restore($input['patch'], $current);
            $next = $current;
            foreach ($patch as $key => $value) {
                $next[$key] = in_array($key, ['auto_switch', 'log', 'waf_resource_limits'], true) && is_array($value) ? array_replace($current[$key] ?? [], $value) : $value;
            }
            foreach ($input['remove'] ?? [] as $key) {
                unset($next[$key]);
            }
            $this->validateConfig($next, array_keys($patch));
            // The master panel's global CC switch uses booleans, unlike its
            // numeric WAF and region/node override switches.
            if ($scope === 'global' && array_key_exists('cc_enable', $patch)) {
                $next['cc_enable'] = (bool) $next['cc_enable'];
            }
            if (array_key_exists('auto_switch', $patch)) {
                $next['auto_switch']['enable'] = (bool) $next['auto_switch']['enable'];
            }
            // Laravel turns empty form strings into null; native configuration uses empty strings.
            foreach (['key', 'resolver', 'custom_white', 'custom_black', 'cc_img_url', 'slider_html', 'captcha_html', 'click_html', 'delay_jump_html', 'rotate_html', 'easy_click_html', 'easy_slider_html'] as $key) {
                if (array_key_exists($key, $patch) && $next[$key] === null) {
                    $next[$key] = '';
                }
            }
            if (array_key_exists('internal_rule', $patch)) {
                $next['internal_rule'] = array_map(fn ($row) => ['period' => $row['period'] ?? '', 'reqs' => $row['reqs'] ?? ''], $next['internal_rule']);
            }
            $this->cdnfly->proxyAdminRequest('PUT', $this->path($scope, $id), ['value' => json_encode((object) $next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

            return response()->json(['ok' => true, 'data' => $this->mask($next)]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }

    private function validateConfig(array $config, array $changed): void
    {
        $rules = [
            'white_time' => ['integer', 'min:600'],
            'default_block_way' => ['in:ipset,exit,page'],
            'cc_img_url_type' => ['in:system,custom'],
            'auto_delete_access_log' => ['integer', 'in:0,1,2'],
            'at_hour' => ['integer', 'between:0,23'],
            'cc_img_url' => ['nullable', 'url:http,https'],
        ];
        foreach (['cc_enable', 'waf_enable', 'ssl_handshake_limit', 'default_page_refuse', 'icmp_drop', 'force_default_page_cc', 'ipset_auto_enable'] as $key) {
            $rules[$key] = ['boolean'];
        }
        foreach (['block_time', 'tmp_white_total_limit', 'tmp_white_per_limit', 'auto_default_page_cc_qps', 'default_page_rule', 'well_known_auto_protect_count', 'internal_qps', 'internal_res_block_time', 'site_block_qps', 'cc_img_url_interval'] as $key) {
            $rules[$key] = ['integer', 'min:0'];
        }
        foreach (['key', 'resolver', 'custom_white', 'custom_black', 'slider_html', 'captcha_html', 'click_html', 'delay_jump_html', 'rotate_html', 'easy_click_html', 'easy_slider_html'] as $key) {
            $rules[$key] = ['nullable', 'string', 'max:200000'];
        }
        validator($config, array_intersect_key($rules, array_flip($changed)))->validate();
        if (in_array('auto_switch', $changed, true)) {
            validator($config, ['auto_switch' => ['array'], 'auto_switch.enable' => ['required', 'boolean'], 'auto_switch.qps_50x' => ['required', 'integer', 'min:0'], 'auto_switch.qps_total' => ['required', 'integer', 'min:0'], 'auto_switch.rule' => ['required', 'integer', 'min:1'], 'auto_switch.seconds' => ['required', 'integer', 'min:1']])->validate();
        }
        if (in_array('log', $changed, true)) {
            validator($config, ['log' => ['array'], 'log.log_level' => ['required', 'in:debug,info'], 'log.debug_ip' => ['required_if:log.log_level,debug', 'nullable', 'string', 'max:4096']])->validate();
        }
        if (in_array('internal_rule', $changed, true)) {
            validator($config, ['internal_rule' => ['array', 'max:3'], 'internal_rule.*.period' => ['nullable', 'integer', 'min:1'], 'internal_rule.*.reqs' => ['nullable', 'integer', 'min:1']])->validate();
            foreach ($config['internal_rule'] as $row) {
                if (empty($row['period']) !== empty($row['reqs'])) {
                    throw ValidationException::withMessages(['internal_rule' => '请同时填写统计时长和最大次数。']);
                }
            }
        }
        if (in_array('waf_resource_limits', $changed, true)) {
            $ranges = ['candidate_per_param' => [256, 4096], 'candidate_per_request' => [1024, 16384], 'ffi_per_request' => [2048, 32768], 'json_max_leaves' => [64, 4096], 'json_max_nodes' => [1024, 32768], 'json_max_depth' => [16, 128], 'json_max_string_bytes' => [16384, 1048576], 'json_body_bytes' => [262144, 4194304], 'multipart_max_parts' => [16, 512], 'body_scan_bytes' => [1048576, 67108864]];
            $rules = [];
            foreach ($ranges as $key => [$min, $max]) {
                $rules['waf_resource_limits.'.$key] = ['required', 'integer', 'between:'.$min.','.$max];
            }
            validator($config, $rules)->validate();
            $v = $config['waf_resource_limits'];
            if ($v['json_max_leaves'] > $v['candidate_per_param'] || $v['candidate_per_param'] > $v['candidate_per_request'] || $v['json_body_bytes'] > $v['body_scan_bytes']) {
                throw ValidationException::withMessages(['waf_resource_limits' => '请求处理上限之间的大小关系无效。']);
            }
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

    public function images(): JsonResponse
    {
        try {
            $this->cdnfly->proxyAdminRequest('PUT', '/v1/configs/global-0-system-node-config', ['value' => json_encode(['type' => 'download_cc_img', 'rnd' => bin2hex(random_bytes(3))])]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->cdnflyFailure($e, __FUNCTION__);
        }
    }
}
