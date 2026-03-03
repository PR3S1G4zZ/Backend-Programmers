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
            // Soft delete column
            $table->softDeletes();

            // Banned at column for user suspension
            $table->timestamp('banned_at')->nullable()->after('deleted_at');
        });

        // Add index to banned_at column
        Schema::table('users', function (Blueprint $table) {
            $table->index('banned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['banned_at']);
            $table->dropColumn(['deleted_at', 'banned_at']);
        });
    }
};
