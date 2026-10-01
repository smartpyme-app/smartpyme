<?php

namespace Tests\Unit\Exports\Contabilidad\CostaRica;

use App\Exports\Contabilidad\CostaRica\ReporteDetalleIvaComprasExport;
use App\Exports\Contabilidad\CostaRica\ReporteDetalleIvaVentasExport;
use PHPUnit\Framework\TestCase;

class ReporteDetalleIvaExportLayoutTest extends TestCase
{
    public function test_ventas_abre_con_periodo_grupos_y_columnas(): void
    {
        $rows = (new ReporteDetalleIvaVentasExport([], '2026-03-01', '2026-03-31'))->collection()->values();

        $this->assertSame('PERIODO: 2026-03-01 al 2026-03-31', $rows[0][0]);
        $this->assertSame('SUBTOTALES', $rows[1][15]);
        $this->assertSame('DETALLES IVA', $rows[1][23]);
        $this->assertSame('NombreEmisor', $rows[2][0]);
        $this->assertSame('ExoPorc', $rows[2][8]);
        $this->assertSame('IVADevuelto', $rows[2][28]);
    }

    public function test_compras_no_tiene_exo_porc(): void
    {
        $rows = (new ReporteDetalleIvaComprasExport([], '2026-03-01', '2026-03-31'))->collection()->values();

        $this->assertSame('PERIODO: 2026-03-01 al 2026-03-31', $rows[0][0]);
        $this->assertSame('SUBTOTALES', $rows[1][14]);
        $this->assertSame('DETALLES IVA', $rows[1][22]);
        $this->assertSame('Exoneracion', $rows[2][7]);
        $this->assertSame('Retenciones', $rows[2][8]);
        $this->assertSame('IVADevuelto', $rows[2][27]);
    }

    public function test_ventas_cierra_con_resumen_por_tarifa(): void
    {
        $rows = (new ReporteDetalleIvaVentasExport([
            ['subtotal_13' => 100, 'iva_13' => 13, 'subtotal_exento' => 40, 'subtotal_exonerado' => 10],
        ], '2026-03-01', '2026-03-31'))->collection()->values();

        $iva13 = $rows->first(fn (array $r) => $r[0] === 'IVA 13%');
        $exento = $rows->first(fn (array $r) => $r[0] === 'Exento (IVA 0%)');

        $this->assertNotNull($rows->first(fn (array $r) => $r[0] === 'Resumen por tipo de impuesto'));
        $this->assertEquals(100.0, $iva13[1]);
        $this->assertEquals(13.0, $iva13[2]);
        $this->assertEquals(40.0, $exento[1]);
        $this->assertEquals(0.0, $exento[2]);
        $this->assertSame('Total', $rows->last()[0]);
        $this->assertEquals(150.0, $rows->last()[1]);
        $this->assertEquals(13.0, $rows->last()[2]);
    }

    public function test_compras_cierra_con_resumen_por_tarifa(): void
    {
        $rows = (new ReporteDetalleIvaComprasExport([
            ['subtotal_8' => 50, 'iva_8' => 4],
        ], '2026-03-01', '2026-03-31'))->collection()->values();

        $iva8 = $rows->first(fn (array $r) => $r[0] === 'IVA 8%');

        $this->assertEquals(50.0, $iva8[1]);
        $this->assertEquals(4.0, $iva8[2]);
        $this->assertSame('Total', $rows->last()[0]);
    }
}
