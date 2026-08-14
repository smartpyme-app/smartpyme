<?php

namespace Tests\Unit;

use App\Constants\PlanillaConstants;
use PHPUnit\Framework\TestCase;

class PlanillaSalarioPeriodoTest extends TestCase
{
    private const SALARIO_MENSUAL = 408.80;

    public function test_salario_semanal_es_la_cuarta_parte_del_mensual(): void
    {
        $semanal = PlanillaConstants::ajustarSalarioBasePorPeriodo(self::SALARIO_MENSUAL, 'semanal');

        $this->assertEquals(102.20, round($semanal, 2));
    }

    public function test_salario_quincenal_es_la_mitad_del_mensual(): void
    {
        $quincenal = PlanillaConstants::ajustarSalarioBasePorPeriodo(self::SALARIO_MENSUAL, 'quincenal');

        $this->assertEquals(204.40, round($quincenal, 2));
    }

    public function test_salario_mensual_no_se_ajusta(): void
    {
        $mensual = PlanillaConstants::ajustarSalarioBasePorPeriodo(self::SALARIO_MENSUAL, 'mensual');

        $this->assertEquals(408.80, round($mensual, 2));
    }

    public function test_semana_completa_de_7_dias_devenga_el_salario_semanal(): void
    {
        $salarioSemanal = PlanillaConstants::ajustarSalarioBasePorPeriodo(self::SALARIO_MENSUAL, 'semanal');
        $diasReferencia = 7;
        $diasLaborados = 7;
        $salarioDevengado = ($salarioSemanal / $diasReferencia) * $diasLaborados;

        $this->assertEquals(102.20, round($salarioDevengado, 2));
    }

    public function test_isss_mas_afp_empleado_es_10_25_por_ciento(): void
    {
        $tasa = PlanillaConstants::DESCUENTO_ISSS_EMPLEADO + PlanillaConstants::DESCUENTO_AFP_EMPLEADO;

        $this->assertEquals(0.1025, $tasa);
    }

    public function test_total_a_pagar_semanal_con_isss_y_afp_sin_renta(): void
    {
        $salarioDevengado = round(
            PlanillaConstants::ajustarSalarioBasePorPeriodo(self::SALARIO_MENSUAL, 'semanal'),
            2
        );
        $isss = round($salarioDevengado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO, 2);
        $afp = round($salarioDevengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO, 2);
        $sueldoNeto = round($salarioDevengado - $isss - $afp, 2);

        $this->assertEquals(3.07, $isss);
        $this->assertEquals(7.41, $afp);
        $this->assertEquals(91.72, $sueldoNeto);
    }
}
