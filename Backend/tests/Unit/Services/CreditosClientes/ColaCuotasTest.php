<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\ColaCuotas;
use PHPUnit\Framework\TestCase;

class ColaCuotasTest extends TestCase
{
    public function test_hoy_o_pasado_sin_venta_es_vencida(): void
    {
        $this->assertSame('vencida', ColaCuotas::estadoCola(null, '2026-08-24', '2026-08-24'));
        $this->assertSame('vencida', ColaCuotas::estadoCola(null, '2026-08-20', '2026-08-24'));
    }

    public function test_dentro_de_7_dias_es_por_facturar(): void
    {
        $this->assertSame('por_facturar', ColaCuotas::estadoCola(null, '2026-08-31', '2026-08-24'));
        $this->assertSame('por_facturar', ColaCuotas::estadoCola(null, '2026-08-25', '2026-08-24'));
    }

    public function test_mas_de_7_dias_no_entra_a_la_cola(): void
    {
        $this->assertNull(ColaCuotas::estadoCola(null, '2026-09-01', '2026-08-24'));
    }

    public function test_con_venta_desaparece_de_la_cola(): void
    {
        $this->assertNull(ColaCuotas::estadoCola(99, '2026-08-20', '2026-08-24'));
        $this->assertNull(ColaCuotas::estadoCola(99, '2026-08-24', '2026-08-24'));
    }

    public function test_no_transmite_dte(): void
    {
        $src = file_get_contents((new \ReflectionClass(ColaCuotas::class))->getFileName());
        $this->assertStringNotContainsString('MH', $src);
        $this->assertStringNotContainsString('schedule', $src);
        $this->assertStringNotContainsString('Artisan', $src);
    }
}
