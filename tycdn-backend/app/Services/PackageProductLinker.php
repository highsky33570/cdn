<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCdnflyMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Links a CDNfly package to the portal product customers actually buy.
 *
 * Before this, the two halves were joined by hand: create the package in
 * CDNfly, read its id out of the panel, write CDNFLY_PACKAGE_IDS into .env and
 * re-run ProductSeeder. Miss a step and checkout fails at provisioning time
 * with `missing_product_mapping`, long after the money has been taken.
 *
 * The prices here are the ones that matter. CDNfly's own month/quarter/year
 * prices bill against the customer's CDNfly balance, which a portal-driven
 * order never credits — so those stay at 0 and the portal collects the money.
 */
class PackageProductLinker
{
    /**
     * @param  array<string, mixed>  $portal
     */
    public function link(int $cdnflyPackageId, array $portal): Product
    {
        return DB::transaction(function () use ($cdnflyPackageId, $portal): Product {
            $existing = ProductCdnflyMapping::query()
                ->where('cdnfly_plan_id', (string) $cdnflyPackageId)
                ->first();

            $product = $existing
                ? Product::query()->find($existing->product_id)
                : null;

            $monthly = $this->money($portal['price_monthly'] ?? 0);

            $attributes = [
                'name' => trim((string) ($portal['name'] ?? '')),
                'description' => $this->nullableText($portal['description'] ?? null),
                'price_monthly' => $monthly,
                // A blank quarterly or yearly price is the common case: the
                // admin sets one number and expects the rest to follow. No
                // discount is assumed — undercharging silently is worse than
                // an obvious round multiple.
                'price_quarterly' => $this->money($portal['price_quarterly'] ?? null) ?: $monthly * 3,
                'price_yearly' => $this->money($portal['price_yearly'] ?? null) ?: $monthly * 12,
                'currency' => strtoupper((string) ($portal['currency'] ?? 'USD')),
                'is_active' => (bool) ($portal['is_active'] ?? true),
                'sort_order' => (int) ($portal['sort_order'] ?? 0),
                'features' => $this->features($portal['features'] ?? null),
            ];

            if ($product) {
                $product->fill($attributes)->save();
            } else {
                $product = Product::query()->create($attributes + [
                    'slug' => $this->slug($portal['slug'] ?? null, $cdnflyPackageId),
                ]);
            }

            ProductCdnflyMapping::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'cdnfly_plan_id' => (string) $cdnflyPackageId,
                    'cdnfly_group_id' => $this->nullableText($portal['cdnfly_group_id'] ?? null),
                ],
            );

            return $product->refresh();
        });
    }

    /**
     * A package name is routinely Chinese, which slugifies to nothing useful,
     * so fall back to the CDNfly id — always present and always unique.
     */
    private function slug(mixed $given, int $cdnflyPackageId): string
    {
        $slug = Str::slug((string) ($given ?? ''));

        if ($slug === '') {
            $slug = 'package-'.$cdnflyPackageId;
        }

        $base = $slug;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    /**
     * @return list<string>|null
     */
    private function features(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $features = array_values(array_filter(
            array_map(static fn ($item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        ));

        return $features === [] ? null : $features;
    }
}
