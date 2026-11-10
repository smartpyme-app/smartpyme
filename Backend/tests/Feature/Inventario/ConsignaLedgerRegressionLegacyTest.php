<?php

namespace Tests\Feature\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Inventario\ConsignaMovimiento;
use App\Services\Inventario\ConsignaMovimientoService;
use Tests\TestCase;

/**
 * ponytail: sin BD de empresa piloto; valida que registrar no escribe si ledger inactivo.
 */
class ConsignaLedgerRegressionLegacyTest extends TestCase
{
    public function test_registrar_no_inserta_si_ledger_inactivo(): void
    {
        $service = $this->createPartialMock(ConsignaMovimientoService::class, ['isLedgerActivo']);
        $service->method('isLedgerActivo')->willReturn(false);

        $before = ConsignaMovimiento::query()->count();

        $result = $service->registrar(1, [
            'tipo' => ConsignaMovimientoTipoConstants::ENTRADA_PROVEEDOR,
            'cantidad' => 5,
            'id_producto' => 1,
            'id_bodega' => 1,
        ]);

        $this->assertNull($result);
        $this->assertSame($before, ConsignaMovimiento::query()->count());
    }
}
