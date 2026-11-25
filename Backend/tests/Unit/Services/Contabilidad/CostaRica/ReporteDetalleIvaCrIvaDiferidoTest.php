<?php

namespace Tests\Unit\Services\Contabilidad\CostaRica;

use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use PHPUnit\Framework\TestCase;

final class ReporteDetalleIvaCrIvaDiferidoTest extends TestCase
{
    public function test_condiciones_diferidas_08_y_10(): void
    {
        self::assertTrue(ReporteDetalleIvaCrService::esCondicionIvaDiferido('08'));
        self::assertTrue(ReporteDetalleIvaCrService::esCondicionIvaDiferido('10'));
        self::assertFalse(ReporteDetalleIvaCrService::esCondicionIvaDiferido('02'));
        self::assertFalse(ReporteDetalleIvaCrService::esCondicionIvaDiferido('01'));
        self::assertFalse(ReporteDetalleIvaCrService::esCondicionIvaDiferido(null));
        self::assertFalse(ReporteDetalleIvaCrService::esCondicionIvaDiferido(''));
    }

    public function test_proporcion_abono_sobre_venta(): void
    {
        self::assertSame(0.5, ReporteDetalleIvaCrService::proporcionAbonoSobreVenta(500.0, 1000.0));
        self::assertSame(1.0, ReporteDetalleIvaCrService::proporcionAbonoSobreVenta(1500.0, 1000.0));
        self::assertSame(0.0, ReporteDetalleIvaCrService::proporcionAbonoSobreVenta(100.0, 0.0));
    }
}
