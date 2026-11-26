<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use PHPUnit\Framework\TestCase;

final class CotizacionFormatoNuevoBladeTest extends TestCase
{
    private function blade(string $name): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 4) . '/resources/views/reportes/facturacion/' . $name
        );
    }

    public function test_plantilla_nueva_incluye_secciones_y_tabla_compartida(): void
    {
        $html = $this->blade('cotizacion-formato-nuevo.blade.php');

        $this->assertStringContainsString('cotizacion-tabla-lineas', $html);
        $this->assertStringContainsString('$cotizacion_carta', $html);
        $this->assertStringContainsString('$cotizacion_terminos', $html);
        $this->assertStringContainsString('$cotizacion_compromisos', $html);
        $this->assertStringContainsString('Observaciones', $html);
        $this->assertStringContainsString('Compromisos', $html);
    }
}