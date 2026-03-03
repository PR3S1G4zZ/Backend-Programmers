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
        // Using raw statement because changing enum via Schema builder can be tricky across drivers
        // and often requires doctrine/dbal which might not be installed or configured.
        // Assuming MySQL/MariaDB
        Schema::table('applications', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous state (default 'sent', no 'pending')
        // Warning: This will fail if there are 'pending' records. 
        // ideally we should update them first, but for now we just revert definition.
        Schema::table('applications', function (Blueprint $table) {
            $table->string('status')->default('sent')->change();
        });
    }
};
