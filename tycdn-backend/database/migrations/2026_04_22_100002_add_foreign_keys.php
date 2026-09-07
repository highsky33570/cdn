<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders 表：user_id → users.id
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete(); // 删除用户时，订单的 user_id 设为 null（保留订单记录）
        });

        // orders 表：product_id → products.id
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });

        // service_instances 表：user_id → users.id
        Schema::table('service_instances', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // service_instances 表：source_order_id → orders.id
        Schema::table('service_instances', function (Blueprint $table) {
            $table->foreign('source_order_id')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
        });

        // payment_transactions 表：order_id → orders.id
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete(); // 有支付流水的订单不允许删除
        });

        // product_cdnfly_mappings 表：product_id → products.id
        Schema::table('product_cdnfly_mappings', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete(); // 有映射的产品不允许直接删除，应该用 is_active=false 下架
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('service_instances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['source_order_id']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('product_cdnfly_mappings', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
    }
};
