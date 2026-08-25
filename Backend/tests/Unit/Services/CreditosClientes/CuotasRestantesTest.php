<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\CuotasRestantes;
use PHPUnit\Framework\TestCase;

class CuotasRestantesTest extends TestCase
{
    public function test_omite_la_cuota_1_ya_facturada(): void
    {
        $restantes = CuotasRestantes::dePlan([
            ['numero' => 1, 'monto' => 30, 'fecha_vencimiento' => '2026-01-15'],
            ['numero' => 2, 'monto' => 30, 'fecha_vencimiento' => '2026-02-15'],
            ['numero' => 3, 'monto' => 30, 'fecha_vencimiento' => '2026-03-15'],
        ]);

        $this->assertSame([2, 3], array_column($restantes, 'numero'));
        $this->assertSame(['2026-02-15', '2026-03-15'], array_column($restantes, 'fecha_vencimiento'));
    }
}
