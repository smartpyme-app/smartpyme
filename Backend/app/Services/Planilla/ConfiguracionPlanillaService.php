<?php

namespace App\Services\Planilla;

use App\Models\EmpresaConfiguracionPlanilla;
use App\Helpers\RentaHelper;

class ConfiguracionPlanillaService
{
    /**
     * Calcular todos los conceptos para un empleado usando configuración dinámica
     */
    public function calcularConceptos($datosEmpleado, $empresaId, $tipoPlanilla = 'mensual', $fecha = null)
    {
        // Obtener configuración de la empresa (o crear si no existe)
        $configuracion = EmpresaConfiguracionPlanilla::obtenerOCrearConfiguracion($empresaId);
        
        $conceptos = $configuracion->getConceptos();
        $resultados = [];

        // Preparar datos base para cálculos
        $datosBase = $this->prepararDatosBase($datosEmpleado, $configuracion, $tipoPlanilla);

        // Calcular conceptos en orden específico (primero ingresos, luego deducciones)
        $conceptosOrdenados = $this->ordenarConceptos($conceptos);

        foreach ($conceptosOrdenados as $codigo => $concepto) {
            $resultados[$codigo] = $this->calcularConcepto(
                $codigo,
                $concepto,
                $datosBase,
                $resultados,
                $tipoPlanilla
            );
        }

        // Calcular totales finales
        $resultados['totales'] = $this->calcularTotales($resultados, $conceptos);

        return $resultados;
    }

    /**
     * Preparar datos base para los cálculos
     */
    private function prepararDatosBase($datosEmpleado, $configuracion, $tipoPlanilla)
    {
        $configGeneral = $configuracion->getConfiguracionesGenerales();
        
        return [
            'salario_base' => $datosEmpleado['salario_base'] ?? 0,
            'salario_devengado' => $datosEmpleado['salario_devengado'] ?? $datosEmpleado['salario_base'] ?? 0,
            'dias_laborados' => $datosEmpleado['dias_laborados'] ?? $configGeneral['dias_mes'] ?? 30,
            'horas_extra' => $datosEmpleado['horas_extra'] ?? 0,
            'monto_horas_extra' => $datosEmpleado['monto_horas_extra'] ?? 0,
            'comisiones' => $datosEmpleado['comisiones'] ?? 0,
            'bonificaciones' => $datosEmpleado['bonificaciones'] ?? 0,
            'otros_ingresos' => $datosEmpleado['otros_ingresos'] ?? 0,
            'prestamos' => $datosEmpleado['prestamos'] ?? 0,
            'anticipos' => $datosEmpleado['anticipos'] ?? 0,
            'otros_descuentos' => $datosEmpleado['otros_descuentos'] ?? 0,
            'descuentos_judiciales' => $datosEmpleado['descuentos_judiciales'] ?? 0,
            'tipo_contrato' => $datosEmpleado['tipo_contrato'] ?? null,
            'tipo_planilla' => $tipoPlanilla,
            'configuracion_general' => $configGeneral
        ];
    }

    /**
     * Ordenar conceptos por prioridad de cálculo
     */
    private function ordenarConceptos($conceptos)
    {
        // Ordenar por campo 'orden' si existe, sino por tipo
        $prioridades = [
            'isss_empleado' => 1,
            'isss_patronal' => 2,
            'afp_empleado' => 3,
            'afp_patronal' => 4,
            'renta' => 5, // Renta debe calcularse después de ISSS y AFP
            'horas_extra' => 6
        ];

        uksort($conceptos, function($a, $b) use ($prioridades, $conceptos) {
            $ordenA = $conceptos[$a]['orden'] ?? $prioridades[$a] ?? 999;
            $ordenB = $conceptos[$b]['orden'] ?? $prioridades[$b] ?? 999;
            return $ordenA <=> $ordenB;
        });

        return $conceptos;
    }

