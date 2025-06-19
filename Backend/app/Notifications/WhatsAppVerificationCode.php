<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhatsAppVerificationCode extends Notification
{
    use Queueable;

    protected $verificationCode;
    protected $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct($verificationCode, $userName = null)
    {
        $this->verificationCode = $verificationCode;
        $this->userName = $userName;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de verificación para WhatsApp - ' . config('app.name'))
            ->greeting('¡Hola ' . ($this->userName ?? $notifiable->name) . '!')
            ->line('Hemos recibido una solicitud para conectar tu cuenta de WhatsApp.')
            ->line('Tu código de verificación es:')
            ->line('')
            ->line('**' . $this->verificationCode . '**')
            ->line('')
            ->line('Este código expirará en 10 minutos por motivos de seguridad.')
            ->line('Si no solicitaste este código, puedes ignorar este mensaje.')
            ->line('¡Gracias por usar nuestro servicio!')
            ->salutation('Saludos,')
            ->salutation('El equipo de ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'verification_code' => $this->verificationCode,
            'user_name' => $this->userName,
            'type' => 'whatsapp_verification'
        ];
    }
}