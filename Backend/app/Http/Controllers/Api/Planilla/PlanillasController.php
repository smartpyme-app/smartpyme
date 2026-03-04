<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Exports\Planillas\DescuentosPatronalesExport;
use App\Exports\PlanillaExport;
use App\Exports\PlanillaExportTemplate;
use App\Exports\Planillas\PlanillaDetallesExport;
use App\Http\Controllers\Controller;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\Empleado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Imports\PlanillasImport;
use App\Mail\BoletaPagoMailable;
use App\Models\Compras\Gastos\Categoria;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\RentaHelper;
use App\Services\Planilla\ConfiguracionPlanillaService;
use App\Services\Planilla\PlanillaTemplatesService;

class PlanillasController extends Controller
{

    protected $configuracionPlanillaService;

    public function __construct(ConfiguracionPlanillaService $configuracionPlanillaService)
    {
        $this->configuracionPlanillaService = $configuracionPlanillaService;
    }


    public function index(Request $request)
    {
        $query = Planilla::with(['detalles.empleado'])
            ->where('id_empresa', auth()->user()->id_empresa)
            ->where('id_sucursal', auth()->user()->id_sucursal);

        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }

        if ($request->filled('mes')) {
            $query->where('mes', $request->mes);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('tipo_planilla')) {
            $query->where('tipo_planilla', $request->tipo_planilla);
        }

