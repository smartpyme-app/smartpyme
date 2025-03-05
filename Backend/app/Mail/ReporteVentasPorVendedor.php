<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReporteVentasPorVendedor extends Mailable
{
    use Queueable, SerializesModels;

    public $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function build()
    {
        $filePath = $this->datos['archivoPath'];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            Log::error("El archivo no existe o no se puede leer: {$filePath}");
            throw new \Exception("No se puede adjuntar el archivo: {$filePath}");
        }

        return $this->subject('Reporte de Ventas por Vendedor - ' . $this->datos['fecha'])
            ->view('reportes.ventas-por-vendedor')
            ->attach($filePath, [
                'as' => $this->datos['nombreArchivo'],
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]);
    }
}
