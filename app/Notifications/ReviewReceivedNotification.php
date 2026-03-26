<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private $review,
        private $project,
        private $company
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nueva reseña recibida')
            ->greeting("Hola {$notifiable->name},")
            ->line("**{$this->company->name}** te ha dejado una reseña de {$this->review->rating} estrellas en el proyecto **{$this->project->title}**.")
            ->line($this->review->comment ? "\"{$this->review->comment}\"" : '')
            ->action('Ver Reseña', url('/'));
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'review_received',
            'title' => 'Nueva reseña recibida',
            'message' => "{$this->company->name} te calificó con {$this->review->rating} estrellas en \"{$this->project->title}\".",
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'rating' => $this->review->rating,
            'comment' => $this->review->comment,
            'company_name' => $this->company->name,
            'action_url' => '/dashboard',
        ];
    }
}
