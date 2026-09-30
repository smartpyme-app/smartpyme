<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\ComandaSemaforo;
use Carbon\Carbon;
use Tests\TestCase;

final class ComandaSemaforoTest extends TestCase
{
    public function test_color_segun_los_cortes(): void
    {
        $this->assertSame('verde', ComandaSemaforo::color(0, 7, 14));
        $this->assertSame('verde', ComandaSemaforo::color(7 * 60 - 1, 7, 14));
        $this->assertSame('amarillo', ComandaSemaforo::color(7 * 60, 7, 14));
        $this->assertSame('amarillo', ComandaSemaforo::color(14 * 60 - 1, 7, 14));
        $this->assertSame('rojo', ComandaSemaforo::color(14 * 60, 7, 14));
        $this->assertSame('rojo', ComandaSemaforo::color(30 * 60, 7, 14));
    }

    public function test_segundos_entre_cambio_de_estado(): void
    {
        $inicio = Carbon::parse('2026-09-30 12:00:00');
        $fin = Carbon::parse('2026-09-30 12:08:30');

        $this->assertSame(510, ComandaSemaforo::segundos($inicio, $fin));
        $this->assertSame(0, ComandaSemaforo::segundos($fin, $inicio));
    }
}
