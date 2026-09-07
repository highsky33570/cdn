<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 20)->default('new')->after('product_id');
            $table->string('billing_cycle', 20)->nullable()->after('order_type');
            $table->unsignedInteger('quantity')->default(1)->after('billing_cycle');
            $table->string('pay_currency', 20)->default('USDT_TRC20')->after('amount_usdt');
            $table->decimal('actual_paid_amount', 18, 6)->nullable()->after('pay_currency');
            $table->timestamp('provisioned_at')->nullable()->after('paid_at');
            $table->timestamp('cancelled_at')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_type',
                'billing_cycle',
                'quantity',
                'pay_currency',
                'actual_paid_amount',
                'provisioned_at',
                'cancelled_at',
            ]);
        });
    }
};
