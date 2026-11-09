<?php

namespace Tests\Unit\MH;

use App\Models\MH\Concerns\BuildsTributosVenta;
use PHPUnit\Framework\TestCase;

class DteV2NotasExportacionTest extends TestCase
{
    private function dte(): object
    {
        return new class {
            use BuildsTributosVenta;

            public function items(array $cuerpo, float $perci, float $rete)
            {
                return $this->agregarRetencionesItemsNota($cuerpo, $perci, $rete);
            }

            public function codigo(float $rete, float $gravada): ?string
            {
                return $this->codigoRetencionMh($rete, $gravada);
            }
        };
    }

    public function test_nota_v4_reparte_percepcion_y_retencion_y_cuadra_con_el_resumen(): void
    {
        $items = $this->dte()->items([
            ['numItem' => 1, 'ventaGravada' => 33.33],
            ['numItem' => 2, 'ventaGravada' => 33.33],
            ['numItem' => 3, 'ventaGravada' => 33.34],
        ], 1.0, 1.0);

        $this->assertSame(1.0, round($items->sum('ivaPerci'), 2));
        $this->assertSame(1.0, round($items->sum('ivaRete'), 2));
        foreach ($items as $item) {
            $this->assertSame(0, $item['noGravado']);
            $this->assertSame(0, $item['totalIva']);
            $this->assertArrayHasKey('ventaGravada', $item);
        }
    }

    public function test_nota_v4_sin_retenciones_deja_ceros(): void
    {
        $items = $this->dte()->items([['numItem' => 1, 'ventaGravada' => 0.0]], 0.0, 0.0);

        $this->assertSame(0.0, $items[0]['ivaPerci']);
        $this->assertSame(0.0, $items[0]['ivaRete']);
    }

    public function test_codigo_retencion_mh_por_proporcion(): void
    {
        $dte = $this->dte();

        $this->assertNull($dte->codigo(0, 100));
        $this->assertSame('22', $dte->codigo(1.0, 100));
        $this->assertSame('C4', $dte->codigo(13.0, 100));
        $this->assertSame('C9', $dte->codigo(5.0, 100));
    }
}
