<?php

namespace App\Services;

use App\Models\ProductCdnflyMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Renders the numeric selling points of a product from the CDNfly package that
 * actually enforces them.
 *
 * Storing "100 Mbps 带宽" as text meant the landing page kept advertising the
 * old number after an operator raised the real limit — nothing linked the two.
 * Here the numbers have one source of truth, and it is the one that enforces
 * them; only non-numeric marketing lines stay as stored text.
 *
 * This feeds a public, unauthenticated endpoint, so it is cached and it never
 * propagates a CDNfly failure: a missing spec set falls back to whatever the
 * product already had.
 */
class PackageSpecResolver
{
    private const CACHE_KEY = 'catalog.package_specs';

    private const CACHE_TTL = 600;

    /**
     * A separate, long-lived copy of the last successful read.
     *
     * Product features are marketing copy now, so they carry no numbers to
     * fall back on. Without this, a master that is briefly unreachable would
     * strip every spec off the storefront; with it the page keeps showing the
     * last known values instead of nothing.
     */
    private const LAST_GOOD_KEY = 'catalog.package_specs.last_good';

    private const LAST_GOOD_TTL = 2592000;

    public function __construct(
        private readonly CdnflyApiService $cdnfly,
    ) {}

    /**
     * Keyed by CDNfly package id; each entry is {limits, lines}.
     *
     * @return array<string, array{limits: array<string, mixed>, lines: list<string>}>
     */
    public function specsByPackageId(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn (): array => $this->resolve(),
        );
    }

    /**
     * The same, keyed by product id, for the products that are mapped.
     *
     * @return array<int, array{limits: array<string, mixed>, lines: list<string>}>
     */
    public function specsByProductId(): array
    {
        $specs = $this->specsByPackageId();

        if ($specs === []) {
            return [];
        }

        $byProduct = [];

        foreach (ProductCdnflyMapping::query()->whereNotNull('cdnfly_plan_id')->get() as $mapping) {
            $planId = (string) $mapping->cdnfly_plan_id;

            if (isset($specs[$planId])) {
                $byProduct[(int) $mapping->product_id] = $specs[$planId];
            }
        }

        return $byProduct;
    }

    /**
     * Drops the short-lived copy so the next read is fresh. The last-known-good
     * copy is deliberately kept: it is only ever consulted when a live read
     * fails, and discarding it would turn a master outage into a blank page.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{limits: array<string, mixed>, lines: list<string>}>
     */
    private function resolve(): array
    {
        try {
            // limit=0 asks CDNfly for every row; the package list is small.
            $payload = $this->cdnfly->listPackages(['limit' => 0]);
        } catch (\Throwable $e) {
            // A public page must not fail because the master is down; serve the
            // last values we saw instead.
            Log::warning('package spec resolution failed', [
                'error' => $e->getMessage(),
                'served_last_good' => Cache::has(self::LAST_GOOD_KEY),
            ]);

            return $this->lastGood();
        }

        $rows = data_get($payload, 'data');

        // A malformed body is as uninformative as an outage — do not let it
        // wipe the storefront either.
        if (! is_array($rows)) {
            return $this->lastGood();
        }

        $specs = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }

            $limits = $this->limits($row);

            if ($limits !== []) {
                $specs[(string) $row['id']] = [
                    'limits' => $limits,
                    'lines' => $this->lines($limits),
                ];
            }
        }

        if ($specs !== []) {
            Cache::put(self::LAST_GOOD_KEY, $specs, self::LAST_GOOD_TTL);
        }

        return $specs;
    }

    /**
     * @return array<string, array{limits: array<string, mixed>, lines: list<string>}>
     */
    private function lastGood(): array
    {
        $specs = Cache::get(self::LAST_GOOD_KEY);

        return is_array($specs) ? $specs : [];
    }

    /**
     * The enforced limits, named the way the storefront talks about them.
     *
     * `main_domain` is the site count a customer recognises as "N 个网站";
     * `domain` counts every hostname including subdomains.
     *
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function limits(array $package): array
    {
        $limits = [];

        foreach ([
            'traffic' => 'traffic',
            'bandwidth' => 'bandwidth',
            'sites' => 'main_domain',
            'domains' => 'domain',
            'connection' => 'connection',
            'stream_port' => 'stream_port',
            'http_port' => 'http_port',
        ] as $name => $key) {
            $value = $this->limit($package, $key);

            if ($value !== null) {
                $limits[$name] = $value;
            }
        }

        $limits['custom_cc_rule'] = $this->enabled($package, 'custom_cc_rule');
        $limits['websocket'] = $this->enabled($package, 'websocket');
        $limits['http3'] = $this->enabled($package, 'http3');

        $ddos = trim((string) ($package['ddos_protect'] ?? ''));
        $limits['ddos'] = ($ddos === '' || $ddos === '不支持') ? null : $ddos;

        return $limits;
    }

    /**
     * Bullet lines for anywhere that wants prose rather than a table. Rendered
     * from the structured limits so the two cannot disagree.
     *
     * @param  array<string, mixed>  $limits
     * @return list<string>
     */
    private function lines(array $limits): array
    {
        $lines = [];

        if (isset($limits['traffic'])) {
            $lines[] = $limits['traffic'] === '不限'
                ? '不限月流量'
                : $limits['traffic'].' GB 月流量';
        }

        // Bandwidth is free text in CDNfly (100Mbps / 1Gbps), so it is passed
        // through rather than formatted.
        if (isset($limits['bandwidth'])) {
            $lines[] = $limits['bandwidth'] === '不限'
                ? '不限带宽'
                : $limits['bandwidth'].' 带宽';
        }

        if (isset($limits['sites'])) {
            $lines[] = $limits['sites'] === '不限'
                ? '不限网站数'
                : $limits['sites'].' 个网站';
        }

        if (isset($limits['connection']) && $limits['connection'] !== '不限') {
            $lines[] = $limits['connection'].' 并发连接';
        }

        if ($limits['custom_cc_rule'] === true) {
            $lines[] = '自定义 CC 规则';
        }

        if (isset($limits['stream_port'])
            && $limits['stream_port'] !== '不限'
            && (int) $limits['stream_port'] > 0) {
            $lines[] = '四层转发 '.$limits['stream_port'].' 端口';
        }

        if ($limits['ddos'] !== null) {
            $lines[] = $limits['ddos'].' DDoS 防护';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function limit(array $package, string $key): ?string
    {
        $value = $package[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value === '-1' ? '不限' : trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function enabled(array $package, string $key): bool
    {
        $value = $package[$key] ?? null;

        return (string) $value === '1';
    }
}
