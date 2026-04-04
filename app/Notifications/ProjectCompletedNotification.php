<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private $project
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Proyecto completado')
            ->greeting("Hola {$notifiable->name},")
            ->line("El proyecto **{$this->project->title}** ha sido marcado como completado.")
            ->line('El pago final ha sido procesado y liberado a los desarrolladores.')
            ->action('Ver Proyecto', url('/'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'project_completed',
            'title' => 'Proyecto completado',
            'message' => "El proyecto \"{$this->project->title}\" ha sido completado exitosamente.",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'action_url' => '/dashboard',
        ];
    }
}
