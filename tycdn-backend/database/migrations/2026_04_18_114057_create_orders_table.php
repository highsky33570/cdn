<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('amount_usdt', 18, 6);
            $table->string('pay_address', 64);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expire_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expire_at']);
            $table->index('pay_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
