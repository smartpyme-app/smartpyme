<?php

namespace Tests\Unit\Ventas;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use Tests\TestCase;

class VentaReferenciaDocumentoContableTest extends TestCase
{
    public function test_nombre_cliente_sin_id_cliente_en_modelo_parcial_cae_a_consumidor_final(): void
    {
        $venta = new Venta(['id' => 1, 'correlativo' => 59]);

        $this->assertSame('Consumidor Final', $venta->nombre_cliente);
    }

    public function test_referencia_documento_contable_incluye_cliente_cuando_esta_cargado(): void
    {
        $cliente = new Cliente([
            'tipo' => 'Persona',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
        ]);
        $venta = new Venta([
            'id' => 1,
            'correlativo' => 59,
            'id_cliente' => 10,
        ]);
        $venta->setRelation('cliente', $cliente);
        $venta->setRelation('documento', (object) ['nombre' => 'Factura']);

        $ref = $venta->referenciaDocumentoContable();

        $this->assertStringContainsString('Factura #59', $ref);
        $this->assertStringContainsString('Juan', $ref);
        $this->assertStringNotContainsString('Consumidor Final', $ref);
    }
}
