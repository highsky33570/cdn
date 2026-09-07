<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use Illuminate\Database\Seeder;

/**
 * Seeds the sellable catalogue.
 *
 * Nothing can be bought until `products` has rows: ProductCatalogController
 * returns an empty list, the portal shows "Coming Soon", and EpusdtCheckoutService
 * 404s on the product lookup. Provisioning additionally needs a
 * `product_cdnfly_mappings` row naming the upstream CDNfly package.
 *
 * Run with:  php artisan db:seed --class=ProductSeeder
 *
 * Safe to re-run: rows are matched on slug and updated in place, so prices can be
 * changed here and re-seeded without creating duplicates or breaking existing
 * orders (which reference product_id).
 *
 * CDNfly package ids are deployment-specific. Supply them via CDNFLY_PACKAGE_IDS
 * as a comma separated "slug:packageId" list, e.g.
 *
 *     CDNFLY_PACKAGE_IDS="jpn-mini:101,jpn-standard:102"
 *
 * Any product without an id still gets a mapping row, so provisioning fails with
 * the actionable `missing_cdnfly_package_id` rather than `missing_product_mapping`.
 */
class ProductSeeder extends Seeder
{
    /**
     * Mirrors frontend/src/data/plans.js. Quarterly/yearly are plain multiples of
     * the monthly price -- no discount is assumed. Review before going live; a
     * zero price would make checkout reject the order outright.
     *
     * @var list<array<string, mixed>>
     */
    private const PLANS = [
        [
            'slug' => 'jpn-mini',
            'name' => 'JPN-Mini',
            'price_monthly' => 5.00,
            'sort_order' => 10,
            'description' => '日本东京节点入门套餐，适合个人站点与小流量业务。',
            'features' => ['50 GiB 流量', '1 个网站', '100 Mbps 带宽', '50MiB 上传', 'WebSocket'],
        ],
        [
            'slug' => 'jpn-standard',
            'name' => 'JPN-Standard',
            'price_monthly' => 10.00,
            'sort_order' => 20,
            'description' => '日本东京节点标准套餐，适合中小企业站点与多域名业务。',
            'features' => ['100 GiB 流量', '5 个网站', '300 Mbps 带宽', '100MiB 上传', 'WebSocket'],
        ],
        [
            'slug' => 'jpn-plus',
            'name' => 'JPN-Plus',
            'price_monthly' => 20.00,
            'sort_order' => 30,
            'description' => '日本东京节点进阶套餐，适合流量增长期的业务。',
            'features' => ['200 GiB 流量', '10 个网站', '1 Gbps 带宽', '200MiB 上传', 'WebSocket'],
        ],
        [
            'slug' => 'jpn-pro',
            'name' => 'JPN-Pro',
            'price_monthly' => 30.00,
            'sort_order' => 40,
            'description' => '日本东京节点高阶套餐，适合高并发与多站点业务。',
            'features' => ['300 GiB 流量', '20 个网站', '1 Gbps 带宽', '300MiB 上传', 'WebSocket'],
        ],
    ];

    public function run(): void
    {
        $packageIds = $this->configuredPackageIds();
        $missing = [];

        foreach (self::PLANS as $plan) {
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

        $this->command?->info('已同步 '.count(self::PLANS).' 个套餐到 products 表。');

        if ($missing !== []) {
            $this->command?->warn('以下套餐尚未绑定 CDNfly 套餐 ID，支付可以完成但开通会失败：');
            $this->command?->warn('  '.implode(', ', $missing));
            $this->command?->warn('请设置 CDNFLY_PACKAGE_IDS="slug:id,slug:id" 后重新执行，或直接更新 product_cdnfly_mappings.cdnfly_plan_id。');
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
