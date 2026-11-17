<?php

namespace Tests\Unit\Services\Planilla;

use App\Services\Planilla\SpuPlanillaService;
use Tests\TestCase;

class SpuPlanillaServiceTest extends TestCase
{
    private function empleador(array $cambios = []): array
    {
        return array_merge([
            'nit_dui' => '06142009610630', 'numero_patronal' => '123456789', 'periodo' => '062023',
            'centro_trabajo' => '1', 'tipo_persona' => 'juridica', 'regimen_especial' => null,
        ], $cambios);
    }

    private function fila(array $cambios = []): array
    {
        return array_merge([
            'id_empleado' => 1, 'empleado' => 'Juan Carlos Perez Lopez',
            'numero_documento' => '01234567-8', 'tipo_documento' => '01', 'afiliacion_isss' => '123456789',
            'institucion_previsional' => 'COF', 'primer_nombre' => 'Juan', 'segundo_nombre' => 'Carlos',
            'primer_apellido' => 'Perez', 'segundo_apellido' => 'Lopez', 'apellido_casada' => '',
            'salario' => 1000, 'pago_adicional' => 0, 'monto_vacacion' => 0,
            'dias' => 30, 'horas' => 8, 'dias_vacacion' => 0, 'codigo_1' => '00', 'codigo_2' => '00',
        ], $cambios);
    }

    private function columnasConError(array $filas, ?array $empleador = null, bool $complementaria = false): array
    {
        $errores = (new SpuPlanillaService())->validar($filas, $empleador ?? $this->empleador(), $complementaria);

        return array_column($errores, 'columna');
    }

    public function test_csv_sigue_el_formato_del_manual(): void
    {
        $csv = (new SpuPlanillaService())->contenidoCsv([
            $this->fila(['segundo_nombre' => 'Carlos Alberto']),
            $this->fila(['monto_vacacion' => 120.5, 'dias_vacacion' => 5, 'codigo_1' => '09']),
            $this->fila(['codigo_1' => '13', 'institucion_previsional' => 'COF']),
        ], $this->empleador());

        $this->assertSame(
            "06142009610630;123456789;062023;001;012345678;01;123456789;COF;Juan;Carlos Alberto;Perez;Lopez;;1000.00;0;0;30;08;0;00;00\r\n"
            . "06142009610630;123456789;062023;001;012345678;01;123456789;COF;Juan;Carlos;Perez;Lopez;;1000.00;0;120.50;30;08;05;09;00\r\n"
            . "06142009610630;123456789;062023;001;012345678;01;123456789;;Juan;Carlos;Perez;Lopez;;1000.00;0;0;30;08;0;13;00\r\n",
            $csv
        );
        foreach (explode("\r\n", trim($csv)) as $linea) {
            $this->assertCount(21, explode(';', $linea));
        }
    }

    public function test_csv_entrecomilla_solo_si_el_valor_trae_el_separador(): void
    {
        $csv = (new SpuPlanillaService())->contenidoCsv([$this->fila(['segundo_apellido' => 'Lopez;Diaz'])], $this->empleador());

        $this->assertStringContainsString(';"Lopez;Diaz";', $csv);
    }

    public function test_fila_valida_no_tiene_errores(): void
    {
        $this->assertSame([], $this->columnasConError([$this->fila()]));
    }

    public function test_institucion_obligatoria_salvo_codigos_13_15_23(): void
    {
        $this->assertContains('Institución previsional', $this->columnasConError([$this->fila(['institucion_previsional' => ''])]));
        $this->assertNotContains('Institución previsional', $this->columnasConError([$this->fila(['institucion_previsional' => '', 'codigo_2' => '13'])]));
    }

    public function test_pago_adicional_y_vacacion_dependen_del_codigo(): void
    {
        $this->assertContains('Pago adicional', $this->columnasConError([$this->fila(['pago_adicional' => 50])]));
        $this->assertContains('Pago adicional', $this->columnasConError([$this->fila(['codigo_1' => '02'])]));
        $this->assertNotContains('Pago adicional', $this->columnasConError([$this->fila(['codigo_1' => '02', 'pago_adicional' => 50])]));
        $this->assertContains('Pago adicional', $this->columnasConError([$this->fila(['codigo_1' => '09', 'pago_adicional' => 50, 'monto_vacacion' => 10, 'dias_vacacion' => 2])]));

        $this->assertContains('Monto de vacación', $this->columnasConError([$this->fila(['monto_vacacion' => 10])]));
        $this->assertContains('Días de vacación', $this->columnasConError([$this->fila(['codigo_1' => '09', 'monto_vacacion' => 10])]));
    }

