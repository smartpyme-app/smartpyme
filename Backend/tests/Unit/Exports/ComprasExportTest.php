<?php

namespace Tests\Unit\Exports;

use App\Exports\ComprasExport;
use App\Models\Compras\Compra;
use App\Models\Compras\Proveedores\Proveedor;
use Tests\TestCase;

class ComprasExportTest extends TestCase
{
    public function test_headings_incluyen_forma_de_pago_y_cuenta_del_proveedor(): void
    {
        $headings = (new ComprasExport())->headings();

        foreach (['Forma de pago', 'Banco', 'Tipo de cuenta', 'Número de cuenta', 'Titular'] as $columna) {
            $this->assertContains($columna, $headings);
        }
    }

    public function test_map_rellena_cuenta_cuando_el_proveedor_la_tiene(): void
    {
        $proveedor = new Proveedor();
        $proveedor->dui = '00000000-0';
        $proveedor->nit = '0614-000000-000-0';
        $proveedor->banco = 'Banco Agrícola';
        $proveedor->tipo_cuenta = 'Ahorro';
        $proveedor->numero_cuenta = '123456';
        $proveedor->titular_cuenta = 'ACME SA';

        $compra = new Compra();
        $compra->fecha = '2026-08-01';
        $compra->nombre_proveedor = 'ACME SA';
        $compra->tipo_documento = 'CCF';
        $compra->referencia = '1';
        $compra->num_identificacion = null;
        $compra->estado = 'Pagada';
        $compra->fecha_pago = '2026-08-15';
        $compra->sub_total = 100;
        $compra->iva = 13;
        $compra->percepcion = 0;
        $compra->descuento = 0;
        $compra->total = 113;
        $compra->forma_pago = 'Transferencia';
        $compra->setRelation('proveedor', $proveedor);
        $compra->setRelation('proyecto', null);

        $fila = (new ComprasExport())->map($compra);
        $this->assertSame('Transferencia', $fila[count($fila) - 5]);
        $this->assertSame('Banco Agrícola', $fila[count($fila) - 4]);
        $this->assertSame('Ahorro', $fila[count($fila) - 3]);
        $this->assertSame('123456', $fila[count($fila) - 2]);
        $this->assertSame('ACME SA', $fila[count($fila) - 1]);
    }

    public function test_map_deja_cuenta_vacia_si_el_proveedor_no_la_tiene(): void
    {
        $compra = new Compra();
        $compra->fecha = '2026-08-01';
        $compra->nombre_proveedor = 'Sin banco';
        $compra->tipo_documento = 'CCF';
        $compra->referencia = '2';
        $compra->num_identificacion = null;
        $compra->estado = 'Pagada';
        $compra->fecha_pago = null;
        $compra->sub_total = 10;
        $compra->iva = 0;
        $compra->percepcion = 0;
        $compra->descuento = 0;
        $compra->total = 10;
        $compra->forma_pago = 'Efectivo';
        $compra->setRelation('proveedor', new Proveedor());
        $compra->setRelation('proyecto', null);

        $fila = (new ComprasExport())->map($compra);
        $this->assertSame('Efectivo', $fila[count($fila) - 5]);
        $this->assertSame(null, $fila[count($fila) - 4]);
        $this->assertSame(null, $fila[count($fila) - 3]);
        $this->assertSame(null, $fila[count($fila) - 2]);
        $this->assertSame(null, $fila[count($fila) - 1]);
    }
}
