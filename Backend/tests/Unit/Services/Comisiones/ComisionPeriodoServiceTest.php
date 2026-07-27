<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionPeriodo;
use App\Services\Comisiones\ComisionPeriodoService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ComisionPeriodoServiceTest extends TestCase
{
    public function test_ajuste_usa_mismo_periodo_si_no_pagado(): void
    {
        $periodoAbierto = (object) [
            'id' => 1,
            'id_empresa' => 1,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'estado' => ComisionPeriodo::ESTADO_ABIERTO,
        ];

        $svc = new ComisionPeriodoService(
            fn (int $id) => $id === 1 ? $periodoAbierto : null,
            fn () => null,
            fn () => $this->fail('firstOrCreate no debe llamarse')
        );

        $original = (object) [
            'id_empresa' => 1,
            'id_periodo' => 1,
            'fecha_evento' => '2026-07-15',
        ];

        $result = $svc->periodoParaAjuste($original);

        $this->assertSame(1, $result->id);
        $this->assertSame(ComisionPeriodo::ESTADO_ABIERTO, $result->estado);
    }

    public function test_ajuste_usa_siguiente_abierto_si_pagado(): void
    {
        $periodoPagado = (object) [
            'id' => 1,
            'id_empresa' => 1,
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'estado' => ComisionPeriodo::ESTADO_PAGADO,
        ];

        $periodoSiguiente = (object) [
            'id' => 2,
            'id_empresa' => 1,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-07-31',
            'estado' => ComisionPeriodo::ESTADO_ABIERTO,
        ];

        $svc = new ComisionPeriodoService(
            fn (int $id) => $id === 1 ? $periodoPagado : null,
            function (int $idEmpresa, Carbon $afterFin) use ($periodoSiguiente) {
                $this->assertSame(1, $idEmpresa);
                $this->assertSame('2026-06-30', $afterFin->toDateString());

                return $periodoSiguiente;
            },
            fn () => $this->fail('firstOrCreate no debe llamarse cuando hay siguiente abierto')
        );

        $original = (object) [
            'id_empresa' => 1,
            'id_periodo' => 1,
            'fecha_evento' => '2026-06-15',
        ];

        $result = $svc->periodoParaAjuste($original);

        $this->assertSame(2, $result->id);
        $this->assertSame(ComisionPeriodo::ESTADO_ABIERTO, $result->estado);
    }

    public function test_periodo_para_fecha_usa_mes_calendario(): void
    {
        $fecha = Carbon::parse('2026-07-15');
        $creado = null;

        $svc = new ComisionPeriodoService(
            fn () => null,
            fn () => null,
            function (int $idEmpresa, Carbon $inicio, Carbon $fin) use (&$creado, $fecha) {
                $this->assertSame(1, $idEmpresa);
                $this->assertSame($fecha->copy()->startOfMonth()->toDateString(), $inicio->toDateString());
                $this->assertSame($fecha->copy()->endOfMonth()->toDateString(), $fin->toDateString());

                $creado = (object) [
                    'id' => 99,
                    'id_empresa' => $idEmpresa,
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin' => $fin->toDateString(),
                    'estado' => ComisionPeriodo::ESTADO_ABIERTO,
                ];

                return $creado;
            }
        );

        $result = $svc->periodoParaFecha(1, $fecha);

        $this->assertNotNull($creado);
        $this->assertSame(99, $result->id);
    }
}
