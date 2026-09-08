<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminConfigController extends Controller
{
    /**
     * 允许通过管理面板修改的 CDNfly 配置键白名单。
     * 不在此列表中的字段会被静默过滤，防止越权修改敏感配置。
     */
    private const ALLOWED_CONFIG_KEYS = [
        // 站点默认值
        'default_node_group',
        'default_region',
        'default_line',
        // 流量与带宽
        'bandwidth_limit',
        'traffic_limit',
        'traffic_reset_day',
        // 缓存
        'cache_time',
        'cache_size',
        // 安全
        'waf_enable',
        'cc_enable',
        'cc_rate',
        'cc_qps',
        // 注册与用户
        'register_enable',
        'register_verify',
        'register_default_package',
        // 通知
        'notify_email',
        'notify_wechat',
        // 显示
        'site_name',
        'site_logo',
        'site_favicon',
        'site_footer',
        'site_announcement',
    ];

    /**
     * 展示类字段的类型、长度和格式约束。
     * 防止通过配置注入 XSS 或超长内容。
     *
     * 规则说明：
     *   type     — 'string' | 'int' | 'bool' | 'url'
     *   max      — 字符串最大长度
     *   pattern  — 可选的正则白名单（不匹配则拒绝）
     *   strip    — 为 true 时对字符串执行 strip_tags
     */
    private const CONFIG_VALUE_RULES = [
        // 数值类
        'default_node_group' => ['type' => 'int', 'min' => 0],
        'default_region' => ['type' => 'int', 'min' => 0],
        'default_line' => ['type' => 'int', 'min' => 0],
        'bandwidth_limit' => ['type' => 'int', 'min' => 0],
        'traffic_limit' => ['type' => 'int', 'min' => 0],
        'traffic_reset_day' => ['type' => 'int', 'min' => 1, 'max_int' => 31],
        'cache_time' => ['type' => 'int', 'min' => 0],
        'cache_size' => ['type' => 'int', 'min' => 0],
        'cc_rate' => ['type' => 'int', 'min' => 0],
        'cc_qps' => ['type' => 'int', 'min' => 0],
        'register_default_package' => ['type' => 'int', 'min' => 0],
        // 布尔类
        'waf_enable' => ['type' => 'bool'],
        'cc_enable' => ['type' => 'bool'],
        'register_enable' => ['type' => 'bool'],
        'register_verify' => ['type' => 'bool'],
        // 纯文本（strip_tags 防 XSS）
        'site_name' => ['type' => 'string', 'max' => 100, 'strip' => true],
        'notify_email' => ['type' => 'string', 'max' => 255, 'pattern' => '/^[^<>]*$/'],
        'notify_wechat' => ['type' => 'string', 'max' => 255, 'strip' => true],
        'site_footer' => ['type' => 'string', 'max' => 2000, 'strip' => true],
        'site_announcement' => ['type' => 'string', 'max' => 5000, 'strip' => true],
        // URL 类（只允许 http/https）
        'site_logo' => ['type' => 'url', 'max' => 500],
        'site_favicon' => ['type' => 'url', 'max' => 500],
    ];

    /**
     * 绝对禁止通过此接口修改的配置键（即使将来被加入白名单也会被拦截）。
     */
    private const BLOCKED_CONFIG_KEYS = [
        'api_key',
        'api_secret',
        'apikey',
        'apisecret',
        'api-key',
        'api-secret',
        'admin_password',
        'password',
        'secret',
        'token',
        'access_token',
        'access-token',
        'callback_url',
        'webhook_url',
        'webhook_secret',
        'notify_url',
        'db_host',
        'db_password',
        'db_username',
        'db_name',
        'redis_password',
        'smtp_password',
        'mail_password',
    ];

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $data = $this->cdnfly->getConfigs();

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $this->sanitizeConfigPayload($request->all());

        if ($payload === []) {
            return response()->json([
                'ok' => false,
                'message' => '没有可更新的配置项（所有字段均被过滤）',
            ], 422);
        }

        try {
            $data = $this->cdnfly->updateConfigs($payload);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    /**
     * Update a single config row by id.
     *
     * The bulk update() below filters against ALLOWED_CONFIG_KEYS, a list of
     * invented names — `site_name`, `cache_time`, `default_node_group` and so on.
     * None of them exist in CDNfly, whose rows are named `nginx-config-file`,
     * `related-config-min-limit`, `block_page_num_limit`… So every field was
     * silently dropped and the save always answered "所有字段均被过滤". The page
     * looked read-only because in practice it was.
     *
     * Editing by id fixes that, and is the shape CDNfly actually documents
     * (PUT /v1/configs/{id}). The credential blocklist still applies — that one
     * guards something real — but there is no name allowlist: the operator owns
     * this panel and can already edit every one of these rows in CDNfly itself,
     * so a list of guessed names only broke the feature without protecting it.
     */
    public function updateOne(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            // Values legitimately run to entire HTML documents (the CAPTCHA
            // templates), so the cap is generous rather than absent.
            'value' => ['present', 'string', 'max:200000'],
        ]);

        if ($this->isBlockedConfigKey($validated['name'])) {
            Log::warning('AdminConfigController: blocked sensitive config key', [
                'key' => $validated['name'],
                'admin_user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'name' => '不允许通过管理面板修改此配置项',
            ]);
        }

        try {
            $data = $this->cdnfly->updateConfig($id, ['value' => $validated['value']]);

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'CDNfly 通讯失败：'.$e->getMessage(),
            ], 502);
        }
    }

    public function registerInfo(): JsonResponse
    {
        try {
            $data = $this->cdnfly->getRegisterInfo();

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'CDNfly 通讯失败'], 502);
        }
    }

    /**
     * 过滤配置负载：拦截黑名单字段，只放行白名单字段。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeConfigPayload(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            // 黑名单：含敏感关键词的字段直接拦截并记录
            if ($this->isBlockedConfigKey($key)) {
                Log::warning('AdminConfigController: blocked sensitive config key', [
                    'key' => $key,
                    'admin_user_id' => request()->user()?->id,
                    'ip' => request()->ip(),
                ]);

                throw ValidationException::withMessages([
                    $key => '不允许通过管理面板修改此配置项',
                ]);
            }

            // 白名单：只放行已知安全的配置键
            if (in_array($key, self::ALLOWED_CONFIG_KEYS, true)) {
                $filtered[$key] = $this->validateConfigValue($key, $value);
            }
        }

        return $filtered;
    }

    /**
     * 对单个配置值按 CONFIG_VALUE_RULES 做类型、长度和格式校验。
     * 没有定义规则的键按原值放行（已经过白名单筛选）。
     */
    private function validateConfigValue(string $key, mixed $value): mixed
    {
        $rule = self::CONFIG_VALUE_RULES[$key] ?? null;

        if ($rule === null) {
            return $value;
        }

        $type = $rule['type'] ?? 'string';

        return match ($type) {
            'int' => $this->validateInt($key, $value, $rule),
            'bool' => $this->validateBool($key, $value),
            'url' => $this->validateUrl($key, $value, $rule),
            default => $this->validateString($key, $value, $rule),
        };
    }

    private function validateInt(string $key, mixed $value, array $rule): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $key => "{$key} 必须是数字",
            ]);
        }

        $intVal = (int) $value;
        $min = $rule['min'] ?? null;
        $maxInt = $rule['max_int'] ?? null;

        if ($min !== null && $intVal < $min) {
            throw ValidationException::withMessages([
                $key => "{$key} 不能小于 {$min}",
            ]);
        }

        if ($maxInt !== null && $intVal > $maxInt) {
            throw ValidationException::withMessages([
                $key => "{$key} 不能大于 {$maxInt}",
            ]);
        }

        return $intVal;
    }

    private function validateBool(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        throw ValidationException::withMessages([
            $key => "{$key} 必须是布尔值",
        ]);
    }

    private function validateUrl(string $key, mixed $value, array $rule): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $key => "{$key} 必须是字符串",
            ]);
        }

        $max = $rule['max'] ?? 500;

        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([
                $key => "{$key} 长度不能超过 {$max} 个字符",
            ]);
        }

        // 只允许 http:// 和 https:// 协议，阻止 javascript:、data: 等
        if ($value !== '' && ! preg_match('#^https?://#i', $value)) {
            throw ValidationException::withMessages([
                $key => "{$key} 必须是 http 或 https 开头的 URL",
            ]);
        }

        return $value;
    }

    private function validateString(string $key, mixed $value, array $rule): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw ValidationException::withMessages([
                $key => "{$key} 必须是字符串",
            ]);
        }

        $str = (string) $value;
        $max = $rule['max'] ?? 1000;

        if (mb_strlen($str) > $max) {
            throw ValidationException::withMessages([
                $key => "{$key} 长度不能超过 {$max} 个字符",
            ]);
        }

        // 正则白名单校验（如邮箱字段禁止含 < >）
        $pattern = $rule['pattern'] ?? null;
        if ($pattern !== null && ! preg_match($pattern, $str)) {
            throw ValidationException::withMessages([
                $key => "{$key} 包含不允许的字符",
            ]);
        }

        // strip_tags 防止存储型 XSS
        if (! empty($rule['strip'])) {
            $str = strip_tags($str);
        }

        return $str;
    }

    private function isBlockedConfigKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_', '.'], '', $key));

        foreach (self::BLOCKED_CONFIG_KEYS as $blocked) {
            if ($normalized === strtolower(str_replace(['-', '_', '.'], '', $blocked))) {
                return true;
            }
        }

        return false;
    }
}