    /**
     * Calcular un concepto individual
     */
    private function calcularConcepto($codigo, $concepto, $datosBase, $resultadosAnteriores, $tipoPlanilla)
    {
        $tipo = $concepto['tipo'] ?? 'porcentaje';
        $baseCalculo = $this->obtenerBaseCalculo($concepto['base_calculo'] ?? 'salario_devengado', $datosBase, $resultadosAnteriores);

        switch ($tipo) {
            case 'porcentaje':
                return $this->calcularPorcentaje($codigo, $concepto, $baseCalculo, $datosBase, $resultadosAnteriores, $tipoPlanilla);

            case 'monto_fijo':
                return $concepto['valor'] ?? 0;

            case 'sistema_existente':
                return $this->calcularSistemaExistente($codigo, $concepto, $datosBase, $resultadosAnteriores, $tipoPlanilla);

            case 'tabla_progresiva':
                return $this->calcularTablaProgresiva($concepto, $baseCalculo);

            case 'escala_antiguedad':
                return $this->calcularEscalaAntiguedad($concepto, $baseCalculo, $datosBase);

            case 'dias_fijos':
                return $this->calcularDiasFijos($concepto, $baseCalculo, $datosBase);

            default:
                return 0;
        }
    }

    /**
     * Obtener base de cálculo según el tipo especificado
     */
    private function obtenerBaseCalculo($tipoBase, $datosBase, $resultadosAnteriores)
    {
        switch ($tipoBase) {
            case 'salario_base':
                return $datosBase['salario_base'];

            case 'salario_devengado':
                return $datosBase['salario_devengado'];

            case 'salario_gravable':
                // Salario después de deducciones que aplican a renta
                $salario = $datosBase['salario_devengado'];
                $isss = $resultadosAnteriores['isss_empleado'] ?? 0;
                $afp = $resultadosAnteriores['afp_empleado'] ?? 0;
                return $salario - $isss - $afp;

            case 'salario_hora':
                $salario = $datosBase['salario_devengado'];
                $diasMes = $datosBase['configuracion_general']['dias_mes'] ?? 30;
                $horasDia = $datosBase['configuracion_general']['horas_dia'] ?? 8;
                return $salario / $diasMes / $horasDia;

            case 'total_ingresos':
                return $datosBase['salario_devengado'] + 
                       $datosBase['monto_horas_extra'] + 
                       $datosBase['comisiones'] + 
                       $datosBase['bonificaciones'] + 
                       $datosBase['otros_ingresos'];

            default:
                return $datosBase['salario_devengado'];
        }
    }

    /**
     * Calcular concepto de porcentaje
     */
    private function calcularPorcentaje($codigo, $concepto, $baseCalculo, $datosBase, $resultadosAnteriores, $tipoPlanilla)
    {
        $valor = ($concepto['valor'] ?? 0) / 100; // Convertir porcentaje a decimal
        $topeMaximo = $concepto['tope_maximo'] ?? null;

        // Aplicar tope máximo si existe
        if ($topeMaximo !== null) {
            $baseCalculo = min($baseCalculo, $topeMaximo);
        }

        // Casos especiales
        switch ($codigo) {
            case 'horas_extra':
                $horasExtra = $datosBase['horas_extra'];
                $valorHora = $this->obtenerBaseCalculo('salario_hora', $datosBase, $resultadosAnteriores);
                return $horasExtra * $valorHora * (1 + $valor); // Valor normal + recargo

            default:
                return round($baseCalculo * $valor, 2);
        }
    }

    /**
     * Calcular usando sistema existente (principalmente para renta)
     */
    private function calcularSistemaExistente($codigo, $concepto, $datosBase, $resultadosAnteriores, $tipoPlanilla)
    {
        switch ($codigo) {
            case 'renta':
                $salarioDevengado = $datosBase['salario_devengado'];
                $isssEmpleado = $resultadosAnteriores['isss_empleado'] ?? 0;
                $afpEmpleado = $resultadosAnteriores['afp_empleado'] ?? 0;
                $tipoContrato = $datosBase['tipo_contrato'];

                // Usar el RentaHelper existente
                return RentaHelper::calcularRetencionRenta(
                    RentaHelper::calcularSalarioGravado($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla, $tipoContrato),
                    $tipoPlanilla,
                    $tipoContrato
                );

            default:
                return 0;
        }
    }

