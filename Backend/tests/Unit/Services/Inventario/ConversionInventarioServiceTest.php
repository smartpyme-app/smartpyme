<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\ConversionInventarioService;
use PHPUnit\Framework\TestCase;

/**
 * SPT-370: el costo de inventario debe usar el neto pagado (cantidad × costo − descuento).
 */
class ConversionInventarioServiceTest extends TestCase
{
    public function test_costo_total_fila_resta_el_descuento(): void
    {
        // 10 × $10 − $20 = $80 pagados
        $this->assertSame(80.0, ConversionInventarioService::calcularCostoTotalFila(10, 10, 20));
    }

    public function test_costo_total_fila_sin_descuento_es_cantidad_por_costo(): void
    {
        $this->assertSame(100.0, ConversionInventarioService::calcularCostoTotalFila(10, 10, 0));
        $this->assertSame(100.0, ConversionInventarioService::calcularCostoTotalFila(10, 10));
    }

    public function test_costo_total_fila_no_queda_negativo(): void
    {
        $this->assertSame(0.0, ConversionInventarioService::calcularCostoTotalFila(10, 10, 200));
    }

    public function test_costo_unitario_neto_usa_precio_total_con_descuento_no_el_unitario(): void
    {
        // Compra 10 unidades a $10 con $20 de descuento → costo real $8, no $10
        $costoUnitario = ConversionInventarioService::calcularCostoUnitarioNetoBase(10, 10, 20);

        $this->assertEqualsWithDelta(8.0, $costoUnitario, 0.000001);
    }

    public function test_costo_unitario_neto_con_presentacion_reparte_el_neto_en_unidades_base(): void
    {
        // 2 cajas × factor 30 = 60 unidades base; 2 × $120 − $24 = $216 → $3.60 c/u base
        $costoUnitario = ConversionInventarioService::calcularCostoUnitarioNetoBase(2, 120, 24, 30);

        $this->assertEqualsWithDelta(3.6, $costoUnitario, 0.000001);
    }

    public function test_costo_unitario_neto_sin_descuento_coincide_con_costo_unitario_base(): void
    {
        $sinDescuento = ConversionInventarioService::calcularCostoUnitarioNetoBase(5, 12, 0, 1);
        $legacy = ConversionInventarioService::calcularCostoUnitarioBase(5 * 12, 5);

        $this->assertEqualsWithDelta($legacy, $sinDescuento, 0.000001);
    }
}
