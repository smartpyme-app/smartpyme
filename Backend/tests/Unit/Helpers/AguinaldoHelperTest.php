<?php

namespace Tests\Unit\Helpers;

use App\Helpers\AguinaldoHelper;
use Carbon\Carbon;
use Tests\TestCase;

final class AguinaldoHelperTest extends TestCase
{
    public function test_costa_rica_aguinaldo_es_100_por_ciento_exento(): void
    {
        $calc = AguinaldoHelper::calcularDeduccionesAguinaldo(1200.00, 2025, null, 'CR');

        $this->assertSame(1200.00, $calc['monto_exento']);
        $this->assertSame(0.00, $calc['monto_gravado']);
        $this->assertSame(0.00, $calc['retencion_renta']);
        $this->assertSame(1200.00, $calc['aguinaldo_neto']);
    }

    public function test_el_salvador_aplica_exencion_de_1500_sobre_el_bruto(): void
    {
        // Bruto sobre $1,500: solo el excedente queda gravado (comportamiento SV intacto)
        $calc = AguinaldoHelper::calcularDeduccionesAguinaldo(2000.00, 2025, null, 'SV');

        $this->assertSame(1500.00, $calc['monto_exento']);
        $this->assertSame(500.00, $calc['monto_gravado']);
        $this->assertSame(round(2000.00 - $calc['retencion_renta'], 2), $calc['aguinaldo_neto']);
    }

    public function test_sugerencia_cr_es_un_salario_proporcional_a_meses(): void
    {
        // Ingresó al inicio del año => trabajó el período completo => ~1 salario
        $sugerencia = AguinaldoHelper::calcularSugerenciaAguinaldo(
            600.00,
            Carbon::create(2020, 1, 1),
            2025,
            Carbon::create(2025, 12, 12),
            'CR'
        );

        $this->assertSame(600.00, $sugerencia);
    }
}
