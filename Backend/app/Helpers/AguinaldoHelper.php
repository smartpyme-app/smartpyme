<?php

namespace App\Helpers;

use App\Constants\PlanillaConstants;
use App\Helpers\RentaHelper;
use Carbon\Carbon;

class AguinaldoHelper
{
    /**
     * Calcula todas las deducciones de aguinaldo de un empleado
     */
    public static function calcularDeduccionesAguinaldo($montoBruto, $anio, $tipoContrato = null)
    {
        // Validar que el monto bruto sea válido
        if ($montoBruto <= 0) {
            return [
                'monto_exento' => 0.00,
                'monto_gravado' => 0.00,
                'retencion_renta' => 0.00,
                'aguinaldo_neto' => 0.00
            ];
        }

        // Redondear monto bruto a 2 decimales
        $montoBruto = round($montoBruto, 2);

        // Calcular monto exento según Decreto 900 ($1,500 exentos)
        $montoExento = min($montoBruto, PlanillaConstants::AGUINALDO_EXENTO_DECRETO_2023);

        // Calcular monto gravado (exceso sobre $1,500)
        $montoGravado = max(0, $montoBruto - PlanillaConstants::AGUINALDO_EXENTO_DECRETO_2023);

        // Calcular retención de renta sobre el monto gravado
        // Si es contrato sin prestaciones (por obra o servicios profesionales), aplicar 10% fijo
        // Si es contrato normal, usar tabla mensual de renta
        if (PlanillaConstants::esContratoSinPrestaciones($tipoContrato)) {
            $retencionRenta = round($montoGravado * 0.10, 2);
        } else {
            // Usar RentaHelper con tipo planilla 'mensual' para aguinaldo
            $retencionRenta = RentaHelper::calcularRetencionRenta($montoGravado, 'mensual', $tipoContrato);
        }

        // Calcular aguinaldo neto
        $aguinaldoNeto = round($montoBruto - $retencionRenta, 2);

        return [
            'monto_exento' => round($montoExento, 2),
            'monto_gravado' => round($montoGravado, 2),
            'retencion_renta' => round($retencionRenta, 2),
            'aguinaldo_neto' => round($aguinaldoNeto, 2)
        ];
    }

    /**
     * Calcula los años de laborar de un empleado hasta un año específico
     * Por ley, el cálculo se hace hasta el 12 de diciembre (o fecha configurada)
     * 
     * @param Carbon $fechaIngreso Fecha de ingreso del empleado
     * @param int $anio Año del aguinaldo
     * @param Carbon|null $fechaCalculo Fecha de cálculo (opcional, por defecto 12 de diciembre)
     * @return float Años de laborar
     */
    public static function calcularAniosLaborar($fechaIngreso, $anio, $fechaCalculo = null)
    {
        // Fecha límite para cálculo de aguinaldo: 12 de diciembre (por ley) o fecha configurada
        if (!$fechaCalculo) {
            $fechaCalculoAguinaldo = Carbon::create($anio, 12, 12);
        } else {
            $fechaCalculoAguinaldo = $fechaCalculo instanceof Carbon ? $fechaCalculo : Carbon::parse($fechaCalculo);
        }
        
        // Si ingresó después de la fecha de cálculo del año en cuestión, retornar 0
        if ($fechaIngreso->year > $anio || 
            ($fechaIngreso->year == $anio && $fechaIngreso->gt($fechaCalculoAguinaldo))) {
            return 0;
        }

        // Calcular años completos desde fecha de ingreso hasta la fecha de cálculo
        $anios = $fechaIngreso->diffInYears($fechaCalculoAguinaldo);
        
        // Si es menos de un año, calcular proporcionalmente
        if ($anios == 0) {
            $meses = $fechaIngreso->diffInMonths($fechaCalculoAguinaldo) + 1; // +1 para incluir el mes actual
            return min($meses / 12, 1); // Máximo 1 año
        }

        return $anios;
    }

