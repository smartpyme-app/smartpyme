<?php

namespace Tests\Unit\Http\Requests\Contabilidad;

use App\Http\Requests\Contabilidad\StoreConfiguracionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreConfiguracionRequestTest extends TestCase
{
    private function validarGenerarPartidas(mixed $valor)
    {
        $reglas = (new StoreConfiguracionRequest())->rules();

        return Validator::make(
            ['generar_partidas' => $valor],
            ['generar_partidas' => $reglas['generar_partidas']]
        );
    }

    public function test_generar_partidas_acepta_manual_y_auto(): void
    {
        $this->assertFalse($this->validarGenerarPartidas('Manual')->errors()->has('generar_partidas'));
        $this->assertFalse($this->validarGenerarPartidas('Auto')->errors()->has('generar_partidas'));
    }

    public function test_generar_partidas_rechaza_valores_invalidos(): void
    {
        $this->assertTrue($this->validarGenerarPartidas(true)->errors()->has('generar_partidas'));
        $this->assertTrue($this->validarGenerarPartidas('automatico')->errors()->has('generar_partidas'));
    }
}
