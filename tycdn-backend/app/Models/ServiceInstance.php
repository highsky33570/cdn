<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceInstance extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'source_order_id',
        'status',
        'cdnfly_user_id',
        'cdnfly_service_id',
        'service_name',
        'opened_at',
        'expired_at',
        'config_snapshot',
        'extra',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'expired_at' => 'datetime',
        'config_snapshot' => 'array',
        'extra' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }
}
// 记录真实开通的cdn套餐
