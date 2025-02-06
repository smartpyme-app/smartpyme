<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\KardexMasivoErrorMail;

class SendKardexMasivoErrorEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $errorMessage;
    public $tries = 3;
    public $timeout = 120;

    public function __construct($email, $errorMessage)
    {
        $this->email = $email;
        $this->errorMessage = $errorMessage;
        $this->onQueue('smartpyme-email-notifications');
    }

    public function handle()
    {
        try {
            Log::info("Enviando email de error de kardex masivo a: {$this->email}");
            
            Mail::to($this->email)->send(new KardexMasivoErrorMail($this->errorMessage));
            
            Log::info("Email de error enviado exitosamente a: {$this->email}");
            
        } catch (\Exception $e) {
            Log::error("Error enviando email de error de kardex masivo: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("Job SendKardexMasivoErrorEmail falló: " . $exception->getMessage());
    }
}