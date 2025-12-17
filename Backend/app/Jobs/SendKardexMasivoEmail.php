<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\KardexMasivoMail;

class SendKardexMasivoEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $filePath;
    protected $fileName;
    public $tries = 3;
    public $timeout = 300;

    public function __construct($email, $filePath, $fileName)
    {
        $this->email = $email;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->onQueue('smartpyme-email-notifications');
    }

    public function handle()
    {
        try {
            Log::info("Enviando kardex masivo por email a: {$this->email}");
            
            Mail::to($this->email)->send(new KardexMasivoMail($this->filePath, $this->fileName));
            
            Log::info("Kardex masivo enviado exitosamente a: {$this->email}");
            
            // Limpiar archivo temporal después del envío
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
                Log::info("Archivo temporal eliminado: {$this->filePath}");
            }
            
        } catch (\Exception $e) {
            Log::error("Error enviando kardex masivo por email: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("Job SendKardexMasivoEmail falló: " . $exception->getMessage());
        
        // Limpiar archivo temporal si el job falla
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }
}