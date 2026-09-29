<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CdnflyApiService;
use App\Services\PackageProductLinker;
use App\Services\PackageSpecResolver;
use App\Support\SampleSubscriptionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Align CDNfly packages + portal products with the five sample pricing tiers.
 *
 * Updates the first N existing packages in place (by id ascending), creates
 * any missing ones, then links portal products and retires the old jpn-* rows.
 */
class SyncSampleSubscriptionCatalog extends Command
{
    protected $signature = 'packages:sync-sample-catalog
                            {--dry-run : Print actions without calling CDNfly or writing products}';

    protected $description = 'Sync the five sample subscription tiers to CDNfly packages and portal products';

    public function handle(
        CdnflyApiService $cdnfly,
        PackageProductLinker $linker,
        PackageSpecResolver $specs,
    ): int {
        $dry = (bool) $this->option('dry-run');
        $tiers = SampleSubscriptionCatalog::tiers();

        try {
            $listed = $cdnfly->listPackages(['limit' => 0]);
        } catch (\Throwable $e) {
            $this->error('无法读取 CDNfly 套餐列表：'.$e->getMessage());

            return self::FAILURE;
        }

        $existing = collect(data_get($listed, 'data', []))
            ->filter(fn ($row): bool => is_array($row) && isset($row['id']))
            ->sortBy(fn (array $row): int => (int) $row['id'])
            ->values();

        $defaults = $this->defaultsFrom($existing->first());

        if ($defaults === null) {
            $this->error('CDNfly 中没有任何基础套餐，无法推断 region/node_group/cname 默认值。请先在面板创建一个套餐。');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Using defaults: region_id=%s node_group_id=%s groups=%s cname_domain=%s',
            $defaults['region_id'],
            $defaults['node_group_id'],
            $defaults['groups'],
            $defaults['cname_domain'],
        ));

        $mappedIds = [];

        foreach ($tiers as $index => $tier) {
            $row = $existing->get($index);
            $packageId = $row ? (int) $row['id'] : null;

            // On update, only send sellable limits — re-sending region /
            // node_group / backup fields trips CDNfly when backup equals main.
            $packageFields = $packageId
                ? array_merge($tier['package'], [
                    'month_price' => 0,
                    'quarter_price' => 0,
                    'year_price' => 0,
                    'enable' => 1,
                ])
                : array_merge($defaults, $tier['package'], [
                    'month_price' => 0,
                    'quarter_price' => 0,
                    'year_price' => 0,
                    'enable' => 1,
                ]);

            if ($packageId) {
                $this->line(sprintf(
                    '[%s] update CDNfly package #%d → %s',
                    $tier['slug'],
                    $packageId,
                    $tier['name'],
                ));

                if (! $dry) {
                    try {
                        $cdnfly->updatePackage($packageId, $packageFields);
                    } catch (\Throwable $e) {
                        $this->error('  failed: '.$e->getMessage());
                        Log::error('sample catalog package update failed', [
                            'package_id' => $packageId,
                            'slug' => $tier['slug'],
                            'error' => $e->getMessage(),
                        ]);

                        return self::FAILURE;
                    }
                }
            } else {
                $this->line(sprintf('[%s] create CDNfly package → %s', $tier['slug'], $tier['name']));

                if ($dry) {
                    $packageId = 0;
                } else {
                    try {
                        $created = $cdnfly->createPackage($packageFields);
                        $packageId = $this->extractId($created);

                        if (! $packageId) {
                            $this->error('  created but could not read package id: '.json_encode($created, JSON_UNESCAPED_UNICODE));

                            return self::FAILURE;
                        }

                        $this->info("  created id={$packageId}");
                    } catch (\Throwable $e) {
                        $this->error('  failed: '.$e->getMessage());

                        return self::FAILURE;
                    }
                }
            }

            $mappedIds[$tier['slug']] = $packageId;

            if ($dry || ! $packageId) {
                continue;
            }

            $product = $linker->link($packageId, [
                'name' => $tier['name'],
                'slug' => $tier['slug'],
                'description' => $tier['description'],
                'price_monthly' => $tier['price_monthly'],
                'price_quarterly' => round($tier['price_monthly'] * 3, 2),
                'price_yearly' => round($tier['price_monthly'] * 12, 2),
                'currency' => 'USD',
                'is_active' => true,
                'sort_order' => $tier['sort_order'],
                'badge' => $tier['badge'],
                'features' => $tier['features'],
            ]);

            $this->info(sprintf(
                '  linked product #%d (%s) @ %s/mo',
                $product->id,
                $product->slug,
                $product->price_monthly,
            ));
        }

        if (! $dry) {
            $this->retireLegacyProducts();
            $specs->forget();
        }

        $this->newLine();
        $this->info('CDNFLY_PACKAGE_IDS suggestion:');
        $pairs = [];

        foreach ($mappedIds as $slug => $id) {
            if ($id > 0) {
                $pairs[] = "{$slug}:{$id}";
            }
        }

        $this->line(implode(',', $pairs));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>|null  $sample
     * @return array{region_id: mixed, node_group_id: mixed, groups: mixed, cname_domain: mixed, cname_mode?: mixed}|null
     */
    private function defaultsFrom(?array $sample): ?array
    {
        if ($sample === null) {
            return null;
        }

        foreach (['region_id', 'node_group_id', 'groups', 'cname_domain'] as $key) {
            if (! array_key_exists($key, $sample) || $sample[$key] === '' || $sample[$key] === null) {
                return null;
            }
        }

        $defaults = [
            'region_id' => $sample['region_id'],
            'node_group_id' => $sample['node_group_id'],
            'groups' => $sample['groups'],
            'cname_domain' => $sample['cname_domain'],
        ];

        if (isset($sample['cname_mode']) && $sample['cname_mode'] !== '') {
            $defaults['cname_mode'] = $sample['cname_mode'];
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractId(array $payload): ?int
    {
        $id = data_get($payload, 'data.id')
            ?? data_get($payload, 'data.0.id')
            ?? data_get($payload, 'id')
            ?? (is_numeric(data_get($payload, 'data')) ? data_get($payload, 'data') : null);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function retireLegacyProducts(): void
    {
        $keep = SampleSubscriptionCatalog::slugs();

        $retired = Product::query()
            ->whereNotIn('slug', $keep)
            ->where('is_active', true)
            ->get();

        foreach ($retired as $product) {
            $product->forceFill(['is_active' => false])->save();
            $this->warn("  deactivated legacy product {$product->slug}");
        }
    }
}
