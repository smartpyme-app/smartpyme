<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnalisisVentasMensualMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $filePath,
        private string $fileName,
    ) {
    }

    public function build()
    {
        return $this->subject('Reporte comportamiento anual - SmartPyme')
            ->view('emails.analisis-ventas-mensual', [
                'fileName' => $this->fileName,
            ])
            ->attach($this->filePath, [
                'as' => $this->fileName,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
