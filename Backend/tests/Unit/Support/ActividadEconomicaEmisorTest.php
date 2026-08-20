<?php

namespace Tests\Unit\Support;

use App\Support\ActividadEconomicaEmisor;
use PHPUnit\Framework\TestCase;

class ActividadEconomicaEmisorTest extends TestCase
{
    public function test_sin_sucursal_usa_empresa(): void
    {
        $empresa = (object) ['cod_actividad_economica' => '01111', 'giro' => 'Cultivo de granos'];

        $this->assertSame(
            ['cod' => '01111', 'giro' => 'Cultivo de granos'],
            ActividadEconomicaEmisor::resolver($empresa, null)
        );
    }

    public function test_sucursal_vacia_usa_empresa(): void
    {
        $empresa = (object) ['cod_actividad_economica' => '01111', 'giro' => 'Cultivo de granos'];
        $sucursal = (object) ['cod_actividad_economica' => null, 'giro' => ''];

        $this->assertSame(
            ['cod' => '01111', 'giro' => 'Cultivo de granos'],
            ActividadEconomicaEmisor::resolver($empresa, $sucursal)
        );
    }

    public function test_sucursal_con_codigo_gana(): void
    {
        $empresa = (object) ['cod_actividad_economica' => '01111', 'giro' => 'Cultivo de granos'];
        $sucursal = (object) ['cod_actividad_economica' => '7020.0', 'giro' => 'Consultoría'];

        $this->assertSame(
            ['cod' => '7020.0', 'giro' => 'Consultoría'],
            ActividadEconomicaEmisor::resolver($empresa, $sucursal)
        );
    }

    public function test_sucursal_solo_texto_hereda_codigo_empresa(): void
    {
        $empresa = (object) ['cod_actividad_economica' => '01111', 'giro' => 'Cultivo de granos'];
        $sucursal = (object) ['cod_actividad_economica' => null, 'giro' => 'Taller de motos'];

        $this->assertSame(
            ['cod' => '01111', 'giro' => 'Taller de motos'],
            ActividadEconomicaEmisor::resolver($empresa, $sucursal)
        );
    }
}
