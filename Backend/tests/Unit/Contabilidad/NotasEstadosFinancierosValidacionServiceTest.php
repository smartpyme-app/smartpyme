<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use App\Services\Contabilidad\NotasEstadosFinancieros\NotasEstadosFinancierosValidacionService;
use PHPUnit\Framework\TestCase;

class NotasEstadosFinancierosValidacionServiceTest extends TestCase
{
    public function test_validaciones_cruzadas_detectan_diferencias(): void
    {
        $svc = new NotasEstadosFinancierosValidacionService();
        $notas = [
            4 => ['contenido' => ['total_efectivo' => 100.0]],
            7 => ['contenido' => ['valor_libros_neto' => 500.0, 'depreciacion_cargada_anio' => 50.0]],
            10 => ['contenido' => ['isr_neto_pagar' => 25.0]],
            11 => ['contenido' => ['saldo_provision_indemnizacion' => 10.0]],
            12 => ['contenido' => ['reserva_legal_cierre' => 80.0]],
        ];
        $lines = [
            'efectivo_equivalentes' => 100.0,
            'propiedad_planta_equipo' => 600.0,
            'depreciacion_acumulada' => -100.0,
            'isr_por_pagar' => 20.0,
            'provision_indemnizaciones' => 10.0,
            'reserva_legal' => 80.0,
        ];
        $er = [
            'L' => ['gasto_venta_deprec' => 30.0, 'gasto_admin_deprec' => 20.0],
            'cascada' => ['isr_neto' => 25.0],
        ];

        $result = $svc->validar($notas, $lines, $er);

        $this->assertCount(6, $result);
        $isr = collect($result)->firstWhere('clave', 'nota_10_isr');
        $this->assertFalse($isr['cuadra']);
        $this->assertSame(5.0, $isr['diferencia']);

        $efectivo = collect($result)->firstWhere('clave', 'nota_4_efectivo');
        $this->assertTrue($efectivo['cuadra']);
    }
}
