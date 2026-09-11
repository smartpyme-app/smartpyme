<?php

namespace Tests\Unit\Exports;

use App\Exports\VentasPorClienteExport;
use PHPUnit\Framework\TestCase;

class VentasPorClienteExportTest extends TestCase
{
    public function test_fecha_cobro_pendiente_es_null(): void
    {
        $this->assertNull(
            VentasPorClienteExport::fechaCobroParaReporte('Pendiente', '2026-01-10', '2026-01-20')
        );
    }

    public function test_fecha_cobro_pagada_usa_ultimo_abono(): void
    {
        $this->assertSame(
            '2026-01-20',
            VentasPorClienteExport::fechaCobroParaReporte('Pagada', '2026-01-10', '2026-01-20')
        );
    }

    public function test_fecha_cobro_pagada_sin_abono_usa_fecha_venta(): void
    {
        $this->assertSame(
            '2026-01-10',
            VentasPorClienteExport::fechaCobroParaReporte('Pagada', '2026-01-10', null)
        );
    }
}
