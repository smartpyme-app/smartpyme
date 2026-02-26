<?php

namespace App\Console\Commands;

use App\Exports\VentasPerdidasExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class RecuperarVentasPerdidas extends Command
{
    protected $signature = 'ventas:recuperar-perdidas
                            {--fecha-inicio=2026-02-11 : Fecha inicio (Y-m-d)}
                            {--fecha-fin=2026-02-12 : Fecha fin (Y-m-d)}
                            {--excel : Generar archivo Excel}';

    protected $description = 'Identifica ventas y clientes perdidos en sp_nova que no están en vps';

    public function handle()
    {
        $fechaInicio = $this->option('fecha-inicio');
        $fechaFin = $this->option('fecha-fin');

        $this->info("Buscando ventas perdidas entre {$fechaInicio} y {$fechaFin}...");

        $export = new VentasPerdidasExport($fechaInicio, $fechaFin);
        $datos = $export->getDatos();

        $totalVentas = count($datos['ventas_perdidas']);
        $totalClientes = count($datos['clientes_perdidos']);

        $this->info("Ventas perdidas encontradas: {$totalVentas}");
        $this->info("Clientes perdidos encontrados: {$totalClientes}");

        if ($totalVentas === 0 && $totalClientes === 0) {
            $this->warn('No se encontraron datos perdidos en el rango indicado.');
            return 0;
        }

        $this->table(
            ['Cliente', 'Ventas'],
            array_map(function ($g) {
                return [$g['nombre_cliente'], count($g['ventas'])];
            }, $datos['ventas_por_cliente'])
        );

        if ($this->option('excel')) {
            $nombreArchivo = "ventas_perdidas_{$fechaInicio}_{$fechaFin}.xlsx";
            Excel::store($export, $nombreArchivo, 'local');
            $path = storage_path('app/' . $nombreArchivo);
            $this->info("Excel generado: {$path}");
        }

        $query = http_build_query(['fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin]);
        $this->info('Reporte web: ' . config('app.url') . '/api/ventas-perdidas?' . $query);

        return 0;
    }
}
