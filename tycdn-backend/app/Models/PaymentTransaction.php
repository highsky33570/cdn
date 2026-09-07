<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'chain',
        'token',
        'txid',
        'from_address',
        'to_address',
        'amount',
        'block_timestamp',
        'confirmations',
        'confirmed_at',
        'processed_at',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'confirmed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
// 记录链上实际到账流水
