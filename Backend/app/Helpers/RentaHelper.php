<?php

namespace App\Helpers;

use App\Constants\PlanillaConstants;
use Illuminate\Support\Facades\Log;

class RentaHelper
{
    /**
     * Calcula la retención de renta según las nuevas tablas 2025
     * 
     * @param float $salarioGravado Salario gravado (después de deducciones ISSS y AFP)
     * @param string $tipoPlanilla Tipo de planilla (mensual, quincenal, semanal)
     * @return float Monto de retención calculado
     */
    public static function calcularRetencionRenta($salarioGravado, $tipoPlanilla = 'mensual')
    {
        // Obtener los tramos según el tipo de planilla
        $tramos = PlanillaConstants::getTramosRenta($tipoPlanilla);
        
        // Redondear el salario gravado a 2 decimales
        $salarioGravado = round($salarioGravado, 2);
        
        // 🔍 LOG ENTRADA
        Log::info('=== RENTAHELPER calcularRetencionRenta ===', [
            'salario_gravado' => $salarioGravado,
            'tipo_planilla' => $tipoPlanilla,
            'tramos_obtenidos' => $tramos
        ]);
        
        // Si el salario gravado es 0 o negativo, no hay retención
        if ($salarioGravado <= 0) {
            Log::info('=== RENTA = 0 (salario gravado <= 0) ===');
            return 0.00;
        }
        
        // Buscar el tramo correspondiente
        foreach ($tramos as $index => $tramo) {
            Log::info("=== EVALUANDO TRAMO {$index} ===", [
                'tramo' => $tramo,
                'salario_gravado' => $salarioGravado,
                'cumple_desde' => $salarioGravado >= $tramo['desde'],
                'cumple_hasta' => $salarioGravado <= $tramo['hasta']
            ]);
            
            if ($salarioGravado >= $tramo['desde'] && $salarioGravado <= $tramo['hasta']) {
                // Calcular la retención según la fórmula:
                // Cuota fija + ((Salario gravado - Sobre exceso) * Porcentaje)
                $exceso = $salarioGravado - $tramo['sobre_exceso'];
                $retencion = $tramo['cuota_fija'] + ($exceso * $tramo['porcentaje']);
                
                Log::info('=== TRAMO ENCONTRADO - CALCULANDO RENTA ===', [
                    'tramo_numero' => $index + 1,
                    'salario_gravado' => $salarioGravado,
                    'desde' => $tramo['desde'],
                    'hasta' => $tramo['hasta'],
                    'sobre_exceso' => $tramo['sobre_exceso'],
                    'cuota_fija' => $tramo['cuota_fija'],
                    'porcentaje' => $tramo['porcentaje'],
                    'exceso' => $exceso,
                    'calculo' => "{$tramo['cuota_fija']} + ({$exceso} * {$tramo['porcentaje']})",
                    'retencion_calculada' => round($retencion, 2)
                ]);
                
                return round($retencion, 2);
            }
        }
        
        // Si no se encuentra en ningún tramo, aplicar el último tramo
        $ultimoTramo = end($tramos);
        $exceso = $salarioGravado - $ultimoTramo['sobre_exceso'];
        $retencion = $ultimoTramo['cuota_fija'] + ($exceso * $ultimoTramo['porcentaje']);
        
        Log::info('=== APLICANDO ÚLTIMO TRAMO ===', [
            'ultimo_tramo' => $ultimoTramo,
            'exceso' => $exceso,
            'retencion' => round($retencion, 2)
        ]);
        
        return round($retencion, 2);
    }
    
