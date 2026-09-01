<?php

namespace App\Services\Planilla;

use App\Constants\PlanillaConstants;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\PrestamoEmpleado;
use App\Models\Planilla\PrestamoMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanillaDetalleService
{
    protected $configuracionPlanillaService;

    public function __construct(ConfiguracionPlanillaService $configuracionPlanillaService)
    {
        $this->configuracionPlanillaService = $configuracionPlanillaService;
    }

    /**
     * Actualizar un detalle de planilla
     */
    public function actualizar($id, array $datos)
    {
        DB::beginTransaction();
        try {
            $detalle = PlanillaDetalle::findOrFail($id);

            // Verificar que la planilla esté en estado editable
            if ($detalle->planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                throw new \Exception('No se puede modificar una planilla aprobada o pagada');
            }

            $this->recalcular($detalle, $datos);
            $detalle->save();

            $this->sincronizarAbonosPrestamos($detalle, $datos['abonos_prestamos'] ?? []);

            DB::commit();

            return $detalle->fresh(['empleado', 'planilla.empresa', 'abonosPrestamo']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando detalle de planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mismo cálculo que actualizar() pero sin persistir: alimenta la vista previa del frontend.
     */
    public function previsualizar($id, array $datos): PlanillaDetalle
    {
        $detalle = PlanillaDetalle::findOrFail($id);
        $this->recalcular($detalle, $datos);

        return $detalle;
    }

    /**
     * Aplica los datos editables y recalcula ingresos, deducciones y neto (sin guardar).
     * Las deducciones de ley las resuelve el motor del país en ConfiguracionPlanillaService.
     */
    private function recalcular(PlanillaDetalle $detalle, array $datos): void
    {
        $planilla = $detalle->planilla;
        $tipoContrato = $detalle->empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;

        $this->aplicarDatosEditables($detalle, $datos, $tipoContrato);

        $diasReferencia = $this->diasReferencia($planilla->tipo_planilla);
        $salarioBaseAjustado = $this->salarioBaseAjustado($detalle, $planilla->tipo_planilla, $tipoContrato);

        $detalle->salario_devengado = round(
            $tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA
                || $tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES
                ? $salarioBaseAjustado
                : ($salarioBaseAjustado / $diasReferencia) * $detalle->dias_laborados,
            2
        );

        $this->calcularHorasExtra($detalle, $salarioBaseAjustado / $diasReferencia / 8, $planilla->id_empresa);

        // Los abonos "sin retención" se pagan pero no forman parte de la base gravable
        $abonos = (float) ($detalle->abonos ?? 0);
        $abonosGravables = $detalle->abonos_sin_retencion === false ? $abonos : 0;

        $configDescuentos = $detalle->empleado->configuracion_descuentos ?? [];

        $datosEmpleado = [
            'salario_base' => $detalle->salario_base,
            'salario_devengado' => $detalle->salario_devengado,
            'dias_laborados' => $detalle->dias_laborados,
            'horas_extra' => $detalle->horas_extra,
            'monto_horas_extra' => $detalle->monto_horas_extra,
            'comisiones' => $detalle->comisiones,
            'bonificaciones' => $detalle->bonificaciones,
            'otros_ingresos' => $detalle->otros_ingresos + $abonosGravables,
            'prestamos' => $detalle->prestamos,
            'anticipos' => $detalle->anticipos,
            'otros_descuentos' => $detalle->otros_descuentos,
            'descuentos_judiciales' => $detalle->descuentos_judiciales,
            'tipo_contrato' => $tipoContrato,
            // Cada motor de país toma lo que necesita de aquí
            'es_pensionado' => (bool) ($detalle->empleado->es_pensionado ?? false),
            'configuracion_descuentos' => $configDescuentos,
            'aplicar_isss' => ($configDescuentos['aplicar_isss'] ?? true) !== false,
            'aplicar_afp' => ($configDescuentos['aplicar_afp'] ?? true) !== false,
            'tiene_conyuge_dependiente' => (bool) ($detalle->empleado->tiene_conyuge_dependiente ?? false),
            'cantidad_hijos_dependientes' => (int) ($detalle->empleado->cantidad_hijos_dependientes ?? 0),
        ];

        $resultados = $this->configuracionPlanillaService->calcularConceptos(
            $datosEmpleado,
            $planilla->id_empresa,
            $planilla->tipo_planilla
        );

        $detalle->pais_configuracion = $resultados['pais_configuracion'] ?? null;
        $detalle->conceptos_personalizados = $resultados['conceptos_personalizados'] ?? null;
        $detalle->isss_empleado = $resultados['isss_empleado'] ?? 0;
        $detalle->isss_patronal = $resultados['isss_patronal'] ?? 0;
        $detalle->afp_empleado = $resultados['afp_empleado'] ?? 0;
        $detalle->afp_patronal = $resultados['afp_patronal'] ?? 0;
        $detalle->renta = $resultados['renta'] ?? 0;

        // El motor ya sumó préstamos, anticipos y demás descuentos manuales
        $detalle->total_descuentos = round($resultados['totales']['total_deducciones'] ?? 0, 2);
        $detalle->total_ingresos = round(($resultados['totales']['total_ingresos'] ?? 0) + $abonos - $abonosGravables, 2);
        $detalle->sueldo_neto = round($detalle->total_ingresos - $detalle->total_descuentos, 2);
    }

    private function aplicarDatosEditables(PlanillaDetalle $detalle, array $datos, int $tipoContrato): void
    {
        $campos = [
            'dias_laborados' => $this->diasReferencia($detalle->planilla->tipo_planilla),
            'horas_extra' => 0,
            'comisiones' => 0,
            'bonificaciones' => 0,
            'otros_ingresos' => 0,
            'viaticos' => 0,
            'abonos' => 0,
            'prestamos' => 0,
            'anticipos' => 0,
            'otros_descuentos' => 0,
            'descuentos_judiciales' => 0,
        ];

        foreach ($campos as $campo => $porDefecto) {
            if (array_key_exists($campo, $datos)) {
                $detalle->$campo = $datos[$campo] ?? $porDefecto;
            } else {
                $detalle->$campo = $detalle->$campo ?? $porDefecto;
            }
        }

        if (array_key_exists('abonos_sin_retencion', $datos)) {
            $detalle->abonos_sin_retencion = (bool) $datos['abonos_sin_retencion'];
        }

        if (array_key_exists('detalle_otras_deducciones', $datos)) {
            $detalle->detalle_otras_deducciones = $datos['detalle_otras_deducciones'];
        }

        if (array_key_exists('detalle_horas_extra', $datos)) {
            $detalle->detalle_horas_extra = $datos['detalle_horas_extra'];
        }

        // Solo los contratos por obra facturan un monto distinto cada período
        if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA && ($datos['salario_base'] ?? null) !== null) {
            $detalle->salario_base = $datos['salario_base'];
        }
    }

    /**
     * Horas extra: si llega detalle por tipo (El Salvador) se liquida por recargo legal;
     * si no, se usa el recargo configurado del país.
     */
    private function calcularHorasExtra(PlanillaDetalle $detalle, float $valorHoraNormal, int $empresaId): void
    {
        $porTipo = $detalle->detalle_horas_extra;

        if (!is_array($porTipo) || $porTipo === []) {
            $detalle->monto_horas_extra = $detalle->horas_extra > 0
                ? round($detalle->horas_extra * $valorHoraNormal * $this->configuracionPlanillaService->factorHoraExtra($empresaId), 2)
                : 0;
            $detalle->detalle_horas_extra = null;

            return;
        }

        $diurna = (float) ($porTipo['diurna'] ?? 0);
        $nocturna = (float) ($porTipo['nocturna'] ?? 0);
        $diaDescanso = (float) ($porTipo['dia_descanso'] ?? 0);
        $diaAsueto = (float) ($porTipo['dia_asueto'] ?? 0);
        $diaDescansoDias = (int) ($porTipo['dia_descanso_dias'] ?? 0);
        if ($diaDescanso > 0 && $diaDescansoDias <= 0) {
            $diaDescansoDias = 1;
        }

        $detalle->horas_extra = round($diurna + $nocturna + $diaDescanso + $diaAsueto, 2);
        $detalle->monto_horas_extra = round(
            $diurna * $valorHoraNormal * 2            // Art. 169: 100% de recargo
            + $nocturna * $valorHoraNormal * 2.25     // Art. 168: 100% + 25% de nocturnidad
            + $diaDescanso * $valorHoraNormal * 1.5   // Art. 175: 50% de recargo
            + $diaDescansoDias * 8 * $valorHoraNormal // Art. 175: día compensatorio
            + $diaAsueto * $valorHoraNormal * 2,      // Art. 192: 100% de recargo
            2
        );
        $detalle->detalle_horas_extra = [
            'diurna' => $diurna,
            'nocturna' => $nocturna,
            'dia_descanso' => $diaDescanso,
            'dia_descanso_dias' => $diaDescansoDias,
            'dia_asueto' => $diaAsueto,
        ];
    }

    private function diasReferencia(?string $tipoPlanilla): int
    {
        return match ($tipoPlanilla) {
            'quincenal' => 15,
            'semanal' => 7,
            default => 30,
        };
    }

    private function salarioBaseAjustado(PlanillaDetalle $detalle, ?string $tipoPlanilla, int $tipoContrato): float
    {
        $salarioBase = (float) $detalle->salario_base;

        // Por obra: el salario base ya es el monto total del período
        if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
            return $salarioBase;
        }

        return PlanillaConstants::ajustarSalarioBasePorPeriodo($salarioBase, $tipoPlanilla);
    }

    /**
     * Rehace los abonos a préstamos de este detalle: revierte los anteriores y aplica los nuevos.
     */
    private function sincronizarAbonosPrestamos(PlanillaDetalle $detalle, $abonos): void
    {
        $idEmpresa = $detalle->planilla->id_empresa;

        $previos = PrestamoMovimiento::where('id_planilla_detalle', $detalle->id)
            ->where('tipo', PrestamoMovimiento::TIPO_ABONO_PLANILLA)
            ->get();

        foreach ($previos as $movimiento) {
            $prestamo = PrestamoEmpleado::where('id', $movimiento->id_prestamo)->where('id_empresa', $idEmpresa)->first();
            if ($prestamo) {
                $prestamo->saldo_actual = round((float) $prestamo->saldo_actual + (float) $movimiento->monto, 2);
                $prestamo->estado = PrestamoEmpleado::ESTADO_ACTIVO;
                $prestamo->save();
            }
            $movimiento->delete();
        }

        $montoPrestamos = (float) $detalle->prestamos;
        if (!is_array($abonos) || $abonos === [] || $montoPrestamos <= 0) {
            return;
        }

        $suma = round(array_sum(array_column($abonos, 'monto')), 2);
        if (abs($suma - $montoPrestamos) > 0.02) {
            throw new \Exception(
                'La suma de los abonos a préstamos (' . number_format($suma, 2) . ') debe coincidir con el monto total en Préstamos (' . number_format($montoPrestamos, 2) . ').'
            );
        }

        $planilla = $detalle->planilla;
        $descripcion = 'Descuento en planilla ' . ($planilla->tipo_planilla ?? '') . ' '
            . optional($planilla->fecha_inicio)->format('d/m/Y') . ' - ' . optional($planilla->fecha_fin)->format('d/m/Y');

        foreach ($abonos as $item) {
            $prestamo = PrestamoEmpleado::where('id', (int) ($item['id_prestamo'] ?? 0))
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$prestamo || $prestamo->id_empleado != $detalle->id_empleado) {
                continue;
            }

            $monto = min((float) ($item['monto'] ?? 0), (float) $prestamo->saldo_actual);
            if ($monto <= 0) {
                continue;
            }

            $nuevoSaldo = round((float) $prestamo->saldo_actual - $monto, 2);
            PrestamoMovimiento::create([
                'id_prestamo' => $prestamo->id,
                'tipo' => PrestamoMovimiento::TIPO_ABONO_PLANILLA,
                'monto' => $monto,
                'saldo_despues' => $nuevoSaldo,
                'descripcion' => $descripcion,
                'fecha' => $planilla->fecha_fin ?? now(),
                'id_planilla_detalle' => $detalle->id,
            ]);

            $prestamo->saldo_actual = $nuevoSaldo;
            $prestamo->estado = $nuevoSaldo <= 0 ? PrestamoEmpleado::ESTADO_LIQUIDADO : PrestamoEmpleado::ESTADO_ACTIVO;
            $prestamo->save();
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
