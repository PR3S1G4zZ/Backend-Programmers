<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('budget_type')->nullable()->after('budget_max');
            $table->unsignedInteger('duration_value')->nullable()->after('budget_type');
            $table->string('duration_unit')->nullable()->after('duration_value');
            $table->string('location')->nullable()->after('duration_unit');
            $table->boolean('remote')->default(true)->after('location');
            $table->string('level')->nullable()->after('remote');
            $table->string('priority')->nullable()->after('level');
            $table->boolean('featured')->default(false)->after('priority');
            $table->date('deadline')->nullable()->after('featured');
            $table->unsignedInteger('max_applicants')->nullable()->after('deadline');
            $table->json('tags')->nullable()->after('max_applicants');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'budget_type',
                'duration_value',
                'duration_unit',
                'location',
                'remote',
                'level',
                'priority',
                'featured',
                'deadline',
                'max_applicants',
                'tags',
            ]);
        });
    }
};
