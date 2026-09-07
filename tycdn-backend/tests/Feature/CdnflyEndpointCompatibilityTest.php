<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards against calling CDNfly endpoints that the installed panel version does
 * not expose.
 *
 * This portal was originally written against an older CDNfly API. On v6.0.8
 * `/v1/acls` no longer exists — it became `/v1/waf-rules` — and the failure was
 * invisible: 404 upstream -> RuntimeException -> the proxy's generic 502 ->
 * Cloudflare replacing the body with its own error page. Three translations
 * between cause and symptom.
 *
 * KNOWN_V6_ENDPOINTS is the path list extracted from the panel's own frontend
 * bundle on a v6.0.8 install:
 *
 *   grep -rhoE "/v1/[a-z0-9/_-]+" /opt/cdnfly-go/panel --include=*.js | sort -u
 *
 * Re-run that after a CDNfly upgrade and update this list; a rename then fails
 * here instead of silently breaking a console page.
 */
class CdnflyEndpointCompatibilityTest extends TestCase
{
    /** @var list<string> */
    private const KNOWN_V6_ENDPOINTS = [
        '/v1/admin/overview', '/v1/agent/upgrades', '/v1/alipay/preorder',
        '/v1/api-key', '/v1/cc-filters', '/v1/cc-matchs', '/v1/cc-rules',
        '/v1/certs', '/v1/cname-check', '/v1/cname-domains', '/v1/common/auth',
        '/v1/common/captcha', '/v1/common/captcha-type', '/v1/common/menu2',
        '/v1/common/package-purchase-notice', '/v1/common/register-info',
        '/v1/common/sysinfo', '/v1/configs', '/v1/coupon-historys', '/v1/coupons',
        '/v1/discounts', '/v1/dnsapis', '/v1/domains', '/v1/email-captcha',
        '/v1/jobs', '/v1/l2-conds', '/v1/l2-configs', '/v1/l2-nodes', '/v1/lines',
        '/v1/login', '/v1/log/ip-switch', '/v1/log/login', '/v1/log/msg-send',
        '/v1/log/op', '/v1/maintain/agent-check', '/v1/master/start-transfer',
        '/v1/master/stop-transfer', '/v1/master/transfer-log',
        '/v1/master/transfer-status', '/v1/master/upgrades', '/v1/messages',
        '/v1/messages/read', '/v1/messages/sub', '/v1/monitor/node/ip-log',
        '/v1/monitor/node/realtime', '/v1/monitor/node/top',
        '/v1/monitor/site/access-log', '/v1/monitor/site/attack-log',
        '/v1/monitor/site/blackip', '/v1/monitor/site/blackip-count',
        '/v1/monitor/site/download-access-log', '/v1/monitor/site/history-blackip',
        '/v1/monitor/site/realtime', '/v1/monitor/site/top',
        '/v1/monitor/stream/access-log', '/v1/monitor/stream/realtime',
        '/v1/monitor/stream/top', '/v1/monitor/usage', '/v1/monitor/usage-count',
        '/v1/monitor/user-package', '/v1/new-user/count', '/v1/node-groups',
        '/v1/nodes', '/v1/node-traffic', '/v1/order/count', '/v1/orders',
        '/v1/package-groups', '/v1/packages', '/v1/package-sold/count',
        '/v1/package-ups', '/v1/pending-nodes', '/v1/phone-captcha',
        '/v1/regions', '/v1/reset-pass', '/v1/site-groups', '/v1/sites',
        '/v1/site-sys-config', '/v1/stream-groups', '/v1/streams', '/v1/tasks',
        '/v1/traffic-packages', '/v1/upload', '/v1/user', '/v1/user/certify',
        '/v1/user-configs', '/v1/user-groups', '/v1/user/login-policy',
        '/v1/user/overview', '/v1/user-package', '/v1/user-packages',
        '/v1/user-package-ups', '/v1/users', '/v1/user-traffic-packages',
        '/v1/user-traffic-package/usage', '/v1/waf-rules', '/v1/wxpay/preorder',
    ];

    public function test_every_cdnfly_path_the_portal_calls_exists_in_v6(): void
    {
        $unknown = [];

        foreach ($this->portalCalledPaths() as $path => $where) {
            if (! $this->isKnown($path)) {
                $unknown[] = "{$path}  ({$where})";
            }
        }

        $this->assertSame([], $unknown, sprintf(
            "these paths are not exposed by CDNfly v6.0.8, so they return 404:\n  %s",
            implode("\n  ", $unknown)
        ));
    }

    public function test_the_v5_acl_path_is_no_longer_used(): void
    {
        foreach ($this->sourceFiles() as $file) {
            $this->assertStringNotContainsString(
                '/v1/acls',
                (string) file_get_contents($file),
                basename($file).' still calls /v1/acls, which v6 replaced with /v1/waf-rules'
            );
        }
    }

    /**
     * @return array<string, string> path => file it came from
     */
    private function portalCalledPaths(): array
    {
        $found = [];

        foreach ($this->sourceFiles() as $file) {
            preg_match_all('#[\'"`](/v1/[a-z0-9/_-]+)#', (string) file_get_contents($file), $m);

            foreach ($m[1] as $path) {
                $found[rtrim($path, '/')] ??= basename($file);
            }
        }

        return $found;
    }

    /**
     * Only files that make real calls. consoleData.ts is excluded: it holds
     * documentation strings with sample ids, not requests.
     *
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        return [
            base_path('app/Services/CdnflyApiService.php'),
            base_path('app/Http/Controllers/Api/CdnProxyController.php'),
            base_path('resources/js/lib/cdnUserApi.ts'),
        ];
    }

    private function isKnown(string $path): bool
    {
        foreach (self::KNOWN_V6_ENDPOINTS as $known) {
            if ($path === $known || str_starts_with($path, $known.'/')) {
                return true;
            }
        }

        return false;
    }
}
