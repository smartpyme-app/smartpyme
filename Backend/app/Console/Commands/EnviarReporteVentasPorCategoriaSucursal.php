<?php

namespace App\Console\Commands;

use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorSucursal\VentasPorCategoriaSucursalMultiExport;
use App\Mail\ReporteVentasPorVendedor;
use App\Models\Admin\Empresa;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EnviarReporteVentasPorCategoriaSucursal extends Command
{
    private const EMPRESAS_IDS = [397, 396, 398, 428, 427, 429, 432, 543, 657, 690, 488];

    private const DESTINATARIOS = [
        'david.c@smartpyme.sv',
        'joseabrego201291@gmail.com'
    ];

    private const CATEGORIAS = [
        ['nombre' => 'Productos', 'porcentaje' => 100],
        ['nombre' => 'Servicios', 'porcentaje' => 90],
    ];

    protected $signature = 'reporte:ventas-por-categoria-sucursal
                            {--inicio= : Fecha inicio YYYY-MM-DD}
                            {--fin= : Fecha fin YYYY-MM-DD}
                            {--dry-run : Generar Excel y guardarlo en storage sin enviar correo}';

    protected $description = 'Reporte de ventas por categoría (Productos 100%, Servicios 90%) agrupado por sucursal para empresas seleccionadas.';

    public function handle(): int
    {
        [$fechaInicio, $fechaFin] = $this->resolvePeriodo();

        $this->info("Generando reporte del {$fechaInicio} al {$fechaFin}...");

        $empresasParaExport = [];

        foreach (self::EMPRESAS_IDS as $idEmpresa) {
            $empresa = Empresa::find($idEmpresa);

            if (! $empresa) {
                $this->warn("Empresa {$idEmpresa} no encontrada, se omite.");

                continue;
            }

            $configuracion = $this->buildConfiguracion($idEmpresa);

            if ($configuracion === null) {
                $this->warn("Empresa {$idEmpresa} ({$empresa->nombre}): sin categorías Productos/Servicios, se omite.");

                continue;
            }

            $empresasParaExport[] = [
                'id' => $idEmpresa,
                'nombre' => $empresa->nombre,
                'configuracion' => $configuracion,
            ];
        }

        if (empty($empresasParaExport)) {
            $this->error('No hay empresas válidas para generar el reporte.');

            return 1;
        }

        $export = new VentasPorCategoriaSucursalMultiExport($fechaInicio, $fechaFin, $empresasParaExport);
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
        $this->info('Empresas incluidas: '.count($empresasParaExport).' de '.count(self::EMPRESAS_IDS));

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
     * @return array{0: string, 1: string}
     */
    private function resolvePeriodo(): array
    {
        $inicio = $this->option('inicio');
        $fin = $this->option('fin');

        if ($inicio && $fin) {
            return [$inicio, $fin];
        }

        $today = Carbon::today();
        $year = $today->year;
        $month = $today->month;
        $day = $today->day;

        if ($day <= 7) {
            return [
                Carbon::create($year, $month, 1)->format('Y-m-d'),
                Carbon::create($year, $month, min($day, 7))->format('Y-m-d'),
            ];
        }

        if ($day <= 15) {
            return [
                Carbon::create($year, $month, 8)->format('Y-m-d'),
                Carbon::create($year, $month, min($day, 15))->format('Y-m-d'),
            ];
        }

        if ($day <= 22) {
            return [
                Carbon::create($year, $month, 16)->format('Y-m-d'),
                Carbon::create($year, $month, min($day, 22))->format('Y-m-d'),
            ];
        }

        return [
            Carbon::create($year, $month, 23)->format('Y-m-d'),
            $today->copy()->endOfMonth()->format('Y-m-d'),
        ];
    }

    private function buildConfiguracion(int $idEmpresa): ?object
    {
        $configuracion = [];

        foreach (self::CATEGORIAS as $cat) {
            $categoria = DB::table('categorias')
                ->where('id_empresa', $idEmpresa)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim($cat['nombre']))])
                ->first();

            if (! $categoria) {
                continue;
            }

            $configuracion[] = [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'porcentaje' => $cat['porcentaje'],
            ];
        }

        if (count($configuracion) !== count(self::CATEGORIAS)) {
            return null;
        }

        $sucursales = DB::table('sucursales')
            ->where('id_empresa', $idEmpresa)
            ->orderBy('nombre')
            ->pluck('id')
            ->toArray();

        return (object) [
            'configuracion' => $configuracion,
            'sucursales' => $sucursales,
        ];
    }
}
