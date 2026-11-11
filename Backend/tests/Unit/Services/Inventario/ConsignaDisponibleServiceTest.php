<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\ConsignaDisponibleService;
use Tests\TestCase;

class ConsignaDisponibleServiceTest extends TestCase
{
    public function test_tras_vender_4_y_pagar_4_disponible_queda_en_2(): void
    {
        // entrada 6, vendidas 4 (liquidado no restaura pool)
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 4, 4, 6);
        $this->assertEquals(2.0, $disponible);
    }

    public function test_vende_4_sin_pagar_disponible_es_6(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(10, 4, 0, 6);
        $this->assertEquals(6.0, $disponible);
    }

    public function test_paga_4_sin_ventas_disponible_es_6(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 0, 4, 10);
        $this->assertEquals(6.0, $disponible);
    }

    public function test_50_vendidas_11_liquidadas_11_disponible_39(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(50, 11, 11, 124);
        $this->assertEquals(39.0, $disponible);
    }

    public function test_tope_por_stock_fisico(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(10, 0, 0, 3);
        $this->assertEquals(3.0, $disponible);
    }

    public function test_entrada_50_vende_1_liquida_1_fisico_49_disponible_49(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(50, 1, 1, 49);
        $this->assertEquals(49.0, $disponible);
    }

    public function test_assert_consistente_con_ledger_no_op_sin_debug(): void
    {
        $prev = config('app.debug');
        config(['app.debug' => false]);

        $service = new ConsignaDisponibleService();
        $this->assertTrue($service->assertConsistenteConLedger(1, 1, 1));

        config(['app.debug' => $prev]);
    }
}
