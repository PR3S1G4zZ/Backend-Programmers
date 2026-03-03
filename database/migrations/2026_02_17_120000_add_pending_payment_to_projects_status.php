<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to modify the enum column as it's the most reliable way without extra dependencies
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('draft', 'open', 'in_progress', 'completed', 'cancelled', 'pending_payment') DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('draft', 'open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open'");
    }
};