    /**
     * Calcula el salario gravado para efectos de renta
     * 
     * @param float $salarioDevengado Salario devengado total
     * @param float $isssEmpleado Descuento ISSS del empleado
     * @param float $afpEmpleado Descuento AFP del empleado
     * @param string $tipoPlanilla Tipo de planilla
     * @return float Salario gravado para renta
     */
    public static function calcularSalarioGravado($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla = 'mensual')
    {
        // ✅ PASO 1: Calcular salario gravado básico (salario - seguridad social)
        $salarioGravado = $salarioDevengado - $isssEmpleado - $afpEmpleado;
        
        // ✅ PASO 2: La deducción de $1,600 se aplica SOLO cuando el SALARIO BRUTO ANUAL <= $9,100
        // NO cuando el salario gravado es <= $9,100
        $salarioBrutoAnual = self::extrapolarSalarioAnual($salarioDevengado, $tipoPlanilla);
        
        // 🔍 LOG PARA DEBUG
        Log::info('=== DEBUG calcularSalarioGravado ===', [
            'salario_devengado' => $salarioDevengado,
            'isss_empleado' => $isssEmpleado,
            'afp_empleado' => $afpEmpleado,
            'salario_gravado_basico' => $salarioGravado,
            'salario_bruto_anual' => $salarioBrutoAnual,
            'califica_deduccion' => $salarioBrutoAnual <= 9100.00
        ]);
        
        // ✅ APLICAR DEDUCCIÓN SOLO SI EL SALARIO BRUTO ANUAL <= $9,100
        if ($salarioBrutoAnual <= 9100.00) {
            $deduccionProporcional = self::calcularDeduccionProporcional($tipoPlanilla);
            $salarioGravadoConDeduccion = max(0, $salarioGravado - $deduccionProporcional);
            
            Log::info('=== APLICANDO DEDUCCIÓN DE $1,600 ===', [
                'deduccion_proporcional' => $deduccionProporcional,
                'salario_gravado_antes' => $salarioGravado,
                'salario_gravado_despues' => $salarioGravadoConDeduccion
            ]);
            
            return round($salarioGravadoConDeduccion, 2);
        }
        
        // ✅ NO APLICA DEDUCCIÓN
        Log::info('=== NO APLICA DEDUCCIÓN (salario bruto anual > $9,100) ===', [
            'salario_gravado_final' => $salarioGravado
        ]);
        
        return round($salarioGravado, 2);
    }

    private static function extrapolarSalarioAnual($salario, $tipoPlanilla)
    {
        switch ($tipoPlanilla) {
            case 'quincenal':
                return $salario * 24; // 24 quincenas al año
            case 'semanal':
                return $salario * 52; // 52 semanas al año
            default: // mensual
                return $salario * 12; // 12 meses al año
        }
    }

    private static function calcularDeduccionProporcional($tipoPlanilla)
    {
        $deduccionAnual = PlanillaConstants::DEDUCCION_EMPLEADOS_ASALARIADOS; // $1,600
        
        switch ($tipoPlanilla) {
            case 'quincenal':
                return round($deduccionAnual / 24, 2); // $66.67 quincenal
            case 'semanal':
                return round($deduccionAnual / 52, 2); // $30.77 semanal
            default: // mensual
                return round($deduccionAnual / 12, 2); // $133.33 mensual
        }
    }
    
    /**
     * Calcula la retención de renta para recálculo (junio/diciembre)
     * 
     * @param float $salarioAcumulado Salario acumulado en el período
     * @param string $tipoRecalculo 'junio' o 'diciembre'
     * @param float $retencionesAnteriores Retenciones ya efectuadas
     * @return float Retención adicional a efectuar
     */
    public static function calcularRecalculoRenta($salarioAcumulado, $tipoRecalculo = 'junio', $retencionesAnteriores = 0)
    {
        $tramos = self::getTramosRecalculo($tipoRecalculo);
        
        // Redondear el salario acumulado a 2 decimales
        $salarioAcumulado = round($salarioAcumulado, 2);
        
        // Si el salario acumulado es 0 o negativo, no hay retención
        if ($salarioAcumulado <= 0) {
            return 0.00;
        }
        
        // Buscar el tramo correspondiente
        foreach ($tramos as $tramo) {
            if ($salarioAcumulado >= $tramo['desde'] && $salarioAcumulado <= $tramo['hasta']) {
                // Calcular la retención total según la fórmula del recálculo
                $exceso = $salarioAcumulado - $tramo['sobre_exceso'];
                $retencionTotal = $tramo['cuota_fija'] + ($exceso * $tramo['porcentaje']);
                
                // Restar las retenciones ya efectuadas
                $retencionAdicional = $retencionTotal - $retencionesAnteriores;
                
                return round(max(0, $retencionAdicional), 2);
            }
        }
        
        // Si no se encuentra en ningún tramo, aplicar el último tramo
        $ultimoTramo = end($tramos);
        $exceso = $salarioAcumulado - $ultimoTramo['sobre_exceso'];
        $retencionTotal = $ultimoTramo['cuota_fija'] + ($exceso * $ultimoTramo['porcentaje']);
        $retencionAdicional = $retencionTotal - $retencionesAnteriores;
        
        return round(max(0, $retencionAdicional), 2);
    }
    
