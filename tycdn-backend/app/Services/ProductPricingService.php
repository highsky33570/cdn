<?php

namespace App\Services;

use App\Models\Product;

class ProductPricingService
{
    /**
     * @return array{unit_price: float, total_amount: float, currency: string}
     */
    public function quote(Product $product, string $billingCycle, int $quantity): array
    {
        $unitPrice = (float) match ($billingCycle) {
            'quarterly' => $product->price_quarterly,
            'yearly' => $product->price_yearly,
            default => $product->price_monthly,
        };

        return [
            'unit_price' => $unitPrice,
            'total_amount' => round($unitPrice * $quantity, 2),
            'currency' => strtoupper($product->currency ?: 'USD'),
        ];
    }
}
