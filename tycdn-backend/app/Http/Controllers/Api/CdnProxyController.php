<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdnflyApiService;
use App\Support\CdnflyRequestGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CdnProxyController extends Controller
{
    private const ALLOWED_PROXY_PREFIXES = [
        '/v1/sites' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/certs' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/site-groups' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/acls' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/dnsapis' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/cc-matchs' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/cc-filters' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/cc-rules' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/jobs' => ['GET', 'POST'],
        '/v1/monitor/site/realtime' => ['GET'],
        '/v1/monitor/site/top' => ['GET'],
        '/v1/monitor/site/blackip' => ['GET'],
        '/v1/monitor/site/access-log' => ['GET'],
        '/v1/domains' => ['GET'],
        '/v1/cname-check' => ['POST'],
        '/v1/user-configs' => ['GET', 'POST', 'PUT', 'DELETE'],
        '/v1/monitor/usage' => ['GET'],
        '/v1/streams' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/stream-groups' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        '/v1/monitor/stream/realtime' => ['GET'],
        '/v1/monitor/stream/top' => ['GET'],
        // user-packages：只读。写入必须通过订单支付流程，防止用户绕过计费直接修改套餐。
        '/v1/user-packages' => ['GET'],
        // user-package/{id}/upgrades (GET=可用升级列表, POST=购买升级), user-package/{id}/usage (GET)
        '/v1/user-package' => ['GET', 'POST'],
        '/v1/packages' => ['GET'],
        '/v1/package-groups' => ['GET'],
        '/v1/package-ups' => ['GET'],
        '/v1/orders' => ['GET'],
        '/v1/messages' => ['GET'],
        '/v1/messages/sub' => ['GET', 'PUT'],
        '/v1/messages/read' => ['POST'],
        // api-key：只读。写入/删除会导致 Laravel 端存储的凭证失同步，需通过专用接口操作。
        '/v1/api-key' => ['GET'],
        '/v1/log/login' => ['GET'],
        '/v1/user/overview' => ['GET'],
        '/v1/user/certify' => ['GET', 'POST'],
    ];

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    /**
     * ANY /api/cdn/proxy/{path}
     */
    public function handle(Request $request, string $path): JsonResponse
    {
        $path = '/'.ltrim($path, '/');
        $method = $request->method();

        if (! str_starts_with($path, '/v1/')) {
            return response()->json([
                'ok' => false,
                'message' => '不允许代理此路径',
            ], 403);
        }

        if (! $this->isAllowedProxyPath($method, $path)) {
            return response()->json([
                'ok' => false,
                'message' => '不允许代理此路径',
            ], 403);
        }

        try {
            $data = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
                ? $request->query()
                : $request->all();
            $data = CdnflyRequestGuard::rejectPrivilegedFields($data);

            $result = $this->cdnfly->proxyUserRequest($request->user(), $method, $path, $data);

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'CDNfly 通讯失败，请稍后重试',
            ], 502);
        }
    }

    private function isAllowedProxyPath(string $method, string $path): bool
    {
        foreach (self::ALLOWED_PROXY_PREFIXES as $prefix => $methods) {
            if (($path === $prefix || str_starts_with($path, $prefix.'/')) && in_array($method, $methods, true)) {
                return true;
            }
        }

        return false;
    }

}
