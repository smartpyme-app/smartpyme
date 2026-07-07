<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // ponytail: auto-select frontend URL based on APP_ENV
        $frontendUrl = config('app.env') === 'local'
            ? env('FRONTEND_URL_LOCAL', 'http://localhost:4200')
            : env('FRONTEND_URL', 'https://app.smartpyme.site');

        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Recupera tu cuenta - SmartPyme')
            ->view('mails.reset-password', ['resetUrl' => $resetUrl]);
    }
}
