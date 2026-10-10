<?php

namespace Tests\Unit;

use App\Exports\Support\RangoFecha;
use PHPUnit\Framework\TestCase;

class RangoFechaTest extends TestCase
{
    public function test_un_dia_sin_hora_cubre_ese_dia_completo(): void
    {
        $this->assertSame(['<', '2026-09-01'], RangoFecha::cotaFin('2026-08-31'));
    }

    public function test_un_fin_con_hora_se_queda_en_ese_instante(): void
    {
        $this->assertSame(['<=', '2026-08-31 18:30:00'], RangoFecha::cotaFin('2026-08-31 18:30:00'));
    }
}
