<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CdnProxyController;
use Tests\TestCase;

/**
 * Every CDNfly path the customer console calls must be in the proxy allowlist,
 * or that page fails with "不允许代理此路径" (403).
 *
 * This reads the paths straight out of the console's own API client, so adding a
 * call there without allowlisting it fails here instead of in the browser. That
 * is how `/v1/monitor/site/history-blackip` and `/v1/monitor/site/blackip-count`
 * shipped broken: the allowlist matches exact path or "prefix/", so the existing
 * `/v1/monitor/site/blackip` entry did not cover its own siblings.
 */
class CdnProxyAllowlistTest extends TestCase
{
    public function test_every_path_the_console_calls_is_allowlisted(): void
    {
        $client = base_path('resources/js/lib/cdnUserApi.ts');
        $this->assertFileExists($client);

        preg_match_all('#[\'"`](/v1/[A-Za-z0-9/_-]*)#', (string) file_get_contents($client), $matches);
        $called = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($called, 'failed to extract any /v1 paths from the console API client');

        // Method-agnostic: the client does not tell us the verb, so this only
        // asserts the path is reachable at all. Verb restrictions are covered by
        // the read-only test below.
        $missing = array_values(array_filter(
            $called,
            fn (string $path): bool => ! $this->isAllowlisted($path, null)
        ));

        $this->assertSame([], $missing, sprintf(
            "these paths are called by the console but not allowlisted, so they 403:\n  %s",
            implode("\n  ", $missing)
        ));
    }

    public function test_the_blocked_ip_page_reads_are_allowed(): void
    {
        foreach ([
            '/v1/monitor/site/blackip',
            '/v1/monitor/site/history-blackip',
            '/v1/monitor/site/blackip-count',
        ] as $path) {
            $this->assertTrue($this->isAllowlisted($path), "{$path} should be readable");
        }
    }

    /**
     * Monitoring is read-only: a customer must not be able to write through it.
     */
    public function test_monitoring_paths_are_read_only(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertFalse(
                $this->isAllowlisted('/v1/monitor/site/history-blackip', $method),
                "{$method} must not be allowed on monitoring paths"
            );
        }
    }

    /**
     * @param  string|null  $method  null = match the path under any verb
     */
    private function isAllowlisted(string $path, ?string $method = 'GET'): bool
    {
        $reflection = new \ReflectionClass(CdnProxyController::class);
        /** @var array<string, list<string>> $allowlist */
        $allowlist = $reflection->getConstant('ALLOWED_PROXY_PREFIXES');

        foreach ($allowlist as $prefix => $methods) {
            if ($path !== $prefix && ! str_starts_with($path, $prefix.'/')) {
                continue;
            }

            if ($method === null || in_array($method, $methods, true)) {
                return true;
            }
        }

        return false;
    }
}
