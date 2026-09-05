<?php

namespace Tests\Unit\Exports;

use App\Exports\ComprasDetallesExport;
use App\Exports\Support\DevolucionEnReporte;
use PHPUnit\Framework\TestCase;

class ComprasDetallesExportDevolucionTest extends TestCase
{
    public function test_no_selecciona_subtotal_iva_ni_descuento_si_no_existen_en_bd(): void
    {
        $select = ComprasDetallesExport::columnasMontoDevolucionSiExisten([]);

        $this->assertNotContains('ddc.subtotal', $select);
        $this->assertNotContains('ddc.iva', $select);
        $this->assertNotContains('ddc.descuento', $select);
        $this->assertContains('ddc.total', $select);
    }

    public function test_incluye_subtotal_iva_y_descuento_si_existen_en_bd(): void
    {
        $select = ComprasDetallesExport::columnasMontoDevolucionSiExisten(['subtotal', 'iva', 'descuento']);

        $this->assertContains('ddc.subtotal', $select);
        $this->assertContains('ddc.iva', $select);
        $this->assertContains('ddc.descuento', $select);
        $this->assertContains('ddc.total', $select);
    }

    public function test_map_devolucion_usa_total_si_faltan_subtotal_e_iva(): void
    {
        $row = (object) [
            'fecha' => '2026-08-10',
            'proveedor_tipo' => 'Empresa',
            'proveedor_empresa' => 'Proveedor Dev',
            'proveedor_nombre' => null,
            'proveedor_apellido' => null,
            'proveedor_dui' => '123',
            'proveedor_nit' => '456',
            'producto_nombre' => 'Café',
            'categoria_nombre' => 'Abarrotes',
            'tipo_documento' => 'CCF',
            'referencia' => 'DEV-1',
            'proyecto_nombre' => null,
            'num_identificacion' => '001',
            'fecha_pago' => null,
            'cantidad' => 2,
            'costo' => 5,
            'total' => 10,
        ];
        DevolucionEnReporte::marcar($row);

        $fila = (new ComprasDetallesExport())->map($row);

        $this->assertSame('2026-08-10', $fila[0]);
        $this->assertSame('Proveedor Dev', $fila[1]);
        $this->assertSame(-2.0, $fila[12]);
        $this->assertSame(-10.0, $fila[14]);
        $this->assertSame(-1.3, $fila[15]);
        $this->assertSame(0.0, $fila[16]);
        $this->assertSame(-11.3, $fila[18]);
    }
}
