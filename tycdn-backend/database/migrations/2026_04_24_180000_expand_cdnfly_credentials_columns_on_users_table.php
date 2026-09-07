<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('cdnfly_api_key')->nullable()->change();
            $table->text('cdnfly_api_secret')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('cdnfly_api_key', 64)->nullable()->change();
            $table->text('cdnfly_api_secret')->nullable()->change();
        });
    }
};
