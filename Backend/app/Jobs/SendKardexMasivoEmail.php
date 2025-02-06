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
    protected $s3Path;
    protected $fileName;
    public $tries = 3;
    public $timeout = 300;

    public function __construct($email, $s3Path, $fileName)
    {
        $this->email = $email;
        $this->s3Path = $s3Path;
        $this->fileName = $fileName;
        $this->onQueue('smartpyme-email-notifications');
    }

    public function handle()
    {
        $attempt = $this->attempts();
        Log::info("Intento {$attempt} de {$this->tries} - Enviando kardex masivo por email a: {$this->email}");
        
        try {
            // Descargar archivo de S3 a /tmp/
            $localPath = '/tmp/' . $this->fileName;
            $s3Contents = \Storage::disk('s3-storage')->get($this->s3Path);
            file_put_contents($localPath, $s3Contents);
            Log::info("Archivo descargado de S3 a: {$localPath}");
            
            Mail::to($this->email)->send(new KardexMasivoMail($localPath, $this->fileName));
            
            Log::info("Kardex masivo enviado exitosamente a: {$this->email}");
            
            // Limpiar archivo temporal local
            if (file_exists($localPath)) {
                unlink($localPath);
                Log::info("Archivo temporal local eliminado: {$localPath}");
            }
            
        } catch (\Exception $e) {
            Log::error("Error enviando kardex masivo por email: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("Job SendKardexMasivoEmail falló: " . $exception->getMessage());
    }
}