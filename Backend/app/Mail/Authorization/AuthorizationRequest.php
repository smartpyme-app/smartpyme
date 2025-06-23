<?php

namespace App\Mail\Authorization;

use App\Models\Authorization\Authorization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AuthorizationRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $authorization;
    public $authorizer;

    public function __construct(Authorization $authorization, User $authorizer)
    {
        $this->authorization = $authorization;
        $this->authorizer = $authorizer;
    }

    public function build()
    {
        return $this->subject('Nueva Solicitud de Autorización - ' . $this->authorization->authorizationType->display_name)
                    ->view('mails.authorization-request')
                    ->with([
                        'code' => $this->authorization->code,
                        'type' => $this->authorization->authorizationType->display_name,
                        'description' => $this->authorization->description,
                        'requester' => $this->authorization->requester->name,
                        'expires_at' => $this->authorization->expires_at->format('d/m/Y H:i'),
                        'authorizer' => $this->authorizer->name,
                        'approve_url' => env('APP_URL') . '/authorization/' . $this->authorization->code,
                    ]);
    }
}