        if ($request->filled('buscador')) {
            $busqueda = $request->buscador;
            $query->whereHas('detalles.empleado', function ($q) use ($busqueda) {
                $q->where('nombres', 'LIKE', "%$busqueda%")
                    ->orWhere('apellidos', 'LIKE', "%$busqueda%")
                    ->orWhere('codigo', 'LIKE', "%$busqueda%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($request->get('paginate', 10));
    }

    public function show(Request $request)
    {

        try {

            $planilla = Planilla::with('empresa')->findOrFail($request->id);

            if (
                $planilla->id_empresa !== auth()->user()->id_empresa ||
                $planilla->id_sucursal !== auth()->user()->id_sucursal
            ) {
                return response()->json(['error' => 'No autorizado'], 403);
            }

            // Iniciar la consulta con el join
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
            if ($request->has('buscador')) {
                $busqueda = $request->buscador;
                $query->where(function ($q) use ($busqueda) {
                    $q->where('empleados.nombres', 'LIKE', "%$busqueda%")
                        ->orWhere('empleados.apellidos', 'LIKE', "%$busqueda%")
                        ->orWhere('empleados.codigo', 'LIKE', "%$busqueda%")
                        ->orWhere('empleados.dui', 'LIKE', "%$busqueda%")
                        ->orWhereRaw("CONCAT(empleados.nombres, ' ', empleados.apellidos) LIKE '%$busqueda%'");
                });
            }

            if ($request->has('id_departamento') && $request->id_departamento) {
                $query->where('empleados.id_departamento', $request->id_departamento);
            }

            if ($request->has('id_cargo') && $request->id_cargo) {
                $query->where('empleados.id_cargo', $request->id_cargo);
            }

            $query->where('planilla_detalles.estado', '!=', 0);

            $query->orderBy('empleados.nombres', 'asc');

            // Paginación
            $detalles = $query->paginate($request->get('paginate', 10));

            // Calcular totales ANTES de convertir a array
            $totales = PlanillaDetalle::where('id_planilla', $planilla->id)
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

            // Convertir a array y añadir la información adicional
            $planillaArray['empresa'] = [
                'id' => $planilla->empresa->id,
                'nombre' => $planilla->empresa->nombre,
                'cod_pais' => $planilla->empresa->cod_pais,
            ];
            $planillaArray['id'] = $planilla->id;
            $planillaArray['detalles'] = $detalles;
            $planillaArray['total_salarios'] = $planilla->total_salarios;
            $planillaArray['total_deducciones'] = $planilla->total_deducciones;
            $planillaArray['total_neto'] = $planilla->total_neto;
            $planillaArray['estado'] = $planilla->estado;
            $planillaArray['totales'] = $totales;
            $planillaArray['tipo_planilla'] = $planilla->tipo_planilla;

            return response()->json($planillaArray);
        } catch (\Exception $e) {
            Log::error('Error en show de planilla: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener la planilla: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'tipo_planilla' => 'required|in:quincenal,mensual,semanal',
            'planillaTemplate' => 'nullable|exists:planillas,id'
        ]);

        try {
            DB::beginTransaction();

            $fechaInicio = Carbon::parse($request->fecha_inicio);
            $fechaFin = Carbon::parse($request->fecha_fin);

            // Validar que no exista una planilla para ese período
            $planillaExistente = Planilla::where('id_empresa', auth()->user()->id_empresa)
                ->where('id_sucursal', auth()->user()->id_sucursal)
                ->where('fecha_inicio', $fechaInicio)
                ->where('fecha_fin', $fechaFin)
                ->first();

            if ($planillaExistente) {
                return response()->json([
                    'error' => 'Ya existe una planilla para este período'
                ], 422);
            }

            // Generar código único
            $codigo = 'PLA-' . $fechaInicio->format('Ym') .
                ($fechaInicio->day <= 15 ? '1-' : '2-') .
                auth()->user()->id_sucursal;

            // Crear nueva planilla con valores iniciales
            $planilla = new Planilla([
                'codigo' => $codigo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'tipo_planilla' => $request->tipo_planilla,
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

            $planilla->save();

            $empleadosIncluidos = 0;
            $empleadosOmitidos = 0;

            if ($request->planillaTemplate) {
                $templatePlanilla = Planilla::with(['detalles' => function ($query) {
                    $query->with(['empleado' => function ($q) {
                        $q->where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO);
                    }]);
                }])->findOrFail($request->planillaTemplate);

                foreach ($templatePlanilla->detalles as $detalleTemplate) {
                    if ($detalleTemplate->empleado) {
                        $detalle = $this->crearDetallePlanilla(
                            $detalleTemplate->empleado,
                            $planilla->id,
                            $request->tipo_planilla
                        );

                        // Solo guardar si el detalle no es null
                        if ($detalle) {
                            $detalle->save();
                            $empleadosIncluidos++;
                        } else {
                            $empleadosOmitidos++;
                            // Log::info("Empleado ID: {$detalleTemplate->empleado->id} omitido de la planilla por tener fecha de baja/fin");
                        }
                    }
                }
            } else {
                $empleados = Empleado::where('id_empresa', auth()->user()->id_empresa)
                    ->where('id_sucursal', auth()->user()->id_sucursal)
                    ->where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO)
                    ->get();

                foreach ($empleados as $empleado) {
                    $detalle = $this->crearDetallePlanilla($empleado, $planilla->id, $request->tipo_planilla);

                    // Solo guardar si el detalle no es null
                    if ($detalle) {
                        $detalle->save();
                        $empleadosIncluidos++;
                    } else {
                        $empleadosOmitidos++;
                        // Log::info("Empleado ID: {$empleado->id} omitido de la planilla por tener fecha de baja/fin");
                    }
                }
            }

            // Verificar si se incluyó al menos un empleado
            if ($empleadosIncluidos === 0) {
                DB::rollback();
                return response()->json([
                    'error' => 'No se pudo generar la planilla porque no hay empleados activos para el período indicado'
                ], 422);
            }

            // Usar el método del modelo para actualizar totales
            $planilla->actualizarTotales();

            // Recargar la planilla con sus relaciones
            $planilla = $planilla->fresh(['detalles']);

            // // Verificar los totales calculados
            // Log::info('Totales de planilla actualizados', [
            //     'id_planilla' => $planilla->id,
            //     'total_salarios' => $planilla->total_salarios,
            //     'total_deducciones' => $planilla->total_deducciones,
            //     'total_neto' => $planilla->total_neto,
            //     'total_aportes_patronales' => $planilla->total_aportes_patronales,
            //     'empleados_incluidos' => $empleadosIncluidos,
            //     'empleados_omitidos' => $empleadosOmitidos
            // ]);

            DB::commit();

            return response()->json([
                'message' => 'Planilla generada exitosamente',
                'planilla' => $planilla,
                'estadisticas' => [
                    'empleados_incluidos' => $empleadosIncluidos,
                    'empleados_omitidos' => $empleadosOmitidos
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error generando planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo_planilla' => 'required|in:quincenal,mensual,semanal'
        ]);

        try {
            DB::beginTransaction();

            $planilla = Planilla::findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                return response()->json([
                    'error' => 'Solo se pueden modificar planillas en estado borrador'
                ], 422);
            }

            $planillaExistente = Planilla::where('id_empresa', auth()->user()->id_empresa)
                ->where('id_sucursal', auth()->user()->id_sucursal)
                ->where('id', '!=', $id)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('fecha_inicio', [$request->fecha_inicio, $request->fecha_fin])
                        ->orWhereBetween('fecha_fin', [$request->fecha_inicio, $request->fecha_fin]);
                })
                ->first();

            if ($planillaExistente) {
                return response()->json([
                    'error' => 'Ya existe una planilla para este período'
                ], 422);
            }

            $planilla->update([
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'tipo_planilla' => $request->tipo_planilla,
                'anio' => Carbon::parse($request->fecha_inicio)->year,
                'mes' => Carbon::parse($request->fecha_inicio)->month
            ]);

            $this->updatePayrollTotals($planilla->id);

            DB::commit();

            return response()->json([
                'message' => 'Planilla actualizada exitosamente',
                'planilla' => $planilla->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al actualizar planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calcularSalarioDevengado($salarioBase, $diasLaborados, $tipoContrato = null, $tipoPlanilla = 'mensual')
    {
        if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
            // Para contratos Por Obra, el salario base ES el monto total del período
            // NO se divide proporcionalmente
            return $salarioBase;
        } elseif ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES) {
            // Para Servicios Profesionales, el salario base es MENSUAL
            // Se divide según tipo de planilla pero NO usa días laborados
            if ($tipoPlanilla === 'quincenal') {
                return $salarioBase / 2;
            } elseif ($tipoPlanilla === 'semanal') {
                return $salarioBase / 4.33;
            } else {
                return $salarioBase; // mensual
            }
        } else {
            // Para empleados asalariados regulares, calcular proporcionalmente según días laborados
            return ($salarioBase / 30) * $diasLaborados;
        }
    }

    private function calcularISSSyAFP($salarioDevengado)
    {
        // Método mantenido para compatibilidad, pero usando constantes actualizadas
        $baseISSSEmpleado = min($salarioDevengado, 1000);
        $isssEmpleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
        $isssPatronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;

        $afpEmpleado = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
        $afpPatronal = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_PATRONO;

        return [
            'isss_empleado' => round($isssEmpleado, 2),
            'isss_patronal' => round($isssPatronal, 2),
            'afp_empleado' => round($afpEmpleado, 2),
            'afp_patronal' => round($afpPatronal, 2)
        ];
    }

    // private function calcularRenta($salarioDevengado, $isssEmpleado, $afpEmpleado)
    // {
    //     $baseRenta = $salarioDevengado - $isssEmpleado - $afpEmpleado;

    //     if ($baseRenta <= PlanillaConstants::RENTA_MINIMA) {
    //         return 0;
    //     } elseif ($baseRenta <= PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) {
    //         return (($baseRenta - PlanillaConstants::RENTA_MINIMA) * PlanillaConstants::PORCENTAJE_PRIMER_TRAMO) + PlanillaConstants::IMPUESTO_PRIMER_TRAMO;
    //     } elseif ($baseRenta <= PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) {
    //         return (($baseRenta - PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) * PlanillaConstants::PORCENTAJE_SEGUNDO_TRAMO) + PlanillaConstants::IMPUESTO_SEGUNDO_TRAMO;
    //     } else {
    //         return (($baseRenta - PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) * PlanillaConstants::PORCENTAJE_TERCER_TRAMO) + PlanillaConstants::IMPUESTO_TERCER_TRAMO;
    //     }
    // }

    private function calcularTotales($salarioDevengado, $descuentos)
    {
        $totalIngresos = $salarioDevengado;
        $totalDescuentos = array_sum($descuentos);
        $sueldoNeto = $totalIngresos - $totalDescuentos;

        return [
            'total_ingresos' => $totalIngresos,
            'total_descuentos' => $totalDescuentos,
            'sueldo_neto' => $sueldoNeto
        ];
    }

    // private function crearDetallePlanilla($empleado, $planillaId, $tipoPlanilla)
    // {
    //     try {
    //         // 🎯 PREPARAR DATOS DEL EMPLEADO
    //         $diasReferencia = 30;

    //         if ($tipoPlanilla === 'quincenal') {
    //             $diasReferencia = 15;
    //         } elseif ($tipoPlanilla === 'semanal') {
    //             $diasReferencia = 7;
    //         }

    //         // Calcular salario base ajustado según tipo de planilla
    //         $salarioBase = $empleado->salario_base;
    //         $salarioBaseAjustado = $salarioBase;

    //         if ($tipoPlanilla === 'quincenal') {
    //             $salarioBaseAjustado = $salarioBase / 2;
    //         } elseif ($tipoPlanilla === 'semanal') {
    //             $salarioBaseAjustado = $salarioBase / 4.33;
    //         }

    //         // Días laborados (por defecto el período completo)
    //         $diasLaborados = $diasReferencia;

    //         // Salario devengado proporcional
    //         $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $diasLaborados;

    //         // 🎯 PREPARAR DATOS PARA EL SERVICE
    //         $datosEmpleado = [
    //             'salario_base' => $salarioBase,
    //             'salario_devengado' => $salarioDevengado,
    //             'dias_laborados' => $diasLaborados,
    //             'horas_extra' => 0,
    //             'monto_horas_extra' => 0,
    //             'comisiones' => 0,
    //             'bonificaciones' => 0,
    //             'otros_ingresos' => 0,
    //             'prestamos' => 0,
    //             'anticipos' => 0,
    //             'otros_descuentos' => 0,
    //             'descuentos_judiciales' => 0,
    //             'tipo_contrato' => $empleado->tipo_contrato ?? null,
    //         ];

    //         // 🎯 USAR SISTEMA OPTIMIZADO
    //         $empresaId = auth()->user()->id_empresa;
    //         $resultados = $this->configuracionPlanillaService->calcularConceptos(
    //             $datosEmpleado,
    //             $empresaId,
    //             $tipoPlanilla
    //         );

    //         // 🎯 CREAR DETALLE CON RESULTADOS
    //         $detalle = new PlanillaDetalle();
    //         $detalle->id_planilla = $planillaId;
    //         $detalle->id_empleado = $empleado->id;
    //         $detalle->salario_base = $salarioBase;
    //         $detalle->salario_devengado = $salarioDevengado;
    //         $detalle->dias_laborados = $diasLaborados;

    //         // Ingresos (siempre inicializar en 0)
    //         $detalle->horas_extra = 0;
    //         $detalle->monto_horas_extra = 0;
    //         $detalle->comisiones = 0;
    //         $detalle->bonificaciones = 0;
    //         $detalle->otros_ingresos = 0;

    //         // Otras deducciones (siempre 0 al crear)
    //         $detalle->prestamos = 0;
    //         $detalle->anticipos = 0;
    //         $detalle->otros_descuentos = 0;
    //         $detalle->descuentos_judiciales = 0;

    //         // ✅ VERIFICAR SI USA CONCEPTOS PERSONALIZADOS
    //         if (isset($resultados['conceptos_personalizados'])) {
    //             // PAÍSES PERSONALIZADOS (Guatemala, etc.)
    //             Log::info('🌎 Usando conceptos personalizados', [
    //                 'conceptos_count' => count($resultados['conceptos_personalizados']),
    //                 'pais' => $resultados['pais_configuracion']
    //             ]);

    //             $detalle->conceptos_personalizados = $resultados['conceptos_personalizados'];
    //             $detalle->pais_configuracion = $resultados['pais_configuracion'];

    //             // Mantener campos fijos en 0 para compatibilidad
    //             $detalle->isss_empleado = 0;
    //             $detalle->isss_patronal = 0;
    //             $detalle->afp_empleado = 0;
    //             $detalle->afp_patronal = 0;
    //             $detalle->renta = 0;
    //         } else {
    //             // EL SALVADOR (campos fijos)
    //             Log::info('🇸🇻 Usando campos fijos El Salvador');

    //             $detalle->isss_empleado = $resultados['isss_empleado'] ?? 0;
    //             $detalle->isss_patronal = $resultados['isss_patronal'] ?? 0;
    //             $detalle->afp_empleado = $resultados['afp_empleado'] ?? 0;
    //             $detalle->afp_patronal = $resultados['afp_patronal'] ?? 0;
    //             $detalle->renta = $resultados['renta'] ?? 0;
    //             $detalle->pais_configuracion = 'SV';
    //             $detalle->conceptos_personalizados = null;
    //         }

    //         // Totales (siempre desde resultados)
    //         $detalle->total_ingresos = $resultados['totales']['total_ingresos'] ?? $salarioDevengado;
    //         $detalle->total_descuentos = $resultados['totales']['total_deducciones'] ?? 0;
    //         $detalle->sueldo_neto = $resultados['totales']['sueldo_neto'] ?? $salarioDevengado;

    //         $detalle->estado = PlanillaConstants::PLANILLA_BORRADOR;

    //         return $detalle;

    //     } catch (\Exception $e) {
    //         Log::error('Error creando detalle de planilla', [
    //             'empleado_id' => $empleado->id ?? null,
    //             'planilla_id' => $planillaId,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         // FALLBACK: usar sistema anterior si falla
    //         return $this->crearDetallePlanillaFallback($empleado, $planillaId, $tipoPlanilla);
    //     }
    // }


    //sirve en el salvador
    // private function crearDetallePlanilla($empleado, $planillaId, $tipoPlanilla)
    // {
    //     $diasReferencia = 30;
    //     $factorAjuste = 1;

    //     if ($tipoPlanilla === 'quincenal') {
    //         $diasReferencia = 15;
    //         $factorAjuste = 2;
    //     } elseif ($tipoPlanilla === 'semanal') {
    //         $diasReferencia = 7;
    //         $factorAjuste = 4.33;
    //     }

    //     // Obtener las fechas de la planilla
    //     $planilla = Planilla::findOrFail($planillaId);
    //     $fechaInicioPlanilla = Carbon::parse($planilla->fecha_inicio)->startOfDay();
    //     $fechaFinPlanilla = Carbon::parse($planilla->fecha_fin)->startOfDay();

    //     // Verificar si el empleado tiene fecha de baja programada
    //     if (($empleado->fecha_baja && Carbon::parse($empleado->fecha_baja)->startOfDay() < $fechaInicioPlanilla) ||
    //         ($empleado->fecha_fin && Carbon::parse($empleado->fecha_fin)->startOfDay() < $fechaInicioPlanilla)) {
    //         return null; // No incluir en planilla
    //     }

    //     // Calcular días proporcionales si hay baja programada
    //     $tieneBajaProgramada = false;
    //     $diasProporcionales = $diasReferencia;

    //     if ($empleado->fecha_baja && Carbon::parse($empleado->fecha_baja)->startOfDay()->between($fechaInicioPlanilla, $fechaFinPlanilla)) {
    //         $tieneBajaProgramada = true;
    //         $diasProporcionales = Carbon::parse($empleado->fecha_baja)->startOfDay()->diffInDays($fechaInicioPlanilla) + 1;
    //     } elseif ($empleado->fecha_fin && Carbon::parse($empleado->fecha_fin)->startOfDay()->between($fechaInicioPlanilla, $fechaFinPlanilla)) {
    //         $tieneBajaProgramada = true;
    //         $diasProporcionales = Carbon::parse($empleado->fecha_fin)->startOfDay()->diffInDays($fechaInicioPlanilla) + 1;
    //     }

    //     $diasLaborados = $tieneBajaProgramada ? min($diasProporcionales, $diasReferencia) : $diasReferencia;

    //     // Calcular salario devengado
    //     $salarioBaseMensual = $empleado->salario_base;
    //     $salarioBaseAjustado = $tipoPlanilla !== 'mensual' ? $salarioBaseMensual / $factorAjuste : $salarioBaseMensual;
    //     $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $diasLaborados;

    //     // ✅ CALCULAR TOTAL DE INGRESOS PRIMERO (INCLUYE TODOS LOS CONCEPTOS)
    //     $horasExtra = 0;
    //     $montoHorasExtra = 0;
    //     $comisiones = 0;
    //     $bonificaciones = 0;
    //     $otrosIngresos = 0;

    //     $totalIngresos = $salarioDevengado + $montoHorasExtra + $comisiones + $bonificaciones + $otrosIngresos;

    //     $tipoContrato = $empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
    //     $esServiciosProfesionales = PlanillaConstants::esContratoServiciosProfesionales($tipoContrato);

    //     if ($esServiciosProfesionales) {
    //         // SERVICIOS PROFESIONALES: Sin ISSS ni AFP
    //         $baseISSSEmpleado = 0;
    //         $isssEmpleado = 0;
    //         $isssPatronal = 0;
    //         $afpEmpleado = 0;
    //         $afpPatronal = 0;
    //     } else {
    //         // EMPLEADOS ASALARIADOS: Con ISSS y AFP normales
    //     $baseISSSEmpleado = min($totalIngresos, 1000);
    //     $isssEmpleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
    //     $isssPatronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;
    //     $afpEmpleado = $totalIngresos * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
    //     $afpPatronal = $totalIngresos * PlanillaConstants::DESCUENTO_AFP_PATRONO;
    //     }

    //     // CALCULAR RENTA USANDO TOTAL DE INGRESOS (NO SOLO SALARIO DEVENGADO)
    //     $salarioGravado = RentaHelper::calcularSalarioGravado(
    //         $totalIngresos,
    //         $isssEmpleado,
    //         $afpEmpleado,
    //         $tipoPlanilla,
    //         $tipoContrato
    //     );

    //     $renta = RentaHelper::calcularRetencionRenta($salarioGravado, $tipoPlanilla, $tipoContrato);
    //     $totalDeducciones = $isssEmpleado + $afpEmpleado + $renta;
    //     $sueldoNeto = $totalIngresos - $totalDeducciones;

    //     return new PlanillaDetalle([
    //         'id_planilla' => $planillaId,
    //         'id_empleado' => $empleado->id,
    //         'salario_base' => $empleado->salario_base,
    //         'salario_devengado' => round($salarioDevengado, 2),
    //         'dias_laborados' => $diasLaborados,
    //         'horas_extra' => 0,
    //         'monto_horas_extra' => 0,
    //         'comisiones' => 0,
    //         'bonificaciones' => 0,
    //         'otros_ingresos' => 0,
    //         'total_ingresos' => round($totalIngresos, 2),
    //         'isss_empleado' => round($isssEmpleado, 2),
    //         'isss_patronal' => round($isssPatronal, 2),
    //         'afp_empleado' => round($afpEmpleado, 2),
    //         'afp_patronal' => round($afpPatronal, 2),
    //         'renta' => round($renta, 2),
    //         'prestamos' => 0,
    //         'anticipos' => 0,
    //         'otros_descuentos' => 0,
    //         'descuentos_judiciales' => 0,
    //         'total_descuentos' => round($totalDeducciones, 2),
    //         'sueldo_neto' => round($sueldoNeto, 2),
    //         'estado' => PlanillaConstants::PLANILLA_BORRADOR
    //     ]);
    // }


    //nueva version madrugada

    private function crearDetallePlanilla($empleado, $planillaId, $tipoPlanilla)
{
    try {
        // 🎯 PREPARAR DATOS DEL EMPLEADO
        $diasReferencia = 30;

        if ($tipoPlanilla === 'quincenal') {
            $diasReferencia = 15;
        } elseif ($tipoPlanilla === 'semanal') {
            $diasReferencia = 7;
        }

        // Calcular salario base ajustado según tipo de planilla
        $salarioBase = $empleado->salario_base;
        $tipoContrato = $empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
        $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);

        // Días laborados (por defecto el período completo)
        $diasLaborados = $diasReferencia;

        // Calcular salario devengado
        if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
            // Para contratos Por Obra, el salario base ES el monto total del período
            // NO se divide proporcionalmente
            $salarioBaseAjustado = $salarioBase;
            $salarioDevengado = $salarioBase;
        } elseif ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES) {
            // Para Servicios Profesionales, el salario base es MENSUAL
            // Se divide según tipo de planilla pero NO usa días laborados
            $salarioBaseAjustado = $salarioBase;
            if ($tipoPlanilla === 'quincenal') {
                $salarioDevengado = $salarioBase / 2;
            } elseif ($tipoPlanilla === 'semanal') {
                $salarioDevengado = $salarioBase / 4.33;
            } else {
                $salarioDevengado = $salarioBase; // mensual
            }
        } else {
            // Para empleados asalariados regulares, ajustar según tipo de planilla y días laborados
            $salarioBaseAjustado = $salarioBase;
            if ($tipoPlanilla === 'quincenal') {
                $salarioBaseAjustado = $salarioBase / 2;
            } elseif ($tipoPlanilla === 'semanal') {
                $salarioBaseAjustado = $salarioBase / 4.33;
            }
            // Calcular proporcionalmente según días laborados
            $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $diasLaborados;
        }

        // 🎯 PREPARAR DATOS PARA EL SERVICE
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
            'tipo_contrato' => $empleado->tipo_contrato ?? null,
        ];

        // 🎯 USAR SISTEMA OPTIMIZADO
        $empresaId = auth()->user()->id_empresa;
        $resultados = $this->configuracionPlanillaService->calcularConceptos(
            $datosEmpleado,
            $empresaId,
            $tipoPlanilla
        );

        // 🎯 CREAR DETALLE CON RESULTADOS
        $detalle = new PlanillaDetalle();
        $detalle->id_planilla = $planillaId;
        $detalle->id_empleado = $empleado->id;
        $detalle->salario_base = $salarioBase;
        $detalle->salario_devengado = $salarioDevengado;
        $detalle->dias_laborados = $diasLaborados;

        // Ingresos (siempre inicializar en 0)
        $detalle->horas_extra = 0;
        $detalle->monto_horas_extra = 0;
        $detalle->comisiones = 0;
        $detalle->bonificaciones = 0;
        $detalle->otros_ingresos = 0;

        // Otras deducciones (siempre 0 al crear)
        $detalle->prestamos = 0;
        $detalle->anticipos = 0;
        $detalle->otros_descuentos = 0;
        $detalle->descuentos_judiciales = 0;

        // ✅ VERIFICAR PAÍS ANTES DE APLICAR CONCEPTOS PERSONALIZADOS
        $pais = $resultados['pais_configuracion'] ?? 'SV';

        if (isset($resultados['conceptos_personalizados']) && $pais !== 'SV') {
            // PAÍSES PERSONALIZADOS (Guatemala, etc.)
            Log::info('🌎 Usando conceptos personalizados', [
                'conceptos_count' => count($resultados['conceptos_personalizados']),
                'pais' => $pais
            ]);

            $detalle->conceptos_personalizados = $resultados['conceptos_personalizados'];
            $detalle->pais_configuracion = $pais;

            // Campos fijos en 0 para compatibilidad
            $detalle->isss_empleado = 0;
            $detalle->isss_patronal = 0;
            $detalle->afp_empleado = 0;
            $detalle->afp_patronal = 0;
            $detalle->renta = 0;

        } else {
            // EL SALVADOR - Usar valores del servicio (que ya maneja servicios profesionales)
            Log::info('🇸🇻 Usando valores del servicio para El Salvador', [
                'pais' => $pais,
                'isss_empleado' => $resultados['isss_empleado'] ?? 0,
                'afp_empleado' => $resultados['afp_empleado'] ?? 0,
                'renta' => $resultados['renta'] ?? 0
            ]);

            // El servicio ya calculó correctamente según tipo de contrato (incluyendo servicios profesionales)
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
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return $this->crearDetallePlanillaFallback($empleado, $planillaId, $tipoPlanilla);
    }
}


    public function calcularRentaAjustada($baseRenta, $tipoPlanilla, $factorAjuste = 1)
    {
        // Usar el RentaHelper para cálculos precisos según decreto 2025
        return \App\Helpers\RentaHelper::calcularRetencionRenta($baseRenta, $tipoPlanilla);
    }

    private function updatePayrollTotals($id_planilla)
    {
        try {
            $planilla = Planilla::findOrFail($id_planilla);

            // Obtener los detalles activos
            $detalles = $planilla->detalles()
                ->where('estado', PlanillaConstants::PLANILLA_BORRADOR)
                ->get();

            // Determinar factor de ajuste según tipo de planilla
            $factorAjuste = 1;
            if ($planilla->tipo_planilla === 'quincenal') {
                $factorAjuste = 2;
            } elseif ($planilla->tipo_planilla === 'semanal') {
                $factorAjuste = 4.33;
            }

            // Inicializar totales
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
                // Agregar al total de salarios devengados
                $total_salarios += $detalle->salario_devengado;

                // Acumular bonificaciones y comisiones
                $bonificaciones_total += $detalle->bonificaciones;
                $comisiones_total += $detalle->comisiones;

                // Acumular deducciones
                $total_isss += $detalle->isss_empleado;
                $total_afp += $detalle->afp_empleado;
                $total_isr += $detalle->renta;

                $total_deducciones += $detalle->total_descuentos;

                // Acumular neto
                $total_neto += $detalle->sueldo_neto;

                // Acumular aportes patronales
                $total_aportes_patronales += $detalle->isss_patronal + $detalle->afp_patronal;
            }

            // Actualizar planilla con todos los totales
            $planilla->update([
                'total_salarios' => round($total_salarios, 2),
                'total_deducciones' => round($total_deducciones, 2),
                'total_neto' => round($total_neto, 2),
                'total_aportes_patronales' => round($total_aportes_patronales, 2),
                'bonificaciones_total' => round($bonificaciones_total, 2),
                'comisiones_total' => round($comisiones_total, 2),
                'total_ingresos' => round($total_salarios, 2), // Total de ingresos es igual al total de salarios
                'total_iss' => round($total_isss, 2), // Total ISSS empleado
                'total_afp' => round($total_afp, 2), // Total AFP empleado
                'total_isr' => round($total_isr, 2) // Total ISR
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error actualizando totales de planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateDetailsPayroll(Request $request, $id)
    {
            $request->validate([
                'horas_extra' => 'nullable|numeric|min:0',
                'monto_horas_extra' => 'nullable|numeric|min:0',
                'detalle_horas_extra' => 'nullable|array',
                'detalle_horas_extra.diurna' => 'nullable|numeric|min:0',
                'detalle_horas_extra.nocturna' => 'nullable|numeric|min:0',
                'detalle_horas_extra.dia_descanso' => 'nullable|numeric|min:0',
                'detalle_horas_extra.dia_descanso_dias' => 'nullable|integer|min:0',
                'detalle_horas_extra.dia_asueto' => 'nullable|numeric|min:0',
                'comisiones' => 'nullable|numeric|min:0',
            'bonificaciones' => 'nullable|numeric|min:0',
            'otros_ingresos' => 'nullable|numeric|min:0',
            'abonos' => 'nullable|numeric|min:0',
            'abonos_sin_retencion' => 'nullable|boolean',
            'dias_laborados' => 'nullable|numeric|min:0|max:31',
            'prestamos' => 'nullable|numeric|min:0',
            'anticipos' => 'nullable|numeric|min:0',
            'otros_descuentos' => 'nullable|numeric|min:0',
            'descuentos_judiciales' => 'nullable|numeric|min:0',
            'detalle_otras_deducciones' => 'nullable|string',
            'salario_base' => 'nullable|numeric|min:0' // ✅ Permitir editar salario_base para contratos por obra
        ]);

        try {
            DB::beginTransaction();

            $detalle = PlanillaDetalle::findOrFail($id);
            $planilla = $detalle->planilla;
            $planilla->load('empresa');

            // Verificar que la planilla esté en estado editable
            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                return response()->json([
                    'error' => 'No se puede modificar una planilla aprobada o pagada'
                ], 422);
            }

            // Determinar días de referencia y factor de ajuste según tipo de planilla
            $diasReferencia = 30;
            $factorAjuste = 1;

            if ($planilla->tipo_planilla === 'quincenal') {
                $diasReferencia = 15;
                $factorAjuste = 2;
            } elseif ($planilla->tipo_planilla === 'semanal') {
                $diasReferencia = 7;
                $factorAjuste = 4.33;
            }

            // Verificar tipo de contrato primero
            $tipoContrato = $detalle->empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
            $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);

            // Actualizar campos básicos
            $detalle->dias_laborados = $request->dias_laborados ?? $diasReferencia;
            $detalle->comisiones = $request->comisiones ?? 0;
            $detalle->bonificaciones = $request->bonificaciones ?? 0;
            $detalle->otros_ingresos = $request->otros_ingresos ?? 0;
            $detalle->abonos = $request->abonos ?? 0;
            $detalle->abonos_sin_retencion = $request->boolean('abonos_sin_retencion', true);
            $detalle->prestamos = $request->prestamos ?? 0;
            $detalle->anticipos = $request->anticipos ?? 0;
            $detalle->otros_descuentos = $request->otros_descuentos ?? 0;
            $detalle->descuentos_judiciales = $request->descuentos_judiciales ?? 0;
            $detalle->detalle_otras_deducciones = $request->detalle_otras_deducciones;

            // ✅ PERMITIR EDITAR SALARIO_BASE SOLO PARA CONTRATOS POR OBRA (tipo 3)
            if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA && $request->has('salario_base') && $request->salario_base !== null) {
                // Para contratos Por obra, permitir editar el monto total del período
                $detalle->salario_base = $request->salario_base;
            }

            // Calcular salario devengado
            $salarioBaseMensual = $detalle->salario_base;

            if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
                // Para contratos Por Obra, el salario base ES el monto total del período
                // NO se divide proporcionalmente
                $salarioDevengado = $salarioBaseMensual;
                $salarioBaseAjustado = $salarioBaseMensual;
            } elseif ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES) {
                // Para Servicios Profesionales, el salario base es MENSUAL
                // Se divide según tipo de planilla pero NO usa días laborados
                if ($planilla->tipo_planilla === 'quincenal') {
                    $salarioDevengado = $salarioBaseMensual / 2;
                    $salarioBaseAjustado = $salarioBaseMensual / 2;
                } elseif ($planilla->tipo_planilla === 'semanal') {
                    $salarioDevengado = $salarioBaseMensual / 4.33;
                    $salarioBaseAjustado = $salarioBaseMensual / 4.33;
                } else {
                    $salarioDevengado = $salarioBaseMensual; // mensual
                    $salarioBaseAjustado = $salarioBaseMensual;
                }
            } else {
                // Para empleados asalariados regulares, calcular proporcionalmente según días laborados
                $salarioBaseAjustado = $planilla->tipo_planilla !== 'mensual' ?
                    $salarioBaseMensual / $factorAjuste : $salarioBaseMensual;
                $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $detalle->dias_laborados;
            }
            $detalle->salario_devengado = round($salarioDevengado, 2);

            // Horas extra: El Salvador con detalle por tipo (diurna, nocturna, día descanso, día asueto)
            $esElSalvador = ($planilla->empresa->cod_pais ?? '') === 'SV';
            $detalleHorasExtra = $request->detalle_horas_extra;

            if ($esElSalvador && $detalleHorasExtra && is_array($detalleHorasExtra)) {
                $valorHoraNormal = $salarioBaseAjustado / $diasReferencia / 8;
                $salarioDiario = 8 * $valorHoraNormal;
                // Art. 169 diurna 100% recargo; Art. 168+169 nocturna 100%+25%; Art. 175 día descanso 50%+día compensatorio; Art. 192 asueto 100% recargo
                $horasDiurna = (float) ($detalleHorasExtra['diurna'] ?? 0);
                $horasNocturna = (float) ($detalleHorasExtra['nocturna'] ?? 0);
                $horasDiaDescanso = (float) ($detalleHorasExtra['dia_descanso'] ?? 0);
                $horasDiaAsueto = (float) ($detalleHorasExtra['dia_asueto'] ?? 0);
                $diaDescansoDias = (int) ($detalleHorasExtra['dia_descanso_dias'] ?? 0);
                if ($horasDiaDescanso > 0 && $diaDescansoDias <= 0) {
                    $diaDescansoDias = 1; // compat: si hay horas pero no días, 1 día
                }
                $detalle->horas_extra = round($horasDiurna + $horasNocturna + $horasDiaDescanso + $horasDiaAsueto, 2);
                $montoDiurna = $horasDiurna * ($valorHoraNormal * 2);       // 100% recargo = pago doble (Art. 169)
                $montoNocturna = $horasNocturna * ($valorHoraNormal * 2.25); // 100% + 25% nocturnidad (Art. 168)
                $montoDiaDescanso = $horasDiaDescanso * ($valorHoraNormal * 1.5) + $diaDescansoDias * $salarioDiario; // Art. 175: 50% + día compensatorio
                $montoDiaAsueto = $horasDiaAsueto * ($valorHoraNormal * 2);   // Art. 192: 100% recargo = doble
                $detalle->monto_horas_extra = round($montoDiurna + $montoNocturna + $montoDiaDescanso + $montoDiaAsueto, 2);
                $detalle->detalle_horas_extra = [
                    'diurna' => $horasDiurna,
                    'nocturna' => $horasNocturna,
                    'dia_descanso' => $horasDiaDescanso,
                    'dia_descanso_dias' => $diaDescansoDias,
                    'dia_asueto' => $horasDiaAsueto,
                ];
            } else {
                $detalle->horas_extra = $request->horas_extra ?? 0;
                if ($detalle->horas_extra > 0) {
                    $valorHoraNormal = $salarioBaseAjustado / $diasReferencia / 8;
                    $detalle->monto_horas_extra = round($detalle->horas_extra * ($valorHoraNormal * 1.25), 2);
                } else {
                    $detalle->monto_horas_extra = 0;
                }
                $detalle->detalle_horas_extra = null;
            }

            // Calcular total de ingresos
            $detalle->total_ingresos = round($detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos +
                ($detalle->abonos ?? 0), 2);

            // Base para retenciones: si abonos son "sin retención", no entran en ISSS/AFP/Renta
            $abonosSinRetencion = $detalle->abonos_sin_retencion !== false;
            $baseParaRetenciones = $abonosSinRetencion
                ? $detalle->total_ingresos - ($detalle->abonos ?? 0)
                : $detalle->total_ingresos;

            // ✅ OBTENER TIPO DE CONTRATO DEL EMPLEADO
            $tipoContrato = $detalle->empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
            $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);

