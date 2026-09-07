<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_cdnfly_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->unique();

            $table->string('cdnfly_plan_id', 64)->nullable();
            $table->string('cdnfly_group_id', 64)->nullable();
            $table->json('provision_payload')->nullable();

            $table->timestamps();

            $table->index('cdnfly_plan_id');
            $table->index('cdnfly_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cdnfly_mappings');
    }
};
