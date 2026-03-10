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
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id')->nullable()->after('google_id');
            $table->string('social_link_token')->nullable()->after('remember_token');
            $table->string('social_link_provider')->nullable()->after('social_link_token');
            $table->string('social_link_id')->nullable()->after('social_link_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'github_id',
                'social_link_token',
                'social_link_provider',
                'social_link_id'
            ]);
        });
    }
};
