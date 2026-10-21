<?php

namespace Tests\Unit\Contabilidad;

use PHPUnit\Framework\TestCase;

/**
 * El mayor genera una tabla por cuenta. position:absolute + celdas vacías
 * agotan los 128M de producción en DomPDF (Style.php).
 */
class ReportesLibroDiarioMayorBladeTest extends TestCase
{
    private function blade(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/reportes/contabilidad/libro_diario_mayor.blade.php'
        );
    }

    public function test_plantilla_no_usa_posicion_absoluta_ni_td_vacios(): void
    {
        $html = $this->blade();

        $this->assertStringNotContainsString('position: absolute', $html);
        $this->assertStringNotContainsString('<td></td>', $html);
        $this->assertStringContainsString('Helvetica', $html);
        $this->assertStringNotContainsString('media="print"', $html);
    }

    public function test_plantilla_usa_titulo_configurable(): void
    {
        $needle = "\$titulo ?? 'Reporte Libro Diario Mayor'";
        $pdf = $this->blade();
        $excel = (string) file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/reportes/contabilidad/excel/libro_diario_mayor_excel.blade.php'
        );

        $this->assertStringContainsString($needle, $pdf);
        $this->assertStringContainsString($needle, $excel);
    }

    public function test_fecha_movimiento_usa_fecha_de_partida(): void
    {
        $fechaCell = "->format('d/m/Y')";
        $pdf = $this->blade();
        $excel = (string) file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/reportes/contabilidad/excel/libro_diario_mayor_excel.blade.php'
        );

        $this->assertStringContainsString($fechaCell, $pdf);
        $this->assertStringContainsString($fechaCell, $excel);
        $this->assertStringNotContainsString('$detalle->created_at', $excel);
    }

    public function test_libro_auxiliar_reutiliza_plantillas_del_mayor(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Api/Contabilidad/Reportes/GenerarReportesController.php'
        );

        $this->assertStringContainsString('generarRepLibroDiarioMayorPDF', $controller);
        $this->assertStringContainsString('generarRepLibroDiarioMayorExcel', $controller);
        $this->assertStringContainsString("'Libro auxiliar'", $controller);
        $this->assertStringNotContainsString('libro_diario_auxiliar', $controller);
    }
}
