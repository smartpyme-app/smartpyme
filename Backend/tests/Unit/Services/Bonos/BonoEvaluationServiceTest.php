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
            'alcance' => 'global',
            'id_vendedores' => null,
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
            obtenerVendedoresConPendiente: fn () => [],
            buscarBonoGenerado: fn () => $existing,
            crearBonoGenerado: fn () => $this->fail('No debe crear bono aprobado'),
            actualizarBonoGenerado: function () use (&$actualizado): void {
                $actualizado = true;
            },
            eliminarBonoGenerado: fn () => $this->fail('No debe eliminar bono aprobado'),
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertSame(1, $resumen['protegidos']);
        $this->assertSame(0, $resumen['actualizados']);
        $this->assertFalse($actualizado);
        $this->assertSame(100.0, $existing->monto);
    }

    public function test_elimina_pendiente_cuando_ya_no_cumple_meta(): void
    {
        $existing = (object) [
            'id' => 2,
            'estado' => BonoGenerado::ESTADO_PENDIENTE,
            'monto' => 300.0,
        ];

        $regla = (object) [
            'id' => 10,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 500, 'bono' => 300],
            'alcance' => 'global',
            'id_vendedores' => null,
        ];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->once()
            ->with(1, 5, '2026-07-01', '2026-07-31')
            ->andReturn(100.0);

        $eliminado = false;

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$regla]),
            obtenerVendedoresConVentas: fn () => [5],
            obtenerVendedoresConPendiente: fn () => [],
            buscarBonoGenerado: fn () => $existing,
            crearBonoGenerado: fn () => $this->fail('No debe crear'),
            actualizarBonoGenerado: fn () => $this->fail('No debe actualizar'),
            eliminarBonoGenerado: function () use (&$eliminado): void {
                $eliminado = true;
            },
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertTrue($eliminado);
        $this->assertSame(1, $resumen['eliminados']);
        $this->assertSame(0, $resumen['omitidos_monto']);
    }

    public function test_regla_por_vendedor_solo_procesa_asignados(): void
    {
        $regla = (object) [
            'id' => 10,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 100, 'bono' => 50],
            'alcance' => 'vendedores',
            'id_vendedores' => [7],
        ];

        $procesados = [];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->once()
            ->with(1, 7, '2026-07-01', '2026-07-31')
            ->andReturn(200.0);

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$regla]),
            obtenerVendedoresConVentas: fn () => [5, 7, 9],
            obtenerVendedoresConPendiente: fn () => [],
            buscarBonoGenerado: fn () => null,
            crearBonoGenerado: function (array $payload) use (&$procesados) {
                $procesados[] = $payload['id_vendedor'];

                return (object) $payload;
            },
            actualizarBonoGenerado: fn () => null,
            eliminarBonoGenerado: fn () => null,
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertSame([7], $procesados);
        $this->assertSame(1, $resumen['creados']);
        $this->assertSame(1, $resumen['vendedores_procesados']);
    }

    public function test_omite_cualitativo_manual_y_no_borra_pendiente(): void
    {
        $existing = (object) [
            'id' => 3,
            'estado' => BonoGenerado::ESTADO_PENDIENTE,
            'monto' => 80.0,
            'origen' => 'manual',
        ];

        $regla = (object) [
            'id' => 10,
            'tipo' => 'cualitativo_manual',
            'config' => [],
            'alcance' => 'global',
            'id_vendedores' => null,
            'reemplaza_global' => false,
        ];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator->shouldReceive('ventasVendedorPeriodo')->never();

        $eliminado = false;
        $creado = false;

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$regla]),
            obtenerVendedoresConVentas: fn () => [5],
            obtenerVendedoresConPendiente: fn () => [5],
            buscarBonoGenerado: fn () => $existing,
            crearBonoGenerado: function () use (&$creado) {
                $creado = true;

                return (object) [];
            },
            actualizarBonoGenerado: fn () => $this->fail('No debe actualizar cualitativo'),
            eliminarBonoGenerado: function () use (&$eliminado): void {
                $eliminado = true;
            },
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertFalse($eliminado);
        $this->assertFalse($creado);
        $this->assertSame(0, $resumen['eliminados']);
        $this->assertSame(0, $resumen['creados']);
        $this->assertSame(0, $resumen['vendedores_procesados']);
    }

    public function test_alcance_individual_solo_procesa_ese_vendedor(): void
    {
        $regla = (object) [
            'id' => 10,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 100, 'bono' => 50],
            'alcance' => 'individual',
            'id_vendedores' => [7],
            'reemplaza_global' => false,
        ];

        $procesados = [];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->once()
            ->with(1, 7, '2026-07-01', '2026-07-31')
            ->andReturn(200.0);

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$regla]),
            obtenerVendedoresConVentas: fn () => [5, 7, 9],
            obtenerVendedoresConPendiente: fn () => [],
            buscarBonoGenerado: fn () => null,
            crearBonoGenerado: function (array $payload) use (&$procesados) {
                $procesados[] = $payload['id_vendedor'];

                return (object) $payload;
            },
            actualizarBonoGenerado: fn () => null,
            eliminarBonoGenerado: fn () => null,
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertSame([7], $procesados);
        $this->assertSame(1, $resumen['creados']);
    }

    public function test_reemplaza_global_descarta_regla_global_para_ese_vendedor(): void
    {
        $global = (object) [
            'id' => 1,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 100, 'bono' => 200],
            'alcance' => 'global',
            'id_vendedores' => null,
            'reemplaza_global' => false,
        ];
        $individual = (object) [
            'id' => 2,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 100, 'bono' => 50],
            'alcance' => 'individual',
            'id_vendedores' => [5],
            'reemplaza_global' => true,
        ];

        $creados = [];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->andReturn(200.0);

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$global, $individual]),
            obtenerVendedoresConVentas: fn () => [5, 7],
            obtenerVendedoresConPendiente: fn () => [],
            buscarBonoGenerado: fn () => null,
            crearBonoGenerado: function (array $payload) use (&$creados) {
                $creados[] = [$payload['id_vendedor'], $payload['id_regla'], $payload['monto']];

                return (object) $payload;
            },
            actualizarBonoGenerado: fn () => null,
            eliminarBonoGenerado: fn () => null,
            registrarEvaluacion: fn () => null,
        );

        $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertEqualsCanonicalizing([
            [5, 2, 50.0],
            [7, 1, 200.0],
        ], $creados);
    }

    public function test_reemplaza_global_elimina_pendiente_de_regla_global(): void
    {
        $global = (object) [
            'id' => 1,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 100, 'bono' => 200],
            'alcance' => 'global',
            'id_vendedores' => null,
            'reemplaza_global' => false,
        ];
        $individual = (object) [
            'id' => 2,
            'tipo' => 'meta_fija',
            'config' => ['meta' => 100, 'bono' => 50],
            'alcance' => 'individual',
            'id_vendedores' => [5],
            'reemplaza_global' => true,
        ];

        $pendingGlobal = (object) [
            'id' => 99,
            'estado' => BonoGenerado::ESTADO_PENDIENTE,
            'monto' => 200.0,
        ];

        $eliminados = [];
        $creados = [];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->andReturn(200.0);

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$global, $individual]),
            obtenerVendedoresConVentas: fn () => [5],
            obtenerVendedoresConPendiente: fn () => [5],
            buscarBonoGenerado: function (array $unique) use ($pendingGlobal) {
                if ((int) $unique['id_regla'] === 1 && (int) $unique['id_vendedor'] === 5) {
                    return $pendingGlobal;
                }

                return null;
            },
            crearBonoGenerado: function (array $payload) use (&$creados) {
                $creados[] = [$payload['id_vendedor'], $payload['id_regla'], $payload['monto']];

                return (object) $payload;
            },
            actualizarBonoGenerado: fn () => $this->fail('No debe actualizar el global reemplazado'),
            eliminarBonoGenerado: function (object $bono) use (&$eliminados): void {
                $eliminados[] = $bono->id;
            },
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertSame([99], $eliminados);
        $this->assertSame(1, $resumen['eliminados']);
        $this->assertEqualsCanonicalizing([
            [5, 2, 50.0],
        ], $creados);
    }

    public function test_grupal_reparte_equitativo_si_el_equipo_cumple(): void
    {
        $regla = (object) [
            'id' => 10,
            'tipo' => 'grupal',
            'config' => ['meta' => 1000, 'bono' => 100, 'reparto' => 'equitativo'],
            'alcance' => 'equipo',
            'id_vendedores' => [5, 7],
            'reemplaza_global' => false,
        ];

        $creados = [];

        $metaCalculator = Mockery::mock(BonoMetaCalculator::class);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->with(1, 5, '2026-07-01', '2026-07-31')
            ->andReturn(600.0);
        $metaCalculator
            ->shouldReceive('ventasVendedorPeriodo')
            ->with(1, 7, '2026-07-01', '2026-07-31')
            ->andReturn(400.0);

        $service = new BonoEvaluationService(
            $metaCalculator,
            new BonoReglaEvaluator(),
            obtenerEmpresasActivas: fn () => [1],
            obtenerReglasActivas: fn () => collect([$regla]),
            obtenerVendedoresConVentas: fn () => [5, 7, 9],
            obtenerVendedoresConPendiente: fn () => [],
            buscarBonoGenerado: fn () => null,
            crearBonoGenerado: function (array $payload) use (&$creados) {
                $creados[] = [$payload['id_vendedor'], $payload['monto']];

                return (object) $payload;
            },
            actualizarBonoGenerado: fn () => null,
            eliminarBonoGenerado: fn () => null,
            registrarEvaluacion: fn () => null,
        );

        $resumen = $service->evaluar(1, '2026-07-01', '2026-07-31');

        $this->assertEqualsCanonicalizing([
            [5, 50.0],
            [7, 50.0],
        ], $creados);
        $this->assertSame(2, $resumen['creados']);
    }
}
