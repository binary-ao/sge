<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RedefinirSenhaNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Recuperação de senha — '.config('app.name'))
            ->greeting('Olá, '.$notifiable->nome)
            ->line('Recebemos um pedido de recuperação da senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line("Esta ligação expira dentro de {$minutos} minutos.")
            ->line('Se não efectuou este pedido, ignore esta mensagem.')
            ->salutation('Direcção Provincial da Educação do Bengo');
    }
}
