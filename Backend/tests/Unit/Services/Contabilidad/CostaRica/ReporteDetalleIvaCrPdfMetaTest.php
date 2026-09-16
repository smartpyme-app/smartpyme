<?php

namespace Tests\Unit\Services\Contabilidad\CostaRica;

use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use PHPUnit\Framework\TestCase;

class ReporteDetalleIvaCrPdfMetaTest extends TestCase
{
    public function test_meta_pdf_ventas_incluye_exo_porc_y_nombre(): void
    {
        $m = ReporteDetalleIvaCrService::metaPdf('ventas');
        $this->assertTrue($m['es_ventas']);
        $this->assertStringContainsString('VENTAS', $m['titulo']);
        $this->assertSame('Reporte_Detalle_IVA.pdf', $m['filename']);
    }

    public function test_meta_pdf_compras_no_incluye_exo_porc(): void
    {
        $m = ReporteDetalleIvaCrService::metaPdf('compras');
        $this->assertFalse($m['es_ventas']);
        $this->assertStringContainsString('COMPRAS', $m['titulo']);
        $this->assertSame('Reporte_Detalle_IVA_Compras.pdf', $m['filename']);
    }
}
