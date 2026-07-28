<?php

namespace Tests\Unit\Services\Bonos;

use App\Models\Bonos\BonoGenerado;
use App\Services\Bonos\BonoEvaluationService;
use App\Services\Bonos\BonoMetaCalculator;
use App\Services\Bonos\BonoReglaEvaluator;
use Mockery;
use PHPUnit\Framework\TestCase;

class BonoEvaluationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_no_modifica_bono_ya_aprobado(): void
    {
        $existing = (object) [
            'id' => 1,
            'estado' => BonoGenerado::ESTADO_APROBADO,
            'monto' => 100.0,
        ];

        $regla = (object) [
            'id' => 10,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 40000, 'bono' => 200],
        ];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->once()
            ->with(1, 5, '2026-07-01', '2026-07-31')
            ->andReturn(50000.0);

        $actualizado = false;

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$regla]),
            obtenerVendedoresConVentas: fn () => [5],
            buscarBonoGenerado: fn () => $existing,
            crearBonoGenerado: fn () => $this->fail('No debe crear bono aprobado'),
            actualizarBonoGenerado: function () use (&$actualizado): void {
                $actualizado = true;
            },
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertSame(1, $resumen['protegidos']);
        $this->assertSame(0, $resumen['actualizados']);
        $this->assertFalse($actualizado);
        $this->assertSame(100.0, $existing->monto);
    }
}
