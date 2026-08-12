<?php

namespace Tests\Unit\Services\Planilla;

use Tests\TestCase;
use App\Models\Planilla\Empleado;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Services\Planilla\PlanillaDetalleService;
use App\Services\Planilla\ConfiguracionPlanillaService;
use Mockery;

/**
 * IMPORTANTE: Estos tests NO usan RefreshDatabase ni ningún trait que afecte la base de datos.
 * Los tests que requieren base de datos están marcados como skipped y se probarán en tests de integración.
 * Estos tests unitarios solo prueban lógica sin acceso a base de datos.
 */
class PlanillaDetalleServiceTest extends TestCase
{
    protected $planillaDetalleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planillaDetalleService = new PlanillaDetalleService(
            Mockery::mock(ConfiguracionPlanillaService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test actualizar detalle con datos válidos
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_actualizar_detalle_con_datos_validos()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test actualizar detalle solo permite planillas en borrador
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_actualizar_detalle_solo_permite_borrador()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test actualizar detalle calcula salario devengado correctamente
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_actualizar_detalle_calcula_salario_devengado()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test actualizar detalle calcula horas extra correctamente
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_actualizar_detalle_calcula_horas_extra()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * El Salvador: las horas extra por tipo se liquidan con el recargo legal, los abonos
     * "sin retención" quedan fuera de la base gravable pero sí se pagan.
     */
    public function test_recalcular_el_salvador_usa_horas_extra_por_tipo_y_excluye_abonos_de_la_base()
    {
        $enviadoAlMotor = null;
        $configuracion = Mockery::mock(ConfiguracionPlanillaService::class);
        $configuracion->shouldReceive('calcularConceptos')
            ->once()
            ->andReturnUsing(function ($datosEmpleado) use (&$enviadoAlMotor) {
                $enviadoAlMotor = $datosEmpleado;

                return [
                    'pais_configuracion' => 'SV',
                    'isss_empleado' => 30,
                    'isss_patronal' => 75,
                    'afp_empleado' => 72.5,
                    'afp_patronal' => 87.5,
                    'renta' => 40,
                    'totales' => ['total_ingresos' => 1050, 'total_deducciones' => 142.5],
                ];
            });

        $detalle = $this->detalleDePrueba('mensual', 1000);
        $this->recalcular($configuracion, $detalle, [
            'dias_laborados' => 30,
            'detalle_horas_extra' => ['diurna' => 2, 'nocturna' => 0, 'dia_descanso' => 0, 'dia_asueto' => 0],
            'abonos' => 100,
            'abonos_sin_retencion' => true,
            'otros_ingresos' => 50,
        ]);

        // 2 horas diurnas: (1000 / 30 / 8) * 2 * 100% de recargo
        $this->assertEquals(16.67, $detalle->monto_horas_extra);
        $this->assertEquals(2, $detalle->horas_extra);
        $this->assertEquals(50, $enviadoAlMotor['otros_ingresos'], 'Los abonos sin retención no deben ir a la base gravable');
        $this->assertEquals(1150, $detalle->total_ingresos, 'Los abonos sí se pagan');
        $this->assertEquals(142.5, $detalle->total_descuentos);
        $this->assertEquals(1007.5, $detalle->sueldo_neto);
    }

    /**
     * Costa Rica: el detalle toma los conceptos del motor CR y el recargo de hora extra del país.
     */
    public function test_recalcular_costa_rica_toma_los_conceptos_del_motor_del_pais()
    {
        $conceptos = [
            'ccss_empleado' => ['codigo' => 'CCSS_EMP', 'valor' => 108.3, 'tipo' => 'deduccion'],
            'renta_cr' => ['codigo' => 'RENTA_CR', 'valor' => 0, 'tipo' => 'deduccion'],
        ];

        $configuracion = Mockery::mock(ConfiguracionPlanillaService::class);
        $configuracion->shouldReceive('factorHoraExtra')->andReturn(1.5);
        $configuracion->shouldReceive('calcularConceptos')->once()->andReturn([
            'pais_configuracion' => 'CR',
            'isss_empleado' => 0,
            'afp_empleado' => 0,
            'renta' => 0,
            'conceptos_personalizados' => $conceptos,
            'totales' => ['total_ingresos' => 1012.5, 'total_deducciones' => 108.3],
        ]);

        $detalle = $this->detalleDePrueba('mensual', 1000);
        $this->recalcular($configuracion, $detalle, ['dias_laborados' => 30, 'horas_extra' => 2]);

        // 2 horas extra al recargo de Costa Rica (50%): (1000 / 30 / 8) * 2 * 1.5
        $this->assertEquals(12.5, $detalle->monto_horas_extra);
        $this->assertEquals('CR', $detalle->pais_configuracion);
        $this->assertEquals($conceptos, $detalle->conceptos_personalizados);
        $this->assertEquals(0, $detalle->isss_empleado);
        $this->assertEquals(1012.5, $detalle->total_ingresos);
        $this->assertEquals(904.2, $detalle->sueldo_neto);
    }

    private function detalleDePrueba(string $tipoPlanilla, float $salarioBase): PlanillaDetalle
    {
        $planilla = new Planilla();
        $planilla->id_empresa = 1;
        $planilla->tipo_planilla = $tipoPlanilla;

        $empleado = new Empleado();
        $empleado->tipo_contrato = 1;

        $detalle = new PlanillaDetalle();
        $detalle->salario_base = $salarioBase;
        $detalle->setRelation('planilla', $planilla);
        $detalle->setRelation('empleado', $empleado);

        return $detalle;
    }

    private function recalcular($configuracion, PlanillaDetalle $detalle, array $datos): void
    {
        $metodo = new \ReflectionMethod(PlanillaDetalleService::class, 'recalcular');
        $metodo->setAccessible(true);
        $metodo->invoke(new PlanillaDetalleService($configuracion), $detalle, $datos);
    }

    /**
     * Test retirar detalle actualiza estado
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_retirar_detalle_actualiza_estado()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test incluir detalle actualiza estado
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_incluir_detalle_actualiza_estado()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }
}

