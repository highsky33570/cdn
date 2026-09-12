<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating a CDNfly package should also create the thing customers can buy.
 *
 * Previously the two halves were joined by hand — read the new package id out
 * of the panel, write CDNFLY_PACKAGE_IDS into .env, re-run ProductSeeder. Skip
 * a step and the failure surfaces as `missing_product_mapping` at provisioning
 * time, after the customer has already paid.
 *
 * The portal price is the real one. CDNfly's own prices bill against the
 * customer's CDNfly balance, which a portal order never credits.
 */
class PackageProductLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_package_creates_the_portal_product_and_mapping(): void
    {
        $this->mockCreate(['data' => ['id' => 41]]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $this->packagePayload([
                'name' => '日本入门',
                'price_monthly' => 5,
                'features' => ['50 GiB 流量', '1 个网站'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.product.name', '日本入门');

        $product = Product::query()->firstOrFail();

        $this->assertEqualsWithDelta(5.0, (float) $product->price_monthly, 0.001);
        $this->assertSame(['50 GiB 流量', '1 个网站'], $product->features);
        $this->assertDatabaseHas('product_cdnfly_mappings', [
            'product_id' => $product->id,
            'cdnfly_plan_id' => '41',
        ]);
    }

    /**
     * An admin sets one number and expects the rest to follow. No discount is
     * assumed — silently undercharging is worse than an obvious multiple.
     */
    public function test_blank_quarterly_and_yearly_prices_derive_from_the_monthly_one(): void
    {
        $this->mockCreate(['data' => ['id' => 42]]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $this->packagePayload([
                'name' => '日本标准',
                'price_monthly' => 10,
                'price_quarterly' => null,
                'price_yearly' => null,
            ]))
            ->assertCreated();

        $product = Product::query()->firstOrFail();

        $this->assertEqualsWithDelta(30.0, (float) $product->price_quarterly, 0.001);
        $this->assertEqualsWithDelta(120.0, (float) $product->price_yearly, 0.001);
    }

    /** A Chinese name slugifies to nothing, so fall back to the CDNfly id. */
    public function test_a_missing_slug_falls_back_to_the_package_id(): void
    {
        $this->mockCreate(['data' => ['id' => 43]]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $this->packagePayload([
                'name' => '日本进阶',
                'price_monthly' => 20,
            ]))
            ->assertCreated();

        $this->assertSame('package-43', Product::query()->firstOrFail()->slug);
    }

    public function test_a_duplicate_slug_is_given_a_suffix(): void
    {
        Product::query()->create([
            'name' => 'Existing', 'slug' => 'jp-mini', 'price_monthly' => 1,
            'price_quarterly' => 3, 'price_yearly' => 12, 'currency' => 'USD',
        ]);

        $this->mockCreate(['data' => ['id' => 44]]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $this->packagePayload([
                'name' => 'JP Mini',
                'slug' => 'jp-mini',
                'price_monthly' => 5,
            ]))
            ->assertCreated();

        $this->assertNotNull(Product::query()->where('slug', 'jp-mini-2')->first());
    }

    /**
     * A package with no CNAME domain is rejected by CDNfly as
     * 「无法找到此cname域名」 — a lookup failure, with no hint that a field is
     * simply absent. Catch it here, where the message can say so.
     */
    public function test_a_package_without_a_cname_domain_is_rejected_before_cdnfly(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('createPackage');

        $payload = $this->packagePayload(['name' => 'x', 'price_monthly' => 5]);
        unset($payload['cname_domain']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('cname_domain');
    }

    /** The portal half must never be forwarded to CDNfly as a package field. */
    public function test_the_portal_block_is_not_sent_to_cdnfly(): void
    {
        $received = null;

        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('createPackage')
            ->once()
            ->andReturnUsing(function (array $payload) use (&$received) {
                $received = $payload;

                return ['data' => ['id' => 45]];
            });

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $this->packagePayload([
                'name' => '日本高阶',
                'price_monthly' => 30,
            ]))
            ->assertCreated();

        $this->assertArrayNotHasKey('portal', $received);
    }

    /** Selling through the portal stays optional. */
    public function test_a_package_without_a_portal_block_creates_no_product(): void
    {
        $this->mockCreate(['data' => ['id' => 46]]);

        $payload = $this->packagePayload([]);
        unset($payload['portal']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $payload)
            ->assertCreated();

        $this->assertSame(0, Product::query()->count());
    }

    /**
     * The package exists in CDNfly whatever happens next, so say so — a flat
     * failure invites a retry that creates a second package.
     */
    public function test_an_unreadable_package_id_warns_instead_of_failing(): void
    {
        $this->mockCreate(['msg' => 'ok']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/packages', $this->packagePayload([
                'name' => '日本入门',
                'price_monthly' => 5,
            ]))
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['portal_warning']]);

        $this->assertSame(0, Product::query()->count());
    }

    public function test_the_price_can_be_edited_without_touching_cdnfly(): void
    {
        $this->mock(CdnflyApiService::class)->shouldNotReceive('updatePackage');

        $this->actingAs($this->admin())
            ->putJson('/api/admin/package-products/41', [
                'portal' => ['name' => '日本入门', 'price_monthly' => 7.5],
            ])
            ->assertOk();

        $this->assertDatabaseHas('product_cdnfly_mappings', ['cdnfly_plan_id' => '41']);
    }

    public function test_editing_the_same_package_updates_rather_than_duplicates(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/package-products/41', [
                'portal' => ['name' => '日本入门', 'price_monthly' => 5],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson('/api/admin/package-products/41', [
                'portal' => ['name' => '日本入门 v2', 'price_monthly' => 9],
            ])
            ->assertOk();

        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, ProductCdnflyMapping::query()->count());
        $this->assertSame('日本入门 v2', Product::query()->firstOrFail()->name);
    }

    public function test_the_products_behind_packages_are_listed_by_package_id(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/api/admin/package-products/41', [
                'portal' => ['name' => '日本入门', 'price_monthly' => 5],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/admin/package-products')
            ->assertOk()
            ->assertJsonPath('data.41.name', '日本入门')
            ->assertJsonPath('data.41.price_monthly', 5);
    }

    public function test_a_non_admin_cannot_change_prices(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->putJson('/api/admin/package-products/41', [
                'portal' => ['name' => 'x', 'price_monthly' => 1],
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mockCreate(array $data): void
    {
        $this->mock(CdnflyApiService::class)
            ->shouldReceive('createPackage')
            ->once()
            ->andReturn($data);
    }

    /**
     * @param  array<string, mixed>  $portal
     * @return array<string, mixed>
     */
    private function packagePayload(array $portal): array
    {
        return [
            'name' => 'cdnfly-package',
            'region_id' => 1,
            'node_group_id' => 2,
            // Zero on purpose: CDNfly bills against the customer's CDNfly
            // balance, and a portal order never credits it.
            'month_price' => '0',
            'quarter_price' => '0',
            'year_price' => '0',
            'groups' => '1',
            'cname_domain' => 1,
            'portal' => $portal,
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
