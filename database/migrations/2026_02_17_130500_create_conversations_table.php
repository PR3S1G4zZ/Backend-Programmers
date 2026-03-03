<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'project'])->default('direct');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            // Initiator usually the company in this context, but could be anyone
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            // Participant the other party
            $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            
            // Should prompt to prevent duplicate direct conversations between same 2 users?
            // For 'direct', unique(initiator, participant)? 
            // Better: unique combination regardless of order... but simplest for now is just index
            $table->index(['initiator_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
