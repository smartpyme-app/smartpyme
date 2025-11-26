<?php

namespace App\Services\Planilla;

use App\Constants\PlanillaConstants;
use App\Models\Planilla\PlanillaDetalle;
use App\Helpers\RentaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanillaDetalleService
{
    /**
     * Actualizar un detalle de planilla
     */
    public function actualizar($id, array $datos)
    {
        DB::beginTransaction();
        try {
            $detalle = PlanillaDetalle::findOrFail($id);
            $planilla = $detalle->planilla;

            // Verificar que la planilla esté en estado editable
            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                throw new \Exception('No se puede modificar una planilla aprobada o pagada');
            }

            // Determinar días de referencia según tipo de planilla
            $diasReferencia = 30;
            $factorAjuste = 1;

            if ($planilla->tipo_planilla === 'quincenal') {
                $diasReferencia = 15;
                $factorAjuste = 2;
            } elseif ($planilla->tipo_planilla === 'semanal') {
                $diasReferencia = 7;
                $factorAjuste = 4.33;
            }

            // Verificar tipo de contrato
            $tipoContrato = $detalle->empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
            $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);

            // Actualizar campos básicos
            $detalle->dias_laborados = $datos['dias_laborados'] ?? $diasReferencia;
            $detalle->horas_extra = $datos['horas_extra'] ?? 0;
            $detalle->comisiones = $datos['comisiones'] ?? 0;
            $detalle->bonificaciones = $datos['bonificaciones'] ?? 0;
            $detalle->otros_ingresos = $datos['otros_ingresos'] ?? 0;
            $detalle->prestamos = $datos['prestamos'] ?? 0;
            $detalle->anticipos = $datos['anticipos'] ?? 0;
            $detalle->otros_descuentos = $datos['otros_descuentos'] ?? 0;
            $detalle->descuentos_judiciales = $datos['descuentos_judiciales'] ?? 0;
            $detalle->detalle_otras_deducciones = $datos['detalle_otras_deducciones'] ?? null;

            // Permitir editar salario_base solo para contratos por obra
            if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA && 
                isset($datos['salario_base']) && $datos['salario_base'] !== null) {
                $detalle->salario_base = $datos['salario_base'];
            }

            // Calcular salario devengado
            $salarioBaseMensual = $detalle->salario_base;

            if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
                $salarioDevengado = $salarioBaseMensual;
                $salarioBaseAjustado = $salarioBaseMensual;
            } elseif ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES) {
                if ($planilla->tipo_planilla === 'quincenal') {
                    $salarioDevengado = $salarioBaseMensual / 2;
                    $salarioBaseAjustado = $salarioBaseMensual / 2;
                } elseif ($planilla->tipo_planilla === 'semanal') {
                    $salarioDevengado = $salarioBaseMensual / 4.33;
                    $salarioBaseAjustado = $salarioBaseMensual / 4.33;
                } else {
                    $salarioDevengado = $salarioBaseMensual;
                    $salarioBaseAjustado = $salarioBaseMensual;
                }
            } else {
                $salarioBaseAjustado = $planilla->tipo_planilla !== 'mensual' ?
                    $salarioBaseMensual / $factorAjuste : $salarioBaseMensual;
                $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $detalle->dias_laborados;
            }

            $detalle->salario_devengado = round($salarioDevengado, 2);

            // Calcular monto de horas extra
            if ($detalle->horas_extra > 0) {
                $valorHoraNormal = $salarioBaseAjustado / $diasReferencia / 8;
                $detalle->monto_horas_extra = round($detalle->horas_extra * ($valorHoraNormal * 1.25), 2);
            } else {
                $detalle->monto_horas_extra = 0;
            }

            // Calcular total de ingresos
            $detalle->total_ingresos = round(
                $detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos,
                2
            );

            // Calcular deducciones según tipo de contrato
            if ($esContratoSinPrestaciones) {
                $detalle->isss_empleado = 0;
                $detalle->isss_patronal = 0;
                $detalle->afp_empleado = 0;
                $detalle->afp_patronal = 0;
            } else {
                $baseISSSEmpleado = min($detalle->total_ingresos, 1000);
                $detalle->isss_empleado = round($baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO, 2);
                $detalle->isss_patronal = round($baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO, 2);
                $detalle->afp_empleado = round($detalle->total_ingresos * PlanillaConstants::DESCUENTO_AFP_EMPLEADO, 2);
                $detalle->afp_patronal = round($detalle->total_ingresos * PlanillaConstants::DESCUENTO_AFP_PATRONO, 2);
            }

            // Calcular renta
            $salarioGravado = RentaHelper::calcularSalarioGravado(
                $detalle->total_ingresos,
                $detalle->isss_empleado,
                $detalle->afp_empleado,
                $planilla->tipo_planilla,
                $tipoContrato
            );

            $detalle->renta = RentaHelper::calcularRetencionRenta(
                $salarioGravado,
                $planilla->tipo_planilla,
                $tipoContrato
            );

            // Calcular total de deducciones
            $detalle->total_descuentos = round(
                $detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->otros_descuentos +
                $detalle->descuentos_judiciales,
                2
            );

            // Calcular sueldo neto
            $detalle->sueldo_neto = round($detalle->total_ingresos - $detalle->total_descuentos, 2);

            $detalle->save();

            DB::commit();

            return $detalle->fresh(['empleado', 'planilla.empresa']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando detalle de planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retirar un detalle de planilla
     */
    public function retirar($id)
    {
        try {
            $detalle = PlanillaDetalle::findOrFail($id);
            $detalle->update(['estado' => 0]);

            return $detalle;
        } catch (\Exception $e) {
            Log::error('Error al retirar detalle de planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Incluir un detalle de planilla
     */
    public function incluir($id)
    {
        try {
            $detalle = PlanillaDetalle::findOrFail($id);
            $detalle->update(['estado' => 2]);

            return $detalle;
        } catch (\Exception $e) {
            Log::error('Error al incluir detalle de planilla: ' . $e->getMessage());
            throw $e;
        }
    }
}

