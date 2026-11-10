<?php

namespace Tests\Unit\Services\Inventario;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Services\Inventario\ConsignaDevolucionLedgerService;
use App\Services\Inventario\ConsignaMovimientoService;
use Mockery;
use Tests\TestCase;

class ConsignaDevolucionLedgerServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_no_registra_si_devolucion_sin_venta(): void
    {
        $movimiento = Mockery::mock(ConsignaMovimientoService::class);
        $movimiento->shouldNotReceive('registrar');

        $service = new ConsignaDevolucionLedgerService($movimiento);
        $devolucion = new Devolucion(['id_venta' => null, 'fecha' => now()]);

        $service->despuesEntradaFisicaDevolucionVenta($devolucion, 1, 2.0);
        $this->addToAssertionCount(1);
    }
}