            // ✅ CALCULAR DEDUCCIONES SEGÚN TIPO DE CONTRATO Y CONFIGURACIÓN DEL EMPLEADO
            if ($esContratoSinPrestaciones) {
                // CONTRATOS SIN PRESTACIONES (Por obra y Servicios Profesionales): Sin ISSS ni AFP
                $detalle->isss_empleado = 0;
                $detalle->isss_patronal = 0;
                $detalle->afp_empleado = 0;
                $detalle->afp_patronal = 0;
            } else {
                // Obtener configuración de descuentos del empleado
                $empleado = $detalle->empleado;
                $configDescuentos = $empleado->configuracion_descuentos ?? [];
                $aplicarAfp = $configDescuentos['aplicar_afp'] ?? true; // Por defecto true
                $aplicarIsss = $configDescuentos['aplicar_isss'] ?? true; // Por defecto true

                // EMPLEADOS ASALARIADOS: Con ISSS y AFP normales (base = base para retenciones)
                if ($aplicarIsss) {
                    $baseISSSEmpleado = min($baseParaRetenciones, 1000);
                    $detalle->isss_empleado = round($baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO, 2);
                    $detalle->isss_patronal = round($baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO, 2);
                } else {
                    // No aplicar ISSS si está desactivado en la configuración
                    $detalle->isss_empleado = 0;
                    $detalle->isss_patronal = 0;
                }

                if ($aplicarAfp) {
                    $detalle->afp_empleado = round($baseParaRetenciones * PlanillaConstants::DESCUENTO_AFP_EMPLEADO, 2);
                    $detalle->afp_patronal = round($baseParaRetenciones * PlanillaConstants::DESCUENTO_AFP_PATRONO, 2);
                } else {
                    // No aplicar AFP si está desactivado en la configuración
                    $detalle->afp_empleado = 0;
                    $detalle->afp_patronal = 0;
                }
            }

