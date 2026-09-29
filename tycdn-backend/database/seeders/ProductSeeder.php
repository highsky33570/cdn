<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use App\Support\SampleSubscriptionCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the sellable catalogue.
 *
 * Nothing can be bought until `products` has rows: ProductCatalogController
 * returns an empty list, the portal shows an empty catalogue, and EpusdtCheckoutService
 * 404s on the product lookup. Provisioning additionally needs a
 * `product_cdnfly_mappings` row naming the upstream CDNfly package.
 *
 * Run with:  php artisan db:seed --class=ProductSeeder
 *
 * Prefer `php artisan packages:sync-sample-catalog` on a live master — that
 * updates CDNfly package limits and links products in one step. This seeder
 * only writes the portal half and is safe to re-run (matched on slug).
 *
 * CDNfly package ids are deployment-specific. Supply them via CDNFLY_PACKAGE_IDS
 * as a comma separated "slug:packageId" list, e.g.
 *
 *     CDNFLY_PACKAGE_IDS="advanced:1,professional:2,commercial:3,invincible:4,private-custom:5"
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $packageIds = $this->configuredPackageIds();
        $missing = [];
        $keep = SampleSubscriptionCatalog::slugs();

        foreach (SampleSubscriptionCatalog::tiers() as $plan) {
            $product = Product::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price_monthly' => $plan['price_monthly'],
                    'price_quarterly' => round($plan['price_monthly'] * 3, 2),
                    'price_yearly' => round($plan['price_monthly'] * 12, 2),
                    'currency' => 'USD',
                    'is_active' => true,
                    'sort_order' => $plan['sort_order'],
                    'badge' => $plan['badge'],
                    'features' => $plan['features'],
                ],
            );

            $packageId = $packageIds[$plan['slug']] ?? null;

            ProductCdnflyMapping::updateOrCreate(
                ['product_id' => $product->id],
                ['cdnfly_plan_id' => $packageId],
            );

            if ($packageId === null) {
                $missing[] = $plan['slug'];
            }
        }

        Product::query()
            ->whereNotIn('slug', $keep)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->command?->info('已同步 '.count($keep).' 个套餐到 products 表。');

        if ($missing !== []) {
            $this->command?->warn('以下套餐尚未绑定 CDNfly 套餐 ID，支付可以完成但开通会失败：');
            $this->command?->warn('  '.implode(', ', $missing));
            $this->command?->warn('请设置 CDNFLY_PACKAGE_IDS="slug:id,slug:id" 后重新执行，或运行 packages:sync-sample-catalog。');
        }
    }

    /**
     * @return array<string, string>
     */
    private function configuredPackageIds(): array
    {
        $raw = trim((string) config('services.cdnfly.package_ids', ''));

        if ($raw === '') {
            return [];
        }

        $ids = [];

        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', trim($pair), 2);

            if (count($parts) !== 2) {
                continue;
            }

            $slug = trim($parts[0]);
            $id = trim($parts[1]);

            if ($slug !== '' && $id !== '') {
                $ids[$slug] = $id;
            }
        }

        return $ids;
    }
}
