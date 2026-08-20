<?php

namespace Tests\Unit\Support;

use App\Support\TotalesPorMonedaLibroIva;
use PHPUnit\Framework\TestCase;

class TotalesPorMonedaLibroIvaTest extends TestCase
{
    public function test_agrupa_nativo_y_equivalente_por_moneda(): void
    {
        $ventas = [
            (object) ['currency_code' => 'CRC', 'total' => 1000, 'equivalent_total' => 1000, 'exchange_rate' => 1],
            (object) ['currency_code' => 'USD', 'total' => 10, 'equivalent_total' => 5000, 'exchange_rate' => 500],
            (object) ['currency_code' => 'USD', 'total' => 5, 'equivalent_total' => 2500, 'exchange_rate' => 500],
        ];

        $rows = TotalesPorMonedaLibroIva::agrupar($ventas);

        $this->assertSame([
            ['moneda' => 'CRC', 'documentos' => 1, 'total_nativo' => 1000.0, 'total_equivalente' => 1000.0],
            ['moneda' => 'USD', 'documentos' => 2, 'total_nativo' => 15.0, 'total_equivalente' => 7500.0],
        ], $rows);
    }

    public function test_devoluciones_restan_sin_contar_documento(): void
    {
        $ventas = [
            (object) ['currency_code' => 'USD', 'total' => 100, 'equivalent_total' => 50000, 'exchange_rate' => 500],
        ];
        $devoluciones = [
            (object) ['currency_code' => 'USD', 'total' => 20, 'equivalent_total' => 10000, 'exchange_rate' => 500],
        ];

        $rows = TotalesPorMonedaLibroIva::agrupar($ventas, $devoluciones);

        $this->assertSame([
            ['moneda' => 'USD', 'documentos' => 1, 'total_nativo' => 80.0, 'total_equivalente' => 40000.0],
        ], $rows);
    }

    public function test_sin_equivalent_usa_nativo_por_tc(): void
    {
        $ventas = [
            (object) ['currency_code' => 'USD', 'total' => 2, 'equivalent_total' => null, 'exchange_rate' => 500],
        ];

        $rows = TotalesPorMonedaLibroIva::agrupar($ventas);

        $this->assertSame(2.0, $rows[0]['total_nativo']);
        $this->assertSame(1000.0, $rows[0]['total_equivalente']);
    }
}
