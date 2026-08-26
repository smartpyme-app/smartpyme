<?php

namespace Tests\Unit\Contabilidad;

use PHPUnit\Framework\TestCase;

/**
 * DomPDF agota 128MB en Style.php cuando el libro diario pinta una celda
 * (y su CSS) por cada columna vacía de cada detalle.
 */
class ReportesLibroDiarioBladeTest extends TestCase
{
    private function blade(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/reportes/contabilidad/libro_diario.blade.php'
        );
    }

    public function test_plantilla_no_emite_td_vacios_ni_css_de_impresion_extra(): void
    {
        $html = $this->blade();

        $this->assertStringNotContainsString(
            '<td></td>',
            $html,
            'Cada td vacío crea un Style de DomPDF; usar colspan en su lugar.'
        );
        $this->assertStringContainsString('Helvetica', $html);
        $this->assertStringNotContainsString('media="print"', $html);
    }
}
