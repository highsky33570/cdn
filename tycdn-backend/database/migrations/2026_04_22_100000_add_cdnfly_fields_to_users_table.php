<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('name'); // admin / user
            $table->unsignedBigInteger('cdnfly_user_id')->nullable()->after('password');
            $table->string('cdnfly_api_key', 64)->nullable()->after('cdnfly_user_id');
            $table->text('cdnfly_api_secret')->nullable()->after('cdnfly_api_key');
            $table->timestamp('cdnfly_synced_at')->nullable()->after('cdnfly_api_secret');

            $table->index('cdnfly_user_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['cdnfly_user_id']);
            $table->dropIndex(['role']);
            $table->dropColumn([
                'role',
                'cdnfly_user_id',
                'cdnfly_api_key',
                'cdnfly_api_secret',
                'cdnfly_synced_at',
            ]);
        });
    }
};
