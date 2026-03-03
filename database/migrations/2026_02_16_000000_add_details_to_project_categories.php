<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('color', 7)->nullable()->after('description');
            $table->string('icon', 50)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('project_categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'color', 'icon']);
        });
    }
};
