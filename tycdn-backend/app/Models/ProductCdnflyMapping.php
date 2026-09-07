<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCdnflyMapping extends Model
{
    protected $fillable = [
        'product_id',
        'cdnfly_plan_id',
        'cdnfly_group_id',
        'provision_payload',
    ];

    protected $casts = [
        'provision_payload' => 'array',
    ];
}
// 套餐与cdnfly中套餐参数的映射