    /**
     * Calcula una sugerencia de aguinaldo basada en el salario base y años de laborar
     * 
     * Según Código de Trabajo de El Salvador, Artículo 198:
     * - Menos de 1 año (pero más de 30 días): proporcional (días trabajados / 365)
     * - De 1 a más, pero menos de 3 años: 15 días de salario
     * - De 3 a más, pero menos de 10 años: 19 días de salario
     * - 10 años o más: 21 días de salario
     * 
     * @param float $salarioBase Salario base mensual del empleado
     * @param Carbon $fechaIngreso Fecha de ingreso del empleado
     * @param int $anio Año del aguinaldo
     * @param Carbon|null $fechaCalculo Fecha de cálculo (opcional, por defecto 12 de diciembre)
     * @return float Sugerencia de aguinaldo calculada
     */
    public static function calcularSugerenciaAguinaldo($salarioBase, $fechaIngreso, $anio, $fechaCalculo = null)
    {
        // Validar parámetros
        if ($salarioBase <= 0) {
            return 0.00;
        }

        // Obtener fecha de cálculo (por defecto 12 de diciembre)
        if (!$fechaCalculo) {
            $fechaCalculoAguinaldo = Carbon::create($anio, 12, 12);
        } else {
            $fechaCalculoAguinaldo = $fechaCalculo instanceof Carbon ? $fechaCalculo : Carbon::parse($fechaCalculo);
        }

        // Calcular años de laborar
        $aniosLaborar = self::calcularAniosLaborar($fechaIngreso, $anio, $fechaCalculoAguinaldo);

        // Calcular salario diario
        $salarioDiario = $salarioBase / 30;

        // Determinar días de aguinaldo según años de laborar
        $diasAguinaldo = 0;
        
        if ($aniosLaborar < 1) {
            // Menos de 1 año: proporcional según días trabajados / 365
            $inicioAnio = Carbon::create($anio, 1, 1);
            $fechaInicio = $fechaIngreso->year == $anio ? $fechaIngreso : $inicioAnio;
            
            // Si ingresó después de la fecha de cálculo, no tiene derecho
            if ($fechaInicio->gt($fechaCalculoAguinaldo)) {
                return 0.00;
            }
            
            // Calcular días trabajados hasta la fecha de cálculo (incluyendo el día de inicio)
            $diasTrabajados = $fechaInicio->diffInDays($fechaCalculoAguinaldo) + 1;
            
            // Verificar que tenga más de 30 días trabajados (requisito mínimo)
            if ($diasTrabajados < 30) {
                return 0.00; // No tiene derecho a aguinaldo si tiene menos de 30 días
            }
            
            // Calcular aguinaldo proporcional según fórmula del Código de Trabajo:
            // Para menos de 1 año: (Salario Diario * 15 días * Días Trabajados) / 365
            // Esto es equivalente a: (Salario Mensual * 15 * Días Trabajados) / (365 * 30)
            $diasAguinaldoProporcional = ($diasTrabajados / 365) * 15;
            $aguinaldoSugerido = $salarioDiario * $diasAguinaldoProporcional;
            return round($aguinaldoSugerido, 2);
        } elseif ($aniosLaborar >= 1 && $aniosLaborar < 3) {
            // De 1 a más, pero menos de 3 años: 15 días
            $diasAguinaldo = 15;
        } elseif ($aniosLaborar >= 3 && $aniosLaborar < 10) {
            // De 3 a más, pero menos de 10 años: 19 días
            $diasAguinaldo = 19;
        } else {
            // 10 años o más: 21 días
            $diasAguinaldo = 21;
        }

        // Calcular aguinaldo sugerido: salario_diario * días_aguinaldo
        $aguinaldoSugerido = $salarioDiario * $diasAguinaldo;

        return round($aguinaldoSugerido, 2);
    }

    /**
     * Calcula los meses totales trabajados desde la fecha de ingreso hasta la fecha de cálculo
     * Por ley, el cálculo se hace hasta el 12 de diciembre (o fecha configurada)
     * 
     * @param Carbon $fechaIngreso Fecha de ingreso del empleado
     * @param int $anio Año del aguinaldo
     * @param Carbon|null $fechaCalculo Fecha de cálculo (opcional, por defecto 12 de diciembre)
     * @return int Meses totales trabajados desde la fecha de ingreso
     */
    public static function calcularMesesTrabajados($fechaIngreso, $anio, $fechaCalculo = null)
    {
        // Fecha límite para cálculo de aguinaldo: 12 de diciembre (por ley) o fecha configurada
        if (!$fechaCalculo) {
            $fechaCalculoAguinaldo = Carbon::create($anio, 12, 12);
        } else {
            $fechaCalculoAguinaldo = $fechaCalculo instanceof Carbon ? $fechaCalculo : Carbon::parse($fechaCalculo);
        }

        // Si ingresó después de la fecha de cálculo del año en cuestión, retornar 0
        if ($fechaIngreso->year > $anio || 
            ($fechaIngreso->year == $anio && $fechaIngreso->gt($fechaCalculoAguinaldo))) {
            return 0;
        }

        // Calcular meses totales desde la fecha de ingreso hasta la fecha de cálculo
        // Usamos diffInMonths que cuenta meses completos, luego agregamos 1 para incluir el mes actual
        $meses = $fechaIngreso->diffInMonths($fechaCalculoAguinaldo) + 1;

        return $meses;
    }

