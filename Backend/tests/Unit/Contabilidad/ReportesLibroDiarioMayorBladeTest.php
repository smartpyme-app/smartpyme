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
}
