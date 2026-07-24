<?php

namespace Tests\Unit\Exports;

use App\Constants\OrigenStockVentaConstants;
use App\Exports\VentasDetallesExport;
use PHPUnit\Framework\TestCase;

class VentasDetallesCanalConsignaTest extends TestCase
{
    public function test_devuelve_consigna_si_alguna_linea_tiene_origen_consigna_compra(): void
    {
        $venta = new class {
            public $canal;
            public $detalles;
            public function relationLoaded($r) { return true; }
        };
        $venta->canal = (object) ['nombre' => 'Tienda'];
        $venta->detalles = collect([
            (object) ['origen_stock' => OrigenStockVentaConstants::NORMAL],
            (object) ['origen_stock' => OrigenStockVentaConstants::CONSIGNA_COMPRA],
        ]);

        $this->assertSame('Consigna', VentasDetallesExport::nombreCanalParaExport($venta));
    }

    public function test_devuelve_canal_normal_si_no_hay_origen_consigna(): void
    {
        $venta = new class {
            public $canal;
            public $detalles;
            public function relationLoaded($r) { return true; }
        };
        $venta->canal = (object) ['nombre' => 'Tienda'];
        $venta->detalles = collect([
            (object) ['origen_stock' => OrigenStockVentaConstants::NORMAL],
        ]);

        $this->assertSame('Tienda', VentasDetallesExport::nombreCanalParaExport($venta));
    }
}
