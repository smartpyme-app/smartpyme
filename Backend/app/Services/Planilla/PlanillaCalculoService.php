<?php

namespace App\Services\Planilla;

use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Helpers\RentaHelper;
use Illuminate\Support\Facades\Log;

class PlanillaCalculoService
{
    /**
     * Recalcular renta para una planilla (junio/diciembre)
     */
    public function recalcularRenta($planillaId)
    {
        try {
            $planilla = Planilla::findOrFail($planillaId);

            // Determinar el tipo de recálculo
            $mesActual = date('n');
            $tipoRecalculo = ($mesActual >= 6 && $mesActual <= 11) ? 'junio' : 'diciembre';

            // Obtener todos los detalles de la planilla
            $detalles = PlanillaDetalle::where('id_planilla', $planillaId)
                ->where('estado', '!=', 0)
                ->get();

            $recalculosAplicados = 0;

            foreach ($detalles as $detalle) {
                // Obtener el salario acumulado del empleado en el año
                $salarioAcumulado = $this->obtenerSalarioAcumuladoAnual(
                    $detalle->id_empleado,
                    $planilla->anio,
                    $tipoRecalculo
                );

                // Obtener retenciones anteriores
                $retencionesAnteriores = $this->obtenerRetencionesAnteriores(
                    $detalle->id_empleado,
                    $planilla->anio,
                    $tipoRecalculo
                );

                // Calcular recálculo
                $recalculo = RentaHelper::calcularRecalculoRenta(
                    $salarioAcumulado,
                    $tipoRecalculo,
                    $retencionesAnteriores
                );

                if ($recalculo > 0) {
                    // Aplicar el recálculo sumándolo a la renta actual
                    $detalle->renta += $recalculo;
                    $detalle->total_descuentos += $recalculo;
                    $detalle->sueldo_neto -= $recalculo;

                    // Redondear valores
                    $detalle->renta = round($detalle->renta, 2);
                    $detalle->total_descuentos = round($detalle->total_descuentos, 2);
                    $detalle->sueldo_neto = round($detalle->sueldo_neto, 2);

                    $detalle->save();
                    $recalculosAplicados++;
                }
            }

            // Actualizar totales de la planilla
            $planilla->actualizarTotales();

            return [
                'tipo_recalculo' => $tipoRecalculo,
                'empleados_afectados' => $recalculosAplicados,
                'planilla' => $planilla->fresh()
            ];
        } catch (\Exception $e) {
            Log::error('Error en recálculo de renta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener detalle del cálculo de renta para un detalle
     */
    public function obtenerDetalleCalculoRenta($detalleId)
    {
        try {
            $detalle = PlanillaDetalle::with(['empleado', 'planilla'])->findOrFail($detalleId);

            $totalIngresos = $detalle->total_ingresos;
            $isssEmpleado = $detalle->isss_empleado;
            $afpEmpleado = $detalle->afp_empleado;

            // Calcular usando RentaHelper
            $salarioGravado = RentaHelper::calcularSalarioGravado(
                $totalIngresos,
                $isssEmpleado,
                $afpEmpleado,
                $detalle->planilla->tipo_planilla,
                $detalle->empleado->tipo_contrato ?? null
            );

            $retencionRenta = RentaHelper::calcularRetencionRenta(
                $salarioGravado,
                $detalle->planilla->tipo_planilla,
                $detalle->empleado->tipo_contrato ?? null
            );

            // Obtener información del tramo
            $informacionTramo = RentaHelper::obtenerInformacionTramo(
                $salarioGravado,
                $detalle->planilla->tipo_planilla
            );

            return [
                'empleado' => [
                    'nombres' => $detalle->empleado->nombres,
                    'apellidos' => $detalle->empleado->apellidos,
                    'codigo' => $detalle->empleado->codigo
                ],
                'calculos' => [
                    'total_ingresos' => $totalIngresos,
                    'isss_empleado' => $isssEmpleado,
                    'afp_empleado' => $afpEmpleado,
                    'salario_gravado' => $salarioGravado,
                    'retencion_renta' => $retencionRenta,
                    'tipo_planilla' => $detalle->planilla->tipo_planilla
                ],
                'tramo_aplicado' => $informacionTramo,
                'decreto_aplicado' => 'Decreto No. 10 - Abril 2025'
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo detalle de cálculo de renta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validar cálculo de renta
     */
    public function validarCalculoRenta($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla)
    {
        try {
            $validacion = RentaHelper::validarCalculoRenta(
                $salarioDevengado,
                $isssEmpleado,
                $afpEmpleado,
                $tipoPlanilla
            );

            return $validacion;
        } catch (\Exception $e) {
            Log::error('Error en validación de cálculo de renta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener salario acumulado anual de un empleado
     */
    private function obtenerSalarioAcumuladoAnual($empleadoId, $anio, $tipoRecalculo)
    {
        $mesLimite = ($tipoRecalculo === 'junio') ? 6 : 12;

        $salarioAcumulado = PlanillaDetalle::join('planillas', 'planilla_detalles.id_planilla', '=', 'planillas.id')
            ->where('planilla_detalles.id_empleado', $empleadoId)
            ->where('planillas.anio', $anio)
            ->where('planillas.mes', '<=', $mesLimite)
            ->where('planilla_detalles.estado', '!=', 0)
            ->sum('planilla_detalles.salario_devengado');

        return $salarioAcumulado;
    }

    /**
     * Obtener retenciones anteriores de un empleado
     */
    private function obtenerRetencionesAnteriores($empleadoId, $anio, $tipoRecalculo)
    {
        $mesLimite = ($tipoRecalculo === 'junio') ? 5 : 11; // Hasta el mes anterior al recálculo

        $retenciones = PlanillaDetalle::join('planillas', 'planilla_detalles.id_planilla', '=', 'planillas.id')
            ->where('planilla_detalles.id_empleado', $empleadoId)
            ->where('planillas.anio', $anio)
            ->where('planillas.mes', '<=', $mesLimite)
            ->where('planilla_detalles.estado', '!=', 0)
            ->sum('planilla_detalles.renta');

        return $retenciones;
    }
}

