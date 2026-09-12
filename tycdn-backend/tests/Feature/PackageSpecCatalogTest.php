<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use App\Models\User;
use App\Services\CdnflyApiService;
use App\Services\PackageSpecResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The numbers on a plan card come from the package that enforces them.
 *
 * Storing "100 Mbps 带宽" as text meant raising the real limit left the
 * homepage advertising the old one, with nothing to detect the drift. The
 * stored text now survives only as a fallback for when CDNfly is unreachable —
 * a public page must never fail because the master is down.
 */
class PackageSpecCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_card_numbers_come_from_the_live_package(): void
    {
        $this->productWithPackage(101, ['被攻击不额外收费']);

        // The operator has since doubled the real limit.
        $this->cdnflyPackages([
            ['id' => 101, 'traffic' => '100', 'bandwidth' => '200Mbps', 'main_domain' => '1'],
        ]);

        $specs = $this->catalogSpecs();

        $this->assertContains('200Mbps 带宽', $specs);
        $this->assertContains('100 GB 月流量', $specs);
        $this->assertContains('1 个网站', $specs);
    }

    /**
     * Authored copy is passed through untouched.
     *
     * An earlier version split specs from marketing by searching stored text
     * for 流量/带宽/… , which swallowed legitimate lines like 「不限流量不加价」.
     * The split is explicit now: specs are derived, features are authored.
     */
    public function test_marketing_lines_are_never_filtered_even_when_they_mention_a_limit(): void
    {
        $this->productWithPackage(101, ['不限流量不加价', '被攻击不额外收费']);

        $this->cdnflyPackages([
            ['id' => 101, 'traffic' => '50', 'bandwidth' => '100Mbps'],
        ]);

        $this->assertSame(
            ['不限流量不加价', '被攻击不额外收费'],
            $this->catalogFeatures(),
        );
    }

    /**
     * Features carry no numbers any more, so an outage would otherwise strip
     * every spec off the storefront. The last successful read covers it.
     */
    public function test_an_unreachable_master_serves_the_last_known_specs(): void
    {
        $this->productWithPackage(101, ['被攻击不额外收费']);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listPackages')
            ->once()
            ->andReturn(['data' => [['id' => 101, 'traffic' => '50', 'bandwidth' => '100Mbps']]]);

        $this->getJson('/api/products')->assertOk();

        // Same resolver, master now unreachable and the short cache expired.
        Cache::forget('catalog.package_specs');
        $cdnfly->shouldReceive('listPackages')
            ->andThrow(new \RuntimeException('master down'));

        $specs = $this->catalogSpecs();

        $this->assertContains('100Mbps 带宽', $specs);
        $this->assertContains('50 GB 月流量', $specs);
    }

    /** With no successful read to fall back on, the page still renders. */
    public function test_a_cold_start_with_an_unreachable_master_still_renders(): void
    {
        $this->productWithPackage(101, ['被攻击不额外收费']);

        $this->mock(CdnflyApiService::class)
            ->shouldReceive('listPackages')
            ->andThrow(new \RuntimeException('master down'));

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertSame(['被攻击不额外收费'], $response->json('data.0.features'));
        $this->assertSame([], $response->json('data.0.specs'));
    }

    public function test_unlimited_reads_as_unlimited_rather_than_minus_one(): void
    {
        $this->productWithPackage(101, []);

        $this->cdnflyPackages([
            ['id' => 101, 'traffic' => '-1', 'bandwidth' => '-1', 'main_domain' => '-1'],
        ]);

        $features = $this->catalogSpecs();

        $this->assertContains('不限月流量', $features);
        $this->assertContains('不限带宽', $features);
        $this->assertContains('不限网站数', $features);
    }

    /** A tier that sells no L4 forwarding must not advertise "0 端口". */
    public function test_zero_valued_capabilities_are_omitted(): void
    {
        $this->productWithPackage(101, []);

        $this->cdnflyPackages([
            [
                'id' => 101, 'traffic' => '50', 'stream_port' => '0',
                'custom_cc_rule' => '0', 'ddos_protect' => '不支持',
            ],
        ]);

        $features = $this->catalogSpecs();

        foreach ($features as $line) {
            $this->assertStringNotContainsString('四层转发', $line);
            $this->assertStringNotContainsString('DDoS', $line);
            $this->assertStringNotContainsString('自定义 CC', $line);
        }
    }

    public function test_capabilities_that_are_sold_do_appear(): void
    {
        $this->productWithPackage(101, []);

        $this->cdnflyPackages([
            [
                'id' => 101, 'traffic' => '200', 'stream_port' => '5',
                'custom_cc_rule' => '1', 'ddos_protect' => '500G',
            ],
        ]);

        $features = $this->catalogSpecs();

        $this->assertContains('四层转发 5 端口', $features);
        $this->assertContains('自定义 CC 规则', $features);
        $this->assertContains('500G DDoS 防护', $features);
    }

    /** An unmapped product has no enforced limits, so it gets no specs. */
    public function test_a_product_without_a_package_has_no_specs(): void
    {
        Product::query()->create([
            'name' => 'Standalone', 'slug' => 'standalone', 'price_monthly' => 5,
            'price_quarterly' => 15, 'price_yearly' => 60, 'currency' => 'USD',
            'features' => ['手写卖点'],
        ]);

        $this->cdnflyPackages([['id' => 999, 'traffic' => '10']]);

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertSame(['手写卖点'], $response->json('data.0.features'));
        $this->assertSame([], $response->json('data.0.specs'));
    }

    /** The frontend lays these out as a table, so the shape matters. */
    public function test_structured_limits_accompany_the_rendered_lines(): void
    {
        $this->productWithPackage(101, []);

        $this->cdnflyPackages([
            [
                'id' => 101, 'traffic' => '200', 'bandwidth' => '1Gbps',
                'main_domain' => '10', 'domain' => '30', 'websocket' => '1',
            ],
        ]);

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertSame('1Gbps', $response->json('data.0.limits.bandwidth'));
        $this->assertSame('200', $response->json('data.0.limits.traffic'));
        $this->assertSame('10', $response->json('data.0.limits.sites'));
        $this->assertSame('30', $response->json('data.0.limits.domains'));
        $this->assertTrue($response->json('data.0.limits.websocket'));
    }

    /** The public endpoint must not hit CDNfly once per visitor. */
    public function test_the_package_lookup_is_cached(): void
    {
        $this->productWithPackage(101, []);

        $this->mock(CdnflyApiService::class)
            ->shouldReceive('listPackages')
            ->once()
            ->andReturn(['data' => [['id' => 101, 'traffic' => '50']]]);

        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/products')->assertOk();
    }

    /** Editing a package must not leave the homepage on the old numbers. */
    public function test_updating_a_package_clears_the_cached_specs(): void
    {
        $this->productWithPackage(101, []);

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('listPackages')
            ->andReturn(['data' => [['id' => 101, 'traffic' => '50']]]);
        $cdnfly->shouldReceive('updatePackage')->andReturn(['code' => 0]);

        $this->getJson('/api/products')->assertOk();
        $this->assertNotNull(app(PackageSpecResolver::class)->specsByPackageId());

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson('/api/admin/packages/101', ['bandwidth' => '500Mbps'])
            ->assertOk();

        $this->assertFalse(Cache::has('catalog.package_specs'));
    }

    /**
     * @param  list<string>  $features
     */
    private function productWithPackage(int $packageId, array $features): void
    {
        $product = Product::query()->create([
            'name' => 'JPN-Mini', 'slug' => 'jpn-mini', 'price_monthly' => 5,
            'price_quarterly' => 15, 'price_yearly' => 60, 'currency' => 'USD',
            'features' => $features,
        ]);

        ProductCdnflyMapping::query()->create([
            'product_id' => $product->id,
            'cdnfly_plan_id' => (string) $packageId,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function cdnflyPackages(array $rows): void
    {
        $this->mock(CdnflyApiService::class)
            ->shouldReceive('listPackages')
            ->andReturn(['data' => $rows]);
    }

    /**
     * @return list<string>
     */
    private function catalogFeatures(): array
    {
        return $this->getJson('/api/products')->assertOk()->json('data.0.features');
    }

    /**
     * @return list<string>
     */
    private function catalogSpecs(): array
    {
        return $this->getJson('/api/products')->assertOk()->json('data.0.specs');
    }
}
