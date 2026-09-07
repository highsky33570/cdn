<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price_monthly' => $product->price_monthly,
                'price_quarterly' => $product->price_quarterly,
                'price_yearly' => $product->price_yearly,
                'currency' => $product->currency,
                'features' => is_array($product->features) ? $product->features : [],
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $products,
        ]);
    }
}
