<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_profiles', function (Blueprint $table) {
            $table->string('location')->nullable()->after('bio');
            $table->string('country')->nullable()->after('location');
            $table->unsignedInteger('hourly_rate')->nullable()->after('country');
            $table->string('availability')->nullable()->after('hourly_rate');
            $table->unsignedTinyInteger('experience_years')->nullable()->after('availability');
            $table->json('languages')->nullable()->after('experience_years');
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('location')->nullable()->after('about');
            $table->string('country')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('developer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'location',
                'country',
                'hourly_rate',
                'availability',
                'experience_years',
                'languages',
            ]);
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['location', 'country']);
        });
    }
};
