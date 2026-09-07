<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('source_order_id')->nullable();

            $table->string('status', 20)->default('pending');
            // pending / provisioning / active / suspended / expired / failed

            $table->string('cdnfly_user_id', 64)->nullable();
            $table->string('cdnfly_service_id', 64)->nullable();

            $table->string('service_name', 100)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->json('config_snapshot')->nullable();
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('product_id');
            $table->index('source_order_id');
            $table->index('cdnfly_user_id');
            $table->index('cdnfly_service_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_instances');
    }
};
