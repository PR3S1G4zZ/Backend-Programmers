<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MilestoneApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private $milestone,
        private $project
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hito aprobado - Pago liberado')
            ->greeting("Hola {$notifiable->name},")
            ->line("El hito **{$this->milestone->title}** del proyecto **{$this->project->title}** ha sido aprobado.")
            ->line("El monto de \${$this->milestone->amount} ha sido liberado a tu billetera.")
            ->action('Ver Billetera', url('/'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'milestone_approved',
            'title' => 'Hito aprobado',
            'message' => "El hito \"{$this->milestone->title}\" del proyecto \"{$this->project->title}\" fue aprobado. \${$this->milestone->amount} liberados.",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'milestone_id' => $this->milestone->id,
            'milestone_title' => $this->milestone->title,
            'amount' => $this->milestone->amount,
            'action_url' => '/dashboard',
        ];
    }
}
