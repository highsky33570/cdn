<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gateway_currency', 10)->nullable()->after('gateway_amount');
            $table->string('gateway_request_id', 64)->nullable()->after('gateway_trade_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_currency',
                'gateway_request_id',
            ]);
        });
    }
};
