<?php

namespace App\Console\Commands;

use App\Services\RecuperarVentasPerdidasService;
use Illuminate\Console\Command;

class ExportarVentasPerdidasPhp extends Command
{
    protected $signature = 'ventas:exportar-recuperar
                            {--fecha-inicio=2026-02-11 : Fecha inicio (Y-m-d)}
                            {--fecha-fin=2026-02-12 : Fecha fin (Y-m-d)}
                            {--ruta=datos/recuperar : Carpeta donde guardar los archivos}';

    protected $description = 'Exporta ventas/clientes/detalles perdidos a arrays PHP para importar en producción';

    public function handle()
    {
        $fechaInicio = $this->option('fecha-inicio');
        $fechaFin = $this->option('fecha-fin');
        $rutaBase = base_path(ltrim($this->option('ruta'), '/'));

        if (!is_dir($rutaBase)) {
            if (!mkdir($rutaBase, 0755, true)) {
                $this->error("No se pudo crear la carpeta: {$rutaBase}");
                return 1;
            }
        }

        $this->info("Exportando ventas perdidas entre {$fechaInicio} y {$fechaFin}...");

        $service = new RecuperarVentasPerdidasService($fechaInicio, $fechaFin);
        $datos = $service->getDatosParaExportar();

        $nombreArchivo = "ventas_perdidas_{$fechaInicio}_{$fechaFin}.php";
        $rutaArchivo = $rutaBase . '/' . $nombreArchivo;

        $contenido = $this->generarContenidoPhp($datos);
        file_put_contents($rutaArchivo, $contenido);

        $this->info("Exportación completada.");
        $this->info("Archivo: {$rutaArchivo}");
        $this->info("Clientes: " . count($datos['clientes']));
        $this->info("Ventas: " . count($datos['ventas']));
        $this->info("Detalles: " . count($datos['detalles_venta']));
        $this->newLine();
        $this->info("Para importar en producción:");
        $this->line("  php artisan ventas:importar-recuperar --archivo={$nombreArchivo}");
        $this->line("  (sube el archivo a la carpeta datos/recuperar/ en el servidor)");

        return 0;
    }

    protected function generarContenidoPhp(array $datos): string
    {
        $export = var_export($datos, true);
        return "<?php\n\n// Generado por ventas:exportar-recuperar el " . date('Y-m-d H:i:s') . "\n\nreturn " . $export . ";\n";
    }
}
