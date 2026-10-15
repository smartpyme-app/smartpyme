<?php

namespace Tests\Unit\Services\Planilla;

use App\Services\Planilla\PlantillaImportacionPlanilla;
use Tests\TestCase;

class PlantillaImportacionPlanillaTest extends TestCase
{
    public function test_el_salvador_conserva_isss_y_afp(): void
    {
        $claves = array_keys(PlantillaImportacionPlanilla::encabezados('SV'));

        $this->assertSame([
            'codigo',
            'nombres_y_apellidos',
            'salario_base',
            'dias_laborados',
            'comisiones',
            'horas_extra',
            'monto_horas_extra',
            'total_horas_extras',
            'bonificaciones',
            'otros_ingresos',
            'prestamos',
            'anticipos',
            'descuentos_judiciales',
            'sub_total',
            'isss',
            'afp',
            'renta',
            'otras_deducciones',
            'detalle_de_otras_deducciones',
            'total_neto',
            'firma',
        ], $claves);
    }

    public function test_costa_rica_no_trae_isss_ni_afp(): void
    {
        $headers = PlantillaImportacionPlanilla::encabezados('CR');

        $this->assertNotContains('ISSS', $headers);
        $this->assertNotContains('AFP', $headers);
        $this->assertContains('CCSS', $headers);
        $this->assertContains('RENTA', $headers);
        $this->assertArrayNotHasKey('isss', $headers);
        $this->assertArrayNotHasKey('afp', $headers);
    }

    public function test_honduras_no_trae_isss_ni_afp(): void
    {
        $headers = PlantillaImportacionPlanilla::encabezados('HN');

        $this->assertNotContains('ISSS', $headers);
        $this->assertNotContains('AFP', $headers);
        $this->assertContains('IHSS', $headers);
        $this->assertContains('RAP', $headers);
        $this->assertContains('ISR', $headers);
        $this->assertArrayNotHasKey('isss', $headers);
        $this->assertArrayNotHasKey('afp', $headers);
    }

    public function test_otro_pais_conserva_la_plantilla_de_el_salvador(): void
    {
        $headers = PlantillaImportacionPlanilla::encabezados('GT');

        $this->assertContains('ISSS', $headers);
        $this->assertContains('AFP', $headers);
    }

    public function test_costa_rica_no_precarga_deducciones_de_el_salvador(): void
    {
        $fila = PlantillaImportacionPlanilla::filaEmpleado('CR', 'E1', 'Ana', 500000);
        $porClave = array_combine(array_keys(PlantillaImportacionPlanilla::encabezados('CR')), $fila);

        $this->assertSame(0, $porClave['ccss']);
        $this->assertSame(0, $porClave['renta']);
        $this->assertArrayNotHasKey('isss', $porClave);
    }

    public function test_el_salvador_precarga_isss_afp_y_renta(): void
    {
        $fila = PlantillaImportacionPlanilla::filaEmpleado('SV', 'E1', 'Ana', 1000, 30, 72.5, 0);
        $porClave = array_combine(array_keys(PlantillaImportacionPlanilla::encabezados('SV')), $fila);

        $this->assertSame(30, $porClave['isss']);
        $this->assertSame(72.5, $porClave['afp']);
        $this->assertSame(0, $porClave['renta']);
        $this->assertSame(1000, $porClave['salario_base']);
    }

    public function test_totales_de_el_salvador_siguen_en_las_mismas_columnas(): void
    {
        $fila = PlantillaImportacionPlanilla::filaTotales('SV', 2);

        $this->assertCount(21, $fila);
        $this->assertSame('', $fila[0]);
        $this->assertSame('TOTAL', $fila[1]);
        $this->assertSame('=SUM(C2:C3)', $fila[2]);
        $this->assertSame('', $fila[3]);
        $this->assertSame('=SUM(O2:O3)', $fila[14]);
        $this->assertSame('', $fila[18]);
        $this->assertSame('=SUM(T2:T3)', $fila[19]);
        $this->assertSame('', $fila[20]);
    }
}
