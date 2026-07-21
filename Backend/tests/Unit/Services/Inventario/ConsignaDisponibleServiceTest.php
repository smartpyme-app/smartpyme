<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\ConsignaDisponibleService;
use PHPUnit\Framework\TestCase;

class ConsignaDisponibleServiceTest extends TestCase
{
    public function test_tras_vender_4_y_pagar_4_de_10_disponible_es_6(): void
    {
        // entrada abierta 6, ventas 4, liquidado 4, físico 6
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 4, 4, 6);
        $this->assertEquals(6.0, $disponible);
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

    public function test_formula_legacy_doble_resta_fallaria_aqui(): void
    {
        // Si alguien restara ventas sin liquidado: max(0, 6-4)=2 — no debe pasar
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 4, 4, 6);
        $this->assertNotEquals(2.0, $disponible);
    }

    public function test_tope_por_stock_fisico(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(10, 0, 0, 3);
        $this->assertEquals(3.0, $disponible);
    }
}
