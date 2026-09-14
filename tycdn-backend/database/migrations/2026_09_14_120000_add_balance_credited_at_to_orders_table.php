<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A package purchase paid by USDT now tops up the CDNfly balance and then buys
 * from it, because CDNfly is a prepaid system that deducts balance at the real
 * package price. The two steps must each happen exactly once across retries, so
 * this records that the top-up half is done — a purchase that fails after a
 * successful credit is retried without crediting again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('balance_credited_at')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('balance_credited_at');
        });
    }
};
