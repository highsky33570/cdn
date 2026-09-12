<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PackageSpecResolver;
use Illuminate\Http\JsonResponse;

class ProductCatalogController extends Controller
{
    public function __construct(
        private readonly PackageSpecResolver $specs,
    ) {}

    public function index(): JsonResponse
    {
        // Numeric selling points come from the CDNfly package that enforces
        // them, so raising a limit cannot leave the homepage advertising the
        // old one.
        //
        // `features` is marketing copy and is passed through untouched. An
        // earlier version tried to separate the two by looking for 流量/带宽/…
        // inside stored text, which silently swallowed legitimate lines such as
        // 「不限流量不加价」. The split is now explicit: specs are derived,
        // features are authored.
        $specsByProduct = $this->specs->specsByProductId();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Product $product) use ($specsByProduct): array {
                $spec = $specsByProduct[$product->id] ?? null;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'price_monthly' => $product->price_monthly,
                    'price_quarterly' => $product->price_quarterly,
                    'price_yearly' => $product->price_yearly,
                    'currency' => $product->currency,
                    // Authored claims, never inferred or filtered.
                    'features' => is_array($product->features) ? $product->features : [],
                    // Enforced limits, rendered as prose.
                    'specs' => $spec['lines'] ?? [],
                    // The same, structured, for callers that lay them out as a
                    // table rather than a bullet list.
                    'limits' => $spec['limits'] ?? null,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $products,
        ]);
    }
}
