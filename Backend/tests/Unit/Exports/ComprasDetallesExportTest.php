<?php

namespace Tests\Unit\Exports;

use App\Exports\ComprasDetallesExport;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle;
use App\Models\Compras\Proveedores\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ComprasDetallesExportTest extends TestCase
{
    public function test_map_usa_relaciones_precargadas_sin_consultas_extra(): void
    {
        $proveedor = new Proveedor();
        $proveedor->tipo = 'Empresa';
        $proveedor->nombre_empresa = 'Proveedor Test SA';
        $proveedor->dui = '00000000-0';
        $proveedor->nit = '0614-000000-000-0';

        $proyecto = new class extends Model {
            protected $table = 'proyectos_test_stub';
            public $timestamps = false;
        };
        $proyecto->nombre = 'Proyecto A';

        $compra = new Compra();
        $compra->fecha = '2026-08-15';
        $compra->tipo_documento = 'CCF';
        $compra->referencia = 'REF-1';
        $compra->num_identificacion = '001-001-01-000001';
        $compra->estado = 'Pagada';
        $compra->fecha_pago = '2026-08-20';
        $compra->setRelation('proveedor', $proveedor);
        $compra->setRelation('proyecto', $proyecto);

        $detalle = new Detalle();
        $detalle->cantidad = 2;
        $detalle->costo = 10.5;
        $detalle->total = 21;
        $detalle->descuento = 0;
        $detalle->setRelation('compra', $compra);
        $detalle->setRelation('producto', null);

        $fila = (new ComprasDetallesExport())->map($detalle);

        $this->assertSame('2026-08-15', $fila[0]);
        $this->assertSame('Proveedor Test SA', $fila[1]);
        $this->assertSame('00000000-0', $fila[2]);
        $this->assertSame('0614-000000-000-0', $fila[3]);
        $this->assertSame('Proyecto A', $fila[8]);
        $this->assertSame(2, $fila[12]);
        $this->assertSame(21.0 + 21.0 * 0.13, $fila[18]);
    }
}
