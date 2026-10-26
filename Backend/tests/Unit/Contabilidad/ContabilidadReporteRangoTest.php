<?php

namespace Tests\Unit\Contabilidad;

use App\Support\ContabilidadReporteRango;
use PHPUnit\Framework\TestCase;

class ContabilidadReporteRangoTest extends TestCase
{
    public function test_pdf_permitido_hasta_31_dias(): void
    {
        $this->assertTrue(ContabilidadReporteRango::pdfPermitido('2025-01-01', '2025-01-31'));
        $this->assertSame(31, ContabilidadReporteRango::diasEnRango('2025-01-01', '2025-01-31'));
    }

    public function test_pdf_no_permitido_mas_de_31_dias(): void
    {
        $this->assertFalse(ContabilidadReporteRango::pdfPermitido('2025-01-01', '2025-02-01'));
    }
}