    /**
     * Obtiene los tramos para recálculo según el tipo
     */
    private static function getTramosRecalculo($tipoRecalculo)
    {
        if ($tipoRecalculo === 'diciembre') {
            return [
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_1_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_1_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_1_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_1_DESDE,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_1_CUOTA_FIJA
                ],
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_2_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_2_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_2_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_2_SOBRE_EXCESO,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_2_CUOTA_FIJA
                ],
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_3_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_3_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_3_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_3_SOBRE_EXCESO,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_3_CUOTA_FIJA
                ],
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_4_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_4_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_4_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_4_SOBRE_EXCESO,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_DICIEMBRE_TRAMO_4_CUOTA_FIJA
                ]
            ];
        } else {
            // Junio por defecto
            return [
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_1_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_1_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_1_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_1_DESDE,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_1_CUOTA_FIJA
                ],
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_2_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_2_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_2_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_2_SOBRE_EXCESO,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_2_CUOTA_FIJA
                ],
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_3_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_3_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_3_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_3_SOBRE_EXCESO,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_3_CUOTA_FIJA
                ],
                [
                    'desde' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_4_DESDE,
                    'hasta' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_4_HASTA,
                    'porcentaje' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_4_PORCENTAJE,
                    'sobre_exceso' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_4_SOBRE_EXCESO,
                    'cuota_fija' => PlanillaConstants::RENTA_RECALCULO_JUNIO_TRAMO_4_CUOTA_FIJA
                ]
            ];
        }
    }
    
    /**
     * Determina si un empleado califica para la deducción de $1,600 anuales
     * 
     * @param float $salarioAnual Salario anual del empleado
     * @return bool
     */
    public static function calificaDeduccionEmpleadoAsalariado($salarioAnual)
    {
        return $salarioAnual <= 9100.00;
    }
    
    /**
     * Obtiene información detallada del tramo aplicado
     * 
     * @param float $salarioGravado
     * @param string $tipoPlanilla
     * @return array
     */
    public static function obtenerInformacionTramo($salarioGravado, $tipoPlanilla = 'mensual')
    {
        $tramos = PlanillaConstants::getTramosRenta($tipoPlanilla);
        $salarioGravado = round($salarioGravado, 2);
        
        foreach ($tramos as $index => $tramo) {
            if ($salarioGravado >= $tramo['desde'] && $salarioGravado <= $tramo['hasta']) {
                return [
                    'tramo_numero' => $index + 1,
                    'desde' => $tramo['desde'],
                    'hasta' => $tramo['hasta'],
                    'porcentaje' => $tramo['porcentaje'] * 100, // Convertir a porcentaje
                    'sobre_exceso' => $tramo['sobre_exceso'],
                    'cuota_fija' => $tramo['cuota_fija'],
                    'exceso' => $salarioGravado - $tramo['sobre_exceso'],
                    'retencion_calculada' => self::calcularRetencionRenta($salarioGravado, $tipoPlanilla)
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Valida que el cálculo de renta sea correcto según las nuevas normativas
     * 
     * @param float $salarioDevengado
     * @param float $isssEmpleado
     * @param float $afpEmpleado
     * @param string $tipoPlanilla
     * @return array
     */
    public static function validarCalculoRenta($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla = 'mensual')
    {
        $salarioGravado = self::calcularSalarioGravado($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla);
        $retencion = self::calcularRetencionRenta($salarioGravado, $tipoPlanilla);
        $infoTramo = self::obtenerInformacionTramo($salarioGravado, $tipoPlanilla);
        
        return [
            'salario_devengado' => round($salarioDevengado, 2),
            'isss_empleado' => round($isssEmpleado, 2),
            'afp_empleado' => round($afpEmpleado, 2),
            'salario_gravado' => $salarioGravado,
            'retencion_renta' => $retencion,
            'tramo_aplicado' => $infoTramo,
            'tipo_planilla' => $tipoPlanilla,
            'decreto_aplicado' => 'Decreto No. 10 - Abril 2025'
        ];
    }
}