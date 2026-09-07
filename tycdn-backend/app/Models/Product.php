<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_quarterly',
        'price_yearly',
        'currency',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function cdnflyMapping(): HasOne
    {
        return $this->hasOne(ProductCdnflyMapping::class, 'product_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
