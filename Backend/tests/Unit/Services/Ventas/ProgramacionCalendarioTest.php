<?php

namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\ProgramacionCalendario;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ProgramacionCalendarioTest extends TestCase
{
    public function test_periodo_es_diario(): void
    {
        $this->assertSame('2026-03-11', ProgramacionCalendario::periodo('mensual', '2026-03-11 08:00:00'));
        $this->assertSame('2026-03-11', ProgramacionCalendario::periodo('anual', '2026-03-11 08:00:00'));
    }

    public function test_dia_31_en_febrero_usa_ultimo_dia(): void
    {
        $desde = Carbon::parse('2026-01-31 08:00:00', 'America/El_Salvador');
        $next = ProgramacionCalendario::siguienteTrasEjecucion('mensual', 31, '08:00:00', $desde);
        $this->assertSame('2026-02-28 08:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_calcular_proxima_hoy_si_aun_no_paso_la_hora(): void
    {
        $ahora = Carbon::parse('2026-03-11 07:00:00', 'America/El_Salvador');
        $p = ProgramacionCalendario::calcularProxima('mensual', 11, '08:00:00', $ahora);
        $this->assertSame('2026-03-11 08:00:00', $p->format('Y-m-d H:i:s'));
    }

    public function test_calcular_proxima_mes_siguiente_si_ya_paso(): void
    {
        $ahora = Carbon::parse('2026-03-11 09:00:00', 'America/El_Salvador');
        $p = ProgramacionCalendario::calcularProxima('mensual', 11, '08:00:00', $ahora);
        $this->assertSame('2026-04-11 08:00:00', $p->format('Y-m-d H:i:s'));
    }
}