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
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('users')->cascadeOnDelete();
            $t->string('title');
            $t->text('description');
            $t->unsignedInteger('budget_min')->nullable();
            $t->unsignedInteger('budget_max')->nullable();
            $t->enum('status', ['draft','open','in_progress','completed','cancelled'])->default('open');
            $t->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
