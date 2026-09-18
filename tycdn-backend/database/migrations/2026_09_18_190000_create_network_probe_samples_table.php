<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_probe_samples', function (Blueprint $table) {
            $table->id();
            $table->string('target_key', 80);
            $table->string('measurement_id', 100)->unique();
            $table->unsignedSmallInteger('checks');
            $table->unsignedSmallInteger('successful_checks');
            $table->decimal('latency_total_ms', 16, 4)->default(0);
            $table->unsignedSmallInteger('latency_samples')->default(0);
            $table->json('probe_cities');
            $table->timestamp('observed_at');
            $table->index(['target_key', 'observed_at']);
            $table->index('observed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_probe_samples');
    }
};
