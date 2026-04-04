<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MilestoneSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private $milestone,
        private $project,
        private $developer
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hito listo para revisión')
            ->greeting("Hola {$notifiable->name},")
            ->line("**{$this->developer->name}** ha entregado el hito **{$this->milestone->title}** del proyecto **{$this->project->title}**.")
            ->action('Revisar Entrega', url('/'))
            ->line('Por favor revisa la entrega y aprueba o solicita cambios.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'milestone_submitted',
            'title' => 'Hito entregado para revisión',
            'message' => "{$this->developer->name} entregó el hito \"{$this->milestone->title}\" del proyecto \"{$this->project->title}\".",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'milestone_id' => $this->milestone->id,
            'milestone_title' => $this->milestone->title,
            'developer_id' => $this->developer->id,
            'developer_name' => $this->developer->name,
            'action_url' => '/dashboard',
        ];
    }
}
