<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Constants\OrigenStockVentaConstants;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;
use App\Services\Inventario\ConsignaMovimientoService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ConsignaMovimientoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_registrar_retorna_null_si_ledger_inactivo(): void
    {
        $service = Mockery::mock(ConsignaMovimientoService::class)->makePartial();
        $service->shouldReceive('isLedgerActivo')->with(1)->andReturn(false);

        $result = $service->registrar(1, [
            'tipo' => ConsignaMovimientoTipoConstants::ENTRADA_PROVEEDOR,
            'cantidad' => 1,
            'id_producto' => 1,
            'id_bodega' => 1,
        ]);

        $this->assertNull($result);
    }

    public function test_registrar_rechaza_tipo_invalido(): void
    {
        $service = Mockery::mock(ConsignaMovimientoService::class)->makePartial();
        $service->shouldReceive('isLedgerActivo')->andReturn(true);

        $this->expectException(ValidationException::class);

        $service->registrar(1, [
            'tipo' => 'invalido',
            'cantidad' => 1,
            'id_producto' => 1,
            'id_bodega' => 1,
        ]);
    }

    public function test_desde_venta_detalle_clasifica_venta_regular_consigna_proveedor(): void
    {
        $service = Mockery::mock(ConsignaMovimientoService::class)->makePartial();
        $service->shouldReceive('registrar')
            ->once()
            ->with(1, Mockery::on(function (array $payload) {
                return $payload['tipo'] === ConsignaMovimientoTipoConstants::VENTA_REGULAR_CONSIGNA_PROVEEDOR
                    && $payload['cantidad'] === -3.0;
            }))
            ->andReturn(null);

        $venta = new Venta();
        $venta->id = 10;
        $venta->id_empresa = 1;
        $venta->id_bodega = 2;
        $venta->estado = 'Pagada';
        $venta->fecha = '2026-01-01';

        $detalle = new DetalleVenta();
        $detalle->id = 5;
        $detalle->id_producto = 9;
        $detalle->cantidad = 3;
        $detalle->origen_stock = OrigenStockVentaConstants::CONSIGNA_COMPRA;

        $service->desdeVentaDetalle($venta, $detalle);
        $this->addToAssertionCount(1);
    }

    public function test_desde_venta_stock_propio_no_registra_en_ledger(): void
    {
        $service = Mockery::mock(ConsignaMovimientoService::class)->makePartial();
        $service->shouldReceive('registrar')->never();

        $venta = new Venta();
        $venta->id = 10;
        $venta->id_empresa = 1;
        $venta->estado = 'Pagada';

        $detalle = new DetalleVenta();
        $detalle->origen_stock = OrigenStockVentaConstants::NORMAL;

        $this->assertNull($service->desdeVentaDetalle($venta, $detalle));
    }

    public function test_desde_venta_consigna_stock_propio_registra_entrega_custodia(): void
    {
        $service = Mockery::mock(ConsignaMovimientoService::class)->makePartial();
        $service->shouldReceive('registrar')
            ->once()
            ->with(1, Mockery::on(function (array $payload) {
                return $payload['tipo'] === ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO
                    && $payload['cantidad'] === -4.0;
            }))
            ->andReturn(null);

        $venta = new Venta();
        $venta->id = 11;
        $venta->id_empresa = 1;
        $venta->id_bodega = 2;
        $venta->id_cliente = 99;
        $venta->estado = 'Consigna';
        $venta->fecha = '2026-01-01';

        $detalle = new DetalleVenta();
        $detalle->id = 7;
        $detalle->id_producto = 9;
        $detalle->cantidad = 4;
        $detalle->origen_stock = OrigenStockVentaConstants::NORMAL;

        $service->desdeVentaDetalle($venta, $detalle);
        $this->addToAssertionCount(1);
    }

    public function test_entrada_proveedor_ignora_compra_no_consigna(): void
    {
        $service = new ConsignaMovimientoService();

        $compra = new Compra();
        $compra->estado = 'Pagada';

        $detalle = new DetalleCompra();

        $this->assertNull($service->entradaProveedorDesdeCompra($compra, $detalle));
    }
}
