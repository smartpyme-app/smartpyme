<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\MH\ActividadEconomica;

class ExportActividades extends Command
{
    protected $signature = 'export:actividades {--format=csv}';
    protected $description = 'Exportar actividades económicas';

    public function handle()
    {
        $actividades = ActividadEconomica::orderBy('cod')->get(); // ← Cambio aquí
        
        $filename = storage_path('app/actividades_economicas.csv');
        
        $file = fopen($filename, 'w');
        
        // Escribir BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($file, ['codigo', 'nombre'], ',', '"');
        
        // Datos
        foreach ($actividades as $actividad) {
            fputcsv($file, [$actividad->cod, $actividad->nombre], ',', '"');
        }
        
        fclose($file);
        
        $this->info("Archivo exportado en: {$filename}");
        
        return 0;
    }
}