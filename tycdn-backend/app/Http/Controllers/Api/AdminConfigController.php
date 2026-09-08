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
     * 绝对禁止通过此接口修改的配置键。
     *
     * 这是唯一保留的名单。此前还有一份 ALLOWED_CONFIG_KEYS 白名单，里面的键
     * （site_name、cache_time、default_node_group 等）在 CDNfly 中并不存在——真实
     * 的配置名是 nginx-config-file、related-config-min-limit、block_page_num_limit
     * 这一类。结果每次保存都会把所有字段过滤掉并返回「所有字段均被过滤」，页面
     * 看起来是只读的，实际上是坏的。
     *
     * 白名单已删除：运营者本来就能在 CDNfly 面板里改这些配置，一份猜出来的键名
     * 列表只会让功能失效，并不能带来任何实际保护。凭据类字段仍然拦截。
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

    /**
     * Add or update one system config.
     *
     * CDNfly identifies a config by scope + type + name, not by id — its rows
     * carry no id at all, which is why an earlier attempt to address them as
     * PUT /v1/configs/{id} left the console's edit button permanently disabled.
     * Quoting the v6 admin reference for PUT /v1/configs:
     *
     *   「按配置作用域、类型和名称新增或更新一条系统配置。目标配置已存在时更新
     *     其值和启用状态，不存在时创建。」
     *
     * So one row per request, identified the way CDNfly identifies it.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', 'string', 'max:50'],
            'scope_name' => ['nullable', 'string', 'max:100'],
            'scope_id' => ['nullable', 'integer', 'min:0'],
            // Values legitimately run to entire HTML documents (the CAPTCHA
            // templates) and whole nginx configs, so the cap is generous rather
            // than absent.
            'value' => ['present', 'string', 'max:200000'],
            'enable' => ['nullable', 'integer', 'in:0,1'],
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
            $data = $this->cdnfly->upsertConfig(array_filter(
                $validated,
                static fn ($value): bool => $value !== null,
            ));

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
