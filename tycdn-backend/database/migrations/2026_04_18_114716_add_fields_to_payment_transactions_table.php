<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('chain', 20)->default('TRON')->after('order_id');
            $table->string('token', 20)->default('USDT')->after('chain');
            $table->timestamp('confirmed_at')->nullable()->after('confirmations');
            $table->timestamp('processed_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'chain',
                'token',
                'confirmed_at',
                'processed_at',
            ]);
        });
    }
};
