<?php

namespace Tests\Unit\Exports;

use App\Exports\Inventario\InventarioAFechaExport;
use App\Exports\ProductosExport;
use App\Exports\VentasExport;
use PHPUnit\Framework\TestCase;

class Sp2133ReportesMejorasTest extends TestCase
{
    public function test_motivo_anulacion_solo_cuando_la_venta_esta_anulada(): void
    {
        $anulada = (object) [
            'estado' => 'Anulada',
            'motivo_anulacion' => 'Rescindir de la operación realizada',
        ];
        $pagada = (object) [
            'estado' => 'Pagada',
            'motivo_anulacion' => 'Rescindir de la operación realizada',
        ];

        $this->assertSame(
            'Rescindir de la operación realizada',
            VentasExport::motivoAnulacionParaExport($anulada)
        );
        $this->assertSame('', VentasExport::motivoAnulacionParaExport($pagada));
    }

    public function test_tiene_fotografia_segun_imagenes_del_producto(): void
    {
        $conFoto = (object) ['imagenes_count' => 2];
        $sinFoto = (object) ['imagenes_count' => 0];

        $this->assertSame('Sí', ProductosExport::tieneFotografiaParaExport($conFoto));
        $this->assertSame('No', ProductosExport::tieneFotografiaParaExport($sinFoto));
        $this->assertSame('Sí', InventarioAFechaExport::tieneFotografiaParaExport($conFoto));
        $this->assertSame('No', InventarioAFechaExport::tieneFotografiaParaExport($sinFoto));
    }
}
