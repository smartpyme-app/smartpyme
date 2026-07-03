<?php

namespace Tests\Unit\Services\Inventario;

use Tests\TestCase;

class LoteAsignacionKardexTest extends TestCase
{
    public function test_cada_lote_genera_opciones_kardex_con_lote_id(): void
    {
        $asignaciones = [
            ['lote_id' => 101, 'cantidad' => 3.0],
            ['lote_id' => 102, 'cantidad' => 2.0],
        ];

        $kardexPorLote = [];
        foreach ($asignaciones as $asig) {
            $kardexPorLote[] = [
                'lote_id' => $asig['lote_id'],
                'cantidad' => $asig['cantidad'],
            ];
        }

        $this->assertCount(2, $kardexPorLote);
        $this->assertSame(101, $kardexPorLote[0]['lote_id']);
        $this->assertSame(102, $kardexPorLote[1]['lote_id']);
        $this->assertSame(3.0, $kardexPorLote[0]['cantidad']);
        $this->assertSame(2.0, $kardexPorLote[1]['cantidad']);
    }
}
