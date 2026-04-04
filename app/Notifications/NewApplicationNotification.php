<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private $project,
        private $developer
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva aplicación a tu proyecto')
            ->greeting("Hola {$notifiable->name},")
            ->line("**{$this->developer->name} {$this->developer->lastname}** ha aplicado a tu proyecto **{$this->project->title}**.")
            ->action('Ver Candidatos', url('/'))
            ->line('Revisa su perfil y decide si es el candidato ideal.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_application',
            'title' => 'Nueva aplicación',
            'message' => "{$this->developer->name} {$this->developer->lastname} aplicó a tu proyecto \"{$this->project->title}\".",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'developer_id' => $this->developer->id,
            'developer_name' => "{$this->developer->name} {$this->developer->lastname}",
            'action_url' => '/dashboard',
        ];
    }
}
