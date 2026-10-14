<?php

namespace Tests\Unit;

use App\Models\Admin\Empresa;
use App\Models\Suscripcion;
use PHPUnit\Framework\TestCase;

class EmpresaCadenciaElegidaTest extends TestCase
{
    public function test_la_cadencia_del_formulario_queda_en_los_tres_campos(): void
    {
        $empresa = new Empresa();
        $empresa->frecuencia_pago = 'Mensual';
        $empresa->tipo_plan = 'Mensual';

        $suscripcion = new Suscripcion();
        $suscripcion->tipo_plan = 'Mensual';

        $empresa->aplicarCadenciaElegida('Anual', $suscripcion);

        $this->assertSame('Anual', $empresa->frecuencia_pago);
        $this->assertSame('Anual', $empresa->tipo_plan);
        $this->assertSame('Anual', $suscripcion->tipo_plan);
    }

    public function test_una_cadencia_vacia_no_pisa_lo_guardado(): void
    {
        $empresa = new Empresa();
        $empresa->frecuencia_pago = 'Anual';
        $empresa->tipo_plan = 'Anual';

        $suscripcion = new Suscripcion();
        $suscripcion->tipo_plan = 'Anual';

        $empresa->aplicarCadenciaElegida('  ', $suscripcion);

        $this->assertSame('Anual', $empresa->frecuencia_pago);
        $this->assertSame('Anual', $empresa->tipo_plan);
        $this->assertSame('Anual', $suscripcion->tipo_plan);
    }
}
