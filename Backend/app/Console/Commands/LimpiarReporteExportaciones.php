<?php

namespace App\Console\Commands;

use App\Models\Admin\ReporteExportacion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LimpiarReporteExportaciones extends Command
{
    protected $signature = 'reportes:limpiar-exportaciones {--horas=24 : Antigüedad mínima en horas}';

    protected $description = 'Elimina exportaciones de reportes terminadas/fallidas y sus archivos temporales';

    public function handle()
    {
        $horas = (int) $this->option('horas');
        $limite = Carbon::now()->subHours($horas);

        $exportaciones = ReporteExportacion::whereIn('estado', [
            ReporteExportacion::ESTADO_DONE,
            ReporteExportacion::ESTADO_FAILED,
        ])
            ->where('created_at', '<', $limite)
            ->get();

        $borradas = 0;

        foreach ($exportaciones as $exportacion) {
            $path = $exportacion->absolutePath();
            if ($path && file_exists($path)) {
                @unlink($path);
            }
            $exportacion->delete();
            $borradas++;
        }

        $this->info("Exportaciones eliminadas: {$borradas}");

        return 0;
    }
}
