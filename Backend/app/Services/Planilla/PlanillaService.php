<?php

namespace App\Services\Planilla;

use App\Constants\PlanillaConstants;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\Empleado;
use App\Services\Planilla\ConfiguracionPlanillaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanillaService
{
    protected $configuracionPlanillaService;

    public function __construct(ConfiguracionPlanillaService $configuracionPlanillaService)
    {
        $this->configuracionPlanillaService = $configuracionPlanillaService;
    }

    /**
     * Obtener lista de planillas con filtros
     */
    public function listar($filtros = [])
    {
        $query = Planilla::with(['detalles.empleado'])
            ->where('id_empresa', auth()->user()->id_empresa)
            ->where('id_sucursal', auth()->user()->id_sucursal);

        if (isset($filtros['anio'])) {
            $query->where('anio', $filtros['anio']);
        }

        if (isset($filtros['mes'])) {
            $query->where('mes', $filtros['mes']);
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (isset($filtros['tipo_planilla'])) {
            $query->where('tipo_planilla', $filtros['tipo_planilla']);
        }

        if (isset($filtros['buscador'])) {
            $busqueda = $filtros['buscador'];
            $query->whereHas('detalles.empleado', function ($q) use ($busqueda) {
                $q->where('nombres', 'LIKE', "%$busqueda%")
                    ->orWhere('apellidos', 'LIKE', "%$busqueda%")
                    ->orWhere('codigo', 'LIKE', "%$busqueda%");
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Crear una nueva planilla
     */
    public function crear(array $datos)
    {
        DB::beginTransaction();
        try {
            $fechaInicio = Carbon::parse($datos['fecha_inicio']);
            $fechaFin = Carbon::parse($datos['fecha_fin']);

            // Validar que no exista una planilla para ese período
            $planillaExistente = Planilla::where('id_empresa', auth()->user()->id_empresa)
                ->where('id_sucursal', auth()->user()->id_sucursal)
                ->where('fecha_inicio', $fechaInicio)
                ->where('fecha_fin', $fechaFin)
                ->first();

            if ($planillaExistente) {
                throw new \Exception('Ya existe una planilla para este período');
            }

            // Generar código único
            $codigo = 'PLA-' . $fechaInicio->format('Ym') .
                ($fechaInicio->day <= 15 ? '1-' : '2-') .
                auth()->user()->id_sucursal;

            // Crear nueva planilla
            $planilla = Planilla::create([
                'codigo' => $codigo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'tipo_planilla' => $datos['tipo_planilla'],
                'estado' => PlanillaConstants::PLANILLA_BORRADOR,
                'id_empresa' => auth()->user()->id_empresa,
                'id_sucursal' => auth()->user()->id_sucursal,
                'anio' => $fechaInicio->year,
                'mes' => $fechaInicio->month,
                'total_salarios' => 0,
                'total_deducciones' => 0,
                'total_neto' => 0,
                'total_aportes_patronales' => 0
            ]);

            $empleadosIncluidos = 0;
            $empleadosOmitidos = 0;

            // Crear detalles desde template o empleados activos
            if (isset($datos['planillaTemplate']) && $datos['planillaTemplate']) {
                $templatePlanilla = Planilla::with(['detalles' => function ($query) {
                    $query->with(['empleado' => function ($q) {
                        $q->where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO);
                    }]);
                }])->findOrFail($datos['planillaTemplate']);

                foreach ($templatePlanilla->detalles as $detalleTemplate) {
                    if ($detalleTemplate->empleado) {
                        $detalle = $this->crearDetallePlanilla(
                            $detalleTemplate->empleado,
                            $planilla->id,
                            $datos['tipo_planilla']
                        );

                        if ($detalle) {
                            $detalle->save();
                            $empleadosIncluidos++;
                        } else {
                            $empleadosOmitidos++;
                        }
                    }
                }
            } else {
                $empleados = Empleado::where('id_empresa', auth()->user()->id_empresa)
                    ->where('id_sucursal', auth()->user()->id_sucursal)
                    ->where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO)
                    ->get();

                foreach ($empleados as $empleado) {
                    $detalle = $this->crearDetallePlanilla($empleado, $planilla->id, $datos['tipo_planilla']);

                    if ($detalle) {
                        $detalle->save();
                        $empleadosIncluidos++;
                    } else {
                        $empleadosOmitidos++;
                    }
                }
            }

            if ($empleadosIncluidos === 0) {
                DB::rollback();
                throw new \Exception('No se pudo generar la planilla porque no hay empleados activos para el período indicado');
            }

            // Actualizar totales
            $planilla->actualizarTotales();
            $planilla = $planilla->fresh(['detalles']);

            DB::commit();

            return [
                'planilla' => $planilla,
                'estadisticas' => [
                    'empleados_incluidos' => $empleadosIncluidos,
                    'empleados_omitidos' => $empleadosOmitidos
                ]
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error generando planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar una planilla existente
     */
    public function actualizar($id, array $datos)
    {
        DB::beginTransaction();
        try {
            $planilla = Planilla::findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                throw new \Exception('Solo se pueden modificar planillas en estado borrador');
            }

            // Validar que no exista otra planilla para ese período
            $planillaExistente = Planilla::where('id_empresa', auth()->user()->id_empresa)
                ->where('id_sucursal', auth()->user()->id_sucursal)
                ->where('id', '!=', $id)
                ->where(function ($query) use ($datos) {
                    $query->whereBetween('fecha_inicio', [$datos['fecha_inicio'], $datos['fecha_fin']])
                        ->orWhereBetween('fecha_fin', [$datos['fecha_inicio'], $datos['fecha_fin']]);
                })
                ->first();

            if ($planillaExistente) {
                throw new \Exception('Ya existe una planilla para este período');
            }

            $planilla->update([
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'tipo_planilla' => $datos['tipo_planilla'],
                'anio' => Carbon::parse($datos['fecha_inicio'])->year,
                'mes' => Carbon::parse($datos['fecha_inicio'])->month
            ]);

            $this->actualizarTotales($planilla->id);

            DB::commit();

            return $planilla->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al actualizar planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar una planilla
     */
    public function eliminar($id)
    {
        DB::beginTransaction();
        try {
            $planilla = Planilla::findOrFail($id);

            // Verificar permisos
            if ($planilla->id_empresa !== auth()->user()->id_empresa ||
                $planilla->id_sucursal !== auth()->user()->id_sucursal) {
                throw new \Exception('No tiene permisos para eliminar esta planilla');
            }

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                throw new \Exception('Solo se pueden eliminar planillas en estado borrador');
            }

            // Eliminar detalles
            PlanillaDetalle::where('id_planilla', $id)->delete();

            // Eliminar planilla
            $planilla->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al eliminar planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener detalles de una planilla
     */
    public function obtenerDetalles($planillaId, $filtros = [])
    {
        $planilla = Planilla::with('empresa')->findOrFail($planillaId);

        // Verificar permisos
        if ($planilla->id_empresa !== auth()->user()->id_empresa ||
            $planilla->id_sucursal !== auth()->user()->id_sucursal) {
            throw new \Exception('No autorizado');
        }

        $query = $planilla->detalles()
            ->join('empleados', 'planilla_detalles.id_empleado', '=', 'empleados.id')
            ->select(
                'planilla_detalles.*',
                'empleados.nombres',
                'empleados.apellidos',
                'empleados.codigo',
                'empleados.dui'
            )
            ->with(['empleado']);

        // Aplicar filtros
        if (isset($filtros['buscador'])) {
            $busqueda = $filtros['buscador'];
            $query->where(function ($q) use ($busqueda) {
                $q->where('empleados.nombres', 'LIKE', "%$busqueda%")
                    ->orWhere('empleados.apellidos', 'LIKE', "%$busqueda%")
                    ->orWhere('empleados.codigo', 'LIKE', "%$busqueda%")
                    ->orWhere('empleados.dui', 'LIKE', "%$busqueda%")
                    ->orWhereRaw("CONCAT(empleados.nombres, ' ', empleados.apellidos) LIKE '%$busqueda%'");
            });
        }

        if (isset($filtros['id_departamento']) && $filtros['id_departamento']) {
            $query->where('empleados.id_departamento', $filtros['id_departamento']);
        }

        if (isset($filtros['id_cargo']) && $filtros['id_cargo']) {
            $query->where('empleados.id_cargo', $filtros['id_cargo']);
        }

        $query->where('planilla_detalles.estado', '!=', 0);
        $query->orderBy('empleados.nombres', 'asc');

        $detalles = $query->paginate($filtros['paginate'] ?? 10);

        // Calcular totales
        $totales = PlanillaDetalle::where('id_planilla', $planilla->id)
            ->where('estado', '!=', PlanillaConstants::PLANILLA_INACTIVA)
            ->selectRaw('
                SUM(salario_base) as total_salarios,
                SUM(bonificaciones) as bonificaciones_total,
                SUM(comisiones) as comisiones_total,
                SUM(total_ingresos) as total_ingresos,
                SUM(isss_empleado) as total_iss,
                SUM(afp_empleado) as total_afp,
                SUM(renta) as total_isr,
                SUM(sueldo_neto) as total_neto
            ')->first();

        return [
            'planilla' => $planilla,
            'detalles' => $detalles,
            'totales' => $totales
        ];
    }

    /**
     * Crear detalle de planilla para un empleado
     */
    private function crearDetallePlanilla($empleado, $planillaId, $tipoPlanilla)
    {
        try {
            $diasReferencia = 30;

            if ($tipoPlanilla === 'quincenal') {
                $diasReferencia = 15;
            } elseif ($tipoPlanilla === 'semanal') {
                $diasReferencia = 7;
            }

            $salarioBase = $empleado->salario_base;
            $tipoContrato = $empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
            $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);

            $diasLaborados = $diasReferencia;

            // Calcular salario devengado según tipo de contrato
            if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
                $salarioBaseAjustado = $salarioBase;
                $salarioDevengado = $salarioBase;
            } elseif ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES) {
                $salarioBaseAjustado = $salarioBase;
                if ($tipoPlanilla === 'quincenal') {
                    $salarioDevengado = $salarioBase / 2;
                } elseif ($tipoPlanilla === 'semanal') {
                    $salarioDevengado = $salarioBase / 4.33;
                } else {
                    $salarioDevengado = $salarioBase;
                }
            } else {
                $salarioBaseAjustado = $salarioBase;
                if ($tipoPlanilla === 'quincenal') {
                    $salarioBaseAjustado = $salarioBase / 2;
                } elseif ($tipoPlanilla === 'semanal') {
                    $salarioBaseAjustado = $salarioBase / 4.33;
                }
                $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $diasLaborados;
            }

            // Preparar datos para el servicio
            $datosEmpleado = [
                'salario_base' => $salarioBase,
                'salario_devengado' => $salarioDevengado,
                'dias_laborados' => $diasLaborados,
                'horas_extra' => 0,
                'monto_horas_extra' => 0,
                'comisiones' => 0,
                'bonificaciones' => 0,
                'otros_ingresos' => 0,
                'prestamos' => 0,
                'anticipos' => 0,
                'otros_descuentos' => 0,
                'descuentos_judiciales' => 0,
                'tipo_contrato' => $tipoContrato,
            ];

            $empresaId = auth()->user()->id_empresa;
            $resultados = $this->configuracionPlanillaService->calcularConceptos(
                $datosEmpleado,
                $empresaId,
                $tipoPlanilla
            );

            // Crear detalle
            $detalle = new PlanillaDetalle();
            $detalle->id_planilla = $planillaId;
            $detalle->id_empleado = $empleado->id;
            $detalle->salario_base = $salarioBase;
            $detalle->salario_devengado = $salarioDevengado;
            $detalle->dias_laborados = $diasLaborados;

            // Ingresos
            $detalle->horas_extra = 0;
            $detalle->monto_horas_extra = 0;
            $detalle->comisiones = 0;
            $detalle->bonificaciones = 0;
            $detalle->otros_ingresos = 0;

            // Deducciones
            $detalle->prestamos = 0;
            $detalle->anticipos = 0;
            $detalle->otros_descuentos = 0;
            $detalle->descuentos_judiciales = 0;

            // Verificar país
            $pais = $resultados['pais_configuracion'] ?? 'SV';

            if (isset($resultados['conceptos_personalizados']) && $pais !== 'SV') {
                $detalle->conceptos_personalizados = $resultados['conceptos_personalizados'];
                $detalle->pais_configuracion = $pais;
                $detalle->isss_empleado = 0;
                $detalle->isss_patronal = 0;
                $detalle->afp_empleado = 0;
                $detalle->afp_patronal = 0;
                $detalle->renta = 0;
            } else {
                $detalle->isss_empleado = $resultados['isss_empleado'] ?? 0;
                $detalle->isss_patronal = $resultados['isss_patronal'] ?? 0;
                $detalle->afp_empleado = $resultados['afp_empleado'] ?? 0;
                $detalle->afp_patronal = $resultados['afp_patronal'] ?? 0;
                $detalle->renta = $resultados['renta'] ?? 0;
                $detalle->pais_configuracion = 'SV';
                $detalle->conceptos_personalizados = null;
            }

            // Totales
            $detalle->total_ingresos = $resultados['totales']['total_ingresos'] ?? $salarioDevengado;
            $detalle->total_descuentos = $resultados['totales']['total_deducciones'] ?? 0;
            $detalle->sueldo_neto = $resultados['totales']['sueldo_neto'] ?? $salarioDevengado;

            $detalle->estado = PlanillaConstants::PLANILLA_BORRADOR;

            return $detalle;
        } catch (\Exception $e) {
            Log::error('Error creando detalle de planilla', [
                'empleado_id' => $empleado->id ?? null,
                'planilla_id' => $planillaId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar totales de una planilla
     */
    public function actualizarTotales($id_planilla)
    {
        try {
            $planilla = Planilla::findOrFail($id_planilla);

            $detalles = $planilla->detalles()
                ->where('estado', '!=', PlanillaConstants::PLANILLA_INACTIVA)
                ->get();

            $total_salarios = 0;
            $total_deducciones = 0;
            $total_neto = 0;
            $total_aportes_patronales = 0;
            $bonificaciones_total = 0;
            $comisiones_total = 0;
            $total_isss = 0;
            $total_afp = 0;
            $total_isr = 0;

            foreach ($detalles as $detalle) {
                $total_salarios += $detalle->salario_devengado;
                $bonificaciones_total += $detalle->bonificaciones;
                $comisiones_total += $detalle->comisiones;
                $total_isss += $detalle->isss_empleado;
                $total_afp += $detalle->afp_empleado;
                $total_isr += $detalle->renta;
                $total_deducciones += $detalle->total_descuentos;
                $total_neto += $detalle->sueldo_neto;
                $total_aportes_patronales += $detalle->isss_patronal + $detalle->afp_patronal;
            }

            $planilla->update([
                'total_salarios' => round($total_salarios, 2),
                'total_deducciones' => round($total_deducciones, 2),
                'total_neto' => round($total_neto, 2),
                'total_aportes_patronales' => round($total_aportes_patronales, 2),
                'bonificaciones_total' => round($bonificaciones_total, 2),
                'comisiones_total' => round($comisiones_total, 2),
                'total_ingresos' => round($total_salarios, 2),
                'total_iss' => round($total_isss, 2),
                'total_afp' => round($total_afp, 2),
                'total_isr' => round($total_isr, 2)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error actualizando totales de planilla: ' . $e->getMessage());
            throw $e;
        }
    }
}

