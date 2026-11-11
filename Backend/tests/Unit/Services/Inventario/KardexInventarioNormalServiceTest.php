<?php

namespace Tests\Unit\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Kardex;
use App\Services\Inventario\KardexInventarioNormalService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class KardexInventarioNormalServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_recalcula_existencias_propias(): void
    {
        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);

        $m1 = new Kardex();
        $m1->id = 1;
        $m1->fecha = '2026-01-01';
        $m1->detalle = 'Ajuste';
        $m1->entrada_cantidad = 100;
        $m1->salida_cantidad = 0;
        $m1->costo_unitario = 1;

        $m2 = new Kardex();
        $m2->id = 2;
        $m2->fecha = '2026-01-02';
        $m2->detalle = 'Venta';
        $m2->referencia = '99';
        $m2->entrada_cantidad = 0;
        $m2->salida_cantidad = 10;
        $m2->costo_unitario = 1;

        $service = new KardexInventarioNormalService();
        $out = $service->paraKardexFisico(collect([$m1, $m2]), 999999, $empresa);

        $this->assertEquals(100.0, (float) $out->firstWhere('id', 1)->total_cantidad);
        $this->assertEquals(90.0, (float) $out->firstWhere('id', 2)->total_cantidad);
    }

    public function test_sin_ledger_no_filtra(): void
    {
        $service = new KardexInventarioNormalService();
        $mov = new Kardex();
        $mov->detalle = 'Venta';
        $out = $service->paraKardexFisico(new Collection([$mov]), 1, null);
        $this->assertCount(1, $out);
    }
}
