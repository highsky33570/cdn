<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('txid', 128)->unique();
            $table->string('from_address', 64);
            $table->string('to_address', 64);
            $table->decimal('amount', 18, 6);
            $table->unsignedBigInteger('block_timestamp');
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('status', 20)->default('confirmed');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