    public function test_salario_dias_y_horas_mayores_a_cero(): void
    {
        $columnas = $this->columnasConError([$this->fila(['salario' => 0, 'dias' => 0, 'horas' => 0])]);

        $this->assertContains('Salario', $columnas);
        $this->assertContains('Días', $columnas);
        $this->assertContains('Horas', $columnas);
    }

    public function test_codigos_del_catalogo_y_documento(): void
    {
        $columnas = $this->columnasConError([$this->fila(['codigo_1' => '', 'codigo_2' => '24', 'numero_documento' => '123'])]);

        $this->assertContains('Código de observación 1', $columnas);
        $this->assertContains('Código de observación 2', $columnas);
        $this->assertContains('Número de documento', $columnas);
    }

    public function test_codigo_12_requiere_06_consecutivo_del_mismo_trabajador(): void
    {
        $con06 = $this->fila(['codigo_1' => '06']);
        $con12 = $this->fila(['codigo_1' => '12']);

        $this->assertNotContains('Códigos de observación', $this->columnasConError([$con06, $con12]));
        $this->assertContains('Códigos de observación', $this->columnasConError([$con12, $con06]));
        $this->assertContains('Códigos de observación', $this->columnasConError([$con06, $this->fila(['codigo_1' => '12', 'id_empleado' => 2])]));
    }

    public function test_codigo_18_solo_en_complementaria(): void
    {
        $fila = $this->fila(['codigo_1' => '18']);

        $this->assertContains('Códigos de observación', $this->columnasConError([$fila]));
        $this->assertNotContains('Códigos de observación', $this->columnasConError([$fila], null, true));
    }

    public function test_codigos_segun_tipo_de_persona_y_regimen(): void
    {
        $natural = $this->empleador(['tipo_persona' => 'natural', 'nit_dui' => '012345678']);

        $this->assertContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '23'])]));
        $this->assertNotContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '23'])], $natural));
        $this->assertContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '15'])], $natural));
        $this->assertNotContains('Códigos de observación', $this->columnasConError(
            [$this->fila(['codigo_1' => '15'])],
            $this->empleador(['tipo_persona' => 'natural', 'nit_dui' => '012345678', 'regimen_especial' => 'domestico'])
        ));
        $this->assertContains('Códigos de observación', $this->columnasConError([$this->fila(['codigo_1' => '16'])], $natural));
    }

    public function test_empleador_incompleto_da_errores_generales(): void
    {
        $columnas = $this->columnasConError([$this->fila()], $this->empleador(['numero_patronal' => '', 'centro_trabajo' => '', 'tipo_persona' => null]));

        $this->assertContains('Número patronal', $columnas);
        $this->assertContains('Centro de trabajo', $columnas);
        $this->assertContains('Tipo de persona', $columnas);
    }

    public function test_construir_fila_deriva_codigos_nombres_y_documentos(): void
    {
        $empleado = [
            'id' => 7, 'nombres' => 'Maria  Jose Elena', 'apellidos' => 'Gomez Ramos', 'apellido_casada' => null,
            'dui' => '01234567-8', 'isss' => '987-654-321', 'institucion_previsional' => 'MAX',
            'es_pensionado' => false, 'fecha_ingreso' => '2023-06-10', 'fecha_baja' => null, 'horas_jornada' => '8.00',
        ];

        $fila = SpuPlanillaService::construirFila($empleado, ['salario' => 500, 'pago_adicional' => 25.5, 'dias' => 21], 2023, 6);

        $this->assertSame(['08', '02'], [$fila['codigo_1'], $fila['codigo_2']]);
        $this->assertSame(['Maria', 'Jose Elena', 'Gomez', 'Ramos'], [$fila['primer_nombre'], $fila['segundo_nombre'], $fila['primer_apellido'], $fila['segundo_apellido']]);
        $this->assertSame('012345678', $fila['numero_documento']);
        $this->assertSame('987654321', $fila['afiliacion_isss']);
        $this->assertSame([21, 8, 25.5], [$fila['dias'], $fila['horas'], $fila['pago_adicional']]);
        $this->assertSame([], $fila['advertencias']);

        $fila = SpuPlanillaService::construirFila(
            ['es_pensionado' => true, 'fecha_baja' => '2023-06-30'] + $empleado,
            ['salario' => 500, 'pago_adicional' => 10, 'dias' => 30],
            2023,
            6
        );
        $this->assertSame(['08', '07'], [$fila['codigo_1'], $fila['codigo_2']]);
        $this->assertCount(1, $fila['advertencias']);

        $sinCodigos = SpuPlanillaService::construirFila(['fecha_ingreso' => '2020-01-01'] + $empleado, ['salario' => 500, 'pago_adicional' => 0, 'dias' => 15], 2023, 6);
        $this->assertSame(['00', '00'], [$sinCodigos['codigo_1'], $sinCodigos['codigo_2']]);
        $this->assertCount(1, $sinCodigos['advertencias']);
    }
}
