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
        // Add indexes for metrics queries
        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_type');
            $table->index(['user_type', 'created_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('status');
            $table->index(['status', 'created_at']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('status');
            $table->index(['status', 'created_at']);
            $table->index('developer_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('sender_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('rating');
            $table->index(['rating', 'created_at']);
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_type']);
            $table->dropIndex(['user_type', 'created_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['developer_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['sender_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['rating']);
            $table->dropIndex(['rating', 'created_at']);
            $table->dropIndex(['project_id']);
        });
    }
};
