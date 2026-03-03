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
        Schema::create('developer_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('developer_id')->constrained('users')->onDelete('cascade');
            $table->integer('progress')->default(0); // 0-100
            $table->json('milestones_completed')->nullable(); // Almacena los IDs de milestones completados por el desarrollador
            $table->json('tasks_completed')->nullable(); // Almacena las tareas completadas
            $table->timestamps();
            
            $table->unique(['project_id', 'developer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developer_progress');
    }
};
