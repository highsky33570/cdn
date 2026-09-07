<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gateway_provider', 20)->nullable()->after('pay_currency');
            $table->string('gateway_trade_id', 64)->nullable()->unique()->after('gateway_provider');
            $table->string('gateway_token', 64)->nullable()->after('gateway_trade_id');
            $table->decimal('gateway_amount', 19, 4)->nullable()->after('gateway_token');
            $table->decimal('gateway_actual_amount', 19, 4)->nullable()->after('gateway_amount');
            $table->string('gateway_payment_url', 255)->nullable()->after('gateway_actual_amount');
            $table->string('gateway_status', 20)->nullable()->after('gateway_payment_url');
            $table->timestamp('gateway_expired_at')->nullable()->after('gateway_status');
            $table->json('gateway_notify_payload')->nullable()->after('gateway_expired_at');

            $table->index('gateway_provider');
            $table->index('gateway_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['gateway_provider']);
            $table->dropIndex(['gateway_status']);
            $table->dropColumn([
                'gateway_provider',
                'gateway_trade_id',
                'gateway_token',
                'gateway_amount',
                'gateway_actual_amount',
                'gateway_payment_url',
                'gateway_status',
                'gateway_expired_at',
                'gateway_notify_payload',
            ]);
        });
    }
};
