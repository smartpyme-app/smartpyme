<?php

namespace Tests\Unit\MH;

use App\Models\MH\Concerns\BuildsTributosVenta;
use PHPUnit\Framework\TestCase;

class ResumenVentasDesdeCuerpoDocumentoTest extends TestCase
{
    public function test_total_gravada_es_la_suma_de_venta_gravada_del_cuerpo(): void
    {
        $dte = new class {
            use BuildsTributosVenta;

            public function bases(array $cuerpo): array
            {
                return $this->resumenVentasDesdeCuerpoDocumento($cuerpo);
            }
        };

        $bases = $dte->bases([
            ['ventaGravada' => 16.92, 'ventaExenta' => 0, 'ventaNoSuj' => 0],
            ['ventaGravada' => 16.92, 'ventaExenta' => 0, 'ventaNoSuj' => 0],
            ['ventaGravada' => 100.0, 'ventaExenta' => 5.5, 'ventaNoSuj' => 1.25],
        ]);

        $this->assertSame(133.84, $bases['totalGravada']);
        $this->assertSame(5.5, $bases['totalExenta']);
        $this->assertSame(1.25, $bases['totalNoSuj']);
        $this->assertSame(140.59, $bases['subTotalVentas']);
    }
}
