<?php

namespace Tests\Unit\Exports;

use App\Exports\Inventario\InventarioVentasMensualAnalisisExport;
use Tests\TestCase;

class InventarioVentasMensualAnalisisExportTest extends TestCase
{
    public function test_valor_kardex_resta_descuento_y_utilidad_es_la_diferencia(): void
    {
        [$valor, $costo, $utilidad, $precio, $costoUnidad] = InventarioVentasMensualAnalisisExport::metricasDesdeKardex(
            100.0,
            10.0,
            4.0,
            50.0,
            3.5
        );

        $this->assertSame(90.0, $valor);
        $this->assertSame(50.0, $costo);
        $this->assertSame(40.0, $utilidad);
        $this->assertSame(22.5, $precio);
        $this->assertSame(3.5, $costoUnidad);
    }

    public function test_sin_unidades_ni_retaceo_deja_promedios_vacios(): void
    {
        [$valor, $costo, $utilidad, $precio, $costoUnidad] = InventarioVentasMensualAnalisisExport::metricasDesdeKardex(
            0.0,
            0.0,
            0.0,
            0.0,
            null
        );

        $this->assertSame(0.0, $valor);
        $this->assertSame(0.0, $costo);
        $this->assertSame(0.0, $utilidad);
        $this->assertSame('', $precio);
        $this->assertSame('', $costoUnidad);
    }
}
