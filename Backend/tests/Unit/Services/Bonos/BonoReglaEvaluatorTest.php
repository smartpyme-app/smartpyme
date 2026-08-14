<?php

namespace Tests\Unit\Services\Bonos;

use App\Services\Bonos\BonoReglaEvaluator;
use PHPUnit\Framework\TestCase;

class BonoReglaEvaluatorTest extends TestCase
{
    public function test_meta_fija_alcanzada(): void
    {
        $eval = new BonoReglaEvaluator();
        $monto = $eval->calcular('meta_fija', ['meta' => 40000, 'bono' => 100], 40000);
        $this->assertSame(100.0, $monto);
    }

    public function test_meta_fija_no_alcanzada(): void
    {
        $eval = new BonoReglaEvaluator();
        $this->assertSame(0.0, $eval->calcular('meta_fija', ['meta' => 40000, 'bono' => 100], 39999));
    }

    public function test_escalonado_elige_mayor_tramo(): void
    {
        $config = ['tramos' => [
            ['meta' => 20000, 'bono' => 50],
            ['meta' => 40000, 'bono' => 100],
            ['meta' => 60000, 'bono' => 200],
        ]];
        $eval = new BonoReglaEvaluator();
        $this->assertSame(100.0, $eval->calcular('escalonado', $config, 45000));
    }

    public function test_porcentaje_excedente_solo_sobre_el_exceso(): void
    {
        $eval = new BonoReglaEvaluator();
        $monto = $eval->calcular('porcentaje_excedente', ['meta' => 40000, 'porcentaje' => 10], 50000);
        $this->assertSame(1000.0, $monto);
    }

    public function test_porcentaje_excedente_sin_exceso_es_cero(): void
    {
        $eval = new BonoReglaEvaluator();
        $this->assertSame(0.0, $eval->calcular('porcentaje_excedente', ['meta' => 40000, 'porcentaje' => 10], 40000));
    }

    public function test_cualitativo_manual_siempre_cero_en_job(): void
    {
        $eval = new BonoReglaEvaluator();
        $this->assertSame(0.0, $eval->calcular('cualitativo_manual', [], 99999));
    }
}
