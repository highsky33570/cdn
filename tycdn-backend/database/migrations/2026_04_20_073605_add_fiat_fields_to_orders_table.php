<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('fiat_amount', 18, 2)->nullable()->after('quantity');
            $table->string('fiat_currency', 10)->default('USD')->after('fiat_amount');
            $table->decimal('fx_rate_snapshot', 18, 6)->nullable()->after('fiat_currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fiat_amount',
                'fiat_currency',
                'fx_rate_snapshot',
            ]);
        });
    }
};
