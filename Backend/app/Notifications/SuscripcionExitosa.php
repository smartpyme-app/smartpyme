<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Suscripcion;
use App\Models\Admin\Empresa;
use App\Models\User;

class SuscripcionExitosa extends Notification implements ShouldQueue
{
    use Queueable;

    protected $suscripcion;
    protected $empresa;
    protected $usuario;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Suscripcion $suscripcion, Empresa $empresa, User $usuario)
    {
        $this->suscripcion = $suscripcion;
        $this->empresa = $empresa;
        $this->usuario = $usuario;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Confirmación de Suscripción - ' . $this->empresa->nombre)
            ->view('mails.suscripcion-exitosa', [
                'suscripcion' => $this->suscripcion,
                'empresa' => $this->empresa,
                'usuario' => $this->usuario
            ]);
    }
}
