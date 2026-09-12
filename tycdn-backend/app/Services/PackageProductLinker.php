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
            $product = $this->resolveProduct($cdnflyPackageId, $portal['slug'] ?? null);

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

            $this->releaseOtherProducts($cdnflyPackageId, $product->id);

            return $product->refresh();
        });
    }

    /**
     * The product this package should be sold as.
     *
     * Order matters. An explicitly supplied slug wins: naming an existing
     * product is a deliberate instruction to sell this package as that tier,
     * and it is the only way to correct a package linked to the wrong one. The
     * form pre-fills the current slug, so an ordinary price edit re-sends what
     * is already linked and nothing moves.
     *
     * Falling back to the existing link covers a save with no slug at all.
     * Creating is the last resort — doing it first left the seeded catalogue
     * (jpn-mini, …) unmapped beside a duplicate set, and the storefront listed
     * every tier twice with the live prices on the copy nothing rendered.
     */
    private function resolveProduct(int $cdnflyPackageId, mixed $slug): ?Product
    {
        $wanted = Str::slug((string) ($slug ?? ''));

        if ($wanted !== '') {
            $named = Product::query()->where('slug', $wanted)->first();

            if ($named) {
                return $named;
            }
        }

        $mapped = ProductCdnflyMapping::query()
            ->where('cdnfly_plan_id', (string) $cdnflyPackageId)
            ->first();

        return $mapped ? Product::query()->find($mapped->product_id) : null;
    }

    /**
     * Detach any other product still claiming this package.
     *
     * Re-pointing a package at a different product leaves the previous one
     * orphaned but still on sale — a tier customers could buy that nothing
     * provisions. A product this class generated and that nobody has ordered is
     * removed; anything else is only deactivated, because deleting a product
     * with order history would take the order rows with it.
     */
    private function releaseOtherProducts(int $cdnflyPackageId, int $keepProductId): void
    {
        $stale = ProductCdnflyMapping::query()
            ->where('cdnfly_plan_id', (string) $cdnflyPackageId)
            ->where('product_id', '!=', $keepProductId)
            ->get();

        foreach ($stale as $mapping) {
            $product = Product::query()->find($mapping->product_id);
            $mapping->delete();

            if (! $product) {
                continue;
            }

            $generated = $product->slug === 'package-'.$cdnflyPackageId;

            if ($generated && $product->orders()->doesntExist()) {
                $product->delete();

                continue;
            }

            $product->forceFill(['is_active' => false])->save();
        }
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
