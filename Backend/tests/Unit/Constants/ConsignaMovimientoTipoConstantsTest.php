<?php

namespace Tests\Unit\Constants;

use App\Constants\ConsignaMovimientoTipoConstants;
use PHPUnit\Framework\TestCase;

class ConsignaMovimientoTipoConstantsTest extends TestCase
{
    public function test_todos_incluye_tipos_del_diseno(): void
    {
        $esperados = [
            'entrada_proveedor',
            'venta_regular_propio',
            'venta_regular_consigna_proveedor',
            'entrega_consigna_propio',
            'entrega_consigna_proveedor',
            'liquidacion_proveedor',
            'liquidacion_cliente',
            'devolucion_proveedor',
            'devolucion_cliente',
            'traslado_consigna',
            'ajuste_consigna',
        ];

        foreach ($esperados as $tipo) {
            $this->assertContains($tipo, ConsignaMovimientoTipoConstants::todos());
        }

        $this->assertCount(count($esperados), ConsignaMovimientoTipoConstants::todos());
    }

    public function test_es_valido_rechaza_valores_invalidos(): void
    {
        $this->assertFalse(ConsignaMovimientoTipoConstants::esValido(''));
        $this->assertFalse(ConsignaMovimientoTipoConstants::esValido(null));
        $this->assertFalse(ConsignaMovimientoTipoConstants::esValido('compra'));
        $this->assertTrue(ConsignaMovimientoTipoConstants::esValido('entrada_proveedor'));
    }
}
