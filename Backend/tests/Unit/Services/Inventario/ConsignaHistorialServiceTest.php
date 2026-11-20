<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ConsignaCustodia;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Inventario\Producto;
use App\Services\Inventario\ConsignaHistorialService;
use App\Services\Inventario\ConsignaMovimientoService;
use App\Services\Inventario\ConsignaProductosVentaListadoService;
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

  /**
   * @return ConsignaHistorialService&\Mockery\MockInterface
   */
    private function historialParcial(ConsignaMovimientoService $movimientoService): ConsignaHistorialService
    {
        $listado = Mockery::mock(ConsignaProductosVentaListadoService::class);
        $listado->shouldReceive('resumenFilasCliente')->andReturn([]);

        $partial = Mockery::mock(ConsignaHistorialService::class, [$movimientoService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $partial->shouldReceive('listadoVentasConsigna')->andReturn($listado);

        return $partial;
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

        $partial = $this->historialParcial($movimientoService);
        $partial->shouldReceive('coleccionMovimientos')->andReturn($coleccion);
        $partial->shouldReceive('custodiasActivasCliente')->andReturn(collect());

        $request = Request::create('/', 'GET', ['id_cliente' => 99, 'alcance' => 'ventas']);
        $reporte = $partial->reporte($request, 855, $empresa);

        $this->assertCount(1, $reporte['resumen']);
        $fila = $reporte['resumen'][0];
        $this->assertSame(100.0, $fila['enviadas']);
        $this->assertSame(30.0, $fila['facturadas']);
        $this->assertSame(20.0, $fila['devueltas']);
        $this->assertSame(50.0, $fila['disponible']);
    }

    public function test_resumen_usa_todo_el_historial_aunque_movimientos_respeten_fechas(): void
    {
        $movimientoService = Mockery::mock(ConsignaMovimientoService::class);
        $movimientoService->shouldReceive('enriquecerMovimientoParaApi')->andReturn([
            'tipo_etiqueta' => 'x',
            'documento' => null,
        ]);

        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);
        $empresa->shouldReceive('isLotesActivo')->andReturn(false);

        $producto = new Producto(['nombre' => 'FIREX', 'codigo' => '375530']);

        $remision = new ConsignaMovimiento([
            'id_producto' => 1,
            'id_bodega' => 2,
            'tipo' => ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            'cantidad' => -20,
            'costo_unitario' => 5,
        ]);
        $remision->setRelation('producto', $producto);
        $remision->setRelation('bodega', (object) ['nombre' => 'Central']);

        $ticket = new ConsignaMovimiento([
            'id_producto' => 1,
            'id_bodega' => 2,
            'tipo' => ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE,
            'cantidad' => -15,
            'costo_unitario' => 5,
        ]);
        $ticket->setRelation('producto', $producto);
        $ticket->setRelation('bodega', (object) ['nombre' => 'Central']);

        $dev = new ConsignaMovimiento([
            'id_producto' => 1,
            'id_bodega' => 2,
            'tipo' => ConsignaMovimientoTipoConstants::DEVOLUCION_CLIENTE,
            'cantidad' => 3,
            'costo_unitario' => 5,
        ]);
        $dev->setRelation('producto', $producto);
        $dev->setRelation('bodega', (object) ['nombre' => 'Central']);

        $soloPeriodo = collect([$ticket, $dev]);
        $todo = collect([$remision, $ticket, $dev]);

        $partial = $this->historialParcial($movimientoService);
        $partial->shouldReceive('coleccionMovimientos')
            ->andReturnUsing(function ($request, $idEmpresa, $alcance, $conFecha = true) use ($soloPeriodo, $todo) {
                return $conFecha ? $soloPeriodo : $todo;
            });
        $partial->shouldReceive('custodiasActivasCliente')->andReturn(collect());

        $request = Request::create('/', 'GET', [
            'id_cliente' => 1,
            'alcance' => 'ventas',
            'fecha_ini' => '2026-11-01',
            'fecha_fin' => '2026-11-12',
        ]);
        $reporte = $partial->reporte($request, 855, $empresa);

        $this->assertCount(2, $reporte['movimientos']);
        $this->assertCount(1, $reporte['resumen']);
        $this->assertSame(2.0, $reporte['resumen'][0]['disponible']);
    }

    public function test_resumen_ventas_usa_cantidad_custodia_cuando_existe(): void
    {
        $movimientoService = Mockery::mock(ConsignaMovimientoService::class);
        $movimientoService->shouldReceive('enriquecerMovimientoParaApi')->andReturn([
            'tipo_etiqueta' => 'x',
            'documento' => null,
        ]);

        $empresa = Mockery::mock(Empresa::class);
        $empresa->shouldReceive('isModuloConsignacionesLedgerActivo')->andReturn(true);
        $empresa->shouldReceive('isLotesActivo')->andReturn(true);

        $producto = new Producto(['nombre' => 'FIREX', 'codigo' => '375530']);
        $entrega = new ConsignaMovimiento([
            'id_producto' => 7,
            'id_bodega' => 3,
            'tipo' => ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            'cantidad' => -5,
            'costo_unitario' => 8,
        ]);
        $entrega->setRelation('producto', $producto);
        $entrega->setRelation('bodega', (object) ['nombre' => 'Central']);

        $custodia = new ConsignaCustodia([
            'id_empresa' => 855,
            'id_cliente' => 10,
            'id_producto' => 7,
            'id_bodega' => 3,
            'cantidad' => 2,
        ]);
        $custodia->setRelation('producto', $producto);
        $custodia->setRelation('bodega', (object) ['nombre' => 'Central']);

        $partial = $this->historialParcial($movimientoService);
        $partial->shouldReceive('coleccionMovimientos')->andReturn(collect([$entrega]));
        $partial->shouldReceive('custodiasActivasCliente')->andReturn(collect([$custodia]));

        $request = Request::create('/', 'GET', ['id_cliente' => 10, 'alcance' => 'ventas']);
        $reporte = $partial->reporte($request, 855, $empresa);

        $this->assertCount(1, $reporte['resumen']);
        $this->assertSame(2.0, $reporte['resumen'][0]['disponible']);
        $this->assertSame(5.0, $reporte['resumen'][0]['enviadas']);
    }
}
