<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use PHPUnit\Framework\TestCase;

/**
 * El formato comercial SmartPyme (empresa ID 2) vive en la plantilla Blade.
 * Comprueba copy fijo del PDF de referencia y bindings dinámicos de la cotización.
 */
class CotizacionSmartpymeBladeTest extends TestCase
{
    private function blade(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 4) . '/resources/views/reportes/facturacion/formatos_empresas/cotizacion-smartpyme.blade.php'
        );
    }

    public function test_plantilla_usa_formato_comercial_con_datos_de_cotizacion(): void
    {
        $html = $this->blade();

        $this->assertStringContainsString('¿Por qué elegir SmartPyme?', $html);
        $this->assertStringContainsString('Propuesta Económica', $html);
        $this->assertStringContainsString('Alcance y Cobertura', $html);
        $this->assertStringNotContainsString('Cantidades', $html);
        $this->assertStringContainsString('Edificio Colabora loca 1-2', $html);
        $this->assertStringContainsString('bg_smarpyme_cotizacion.png', $html);
        $this->assertStringContainsString('contact@smartpyme.sv', $html);
        $this->assertStringContainsString('$venta->nombre_cliente', $html);
        $this->assertStringContainsString('$venta->fecha', $html);
        $this->assertStringContainsString('$venta->fecha_expiracion', $html);
        $this->assertStringContainsString('$venta->correlativo', $html);
        $this->assertStringContainsString('NCR:', $html);
        $this->assertStringContainsString('Válido hasta', $html);
        $this->assertStringContainsString('$venta->detalles', $html);
        $this->assertStringContainsString('$impuestoLabel', $html);
    }
}
