<?php

namespace App\Console\Commands;

use App\Mail\ReporteVentasPorVendedor;
use App\Services\EstilosSalon\ConsolidadoEstilosSalonService;
use App\Support\EstilosSalon\EstilosSalonPeriodo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EnviarReporteVentasPorCategoriaSucursal extends Command
{
    private const DESTINATARIOS = [
        'david.c@smartpyme.sv',
        'joseabrego201291@gmail.com',
    ];

    protected $signature = 'reporte:ventas-por-categoria-sucursal
                            {--inicio= : Fecha inicio YYYY-MM-DD}
                            {--fin= : Fecha fin YYYY-MM-DD}
                            {--dry-run : Generar Excel y guardarlo en storage sin enviar correo}';

    protected $description = 'Reporte de ventas por categoría (Productos 100%, Servicios 90%) agrupado por sucursal para empresas seleccionadas.';

    public function __construct(private ConsolidadoEstilosSalonService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodo = $this->resolvePeriodo();

        if ($periodo === null) {
            $this->info('Hoy no es día de envío del consolidado Estilo\'s. No se envió correo.');

            return 0;
        }

        [$fechaInicio, $fechaFin] = $periodo;

        $this->info("Generando reporte del {$fechaInicio} al {$fechaFin}...");

        $empresasParaExport = $this->service->empresasParaExport();

        if ($empresasParaExport === []) {
            $this->error('No hay empresas válidas para generar el reporte.');

            return 1;
        }

        $export = $this->service->makeExport($fechaInicio, $fechaFin, $empresasParaExport);
        $filename = "ventas-por-categoria-sucursal-{$fechaInicio}-{$fechaFin}.xlsx";
        $relativePath = "reportes-prueba/{$filename}";

        try {
            Storage::disk('local')->makeDirectory('reportes-prueba');
            Excel::store($export, $relativePath, 'local');
        } catch (\Throwable $e) {
            Log::error('Error generando reporte ventas por categoría sucursal', [
                'error' => $e->getMessage(),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ]);
            $this->error('Error al generar Excel: '.$e->getMessage());

            return 1;
        }

        $filePath = storage_path('app/'.$relativePath);

        if (! file_exists($filePath)) {
            $this->error("El archivo no se generó en: {$filePath}");

            return 1;
        }

        $this->info('Excel generado: '.$filePath);
        $this->info('Empresas incluidas: '.count($empresasParaExport).' de '.count(EstilosSalonPeriodo::EMPRESAS_IDS));

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: archivo guardado, no se envió correo.');

            return 0;
        }

        $asunto = "Reporte de Ventas por Categoría y Sucursal {$fechaInicio} al {$fechaFin}";

        try {
            Mail::to(self::DESTINATARIOS)->send(new ReporteVentasPorVendedor([
                'fecha' => $fechaInicio,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => 0,
                'totalVentas' => 0,
                'vendedoresConVentas' => 0,
                'archivoPath' => $filePath,
                'nombreArchivo' => $filename,
                'asunto' => $asunto,
                'automatico' => true,
                'tipo_reporte' => 'ventas-por-categoria-sucursal',
                'empresa' => 'Consolidado ('.count($empresasParaExport).' empresas)',
            ]));

            Log::info('Reporte ventas por categoría sucursal enviado', [
                'destinatarios' => self::DESTINATARIOS,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'empresas' => count($empresasParaExport),
            ]);

            $this->info('Correo enviado a '.implode(', ', self::DESTINATARIOS));
            unlink($filePath);

            return 0;
        } catch (\Throwable $e) {
            Log::error('Error enviando reporte ventas por categoría sucursal', [
                'error' => $e->getMessage(),
                'archivo' => $filePath,
            ]);
            $this->error('Error al enviar correo: '.$e->getMessage());
            $this->warn("El archivo permanece en: {$filePath}");

            return 1;
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function resolvePeriodo(): ?array
    {
        $inicio = $this->option('inicio');
        $fin = $this->option('fin');

        if ($inicio && $fin) {
            return [$inicio, $fin];
        }

        return EstilosSalonPeriodo::periodoCron(Carbon::today());
    }
}
