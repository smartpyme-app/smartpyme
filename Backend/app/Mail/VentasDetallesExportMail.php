<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class VentasDetallesExportMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $filePath;
    protected $fileName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($filePath, $fileName)
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mimeType = str_ends_with($this->fileName, '.csv') 
            ? 'text/csv' 
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            
        return $this->subject('Reporte de Detalles de Ventas - SmartPyme')
                    ->view('emails.ventas-detalles-export', [
                        'fileName' => $this->fileName
                    ])
                    ->attach($this->filePath, [
                        'as' => $this->fileName,
                        'mime' => $mimeType
                    ]);
    }
}






