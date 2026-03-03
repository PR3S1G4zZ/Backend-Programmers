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
        Schema::table('reviews', function (Blueprint $table) {
            // Métricas de evaluación (1-5 estrellas cada una)
            $table->tinyInteger('clean_code_rating')->default(5)->after('rating');
            $table->tinyInteger('communication_rating')->default(5)->after('clean_code_rating');
            $table->tinyInteger('compliance_rating')->default(5)->after('communication_rating');
            $table->tinyInteger('creativity_rating')->default(5)->after('compliance_rating');
            $table->tinyInteger('post_delivery_support_rating')->default(5)->after('creativity_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'clean_code_rating',
                'communication_rating',
                'compliance_rating',
                'creativity_rating',
                'post_delivery_support_rating',
            ]);
        });
    }
};