            // ✅ CALCULAR RENTA CON TIPO DE CONTRATO (base = base para retenciones)
            $salarioGravado = RentaHelper::calcularSalarioGravado(
                $baseParaRetenciones,
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
            $detalle->total_descuentos = round($detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->otros_descuentos +
                $detalle->descuentos_judiciales, 2);

            // Calcular sueldo neto
            $detalle->sueldo_neto = round($detalle->total_ingresos - $detalle->total_descuentos, 2);

            // Guardar cambios
            $detalle->save();

            // Actualizar totales de la planilla
            $this->updatePayrollTotals($planilla->id);

            DB::commit();

            return response()->json([
                'message' => 'Detalle actualizado exitosamente con nuevas tablas 2025',
                'detalle' => $detalle,
                'empleado' => $detalle->empleado,
                'planilla' => $planilla->fresh(['empresa'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando detalle de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el detalle: ' . $e->getMessage()
            ], 500);
        }
    }


    public function approvePayroll($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Planilla::with('detalles')->findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                return response()->json([
                    'error' => 'Solo se pueden aprobar planillas en estado borrador'
                ], 422);
            }

            // Actualizar el estado de la planilla principal
            $planilla->estado = PlanillaConstants::PLANILLA_APROBADA; // Aprobada
            $planilla->save();

            // Inicializar contador de detalles actualizados
            $detallesActualizados = 0;

            // Actualizar el estado de todos los detalles activos
            foreach ($planilla->detalles as $detalle) {
                // Solo actualizamos los detalles que están en estado borrador o activo
                if ($detalle->estado == PlanillaConstants::PLANILLA_BORRADOR ||
                    $detalle->estado == PlanillaConstants::PLANILLA_ACTIVA) {

                    $detalle->estado = PlanillaConstants::PLANILLA_APROBADA;
                    $detalle->save();
                    $detallesActualizados++;
                }
            }

            DB::commit();

            // Log::info('Planilla aprobada exitosamente', [
            //     'planilla_id' => $id,
            //     'detalles_actualizados' => $detallesActualizados
            // ]);

            return response()->json([
                'message' => 'Planilla aprobada exitosamente',
                'detalles_actualizados' => $detallesActualizados
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al aprobar la planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al aprobar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function revertPayroll($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Planilla::with('detalles')->findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_APROBADA) {
                return response()->json([
                    'error' => 'Solo se pueden revertir planillas en estado aprobado'
                ], 422);
            }

            // Actualizar el estado de la planilla principal
            $planilla->estado = PlanillaConstants::PLANILLA_BORRADOR; // Aprobada
            $planilla->save();

            // Inicializar contador de detalles actualizados
            $detallesActualizados = 0;

            // Actualizar el estado de todos los detalles activos
            foreach ($planilla->detalles as $detalle) {
                // Solo actualizamos los detalles que están en estado borrador o activo
                if ($detalle->estado == PlanillaConstants::PLANILLA_APROBADA) {

                    $detalle->estado = PlanillaConstants::PLANILLA_BORRADOR;
                    $detalle->save();
                    $detallesActualizados++;
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Planilla aprobada exitosamente',
                'detalles_actualizados' => $detallesActualizados
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al aprobar la planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al aprobar la planilla: ' . $e->getMessage()
            ], 500);
        }

    }

    public function processPayment($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Planilla::with(['detalles.empleado', 'empresa'])->findOrFail($id);

            // Log inicial
            // Log::info('Iniciando procesamiento de planilla', [
            //     'planilla_id' => $id,
            //     'total_detalles' => $planilla->detalles->count()
            // ]);

            if ($planilla->estado != PlanillaConstants::PLANILLA_APROBADA) {
                return response()->json([
                    'error' => 'Solo se pueden pagar planillas aprobadas'
                ], 422);
            }

            // Verificar configuración de correo
            $this->verificarConfiguracionCorreo();

            // 1. Registrar gastos en contabilidad ANTES de cambiar estado
            $resultadoGastos = $this->registrarGastosPlanilla($planilla);

            if (!$resultadoGastos) {
                throw new \Exception('Error al registrar los gastos de planilla');
            }

            // 2. Actualizar estado de la planilla y sus detalles
            $planilla->estado = PlanillaConstants::PLANILLA_PAGADA;
            $planilla->save();

            // Actualizar detalles masivamente
            PlanillaDetalle::where('id_planilla', $planilla->id)
                ->whereIn('estado', [PlanillaConstants::PLANILLA_BORRADOR, PlanillaConstants::PLANILLA_APROBADA])
                ->update(['estado' => PlanillaConstants::PLANILLA_PAGADA]);

            // 3. Enviar correos tras el registro exitoso de gastos
            $emailsEnviados = 0;
            $errores = [];
            $detallesProcesados = 0;
            $empleadosSinEmail = 0;
            $empleadosInactivos = 0;

            // Recargar planilla con detalles actualizados
            $planilla = $planilla->fresh(['detalles.empleado', 'empresa']);

            // Enviar boletas por correo a cada empleado
            foreach ($planilla->detalles as $detalle) {
                $detallesProcesados++;

                // Log de cada detalle
                // Log::info('Procesando detalle de planilla', [
                //     'detalle_id' => $detalle->id,
                //     'empleado_id' => $detalle->empleado->id ?? 'No tiene empleado',
                //     'estado_detalle' => $detalle->estado,
                //     'tiene_empleado' => isset($detalle->empleado),
                //     'email_empleado' => $detalle->empleado->email ?? 'No tiene email'
                // ]);

                if (!isset($detalle->empleado)) {
                    Log::warning('Detalle sin empleado asociado', ['detalle_id' => $detalle->id]);
                    $errores[] = "Detalle ID {$detalle->id} no tiene empleado asociado";
                    continue;
                }

                if ($detalle->estado == PlanillaConstants::ESTADO_INACTIVO) {
                    $empleadosInactivos++;
                    // Log::info('Empleado inactivo en planilla', [
                    //     'empleado_id' => $detalle->empleado->id,
                    //     'estado' => $detalle->estado
                    // ]);
                    continue;
                }

                if (empty($detalle->empleado->email)) {
                    $empleadosSinEmail++;
                    Log::warning('Empleado sin email', [
                        'empleado_id' => $detalle->empleado->id,
                        'nombre' => $detalle->empleado->nombres . ' ' . $detalle->empleado->apellidos
                    ]);
                    $errores[] = "Empleado {$detalle->empleado->nombres} {$detalle->empleado->apellidos} no tiene correo electrónico";
                    continue;
                }

                $periodo = [
                    'inicio' => $planilla->fecha_inicio,
                    'fin' => $planilla->fecha_fin
                ];

                try {
                    // Log::info('Intentando enviar correo', [
                    //     'empleado_email' => $detalle->empleado->email,
                    //     'empleado_id' => $detalle->empleado->id
                    // ]);

                    // Enviar correo de forma síncrona para mejor debugging
                    Mail::to($detalle->empleado->email)
                        ->send(new BoletaPagoMailable(
                            $detalle,
                            $planilla,
                            $planilla->empresa,
                            $periodo
                        ));

                    $emailsEnviados++;

                    // Log::info('Correo enviado exitosamente', [
                    //     'empleado_email' => $detalle->empleado->email,
                    //     'empleado_id' => $detalle->empleado->id
                    // ]);
                } catch (\Exception $e) {
                    Log::error('Error enviando correo', [
                        'empleado_email' => $detalle->empleado->email,
                        'empleado_id' => $detalle->empleado->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $errores[] = "Error enviando correo a {$detalle->empleado->email}: {$e->getMessage()}";
                }
            }

            DB::commit();

            // Log final con estadísticas
            // Log::info('Finalizado procesamiento de planilla', [
            //     'detalles_procesados' => $detallesProcesados,
            //     'emails_enviados' => $emailsEnviados,
            //     'empleados_sin_email' => $empleadosSinEmail,
            //     'empleados_inactivos' => $empleadosInactivos,
            //     'total_errores' => count($errores)
            // ]);

            return response()->json([
                'message' => "Pago procesado exitosamente. Correos enviados: {$emailsEnviados}",
                'emails_enviados' => $emailsEnviados,
                'detalles_procesados' => $detallesProcesados,
                'empleados_sin_email' => $empleadosSinEmail,
                'empleados_inactivos' => $empleadosInactivos,
                'errores' => $errores,
                'estadisticas' => [
                    'total_detalles' => $planilla->detalles->count(),
                    'detalles_procesados' => $detallesProcesados,
                    'emails_enviados' => $emailsEnviados,
                    'empleados_sin_email' => $empleadosSinEmail,
                    'empleados_inactivos' => $empleadosInactivos
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al procesar pago de planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al procesar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    private function verificarConfiguracionCorreo()
    {
        $config = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
            'MAIL_ENCRYPTION' => config('mail.mailers.smtp.encryption'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        ];

        $faltantes = [];
        foreach ($config as $key => $value) {
            if (empty($value)) {
                $faltantes[] = $key;
            }
        }

        if (!empty($faltantes)) {
            Log::warning('Configuración de correo incompleta', [
                'faltantes' => $faltantes
            ]);
            throw new \Exception('Configuración de correo incompleta. Falta: ' . implode(', ', $faltantes));
        }

        // Log::info('Configuración de correo verificada', $config);
    }

    public function sendInvoicesByEmail($id)
    {
        try {
            $planilla = Planilla::with(['detalles.empleado'])->findOrFail($id);

            // Enviar boletas de pago por correo

            return response()->json([
                'message' => 'Boletas enviadas exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'error' => 'Error al enviar las boletas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Planilla::findOrFail($id);

            // Verificar que la planilla pertenece a la empresa y sucursal del usuario
            if ($planilla->id_empresa !== auth()->user()->id_empresa ||
                $planilla->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json([
                    'error' => 'No tiene permisos para eliminar esta planilla'
                ], 403);
            }

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                return response()->json([
                    'error' => 'Solo se pueden eliminar planillas en estado borrador'
                ], 422);
            }

            // Eliminar detalles
            PlanillaDetalle::where('id_planilla', $id)->delete();

            // Eliminar planilla
            $planilla->delete();

            DB::commit();

            return response()->json([
                'message' => 'Planilla eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportExcel($id)
    {
        try {
            $planilla = Planilla::with(['detalles.empleado'])->findOrFail($id);

            return Excel::download(
                new PlanillaExport($planilla),
                'planilla_' . $planilla->codigo . '.xlsx'
            );
        } catch (\Exception $e) {
            Log::error('Error exportando planilla a Excel: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar a Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportPDF($id)
    {
        try {
            $planilla = Planilla::with(['detalles' => function($query) {
                    $query->where('estado', '!=', 0);
                }, 'detalles.empleado', 'empresa.currency'])
                ->findOrFail($id);

            $pdf = app('dompdf.wrapper')->loadView('pdf.planilla-detalle', [
                'planilla' => $planilla,
                'detalles' => $planilla->detalles,
                'empresa' => $planilla->empresa
            ]);

            // Configurar PDF en horizontal para mejor visualización
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('planilla_' . $planilla->codigo . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error exportando planilla a PDF: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar a PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function withdrawPayroll(Request $request)
    {
        try {
            $detalle = PlanillaDetalle::findOrFail($request->id);
            $detalle->update(['estado' => 0]);

            $this->updatePayrollTotals($detalle->id_planilla);

            return response()->json([
                'message' => 'Detalle de planilla retirado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'error' => 'Error al retirar detalle de planilla: '
            ], 500);
        }
    }

    public function includePayroll(Request $request)
    {
        try {
            $detalle = PlanillaDetalle::findOrFail($request->id);
            $detalle->update(['estado' => 2]);

            $this->updatePayrollTotals($detalle->id_planilla);

            return response()->json([
                'message' => 'Detalle de planilla incluido exitosamente'
            ]);
        } catch (\Exception $e) {

            Log::error($e->getMessage());
            return response()->json([
                'error' => 'Error al incluir detalle de planilla: '
            ], 500);
        }
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'tipo_planilla' => 'required|in:quincenal,mensual'
        ]);

        try {
            $importData = [
                'empresa_id' => auth()->user()->id_empresa,
                'sucursal_id' => auth()->user()->id_sucursal,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'tipo_planilla' => $request->tipo_planilla
            ];

            Excel::import(new PlanillasImport($importData), $request->file('archivo'));

            return response()->json([
                'message' => 'Planilla importada exitosamente',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error('Error importando planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al importar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function plantillaImportacion()
    {
        // Definir los headers
        $headers = [
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
            'isss' => 'ISSS',
            'afp' => 'AFP',
            'renta' => 'RENTA',
            'otras_deducciones' => 'Otras Deducciones',
            'detalle_de_otras_deducciones' => 'Detalle de Otras Deducciones',
            'total_neto' => 'Total Neto',
            'firma' => 'Firma'
        ];

        // Obtener empleados activos de la empresa
        $empleados = Empleado::where('id_empresa', auth()->user()->id_empresa)
            ->where('id_sucursal', auth()->user()->id_sucursal)
            ->where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO)
            ->get();

        // Preparar datos de ejemplo
        $data = [];

        foreach ($empleados as $empleado) {
            $data[] = [
                $empleado->codigo,                    // Código Empleado
                $empleado->nombres . ' ' . $empleado->apellidos,  // Nombres y Apellidos
                $empleado->salario_base,             // Salario Base
                30,                                  // Días Laborados (default)
                0,                                   // Comisiones
                0,                                   // Horas Extra
                0,                                   // Monto Horas Extra
                0,                                   // Total Horas Extras
                0,                                   // Bonificaciones
                0,                                   // Otros Ingresos
                0,                                   // Préstamos
                0,                                   // Anticipos
                0,                                   // Descuentos Judiciales
                $empleado->salario_base,             // Sub Total
                $this->calcularISSSEmpleado($empleado->salario_base),  // ISSS
                $this->calcularAFPEmpleado($empleado->salario_base),   // AFP
                $this->calcularRentaImportacion($empleado->salario_base),         // RENTA
                0,                                   // Otras Deducciones
                '',                                  // Detalle de Otras Deducciones
                0,                                   // Total Neto (se calculará con fórmula)
                ''                                   // Firma
            ];
        }

        // Agregar fila de totales
        $data[] = [
            '',             // Código
            'TOTAL',       // Nombres y Apellidos
            '=SUM(C2:C' . (count($data) + 1) . ')',    // Suma Salario Base
            '',            // Días Laborados
            '=SUM(E2:E' . (count($data) + 1) . ')',    // Suma Comisiones
            '=SUM(F2:F' . (count($data) + 1) . ')',    // Suma Horas Extra
            '=SUM(G2:G' . (count($data) + 1) . ')',    // Suma Monto Horas Extra
            '=SUM(H2:H' . (count($data) + 1) . ')',    // Suma Total Horas Extras
            '=SUM(I2:I' . (count($data) + 1) . ')',    // Suma Bonificaciones
            '=SUM(J2:J' . (count($data) + 1) . ')',    // Suma Otros Ingresos
            '=SUM(K2:K' . (count($data) + 1) . ')',    // Suma Préstamos
            '=SUM(L2:L' . (count($data) + 1) . ')',    // Suma Anticipos
            '=SUM(M2:M' . (count($data) + 1) . ')',    // Suma Descuentos Judiciales
            '=SUM(N2:N' . (count($data) + 1) . ')',    // Suma Sub Total
            '=SUM(O2:O' . (count($data) + 1) . ')',    // Suma ISSS
            '=SUM(P2:P' . (count($data) + 1) . ')',    // Suma AFP
            '=SUM(Q2:Q' . (count($data) + 1) . ')',    // Suma RENTA
            '=SUM(R2:R' . (count($data) + 1) . ')',    // Suma Otras Deducciones
            '',            // Detalle de Otras Deducciones
            '=SUM(T2:T' . (count($data) + 1) . ')',    // Suma Total Neto
            ''            // Firma
        ];

        return Excel::download(
            new PlanillaExportTemplate($headers, $data),
            'plantilla_importacion_planillas.xlsx'
        );
    }

    private function calcularRentaImportacion($salario)
    {
        $baseImponible = $salario -
            $this->calcularISSSEmpleado($salario) -
            $this->calcularAFPEmpleado($salario);

        if ($baseImponible <= PlanillaConstants::RENTA_MINIMA) {
            return 0;
        } elseif ($baseImponible <= PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) {
            return round((($baseImponible - PlanillaConstants::RENTA_MINIMA) *
                PlanillaConstants::PORCENTAJE_PRIMER_TRAMO) +
                PlanillaConstants::IMPUESTO_PRIMER_TRAMO, 2);
        } elseif ($baseImponible <= PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) {
            return round((($baseImponible - PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) *
                PlanillaConstants::PORCENTAJE_SEGUNDO_TRAMO) +
                PlanillaConstants::IMPUESTO_SEGUNDO_TRAMO, 2);
        } else {
            return round((($baseImponible - PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) *
                PlanillaConstants::PORCENTAJE_TERCER_TRAMO) +
                PlanillaConstants::IMPUESTO_TERCER_TRAMO, 2);
        }
    }

    private function calcularISSSEmpleado($salario)
    {
        $baseISSSEmpleado = min($salario, 1000);
        return round($baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO, 2);
    }

    private function calcularAFPEmpleado($salario)
    {
        return round($salario * PlanillaConstants::DESCUENTO_AFP_EMPLEADO, 2);
    }

    public function generarBoletas($id)
    {
        try {
            $planilla = Planilla::with(['detalles' => function($query) {
                    $query->where('estado', '!=', 0);
                }, 'detalles.empleado', 'empresa', 'sucursal'])
                ->findOrFail($id);

            $pdf = app('dompdf.wrapper')->loadView('pdf.boletas-pago', [
                'planilla' => $planilla,
                'empresa' => $planilla->empresa,
                'sucursal' => $planilla->sucursal,
                'detalles' => $planilla->detalles
            ]);

            // Configurar PDF
            $pdf->setPaper('letter', 'portrait');
            $pdf->setOptions([
                'enable_php' => true,
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);

            return $pdf->stream("boletas_planilla_{$planilla->codigo}.pdf");
        } catch (\Exception $e) {
            Log::error('Error generando boletas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar las boletas: ' . $e->getMessage()
            ], 500);
        }
    }


    private function registrarGastosPlanilla(Planilla $planilla)
    {
        try {
            // Log::info('Iniciando registro de gastos de planilla', [
            //     'planilla_id' => $planilla->id,
            //     'codigo' => $planilla->codigo,
            //     'total_detalles' => $planilla->detalles->count()
            // ]);

            // Obtener o crear la categoría de gastos de planilla
            $categoria = Categoria::firstOrCreate(
                [
                    'nombre' => 'Gastos de Planilla',
                    'id_empresa' => $planilla->id_empresa
                ]
            );

            // Log::info('Categoría de gastos obtenida', ['categoria_id' => $categoria->id]);

            // Obtener o crear el proveedor para planillas
            $proveedor = Proveedor::firstOrCreate(
                [
                    'tipo' => 'Empresa',
                    'nombre_empresa' => 'Planillas - Empleados',
                    'id_empresa' => $planilla->id_empresa,
                    'id_usuario' => auth()->user()->id
                ],
                [
                    'tipo_contribuyente' => 'Otros',
                    'estado' => 'Activo',
                    'id_sucursal' => $planilla->id_sucursal
                ]
            );

            // Log::info('Proveedor para planillas obtenido', ['proveedor_id' => $proveedor->id]);

            // Contador para detalles procesados
            $detallesProcesados = 0;
            $gastosCreados = 0;

            // Totales para deducciones patronales
            $totalISSS_Patronal = 0;
            $totalAFP_Patronal = 0;

            // Fecha de pago
            $fecha_pago = now();

            // 1. Crear un gasto por cada empleado con su salario neto
            foreach ($planilla->detalles as $detalle) {
                $detallesProcesados++;

                // Incluir detalles con estado 1, 2 o 4
                if ($detalle->estado == 1 || $detalle->estado == 2 || $detalle->estado == 4) {
                    // Verificar si tiene empleado asociado
                    if (!isset($detalle->empleado)) {
                        Log::warning('Detalle sin empleado asociado', ['detalle_id' => $detalle->id]);
                        continue;
                    }

                    // Obtener el nombre completo del empleado
                    $nombreEmpleado = $detalle->empleado->nombres . ' ' . $detalle->empleado->apellidos;

                    // Salario neto (después de deducciones)
                    $sueldoNeto = round(floatval($detalle->sueldo_neto ?? 0), 2);

                    // Acumular totales para deducciones patronales
                    $isssPatronal = round(floatval($detalle->isss_patronal ?? 0), 2);
                    $afpPatronal = round(floatval($detalle->afp_patronal ?? 0), 2);

                    $totalISSS_Patronal += $isssPatronal;
                    $totalAFP_Patronal += $afpPatronal;

                    // Solo crear gasto si el salario neto es mayor a cero
                    if ($sueldoNeto > 0) {
                        // Log::info('Creando gasto para salario neto de empleado', [
                        //     'empleado' => $nombreEmpleado,
                        //     'monto' => $sueldoNeto
                        // ]);

                        $gastoEmpleado = Gasto::create([
                            'fecha' => $fecha_pago,
                            'fecha_pago' => $fecha_pago,
                            'tipo_documento' => 'Planilla',
                            'referencia' => $planilla->codigo,
                            'concepto' => "Salario neto - {$nombreEmpleado}",
                            'tipo' => 'Sueldos y Salarios',
                            'estado' => PlanillaConstants::ESTADO_GASTO_PLANILLA_PAGADO,
                            'forma_pago' => 'Transferencia',
                            'total' => $sueldoNeto,
                            'id_proveedor' => $proveedor->id,
                            'id_categoria' => $categoria->id,
                            'id_usuario' => auth()->id(),
                            'id_empresa' => $planilla->id_empresa,
                            'id_sucursal' => $planilla->id_sucursal,
                            'nota' => "Pago de salario neto a {$nombreEmpleado} - Planilla {$planilla->codigo} - Período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                        ]);

                        $gastosCreados++;
                    }
                }
            }

            // 2. Crear un gasto para el total de ISSS patronal
            if ($totalISSS_Patronal > 0) {
                // Log::info('Creando gasto para total de ISSS patronal', [
                //     'monto' => $totalISSS_Patronal
                // ]);

                $gastoISSS = Gasto::create([
                    'fecha' => $fecha_pago,
                    'fecha_pago' => $fecha_pago,
                    'tipo_documento' => 'Planilla',
                    'referencia' => $planilla->codigo,
                    'concepto' => "Aporte patronal ISSS - Planilla {$planilla->codigo}",
                    'tipo' => 'ISSS Patronal',
                    'estado' => PlanillaConstants::ESTADO_GASTO_PLANILLA_PAGADO,
                    'forma_pago' => 'Transferencia',
                    'total' => $totalISSS_Patronal,
                    'id_proveedor' => $proveedor->id,
                    'id_categoria' => $categoria->id,
                    'id_usuario' => auth()->id(),
                    'id_empresa' => $planilla->id_empresa,
                    'id_sucursal' => $planilla->id_sucursal,
                    'nota' => "Aporte patronal total ISSS - Planilla {$planilla->codigo} - Período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                ]);

                $gastosCreados++;
            }

            // 3. Crear un gasto para el total de AFP patronal
            if ($totalAFP_Patronal > 0) {
                // Log::info('Creando gasto para total de AFP patronal', [
                //     'monto' => $totalAFP_Patronal
                // ]);

                $gastoAFP = Gasto::create([
                    'fecha' => $fecha_pago,
                    'fecha_pago' => $fecha_pago,
                    'tipo_documento' => 'Planilla',
                    'referencia' => $planilla->codigo,
                    'concepto' => "Aporte patronal AFP - Planilla {$planilla->codigo}",
                    'tipo' => 'AFP Patronal',
                    'estado' => PlanillaConstants::ESTADO_GASTO_PLANILLA_PAGADO,
                    'forma_pago' => 'Transferencia',
                    'total' => $totalAFP_Patronal,
                    'id_proveedor' => $proveedor->id,
                    'id_categoria' => $categoria->id,
                    'id_usuario' => auth()->id(),
                    'id_empresa' => $planilla->id_empresa,
                    'id_sucursal' => $planilla->id_sucursal,
                    'nota' => "Aporte patronal total AFP - Planilla {$planilla->codigo} - Período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                ]);

                $gastosCreados++;
            }

            // Log::info('Finalizado registro de gastos de planilla', [
            //     'detalles_procesados' => $detallesProcesados,
            //     'gastos_creados' => $gastosCreados,
            //     'total_isss_patronal' => $totalISSS_Patronal,
            //     'total_afp_patronal' => $totalAFP_Patronal
            // ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error registrando gastos de planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function generarBoletaIndividual($id_detalle)
    {
        try {
            $detalle = PlanillaDetalle::with(['empleado', 'planilla'])->findOrFail($id_detalle);

            // Calcular totales
            $totalIngresos = $detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos;

            $totalDeducciones = $detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->descuentos_judiciales +
                $detalle->otros_descuentos;

            // Generar el PDF
            $pdf = app('dompdf.wrapper')->loadView('pdf.boleta-individual', [
                'detalle' => $detalle,
                'totalIngresos' => $totalIngresos,
                'totalDeducciones' => $totalDeducciones,
                'periodo' => [
                    'inicio' => $detalle->planilla->fecha_inicio,
                    'fin' => $detalle->planilla->fecha_fin
                ]
            ]);

            // Configurar el PDF
            $pdf->setPaper('letter');

            // Nombre del archivo
            $filename = "boleta_{$detalle->planilla->codigo}_{$detalle->empleado->codigo}.pdf";

            // Retornar el PDF para descarga
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error generando boleta individual: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar la boleta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function descargarPlantilla()
    {
        $filePath = public_path('docs/plantilla_importacion_planillas.xlsx');

        if (file_exists($filePath)) {
            return response()->download($filePath, 'plantilla_importacion_planillas.xlsx');
        } else {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }
    }

    /*** Obtener descuentos patronales de una planilla específica */
    public function obtenerDescuentosPatronales($id)
    {
        try {
            // Obtener la planilla
            $planilla = Planilla::with(['empresa', 'sucursal'])->find($id);

            if (!$planilla) {
                return response()->json(['error' => 'Planilla no encontrada'], 404);
            }

            // Obtener los detalles de la planilla con empleados
            $detalles = PlanillaDetalle::where('id_planilla', $planilla->id)
                ->join('empleados', 'planilla_detalles.id_empleado', '=', 'empleados.id')
                ->leftJoin('cargos_de_empresa', 'empleados.id_cargo', '=', 'cargos_de_empresa.id')
                ->leftJoin('departamentos_empresa', 'empleados.id_departamento', '=', 'departamentos_empresa.id')
                ->select(
                    'planilla_detalles.*',
                    'empleados.nombres',
                    'empleados.apellidos',
                    'empleados.codigo',
                    'empleados.dui',
                    'cargos_de_empresa.nombre as cargo_nombre',
                    'departamentos_empresa.nombre as departamento_nombre'
                )
                ->where('planilla_detalles.estado', '!=', 0)
                ->get();

            // Calcular totales de descuentos patronales
            $totalIsssPatronal = $detalles->sum('isss_patronal');
            $totalAfpPatronal = $detalles->sum('afp_patronal');
            $totalDescuentosPatronales = $totalIsssPatronal + $totalAfpPatronal;
            $totalSalariosDevengados = $detalles->sum('salario_devengado');

            // Formatear los datos para el frontend
            $detallesFormateados = $detalles->map(function ($detalle) {
                return [
                    'id' => $detalle->id,
                    'empleado' => [
                        'nombres' => $detalle->nombres,
                        'apellidos' => $detalle->apellidos,
                        'codigo' => $detalle->codigo,
                        'dui' => $detalle->dui,
                        'cargo' => [
                            'nombre' => $detalle->cargo_nombre
                        ],
                        'departamento' => [
                            'nombre' => $detalle->departamento_nombre
                        ]
                    ],
                    'salario_base' => round(floatval($detalle->salario_base), 2),
                    'salario_devengado' => round(floatval($detalle->salario_devengado), 2),
                    'isss_patronal' => round(floatval($detalle->isss_patronal), 2),
                    'afp_patronal' => round(floatval($detalle->afp_patronal), 2),
                    'total_aportes_patronales' => round(floatval($detalle->isss_patronal + $detalle->afp_patronal), 2),
                    'porcentaje_sobre_salario' => $detalle->salario_devengado > 0 ?
                        round((($detalle->isss_patronal + $detalle->afp_patronal) / $detalle->salario_devengado) * 100, 2) : 0
                ];
            });

            return response()->json([
                'planilla' => [
                    'id' => $planilla->id,
                    'codigo' => $planilla->codigo,
                    'fecha_inicio' => $planilla->fecha_inicio,
                    'fecha_fin' => $planilla->fecha_fin,
                    'tipo_planilla' => $planilla->tipo_planilla,
                    'estado' => $planilla->estado
                ],
                'detalles' => $detallesFormateados,
                'resumen' => [
                    'total_isss_patronal' => round($totalIsssPatronal, 2),
                    'total_afp_patronal' => round($totalAfpPatronal, 2),
                    'total_descuentos_patronales' => round($totalDescuentosPatronales, 2),
                    'total_salarios_devengados' => round($totalSalariosDevengados, 2),
                    'porcentaje_total' => $totalSalariosDevengados > 0 ?
                        round(($totalDescuentosPatronales / $totalSalariosDevengados) * 100, 2) : 0,
                    'cantidad_empleados' => $detalles->count()
                ],
                'detalle_porcentajes' => [
                    'isss_patronal' => '7.5%',
                    'afp_patronal' => '7.73%',
                    'total_patronal' => '15.23%'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo descuentos patronales: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los descuentos patronales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular renta según las nuevas tablas 2025
     */
    private function calcularRenta($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla = 'mensual')
    {
        try {
            $salarioGravado = RentaHelper::calcularSalarioGravado($salarioDevengado, $isssEmpleado, $afpEmpleado, $tipoPlanilla);
            $retencionRenta = RentaHelper::calcularRetencionRenta($salarioGravado, $tipoPlanilla);

            return [
                'salario_gravado' => $salarioGravado,
                'retencion_renta' => $retencionRenta,
                'aplicado_correctamente' => true
            ];

        } catch (\Exception $e) {
            Log::error('Error calculando renta: ' . $e->getMessage());
            return [
                'salario_gravado' => 0,
                'retencion_renta' => 0,
                'aplicado_correctamente' => false
            ];
        }
    }

    /**
     * Método actualizado para generar detalles de planilla con nuevas tablas de renta
     */
    private function generarDetallePlanilla($planilla, $empleado, $tipoCalcule)
    {
        try {
            // Calcular salario base según el tipo de planilla
            $salarioBaseMensual = $empleado->salario_base;
            $tipoContrato = $empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
            $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);
            $diasReferencia = 30; // Por defecto, mensual

            // Ajustar según el tipo de planilla
            switch ($planilla->tipo_planilla) {
                case 'quincenal':
                    $diasReferencia = 15;
                    break;
                case 'semanal':
                    $diasReferencia = 7;
                    break;
                default:
                    $diasReferencia = 30;
                    break;
            }

            // Calcular días laborados (por defecto el período completo)
            $diasLaborados = $diasReferencia;

            // Calcular salario base y devengado según tipo de contrato
            if ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_POR_OBRA) {
                // Para contratos Por Obra, el salario base ES el monto total del período
                // NO se divide proporcionalmente
                $salarioBase = $salarioBaseMensual;
                $salarioDevengado = $salarioBaseMensual;
            } elseif ($tipoContrato === PlanillaConstants::TIPO_CONTRATO_SERVICIOS_PROFESIONALES) {
                // Para Servicios Profesionales, el salario base es MENSUAL
                // Se divide según tipo de planilla pero NO usa días laborados
                $salarioBase = $salarioBaseMensual;
                switch ($planilla->tipo_planilla) {
                    case 'quincenal':
                        $salarioDevengado = $salarioBaseMensual / 2;
                        break;
                    case 'semanal':
                        $salarioDevengado = $salarioBaseMensual / 4.33;
                        break;
                    default:
                        $salarioDevengado = $salarioBaseMensual; // mensual
                        break;
                }
            } else {
                // Para empleados asalariados regulares, ajustar según tipo de planilla y días laborados
                $salarioBase = $salarioBaseMensual;
                switch ($planilla->tipo_planilla) {
                    case 'quincenal':
                        $salarioBase = $salarioBaseMensual / 2;
                        break;
                    case 'semanal':
                        $salarioBase = $salarioBaseMensual / 4.33;
                        break;
                }
                // Calcular proporcionalmente según días laborados
                $salarioDevengado = ($salarioBase / $diasReferencia) * $diasLaborados;
            }

            // ✅ CALCULAR DEDUCCIONES SEGÚN TIPO DE CONTRATO (variables ya definidas arriba)
            if ($esContratoSinPrestaciones) {
                // CONTRATOS SIN PRESTACIONES (Por obra y Servicios Profesionales): Sin ISSS ni AFP
                $isssEmpleado = 0;
                $isssPatronal = 0;
                $afpEmpleado = 0;
                $afpPatronal = 0;
            } else {
                // EMPLEADOS ASALARIADOS: Con ISSS y AFP normales
                $baseISSSEmpleado = min($salarioDevengado, 1000);
                $isssEmpleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
                $isssPatronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;
                $afpEmpleado = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
                $afpPatronal = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_PATRONO;
            }

            // Calcular renta usando las nuevas tablas (con tipo de contrato)
            $salarioGravado = RentaHelper::calcularSalarioGravado(
                $salarioDevengado,
                $isssEmpleado,
                $afpEmpleado,
                $planilla->tipo_planilla,
                $tipoContrato
            );
            $renta = RentaHelper::calcularRetencionRenta(
                $salarioGravado,
                $planilla->tipo_planilla,
                $tipoContrato
            );

            // Inicializar otros valores
            $horasExtra = 0;
            $montoHorasExtra = 0;
            $comisiones = 0;
            $bonificaciones = 0;
            $otrosIngresos = 0;
            $prestamos = 0;
            $anticipos = 0;
            $otrosDescuentos = 0;
            $descuentosJudiciales = 0;

            // Calcular totales
            $totalIngresos = $salarioDevengado + $montoHorasExtra + $comisiones + $bonificaciones + $otrosIngresos;
            $totalDescuentos = $isssEmpleado + $afpEmpleado + $renta + $prestamos + $anticipos + $otrosDescuentos + $descuentosJudiciales;
            $sueldoNeto = $totalIngresos - $totalDescuentos;

            // Crear el detalle de planilla
            $detalle = new PlanillaDetalle();
            $detalle->id_planilla = $planilla->id;
            $detalle->id_empleado = $empleado->id;
            $detalle->salario_base = round($salarioBase, 2);
            $detalle->dias_laborados = $diasLaborados;
            $detalle->salario_devengado = round($salarioDevengado, 2);
            $detalle->horas_extra = $horasExtra;
            $detalle->monto_horas_extra = round($montoHorasExtra, 2);
            $detalle->comisiones = round($comisiones, 2);
            $detalle->bonificaciones = round($bonificaciones, 2);
            $detalle->otros_ingresos = round($otrosIngresos, 2);
            $detalle->total_ingresos = round($totalIngresos, 2);
            $detalle->isss_empleado = round($isssEmpleado, 2);
            $detalle->afp_empleado = round($afpEmpleado, 2);
            $detalle->renta = round($renta, 2);
            $detalle->prestamos = round($prestamos, 2);
            $detalle->anticipos = round($anticipos, 2);
            $detalle->otros_descuentos = round($otrosDescuentos, 2);
            $detalle->descuentos_judiciales = round($descuentosJudiciales, 2);
            $detalle->total_descuentos = round($totalDescuentos, 2);
            $detalle->sueldo_neto = round($sueldoNeto, 2);
            $detalle->isss_patronal = round($isssPatronal, 2);
            $detalle->afp_patronal = round($afpPatronal, 2);
            $detalle->estado = 1; // Activo

            $detalle->save();

            return $detalle;

        } catch (\Exception $e) {
            Log::error('Error generando detalle de planilla: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Método para recalcular renta en junio y diciembre
     */
    public function recalcularRenta(Request $request, $planillaId)
    {
        try {
            $planilla = Planilla::find($planillaId);

            if (!$planilla) {
                return response()->json(['error' => 'Planilla no encontrada'], 404);
            }

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
                $salarioAcumulado = $this->obtenerSalarioAcumuladoAnual($detalle->id_empleado, $planilla->anio, $tipoRecalculo);

                // Obtener retenciones anteriores
                $retencionesAnteriores = $this->obtenerRetencionesAnteriores($detalle->id_empleado, $planilla->anio, $tipoRecalculo);

                // Calcular recálculo
                $recalculo = RentaHelper::calcularRecalculoRenta($salarioAcumulado, $tipoRecalculo, $retencionesAnteriores);

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

            return response()->json([
                'message' => 'Recálculo de renta aplicado exitosamente',
                'tipo_recalculo' => $tipoRecalculo,
                'empleados_afectados' => $recalculosAplicados,
                'planilla' => $planilla->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en recálculo de renta: ' . $e->getMessage());
            return response()->json(['error' => 'Error al recalcular renta: ' . $e->getMessage()], 500);
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


    public function obtenerDetalleCalculoRenta($detalleId)
    {
        try {
            $detalle = PlanillaDetalle::with(['empleado', 'planilla'])->findOrFail($detalleId);

            $totalIngresos = $detalle->total_ingresos;
            $isssEmpleado = $detalle->isss_empleado;
            $afpEmpleado = $detalle->afp_empleado;

            // Calcular usando RentaHelper
            $salarioGravado = \App\Helpers\RentaHelper::calcularSalarioGravado(
                $totalIngresos,
                $isssEmpleado,
                $afpEmpleado,
                $detalle->planilla->tipo_planilla
            );

            $retencionRenta = \App\Helpers\RentaHelper::calcularRetencionRenta(
                $salarioGravado,
                $detalle->planilla->tipo_planilla
            );

            // Obtener información del tramo
            $informacionTramo = \App\Helpers\RentaHelper::obtenerInformacionTramo(
                $salarioGravado,
                $detalle->planilla->tipo_planilla
            );

            return response()->json([
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
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo detalle de cálculo de renta: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el detalle del cálculo'
            ], 500);
        }
    }

    public function validarCalculoRenta(Request $request)
    {
        $request->validate([
            'salario_devengado' => 'required|numeric|min:0',
            'isss_empleado' => 'required|numeric|min:0',
            'afp_empleado' => 'required|numeric|min:0',
            'tipo_planilla' => 'required|in:mensual,quincenal,semanal'
        ]);

        try {
            $validacion = \App\Helpers\RentaHelper::validarCalculoRenta(
                $request->salario_devengado,
                $request->isss_empleado,
                $request->afp_empleado,
                $request->tipo_planilla
            );

            return response()->json([
                'validacion' => $validacion,
                'es_valido' => true,
                'mensaje' => 'Cálculo validado correctamente según decreto 2025'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error en la validación: ' . $e->getMessage(),
                'es_valido' => false
            ], 400);
        }
    }

    public function exportarDetallesPlanilla(Request $request)
    {
        try {
            $idPlanilla = $request->input('id_planilla');
            $vista = $request->input('vista', 'empleados');

            if (!$idPlanilla) {
                return response()->json(['error' => 'ID de planilla requerido'], 400);
            }

            $planilla = Planilla::with(['empresa', 'sucursal'])->findOrFail($idPlanilla);

            // Verificar permisos
            if ($planilla->id_empresa !== auth()->user()->id_empresa ||
                $planilla->id_sucursal !== auth()->user()->id_sucursal) {
                return response()->json(['error' => 'No tiene permisos para exportar esta planilla'], 403);
            }

            // Construir query base
            $query = PlanillaDetalle::where('id_planilla', $planilla->id)
                ->join('empleados', 'planilla_detalles.id_empleado', '=', 'empleados.id')
                ->leftJoin('cargos_de_empresa', 'empleados.id_cargo', '=', 'cargos_de_empresa.id')
                ->leftJoin('departamentos_empresa', 'empleados.id_departamento', '=', 'departamentos_empresa.id')
                ->where('planilla_detalles.estado', '!=', 0);

            // Aplicar filtros del frontend
            if ($request->filled('buscador')) {
                $buscador = $request->input('buscador');
                $query->where(function($q) use ($buscador) {
                    $q->where('empleados.nombres', 'LIKE', "%{$buscador}%")
                      ->orWhere('empleados.apellidos', 'LIKE', "%{$buscador}%")
                      ->orWhere('empleados.codigo', 'LIKE', "%{$buscador}%")
                      ->orWhere('empleados.dui', 'LIKE', "%{$buscador}%");
                });
            }

            if ($request->filled('id_departamento')) {
                $query->where('empleados.id_departamento', $request->input('id_departamento'));
            }

            if ($request->filled('id_cargo')) {
                $query->where('empleados.id_cargo', $request->input('id_cargo'));
            }

            if ($request->filled('estado')) {
                $query->where('planilla_detalles.estado', $request->input('estado'));
            }

            // Seleccionar campos según la vista
            if ($vista === 'descuentos_patronales') {
                $query->select([
                    'planilla_detalles.id',
                    'planilla_detalles.salario_base',
                    'planilla_detalles.salario_devengado',
                    'planilla_detalles.isss_patronal',
                    'planilla_detalles.afp_patronal',
                    'empleados.codigo as empleado_codigo',
                    'empleados.nombres',
                    'empleados.apellidos',
                    'empleados.dui',
                    'empleados.nit',
                    'empleados.isss as empleado_isss',
                    'empleados.afp as empleado_afp',
                    'cargos_de_empresa.nombre as cargo_nombre',
                    'departamentos_empresa.nombre as departamento_nombre'
                ]);

                $exportClass = new DescuentosPatronalesExport($planilla, $query->orderBy('empleados.nombres')->get());
                $filename = 'descuentos_patronales_' . $planilla->codigo . '.xlsx';

            } else {
                $query->select([
                    'planilla_detalles.*',
                    'empleados.codigo as empleado_codigo',
                    'empleados.nombres',
                    'empleados.apellidos',
                    'empleados.dui',
                    'empleados.nit',
                    'empleados.isss as empleado_isss',
                    'empleados.afp as empleado_afp',
                    'cargos_de_empresa.nombre as cargo_nombre',
                    'departamentos_empresa.nombre as departamento_nombre'
                ]);

                $exportClass = new PlanillaDetallesExport($planilla, $query->orderBy('empleados.nombres')->get());
                $filename = 'planilla_empleados_' . $planilla->codigo . '.xlsx';
            }

            return Excel::download($exportClass, $filename);

        } catch (\Exception $e) {
            Log::error('Error exportando detalles de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calcularConceptosHibrido($datosEmpleado, $empresaId, $tipoPlanilla)
    {
        Log::info('🔍 DEBUG HÍBRIDO ENTRADA', [
            'empresa_id' => $empresaId,
            'salario_devengado' => $datosEmpleado['salario_devengado'],
            'tipo_planilla' => $tipoPlanilla,
            'servicio_existe' => $this->configuracionPlanillaService !== null
        ]);

        try {
            if (!$this->configuracionPlanillaService) {
                throw new \Exception('Servicio ConfiguracionPlanillaService no está inyectado');
            }

            $resultados = $this->configuracionPlanillaService->calcularConceptos(
                $datosEmpleado,
                $empresaId,
                $tipoPlanilla
            );
            return $resultados;

        } catch (\Exception $e) {
            Log::error('❌ FALLÓ HÍBRIDO, usando legacy', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => basename($e->getFile())
            ]);

            // FALLBACK al sistema anterior
            return $this->calcularConceptosLegacy($datosEmpleado, $tipoPlanilla);
        }
    }

    private function calcularConceptosLegacy($datosEmpleado, $tipoPlanilla)
    {
        $salarioDevengado = $datosEmpleado['salario_devengado'];
        $tipoContrato = $datosEmpleado['tipo_contrato'];

        // Usar tu lógica actual
        $baseISSSEmpleado = min($salarioDevengado, 1000);
        $isssEmpleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
        $isssPatronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;

        $afpEmpleado = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
        $afpPatronal = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_PATRONO;

        // Usar tu RentaHelper existente
        $renta = \App\Helpers\RentaHelper::calcularRetencionRenta(
            \App\Helpers\RentaHelper::calcularSalarioGravado(
                $salarioDevengado,
                $isssEmpleado,
                $afpEmpleado,
                $tipoPlanilla,
                $tipoContrato
            ),
            $tipoPlanilla,
            $tipoContrato
        );

        $totalIngresos = $salarioDevengado;
        $totalDeducciones = $isssEmpleado + $afpEmpleado + $renta;
        $sueldoNeto = $totalIngresos - $totalDeducciones;

        return [
            'isss_empleado' => round($isssEmpleado, 2),
            'isss_patronal' => round($isssPatronal, 2),
            'afp_empleado' => round($afpEmpleado, 2),
            'afp_patronal' => round($afpPatronal, 2),
            'renta' => round($renta, 2),
            'totales' => [
                'total_ingresos' => round($totalIngresos, 2),
                'total_deducciones' => round($totalDeducciones, 2),
                'sueldo_neto' => round($sueldoNeto, 2),
                'aportes_patronales' => round($isssPatronal + $afpPatronal, 2)
            ]
        ];
    }

    private function crearDetallePlanillaFallback($empleado, $planillaId, $tipoPlanilla)
    {
        // 🎯 TU LÓGICA ACTUAL EXACTA - sin cambios
        $diasReferencia = 30;

        if ($tipoPlanilla === 'quincenal') {
            $diasReferencia = 15;
        } elseif ($tipoPlanilla === 'semanal') {
            $diasReferencia = 7;
        }

        $salarioBase = $empleado->salario_base;
        $salarioBaseAjustado = $salarioBase;

        if ($tipoPlanilla === 'quincenal') {
            $salarioBaseAjustado = $salarioBase / 2;
        } elseif ($tipoPlanilla === 'semanal') {
            $salarioBaseAjustado = $salarioBase / 4.33;
        }

        $diasLaborados = $diasReferencia;
        $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $diasLaborados;

        // ✅ VERIFICAR SI ES CONTRATO SIN PRESTACIONES
        $tipoContrato = $empleado->tipo_contrato ?? PlanillaConstants::TIPO_CONTRATO_PERMANENTE;
        $esContratoSinPrestaciones = PlanillaConstants::esContratoSinPrestaciones($tipoContrato);

        // ✅ CALCULAR DEDUCCIONES SEGÚN TIPO DE CONTRATO
        if ($esContratoSinPrestaciones) {
            // CONTRATOS SIN PRESTACIONES (Por obra y Servicios Profesionales): Sin ISSS ni AFP
            $isssEmpleado = 0;
            $isssPatronal = 0;
            $afpEmpleado = 0;
            $afpPatronal = 0;
        } else {
            // EMPLEADOS ASALARIADOS: Con ISSS y AFP normales
            $baseISSSEmpleado = min($salarioDevengado, 1000);
            $isssEmpleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
            $isssPatronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;
            $afpEmpleado = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
            $afpPatronal = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_PATRONO;
        }

        // Calcular renta usando RentaHelper actual (con tipo de contrato)
        $salarioGravado = RentaHelper::calcularSalarioGravado(
            $salarioDevengado,
            $isssEmpleado,
            $afpEmpleado,
            $tipoPlanilla,
            $tipoContrato
        );
        $renta = RentaHelper::calcularRetencionRenta(
            $salarioGravado,
            $tipoPlanilla,
            $tipoContrato
        );

        $totalIngresos = $salarioDevengado;
        $totalDescuentos = $isssEmpleado + $afpEmpleado + $renta;
        $sueldoNeto = $totalIngresos - $totalDescuentos;

        $detalle = new PlanillaDetalle();
        $detalle->id_planilla = $planillaId;
        $detalle->id_empleado = $empleado->id;
        $detalle->salario_base = $salarioBase;
        $detalle->salario_devengado = round($salarioDevengado, 2);
        $detalle->dias_laborados = $diasLaborados;
        $detalle->horas_extra = 0;
        $detalle->monto_horas_extra = 0;
        $detalle->comisiones = 0;
        $detalle->bonificaciones = 0;
        $detalle->otros_ingresos = 0;
        $detalle->isss_empleado = round($isssEmpleado, 2);
        $detalle->isss_patronal = round($isssPatronal, 2);
        $detalle->afp_empleado = round($afpEmpleado, 2);
        $detalle->afp_patronal = round($afpPatronal, 2);
        $detalle->renta = round($renta, 2);
        $detalle->prestamos = 0;
        $detalle->anticipos = 0;
        $detalle->otros_descuentos = 0;
        $detalle->descuentos_judiciales = 0;
        $detalle->total_ingresos = round($totalIngresos, 2);
        $detalle->total_descuentos = round($totalDescuentos, 2);
        $detalle->sueldo_neto = round($sueldoNeto, 2);
        $detalle->estado = PlanillaConstants::PLANILLA_BORRADOR;

        return $detalle;
    }

    public function actualizarDetalle(Request $request, $detalleId)
    {
        try {
            $detalle = PlanillaDetalle::findOrFail($detalleId);
            $planilla = $detalle->planilla;

            // Validar datos de entrada
            $request->validate([
                'salario_devengado' => 'sometimes|numeric|min:0',
                'horas_extra' => 'sometimes|numeric|min:0',
                'monto_horas_extra' => 'sometimes|numeric|min:0',
                'comisiones' => 'sometimes|numeric|min:0',
                'bonificaciones' => 'sometimes|numeric|min:0',
                'otros_ingresos' => 'sometimes|numeric|min:0',
                'abonos' => 'sometimes|numeric|min:0',
                'abonos_sin_retencion' => 'sometimes|boolean',
                'prestamos' => 'sometimes|numeric|min:0',
                'anticipos' => 'sometimes|numeric|min:0',
                'otros_descuentos' => 'sometimes|numeric|min:0',
                'descuentos_judiciales' => 'sometimes|numeric|min:0',
            ]);

            // Preparar datos del empleado con valores actualizados
            $datosEmpleado = [
                'salario_base' => $detalle->salario_base,
                'salario_devengado' => $request->input('salario_devengado', $detalle->salario_devengado),
                'dias_laborados' => $detalle->dias_laborados,
                'horas_extra' => $request->input('horas_extra', $detalle->horas_extra),
                'monto_horas_extra' => $request->input('monto_horas_extra', $detalle->monto_horas_extra),
                'comisiones' => $request->input('comisiones', $detalle->comisiones),
                'bonificaciones' => $request->input('bonificaciones', $detalle->bonificaciones),
                'otros_ingresos' => $request->input('otros_ingresos', $detalle->otros_ingresos),
                'abonos' => $request->input('abonos', $detalle->abonos ?? 0),
                'abonos_sin_retencion' => $request->boolean('abonos_sin_retencion', $detalle->abonos_sin_retencion ?? true),
                'prestamos' => $request->input('prestamos', $detalle->prestamos),
                'anticipos' => $request->input('anticipos', $detalle->anticipos),
                'otros_descuentos' => $request->input('otros_descuentos', $detalle->otros_descuentos),
                'descuentos_judiciales' => $request->input('descuentos_judiciales', $detalle->descuentos_judiciales),
                'tipo_contrato' => $detalle->empleado->tipo_contrato ?? null,
            ];

            // Calcular usando sistema híbrido
            $resultados = $this->calcularConceptosHibrido(
                $datosEmpleado,
                $planilla->id_empresa,
                $planilla->tipo_planilla
            );

            $totalIngresos = ($resultados['totales']['total_ingresos'] ?? 0) + ($datosEmpleado['abonos'] ?? 0);

            // Actualizar detalle con nuevos valores (total_ingresos debe incluir abonos)
            $detalle->update([
                'salario_devengado' => $datosEmpleado['salario_devengado'],
                'horas_extra' => $datosEmpleado['horas_extra'],
                'monto_horas_extra' => $datosEmpleado['monto_horas_extra'],
                'comisiones' => $datosEmpleado['comisiones'],
                'bonificaciones' => $datosEmpleado['bonificaciones'],
                'otros_ingresos' => $datosEmpleado['otros_ingresos'],
                'abonos' => $datosEmpleado['abonos'],
                'abonos_sin_retencion' => $datosEmpleado['abonos_sin_retencion'],
                'prestamos' => $datosEmpleado['prestamos'],
                'anticipos' => $datosEmpleado['anticipos'],
                'otros_descuentos' => $datosEmpleado['otros_descuentos'],
                'descuentos_judiciales' => $datosEmpleado['descuentos_judiciales'],

                // Valores calculados (total_ingresos incluye abonos)
                'isss_empleado' => $resultados['isss_empleado'] ?? 0,
                'afp_empleado' => $resultados['afp_empleado'] ?? 0,
                'renta' => $resultados['renta'] ?? 0,
                'isss_patronal' => $resultados['isss_patronal'] ?? 0,
                'afp_patronal' => $resultados['afp_patronal'] ?? 0,
                'total_ingresos' => round($totalIngresos, 2),
                'total_descuentos' => $resultados['totales']['total_deducciones'] ?? 0,
                'sueldo_neto' => round($totalIngresos - ($resultados['totales']['total_deducciones'] ?? 0), 2),
            ]);

            // Actualizar totales de la planilla
            $planilla->actualizarTotales();

            return response()->json([
                'success' => true,
                'message' => 'Detalle actualizado exitosamente',
                'data' => $detalle->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando detalle de planilla', [
                'detalle_id' => $detalleId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el detalle: ' . $e->getMessage()
            ], 500);
        }
    }
}
