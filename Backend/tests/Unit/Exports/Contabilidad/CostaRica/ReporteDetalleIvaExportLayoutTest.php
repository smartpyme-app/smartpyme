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
}
