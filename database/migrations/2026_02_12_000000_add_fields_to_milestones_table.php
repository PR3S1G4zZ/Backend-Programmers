<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->enum('progress_status', ['todo', 'in_progress', 'review', 'completed'])->default('todo')->after('status');
            $table->date('due_date')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn(['description', 'progress_status', 'due_date']);
        });
    }
};
