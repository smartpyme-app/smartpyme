<?php

namespace Tests\Unit;

use App\Helpers\IsssHelper;
use PHPUnit\Framework\TestCase;

class IsssHelperTest extends TestCase
{
    public function test_calcula_retencion_proporcional_sobre_salario_quincenal(): void
    {
        $this->assertSame(17.03, IsssHelper::calcularRetencionEmpleado(567.62, 'quincenal'));
    }

    public function test_calcula_retencion_para_salarios_referencia(): void
    {
        $this->assertSame(15.00, IsssHelper::calcularRetencionEmpleado(500.00, 'quincenal'));
        $this->assertSame(18.00, IsssHelper::calcularRetencionEmpleado(600.00, 'quincenal'));
    }

    public function test_no_redondea_al_siguiente_monto_fijo(): void
    {
        $retencion = IsssHelper::calcularRetencionEmpleado(567.62, 'quincenal');
        $retencionIncorrecta = IsssHelper::calcularRetencionEmpleado(600.00, 'quincenal');

        $this->assertNotSame($retencionIncorrecta, $retencion);
        $this->assertSame(17.03, $retencion);
        $this->assertSame(18.00, $retencionIncorrecta);
    }

    public function test_calcula_aporte_patronal_proporcional(): void
    {
        $this->assertSame(42.57, IsssHelper::calcularAportePatronal(567.62, 'quincenal'));
    }

    public function test_retorna_cero_para_base_no_positiva(): void
    {
        $this->assertSame(0.00, IsssHelper::calcularRetencionEmpleado(0, 'quincenal'));
        $this->assertSame(0.00, IsssHelper::calcularRetencionEmpleado(-100, 'quincenal'));
    }

    public function test_aplica_tope_mensual(): void
    {
        $this->assertSame(30.00, IsssHelper::calcularRetencionEmpleado(1500.00, 'mensual'));
    }
}
