<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_no',
        'user_id',
        'product_id',
        'order_type',
        'billing_cycle',
        'quantity',
        'target_service_instance_id',

        'fiat_amount',
        'fiat_currency',
        'fx_rate_snapshot',

        'amount_usdt',
        'pay_currency',
        'actual_paid_amount',
        'pay_address',
        'status',
        'expire_at',
        'paid_at',
        'provisioned_at',
        'cancelled_at',

        'gateway_provider',
        'gateway_trade_id',
        'gateway_request_id',
        'gateway_token',
        'gateway_amount',
        'gateway_currency',
        'gateway_actual_amount',
        'gateway_payment_url',
        'gateway_status',
        'gateway_expired_at',
        'gateway_notify_payload',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
        'paid_at' => 'datetime',
        'provisioned_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'gateway_expired_at' => 'datetime',
        'gateway_notify_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function targetServiceInstance(): BelongsTo
    {
        return $this->belongsTo(ServiceInstance::class, 'target_service_instance_id');
    }

    public function serviceInstance(): HasOne
    {
        return $this->hasOne(ServiceInstance::class, 'source_order_id');
    }
}