    /**
     * Calcular tabla progresiva (para países con sistemas diferentes)
     */
    private function calcularTablaProgresiva($concepto, $baseCalculo)
    {
        $tabla = $concepto['tabla'] ?? [];
        
        foreach ($tabla as $tramo) {
            $desde = $tramo['desde'];
            $hasta = $tramo['hasta'];
            
            if ($baseCalculo >= $desde && ($hasta === null || $baseCalculo <= $hasta)) {
                $exceso = $baseCalculo - $desde;
                $impuesto = ($exceso * ($tramo['porcentaje'] / 100)) + ($tramo['cuota_fija'] ?? 0);
                return round($impuesto, 2);
            }
        }
        
        return 0;
    }

    /**
     * Calcular escala por antigüedad (aguinaldo, vacaciones)
     */
    private function calcularEscalaAntiguedad($concepto, $baseCalculo, $datosBase)
    {
        // Implementar cuando sea necesario para otros países
        return 0;
    }

    /**
     * Calcular días fijos (vacaciones)
     */
    private function calcularDiasFijos($concepto, $baseCalculo, $datosBase)
    {
        $dias = $concepto['dias'] ?? 0;
        $diasMes = $datosBase['configuracion_general']['dias_mes'] ?? 30;
        return round(($baseCalculo / $diasMes) * $dias, 2);
    }

    /**
     * Calcular totales finales
     */
    private function calcularTotales($resultados, $conceptos)
    {
        $totalIngresos = 0;
        $totalDeducciones = 0;
        $aportesPAtronales = 0;

        foreach ($resultados as $codigo => $valor) {
            if (isset($conceptos[$codigo])) {
                $concepto = $conceptos[$codigo];
                
                if ($concepto['es_deduccion'] ?? false) {
                    $totalDeducciones += $valor;
                } else {
                    // Si no es deducción y no es patronal, es ingreso
                    if (!str_contains($codigo, '_patronal')) {
                        $totalIngresos += $valor;
                    } else {
                        $aportesPAtronales += $valor;
                    }
                }
            }
        }

        return [
            'total_ingresos' => round($totalIngresos, 2),
            'total_deducciones' => round($totalDeducciones, 2),
            'sueldo_neto' => round($totalIngresos - $totalDeducciones, 2),
            'aportes_patronales' => round($aportesPAtronales, 2)
        ];
    }

    /**
     * Método de conveniencia para mantener compatibilidad con sistema actual
     */
    public function calcularDetalleCompatible($empleado, $planilla)
    {
        $datosEmpleado = [
            'salario_base' => $empleado->salario_base ?? 0,
            'salario_devengado' => $empleado->salario_devengado ?? 0,
            'dias_laborados' => $empleado->dias_laborados ?? 30,
            'horas_extra' => $empleado->horas_extra ?? 0,
            'monto_horas_extra' => $empleado->monto_horas_extra ?? 0,
            'comisiones' => $empleado->comisiones ?? 0,
            'bonificaciones' => $empleado->bonificaciones ?? 0,
            'otros_ingresos' => $empleado->otros_ingresos ?? 0,
            'prestamos' => $empleado->prestamos ?? 0,
            'anticipos' => $empleado->anticipos ?? 0,
            'otros_descuentos' => $empleado->otros_descuentos ?? 0,
            'descuentos_judiciales' => $empleado->descuentos_judiciales ?? 0,
            'tipo_contrato' => $empleado->tipo_contrato ?? null,
        ];

        return $this->calcularConceptos(
            $datosEmpleado, 
            $planilla->id_empresa, 
            $planilla->tipo_planilla ?? 'mensual'
        );
    }

    /**
     * Validar configuración antes de aplicar
     */
    public function validarConfiguracion($empresaId)
    {
        try {
            $configuracion = EmpresaConfiguracionPlanilla::obtenerConfiguracion($empresaId);
            
            if (!$configuracion) {
                return ['valida' => false, 'mensaje' => 'No existe configuración para la empresa'];
            }

            $configuracion->validarConfiguracion();
            
            return ['valida' => true, 'mensaje' => 'Configuración válida'];
            
        } catch (\Exception $e) {
            return ['valida' => false, 'mensaje' => $e->getMessage()];
        }
    }
}