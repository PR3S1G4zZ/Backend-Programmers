<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private $project,
        private $company
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('¡Tu aplicación fue aceptada!')
            ->greeting("Hola {$notifiable->name},")
            ->line("La empresa **{$this->company->name}** ha aceptado tu aplicación al proyecto **{$this->project->title}**.")
            ->action('Ver Proyecto', url('/'))
            ->line('¡Felicidades! Ya puedes comenzar a trabajar en este proyecto.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'application_accepted',
            'title' => 'Aplicación aceptada',
            'message' => "La empresa {$this->company->name} aceptó tu aplicación al proyecto \"{$this->project->title}\".",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'company_name' => $this->company->name,
            'action_url' => '/dashboard',
        ];
    }
}
