<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('developer_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unsignedTinyInteger('proficiency')->nullable();
            $table->timestamps();

            $table->unique(['developer_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_skill');
        Schema::dropIfExists('skills');
    }
};
