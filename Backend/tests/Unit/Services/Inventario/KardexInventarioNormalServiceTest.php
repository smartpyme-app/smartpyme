<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\KardexDetalleConstants;
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
        $m2->referencia = null;
        $m2->entrada_cantidad = 0;
        $m2->salida_cantidad = 10;
        $m2->costo_unitario = 1;

        $service = new KardexInventarioNormalService();
        $out = $service->paraKardexFisico(collect([$m1, $m2]), 999999, $empresa, 90.0);

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

    public function test_muestra_remision_consigna_con_ledger(): void
    {
        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);

        $mov = new Kardex();
        $mov->id = 3;
        $mov->fecha = '2026-01-03';
        $mov->detalle = 'Remisión consigna';
        $mov->referencia = '500';
        $mov->entrada_cantidad = 0;
        $mov->salida_cantidad = 15;
        $mov->costo_unitario = 2;

        $service = new KardexInventarioNormalService();
        $out = $service->paraKardexFisico(collect([$mov]), 999999, $empresa);

        $this->assertCount(1, $out);
        $this->assertSame('Remisión consigna', $out->first()->detalle);
    }

    public function test_liquidacion_cliente_muestra_salida_sin_mover_existencias(): void
    {
        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);

        $remision = new Kardex();
        $remision->id = 20;
        $remision->fecha = '2026-01-04';
        $remision->detalle = 'Remisión consigna';
        $remision->entrada_cantidad = 0;
        $remision->salida_cantidad = 30;
        $remision->costo_unitario = 1;

        $liq = new Kardex();
        $liq->id = 21;
        $liq->fecha = '2026-01-05';
        $liq->detalle = KardexDetalleConstants::LIQUIDACION_CONSIGNA_CLIENTE;
        $liq->entrada_cantidad = 0;
        $liq->salida_cantidad = 15;
        $liq->costo_unitario = 1;

        $service = new KardexInventarioNormalService();
        $out = $service->paraKardexFisico(collect([$remision, $liq]), 1, $empresa, 70.0);

        $this->assertEquals(70.0, (float) $out->firstWhere('id', 20)->total_cantidad);
        $this->assertEquals(70.0, (float) $out->firstWhere('id', 21)->total_cantidad);
        $this->assertEquals(15.0, (float) $out->firstWhere('id', 21)->salida_cantidad);
    }

    public function test_saldo_ancla_stock_sin_historial_previo(): void
    {
        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);

        $venta = new Kardex();
        $venta->id = 10;
        $venta->fecha = '2026-01-05';
        $venta->detalle = 'Venta';
        $venta->entrada_cantidad = 0;
        $venta->salida_cantidad = 10;
        $venta->costo_unitario = 1;

        $service = new KardexInventarioNormalService();
        $out = $service->paraKardexFisico(collect([$venta]), 1, $empresa, 90.0);

        $this->assertEquals(90.0, (float) $out->first()->total_cantidad);
    }
}
