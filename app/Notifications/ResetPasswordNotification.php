<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public $token) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
    $frontend = config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:5173'));

    $url = "{$frontend}/reset-password?token={$this->token}&email={$notifiable->email}";

    return (new MailMessage)
        ->subject('Recuperar contraseña')
        ->greeting('Hola ' . $notifiable->name)
        ->line('Solicitaste restablecer tu contraseña.')
        ->action('Restablecer contraseña', $url)
        ->line('Si no realizaste esta solicitud, ignora este correo.');
    }
}
