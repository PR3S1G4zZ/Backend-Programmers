<?php

namespace App\Listeners;

use App\Events\ApplicationAccepted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\{Conversation, Message, User};

class CreateProjectChat
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    public function handle(ApplicationAccepted $event): void
    {
        $application = $event->application;
        $project = $application->project;
        $developer = $application->developer;
        
        // The company (owner of the project) is the initiator
        $initiatorId = $project->company_id ?? $project->user_id; // Fallback if company_id handled differently

        // 1. Check if a conversation already exists for this project between these two users
        $conversation = Conversation::where('project_id', $project->id)
            ->where(function($q) use ($initiatorId, $developer) {
                $q->where('initiator_id', $initiatorId)
                  ->where('participant_id', $developer->id);
            })->orWhere(function($q) use ($initiatorId, $developer) {
                $q->where('initiator_id', $developer->id)
                  ->where('participant_id', $initiatorId);
            })->first();

        if (!$conversation) {
            // Create new conversation
            $conversation = Conversation::create([
                'project_id' => $project->id,
                'initiator_id' => $initiatorId, 
                'participant_id' => $developer->id,
                'type' => 'project',
            ]);

            // Send Welcome Message
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $initiatorId,
                'content' => "¡Hola! Bienvenidos al proyecto '{$project->title}'. He aceptado tu solicitud. ¡Empecemos!",
            ]);
        } 
    }
}
