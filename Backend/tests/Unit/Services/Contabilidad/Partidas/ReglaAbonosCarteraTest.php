<?php

namespace Tests\Unit\Services\Contabilidad\Partidas;

use App\Services\Contabilidad\Partidas\ReglaAbonosCartera;
use PHPUnit\Framework\TestCase;

class ReglaAbonosCarteraTest extends TestCase
{
    public function test_apagado_incluye_abonos_en_ingresos_egresos(): void
    {
        $this->assertTrue(ReglaAbonosCartera::incluirEnIngresosEgresos((object) []));
        $this->assertTrue(ReglaAbonosCartera::incluirEnIngresosEgresos((object) ['abonos_en_cartera' => false]));
        $this->assertTrue(ReglaAbonosCartera::incluirEnIngresosEgresos((object) ['abonos_en_cartera' => 0]));
    }

    public function test_encendido_saca_abonos_de_ingresos_egresos(): void
    {
        $this->assertFalse(ReglaAbonosCartera::incluirEnIngresosEgresos((object) ['abonos_en_cartera' => true]));
        $this->assertFalse(ReglaAbonosCartera::incluirEnIngresosEgresos((object) ['abonos_en_cartera' => 1]));
    }
}
