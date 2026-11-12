<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Inventario\Producto;
use App\Services\Inventario\ConsignaHistorialService;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ConsignaHistorialServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resumen_cliente_suma_enviadas_facturadas_y_devueltas(): void
    {
        $movimientoService = Mockery::mock(ConsignaMovimientoService::class);
        $movimientoService->shouldReceive('enriquecerMovimientoParaApi')->andReturnUsing(
            fn (ConsignaMovimiento $m) => [
                'tipo_etiqueta' => $m->tipo,
                'documento' => null,
            ]
        );

        $service = new ConsignaHistorialService($movimientoService);
        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);
        $empresa->shouldReceive('isLotesActivo')->andReturn(false);

        $producto = new Producto(['nombre' => 'FIREX', 'codigo' => '375530']);

        $entrega = new ConsignaMovimiento([
            'id_producto' => 1,
            'id_bodega' => 2,
            'tipo' => ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            'cantidad' => -100,
            'costo_unitario' => 10,
        ]);
        $entrega->setRelation('producto', $producto);
        $entrega->setRelation('bodega', (object) ['nombre' => 'Central']);

        $factura = new ConsignaMovimiento([
            'id_producto' => 1,
            'id_bodega' => 2,
            'tipo' => ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE,
            'cantidad' => -30,
            'costo_unitario' => 10,
        ]);
        $factura->setRelation('producto', $producto);
        $factura->setRelation('bodega', (object) ['nombre' => 'Central']);

        $devolucion = new ConsignaMovimiento([
            'id_producto' => 1,
            'id_bodega' => 2,
            'tipo' => ConsignaMovimientoTipoConstants::DEVOLUCION_CLIENTE,
            'cantidad' => 20,
            'costo_unitario' => 10,
        ]);
        $devolucion->setRelation('producto', $producto);
        $devolucion->setRelation('bodega', (object) ['nombre' => 'Central']);

        $coleccion = collect([$entrega, $factura, $devolucion]);

        $partial = Mockery::mock(ConsignaHistorialService::class, [$movimientoService])->makePartial();
        $partial->shouldReceive('coleccionMovimientos')->andReturn($coleccion);

        $request = Request::create('/', 'GET', ['id_cliente' => 99, 'alcance' => 'ventas']);
        $reporte = $partial->reporte($request, 855, $empresa);

        $this->assertCount(1, $reporte['resumen']);
        $fila = $reporte['resumen'][0];
        $this->assertSame(100.0, $fila['enviadas']);
        $this->assertSame(30.0, $fila['facturadas']);
        $this->assertSame(20.0, $fila['devueltas']);
        $this->assertSame(50.0, $fila['disponible']);
    }
}
