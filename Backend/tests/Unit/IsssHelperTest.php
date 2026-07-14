<?php

namespace Tests\Unit;

use App\Helpers\IsssHelper;
use PHPUnit\Framework\TestCase;

class IsssHelperTest extends TestCase
{
    public function test_tope_quincenal_es_la_mitad_del_mensual(): void
    {
        $this->assertSame(500.00, IsssHelper::obtenerTopePorPeriodo('quincenal'));
        $this->assertSame(1000.00, IsssHelper::obtenerTopePorPeriodo('mensual'));
    }

    public function test_aplica_tope_quincenal_cuando_salario_supera_500(): void
    {
        // Salario quincenal $567.62 → mensual ~$1,135 → tope quincenal $500 → ISSS $15
        $this->assertSame(15.00, IsssHelper::calcularRetencionEmpleado(567.62, 'quincenal'));
        $this->assertSame(15.00, IsssHelper::calcularRetencionEmpleado(600.00, 'quincenal'));
    }

    public function test_calcula_retencion_proporcional_bajo_el_tope_quincenal(): void
    {
        $this->assertSame(13.50, IsssHelper::calcularRetencionEmpleado(450.00, 'quincenal'));
        $this->assertSame(15.00, IsssHelper::calcularRetencionEmpleado(500.00, 'quincenal'));
    }

    public function test_dos_quincenas_al_tope_no_superan_maximo_mensual(): void
    {
        $quincena1 = IsssHelper::calcularRetencionEmpleado(600.00, 'quincenal');
        $quincena2 = IsssHelper::calcularRetencionEmpleado(567.62, 'quincenal');

        $this->assertSame(15.00, $quincena1);
        $this->assertSame(15.00, $quincena2);
        $this->assertSame(30.00, round($quincena1 + $quincena2, 2));
    }

    public function test_calcula_aporte_patronal_con_tope_quincenal(): void
    {
        $this->assertSame(37.50, IsssHelper::calcularAportePatronal(567.62, 'quincenal'));
    }

    public function test_aplica_tope_mensual(): void
    {
        $this->assertSame(30.00, IsssHelper::calcularRetencionEmpleado(1500.00, 'mensual'));
    }

    public function test_retorna_cero_para_base_no_positiva(): void
    {
        $this->assertSame(0.00, IsssHelper::calcularRetencionEmpleado(0, 'quincenal'));
        $this->assertSame(0.00, IsssHelper::calcularRetencionEmpleado(-100, 'quincenal'));
    }
}
