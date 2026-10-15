<?php

namespace App\Services\Planilla;

class PlantillaImportacionPlanilla
{
    public static function encabezados(string $codPais): array
    {
        return array_merge(self::comunes(), self::deducciones($codPais), self::cierre());
    }

    public static function filaEmpleado(string $codPais, $codigo, string $nombre, $salario, $isss = 0, $afp = 0, $renta = 0): array
    {
        $pais = self::pais($codPais);
        $deducciones = match ($pais) {
            'CR' => ['ccss' => 0, 'renta' => 0],
            'HN' => ['ihss' => 0, 'rap' => 0, 'isr' => 0],
            default => ['isss' => $isss, 'afp' => $afp, 'renta' => $renta],
        };

        $valores = array_merge([
            'codigo' => $codigo,
            'nombres_y_apellidos' => $nombre,
            'salario_base' => $salario,
            'dias_laborados' => 30,
            'comisiones' => 0,
            'horas_extra' => 0,
            'monto_horas_extra' => 0,
            'total_horas_extras' => 0,
            'bonificaciones' => 0,
            'otros_ingresos' => 0,
            'prestamos' => 0,
            'anticipos' => 0,
            'descuentos_judiciales' => 0,
            'sub_total' => $salario,
        ], $deducciones, [
            'otras_deducciones' => 0,
            'detalle_de_otras_deducciones' => '',
            'total_neto' => 0,
            'firma' => '',
        ]);

        return array_map(fn ($clave) => $valores[$clave], array_keys(self::encabezados($codPais)));
    }

    public static function filaTotales(string $codPais, int $filasDatos): array
    {
        $sinSuma = ['codigo', 'dias_laborados', 'detalle_de_otras_deducciones', 'firma'];
        $hasta = $filasDatos + 1;
        $fila = [];

        foreach (array_keys(self::encabezados($codPais)) as $indice => $clave) {
            if ($clave === 'nombres_y_apellidos') {
                $fila[] = 'TOTAL';
                continue;
            }
            if (in_array($clave, $sinSuma, true)) {
                $fila[] = '';
                continue;
            }
            $columna = chr(65 + $indice);
            $fila[] = "=SUM({$columna}2:{$columna}{$hasta})";
        }

        return $fila;
    }

    private static function deducciones(string $codPais): array
    {
        return match (self::pais($codPais)) {
            'CR' => ['ccss' => 'CCSS', 'renta' => 'RENTA'],
            'HN' => ['ihss' => 'IHSS', 'rap' => 'RAP', 'isr' => 'ISR'],
            default => ['isss' => 'ISSS', 'afp' => 'AFP', 'renta' => 'RENTA'],
        };
    }

    private static function pais(string $codPais): string
    {
        return strtoupper(trim($codPais));
    }

    private static function comunes(): array
    {
        return [
            'codigo' => 'Código Empleado',
            'nombres_y_apellidos' => 'Nombres y Apellidos',
            'salario_base' => 'Salario Base',
            'dias_laborados' => 'Días Laborados',
            'comisiones' => 'Comisiones',
            'horas_extra' => 'Horas Extra',
            'monto_horas_extra' => 'Monto Horas Extra',
            'total_horas_extras' => 'Total Horas Extras',
            'bonificaciones' => 'Bonificaciones',
            'otros_ingresos' => 'Otros Ingresos',
            'prestamos' => 'Préstamos',
            'anticipos' => 'Anticipos',
            'descuentos_judiciales' => 'Descuentos Judiciales',
            'sub_total' => 'Sub Total',
        ];
    }

    private static function cierre(): array
    {
        return [
            'otras_deducciones' => 'Otras Deducciones',
            'detalle_de_otras_deducciones' => 'Detalle de Otras Deducciones',
            'total_neto' => 'Total Neto',
            'firma' => 'Firma',
        ];
    }
}
