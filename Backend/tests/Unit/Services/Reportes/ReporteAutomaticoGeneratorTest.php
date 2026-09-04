<?php

namespace Tests\Unit\Services\Reportes;

use App\Exports\ComprasDetallesExport;
use App\Exports\ComprasExport;
use App\Models\Admin\ReporteConfiguracion;
use App\Services\Reportes\ReporteAutomaticoGenerator;
use ReflectionMethod;
use Tests\TestCase;

class ReporteAutomaticoGeneratorTest extends TestCase
{
    public function test_build_excel_export_soporta_detalle_compras_totales(): void
    {
        $config = new ReporteConfiguracion();
        $config->tipo_reporte = 'detalle-compras-totales';
        $config->id_empresa = 1;
        $config->sucursales = [1, 2];

        $export = $this->invokeBuildExcelExport($config, '2026-08-01', '2026-08-31');

        $this->assertInstanceOf(ComprasExport::class, $export);
    }

    public function test_build_excel_export_soporta_detalle_compras_por_producto(): void
    {
        $config = new ReporteConfiguracion();
        $config->tipo_reporte = 'detalle-compras-por-producto';
        $config->id_empresa = 1;
        $config->sucursales = [1];

        $export = $this->invokeBuildExcelExport($config, '2026-08-01', '2026-08-31');

        $this->assertInstanceOf(ComprasDetallesExport::class, $export);
    }

    private function invokeBuildExcelExport(
        ReporteConfiguracion $config,
        string $fechaInicio,
        string $fechaFin
    ) {
        $generator = new ReporteAutomaticoGenerator();
        $method = new ReflectionMethod($generator, 'buildExcelExport');
        $method->setAccessible(true);

        return $method->invoke($generator, $config, $fechaInicio, $fechaFin);
    }
}
