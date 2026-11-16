<?php

namespace Tests\Unit\Services\Contabilidad\CostaRica;

use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\Contabilidad\LibroVentasResumenContableService;
use PHPUnit\Framework\TestCase;

class ReporteDetalleIvaCrResumenFiscalTest extends TestCase
{
    public function test_resumen_fiscal_cuadra_con_los_libros(): void
    {
        $ventas = [
            ['tipo_doc' => 'Factura electrónica', 'subtotal_13' => 1000, 'iva_13' => 130, 'iva_devuelto' => 10],
            ['tipo_doc' => 'Nota de crédito', 'subtotal_13' => -100, 'iva_13' => -13],
            ['tipo_doc' => 'Factura electrónica', 'subtotal_exento' => 500],
        ];
        $compras = [
            ['origen' => 'compra', 'subtotal_13' => 200, 'iva_13' => 26, 'subtotal_1' => 100, 'iva_1' => 1],
            ['origen' => 'gasto', 'subtotal_13' => 100, 'iva_13' => 13],
            ['origen' => 'devolucion', 'subtotal_13' => -50, 'iva_13' => -6.5],
        ];

        $r = ReporteDetalleIvaCrService::resumenFiscal($ventas, $compras);

        $this->assertEqualsWithDelta(107.0, $r['iva']['iva_en_contra'], 0.001);
        $this->assertEqualsWithDelta(27.0, $r['iva']['credito_fiscal_compras'], 0.001);
        $this->assertEqualsWithDelta(13.0, $r['iva']['credito_fiscal_gastos'], 0.001);
        $this->assertEqualsWithDelta(-6.5, $r['iva']['credito_fiscal_devoluciones_compras'], 0.001);
        $this->assertEqualsWithDelta(33.5, $r['iva']['iva_a_favor'], 0.001);
        $this->assertEqualsWithDelta(73.5, $r['iva']['diferencia_estimada_pago_iva'], 0.001);

        $this->assertEqualsWithDelta($r['iva']['iva_en_contra'], array_sum(array_column($r['ventas_por_impuesto'], 'iva')), 0.001);
        $this->assertEqualsWithDelta($r['iva']['iva_a_favor'], array_sum(array_column($r['compras_por_impuesto'], 'iva')), 0.001);

        $libro = new ReporteDetalleIvaCrService();
        foreach ([[$ventas, 'ventas_por_impuesto'], [$compras, 'compras_por_impuesto']] as [$filas, $clave]) {
            $porEtiqueta = array_column($r[$clave], null, 'etiqueta');
            foreach (ReporteDetalleIvaCrService::resumenTarifas($libro->totales($filas)) as $t) {
                $fila = $porEtiqueta[$t['etiqueta']] ?? ['base' => 0, 'iva' => 0];
                $this->assertEqualsWithDelta($t['base'], $fila['base'], 0.001, $clave.' '.$t['etiqueta']);
                $this->assertEqualsWithDelta($t['iva'], $fila['iva'], 0.001, $clave.' '.$t['etiqueta']);
            }
        }

        $iva13 = array_column($r['ventas_por_impuesto'], null, 'etiqueta')['IVA 13%'];
        $this->assertSame('13%', $iva13['tarifa']);
        $this->assertSame(2, $iva13['documentos']);

        $contable = (new \ReflectionClass(LibroVentasResumenContableService::class))
            ->newInstanceWithoutConstructor()
            ->buildDesdeLibroCr($ventas);
        $totales = array_column($contable['filas'], null, 'descripcion')['TOTALES'];
        $this->assertEqualsWithDelta($r['iva']['iva_en_contra'], $totales['debito_fiscal'], 0.001);
        $this->assertEqualsWithDelta(1400.0, $totales['valor_neto'], 0.001);
    }
}
