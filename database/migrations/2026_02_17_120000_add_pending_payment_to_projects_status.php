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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }
};
