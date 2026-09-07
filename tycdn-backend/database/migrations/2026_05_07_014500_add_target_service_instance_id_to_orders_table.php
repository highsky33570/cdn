<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('target_service_instance_id')
                ->nullable()
                ->after('quantity');

            $table->index('target_service_instance_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('target_service_instance_id')
                ->references('id')
                ->on('service_instances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['target_service_instance_id']);
            $table->dropIndex(['target_service_instance_id']);
            $table->dropColumn('target_service_instance_id');
        });
    }
};