    /**
     * Valida si un empleado es elegible para recibir aguinaldo
     */
    public static function validarElegibilidadAguinaldo($empleado, $anio)
    {
        // 1. Verificar que el empleado esté activo
        if ($empleado->estado != PlanillaConstants::ESTADO_EMPLEADO_ACTIVO) {
            return [
                'elegible' => false,
                'razon' => 'El empleado no está activo'
            ];
        }

        // 2. Verificar que tenga un tipo de contrato válido
        if (!$empleado->tipo_contrato) {
            return [
                'elegible' => false,
                'razon' => 'El empleado no tiene un tipo de contrato definido'
            ];
        }

        // 3. Verificar que el tipo de contrato tenga derecho a aguinaldo
        if (!PlanillaConstants::contratoTieneDerechoAguinaldo($empleado->tipo_contrato)) {
            $nombreContrato = PlanillaConstants::getTiposContrato()[$empleado->tipo_contrato] ?? 'Desconocido';
            return [
                'elegible' => false,
                'razon' => "El tipo de contrato '{$nombreContrato}' no tiene derecho a aguinaldo"
            ];
        }

        // 4. Verificar que tenga fecha de ingreso
        if (!$empleado->fecha_ingreso) {
            return [
                'elegible' => false,
                'razon' => 'El empleado no tiene fecha de ingreso registrada'
            ];
        }

        // 5. Verificar que haya trabajado al menos en el año especificado
        $fechaIngreso = Carbon::parse($empleado->fecha_ingreso);
        if ($fechaIngreso->year > $anio) {
            return [
                'elegible' => false,
                'razon' => "El empleado ingresó después del año {$anio}"
            ];
        }

        // Si pasa todas las validaciones
        return [
            'elegible' => true,
            'razon' => 'El empleado es elegible para aguinaldo'
        ];
    }

    /**
     * Obtiene información detallada de los cálculos de aguinaldo
     * Útil para debugging y mostrar al usuario
     */
    public static function obtenerInformacionCalculo($montoBruto, $anio, $tipoContrato = null)
    {
        $calculos = self::calcularDeduccionesAguinaldo($montoBruto, $anio, $tipoContrato);

        $tramoRenta = null;
        if ($calculos['monto_gravado'] > 0) {
            $tramoRenta = RentaHelper::obtenerInformacionTramo($calculos['monto_gravado'], 'mensual');
        }

        return [
            'monto_bruto' => round($montoBruto, 2),
            'exencion_decreto_900' => PlanillaConstants::AGUINALDO_EXENTO_DECRETO_2023,
            'monto_exento' => $calculos['monto_exento'],
            'monto_gravado' => $calculos['monto_gravado'],
            'retencion_renta' => $calculos['retencion_renta'],
            'aguinaldo_neto' => $calculos['aguinaldo_neto'],
            'tramo_renta_aplicado' => $tramoRenta,
            'tipo_contrato' => $tipoContrato,
            'es_contrato_sin_prestaciones' => PlanillaConstants::esContratoSinPrestaciones($tipoContrato),
            'anio' => $anio
        ];
    }

    /**
     * Valida los cálculos de aguinaldo para asegurar que son correctos
     * Útil para pruebas y validaciones
     */
    public static function validarCalculoAguinaldo($montoBruto, $retencionRentaCalculada, $anio, $tipoContrato = null)
    {
        $calculosEsperados = self::calcularDeduccionesAguinaldo($montoBruto, $anio, $tipoContrato);
        $retencionEsperada = $calculosEsperados['retencion_renta'];

        $diferencia = abs($retencionRentaCalculada - $retencionEsperada);

        // Consideramos válido si la diferencia es menor a 0.01 (por redondeos)
        $esValido = $diferencia < 0.01;

        return [
            'valido' => $esValido,
            'diferencia' => round($diferencia, 2),
            'calculado' => round($retencionRentaCalculada, 2),
            'esperado' => round($retencionEsperada, 2),
            'calculos_completos' => $calculosEsperados
        ];
    }
}
