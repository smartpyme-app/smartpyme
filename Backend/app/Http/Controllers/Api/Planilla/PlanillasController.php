<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Exports\PlanillaExport;
use App\Exports\PlanillaExportTemplate;
use App\Http\Controllers\Controller;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Planilla\Empleado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDF;
use App\Imports\PlanillasImport;
use App\Mail\BoletaPagoMailable;
use App\Models\Compras\Gastos\Categoria;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

class PlanillasController extends Controller
{
    public function index(Request $request)
    {
        $query = Planilla::with(['detalles.empleado'])
            ->where('id_empresa', auth()->user()->id_empresa)
            ->where('id_sucursal', auth()->user()->id_sucursal);

        if ($request->has('anio') && $request->anio !== null) {
            $query->where('anio', $request->anio);
        }

        if ($request->has('mes') && $request->mes !== null) {
            $query->where('mes', $request->mes);
        }

        if ($request->has('estado') && $request->estado !== null) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_planilla') && $request->tipo_planilla !== null) {
            $query->where('tipo_planilla', $request->tipo_planilla);
        }

        if ($request->has('buscador')) {
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
            $planilla = Planilla::findOrFail($request->id);

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
            $planillaArray = $planilla->toArray();
            $planillaArray['detalles'] = $detalles;
            $planillaArray['totales'] = $totales;

            return response()->json($planillaArray);
        } catch (\Exception $e) {
            Log::error('Error en show de planilla: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener la planilla: ' . $e->getMessage()], 500);
        }
    }

    public function Store(Request $request)
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
                            Log::info("Empleado ID: {$detalleTemplate->empleado->id} omitido de la planilla por tener fecha de baja/fin");
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
                        Log::info("Empleado ID: {$empleado->id} omitido de la planilla por tener fecha de baja/fin");
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

            // Verificar los totales calculados
            Log::info('Totales de planilla actualizados', [
                'id_planilla' => $planilla->id,
                'total_salarios' => $planilla->total_salarios,
                'total_deducciones' => $planilla->total_deducciones,
                'total_neto' => $planilla->total_neto,
                'total_aportes_patronales' => $planilla->total_aportes_patronales,
                'empleados_incluidos' => $empleadosIncluidos,
                'empleados_omitidos' => $empleadosOmitidos
            ]);

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

    private function calcularSalarioDevengado($salarioBase, $diasLaborados)
    {
        return ($salarioBase / 30) * $diasLaborados;
    }

    private function calcularISSSyAFP($salarioDevengado)
    {
        // Cálculo ISSS - el tope es de $1000 sin importar el período
        $baseISSSEmpleado = min($salarioDevengado, 1000);
        $isssEmpleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
        $isssPatronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;

        // Cálculo AFP - no tiene tope
        $afpEmpleado = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
        $afpPatronal = $salarioDevengado * PlanillaConstants::DESCUENTO_AFP_PATRONO;

        return [
            'isss_empleado' => round($isssEmpleado, 2),
            'isss_patronal' => round($isssPatronal, 2),
            'afp_empleado' => round($afpEmpleado, 2),
            'afp_patronal' => round($afpPatronal, 2)
        ];
    }

    private function calcularRenta($salarioDevengado, $isssEmpleado, $afpEmpleado)
    {
        $baseRenta = $salarioDevengado - $isssEmpleado - $afpEmpleado;

        if ($baseRenta <= PlanillaConstants::RENTA_MINIMA) {
            return 0;
        } elseif ($baseRenta <= PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) {
            return (($baseRenta - PlanillaConstants::RENTA_MINIMA) * PlanillaConstants::PORCENTAJE_PRIMER_TRAMO) + PlanillaConstants::IMPUESTO_PRIMER_TRAMO;
        } elseif ($baseRenta <= PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) {
            return (($baseRenta - PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) * PlanillaConstants::PORCENTAJE_SEGUNDO_TRAMO) + PlanillaConstants::IMPUESTO_SEGUNDO_TRAMO;
        } else {
            return (($baseRenta - PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) * PlanillaConstants::PORCENTAJE_TERCER_TRAMO) + PlanillaConstants::IMPUESTO_TERCER_TRAMO;
        }
    }

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

    private function crearDetallePlanilla($empleado, $planillaId, $tipoPlanilla)
    {
        // Determinar días de referencia según tipo de planilla
        $diasReferencia = 30; // Por defecto, mensual
        $factorAjuste = 1;
    
        if ($tipoPlanilla === 'quincenal') {
            $diasReferencia = 15;
            $factorAjuste = 2; // 2 quincenas por mes
        } elseif ($tipoPlanilla === 'semanal') {
            $diasReferencia = 7;
            $factorAjuste = 4.33; // ~4.33 semanas por mes (promedio)
        }
        
        // Obtener las fechas de la planilla
        $planilla = Planilla::findOrFail($planillaId);
        $fechaInicioPlanilla = Carbon::parse($planilla->fecha_inicio)->startOfDay();
        $fechaFinPlanilla = Carbon::parse($planilla->fecha_fin)->startOfDay();
        
        // Verificar si el empleado tiene fecha de baja o fin programada
        $tieneBajaProgramada = false;
        $diasProporcionales = $diasReferencia;
        
        // Si la baja es anterior al inicio de la planilla, no incluir en la planilla
        if (($empleado->fecha_baja && Carbon::parse($empleado->fecha_baja)->startOfDay() < $fechaInicioPlanilla) ||
            ($empleado->fecha_fin && Carbon::parse($empleado->fecha_fin)->startOfDay() < $fechaInicioPlanilla)) {
            // Verificar si debería estar inactivo pero no lo está
            if ($empleado->estado == PlanillaConstants::ESTADO_EMPLEADO_ACTIVO) {
                // Log warning - empleado debería estar inactivo
                Log::warning("Empleado {$empleado->id} ({$empleado->nombres} {$empleado->apellidos}) tiene fecha de baja/fin pasada pero sigue activo");
            }
            
            // En este caso, no incluir en la planilla
            return null;
        }
        
        // Calcular días proporcionales si hay baja programada dentro del período
        if ($empleado->fecha_baja && Carbon::parse($empleado->fecha_baja)->startOfDay()->between($fechaInicioPlanilla, $fechaFinPlanilla)) {
            $tieneBajaProgramada = true;
            $diasProporcionales = Carbon::parse($empleado->fecha_baja)->startOfDay()->diffInDays($fechaInicioPlanilla) + 1;
        } elseif ($empleado->fecha_fin && Carbon::parse($empleado->fecha_fin)->startOfDay()->between($fechaInicioPlanilla, $fechaFinPlanilla)) {
            $tieneBajaProgramada = true;
            $diasProporcionales = Carbon::parse($empleado->fecha_fin)->startOfDay()->diffInDays($fechaInicioPlanilla) + 1;
        }
        
        // Calcular días laborados
        $diasLaborados = $diasReferencia; // Por defecto, todos los días del período
        
        // Ajustar días laborados si hay baja programada
        if ($tieneBajaProgramada) {
            // Asegurarse de que los días proporcionales no excedan los días de referencia
            $diasLaborados = min($diasProporcionales, $diasReferencia);
            
            // Logging para depuración
            Log::info("Empleado {$empleado->id} con baja programada: días proporcionales = {$diasLaborados} de {$diasReferencia}");
        }
    
        // Obtener salario base mensual
        $salarioBaseMensual = $empleado->salario_base;
    
        // Ajustar el salario base según el tipo de planilla
        $salarioBaseAjustado = $salarioBaseMensual;
        if ($tipoPlanilla !== 'mensual') {
            // Si no es mensual, ajustar según el factor correspondiente
            $salarioBaseAjustado = $salarioBaseMensual / $factorAjuste;
        }
    
        // Calcular salario devengado según días laborados
        $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $diasLaborados;
    
        // Calcular ISSS y AFP
        $descuentosLey = $this->calcularISSSyAFP($salarioDevengado);
    
        // Calcular Renta - se debe ajustar para planilla no mensual
        $baseRenta = $salarioDevengado - $descuentosLey['isss_empleado'] - $descuentosLey['afp_empleado'];
    
        // Para planillas no mensuales, ajustar la base para el cálculo de renta
        $baseRentaAnualizada = $baseRenta;
        if ($tipoPlanilla !== 'mensual') {
            // Multiplicamos por el factor para obtener el valor mensual equivalente
            $baseRentaAnualizada = $baseRenta * $factorAjuste;
        }
    
        $renta = $this->calcularRentaAjustada($baseRentaAnualizada, $tipoPlanilla, $factorAjuste);
    
        // Calcular total de deducciones
        $totalDeducciones =
            $descuentosLey['isss_empleado'] +
            $descuentosLey['afp_empleado'] +
            $renta;
    
        // Calcular total de ingresos (por ahora solo salario devengado)
        $totalIngresos = $salarioDevengado;
    
        // Calcular sueldo neto
        $sueldoNeto = $totalIngresos - $totalDeducciones;
    
        return new PlanillaDetalle([
            'id_planilla' => $planillaId,
            'id_empleado' => $empleado->id,
            'salario_base' => $empleado->salario_base, // Guardamos el salario base mensual completo
            'salario_devengado' => $salarioDevengado,
            'dias_laborados' => $diasLaborados,
            'horas_extra' => 0,
            'monto_horas_extra' => 0,
            'comisiones' => 0,
            'bonificaciones' => 0,
            'otros_ingresos' => 0,
            'isss_empleado' => $descuentosLey['isss_empleado'],
            'isss_patronal' => $descuentosLey['isss_patronal'],
            'afp_empleado' => $descuentosLey['afp_empleado'],
            'afp_patronal' => $descuentosLey['afp_patronal'],
            'renta' => $renta,
            'prestamos' => 0,
            'anticipos' => 0,
            'otros_descuentos' => 0,
            'descuentos_judiciales' => 0,
            'total_ingresos' => $totalIngresos,
            'total_descuentos' => $totalDeducciones,
            'sueldo_neto' => $sueldoNeto,
            'estado' => PlanillaConstants::PLANILLA_BORRADOR
        ]);
    }

    public function calcularRentaAjustada($baseRenta, $tipoPlanilla, $factorAjuste = 1)
    {
        // Calcular renta según tabla de El Salvador
        $renta = 0;

        if ($baseRenta <= PlanillaConstants::RENTA_MINIMA) {
            return 0;
        } elseif ($baseRenta <= PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) {
            $renta = (($baseRenta - PlanillaConstants::RENTA_MINIMA) *
                PlanillaConstants::PORCENTAJE_PRIMER_TRAMO) +
                PlanillaConstants::IMPUESTO_PRIMER_TRAMO;
        } elseif ($baseRenta <= PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) {
            $renta = (($baseRenta - PlanillaConstants::RENTA_MAXIMA_PRIMER_TRAMO) *
                PlanillaConstants::PORCENTAJE_SEGUNDO_TRAMO) +
                PlanillaConstants::IMPUESTO_SEGUNDO_TRAMO;
        } else {
            $renta = (($baseRenta - PlanillaConstants::RENTA_MAXIMA_SEGUNDO_TRAMO) *
                PlanillaConstants::PORCENTAJE_TERCER_TRAMO) +
                PlanillaConstants::IMPUESTO_TERCER_TRAMO;
        }

        // Si no es mensual, dividir la renta calculada por el factor de ajuste
        if ($tipoPlanilla !== 'mensual') {
            $renta = $renta / $factorAjuste;
        }

        return round($renta, 2);
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
            'comisiones' => 'nullable|numeric|min:0',
            'bonificaciones' => 'nullable|numeric|min:0',
            'otros_ingresos' => 'nullable|numeric|min:0',
            'dias_laborados' => 'nullable|numeric|min:0|max:31',
            'prestamos' => 'nullable|numeric|min:0',
            'anticipos' => 'nullable|numeric|min:0',
            'otros_descuentos' => 'nullable|numeric|min:0',
            'descuentos_judiciales' => 'nullable|numeric|min:0',
            'detalle_otras_deducciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $detalle = PlanillaDetalle::findOrFail($id);
            $planilla = $detalle->planilla;

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

            // Actualizar campos básicos
            $detalle->dias_laborados = $request->dias_laborados ?? $diasReferencia;
            $detalle->horas_extra = $request->horas_extra ?? 0;
            $detalle->comisiones = $request->comisiones ?? 0;
            $detalle->bonificaciones = $request->bonificaciones ?? 0;
            $detalle->otros_ingresos = $request->otros_ingresos ?? 0;
            $detalle->prestamos = $request->prestamos ?? 0;
            $detalle->anticipos = $request->anticipos ?? 0;
            $detalle->otros_descuentos = $request->otros_descuentos ?? 0;
            $detalle->descuentos_judiciales = $request->descuentos_judiciales ?? 0;
            $detalle->detalle_otras_deducciones = $request->detalle_otras_deducciones;

            // Calcular salario devengado según días laborados
            $salarioBaseMensual = $detalle->salario_base;
            $salarioBaseAjustado = $planilla->tipo_planilla !== 'mensual' ?
                $salarioBaseMensual / $factorAjuste : $salarioBaseMensual;
            $salarioDevengado = ($salarioBaseAjustado / $diasReferencia) * $detalle->dias_laborados;
            $detalle->salario_devengado = $salarioDevengado;

            // Calcular monto de horas extra si aplica
            if ($detalle->horas_extra > 0) {
                $valorHoraNormal = $salarioBaseAjustado / $diasReferencia / 8;
                $detalle->monto_horas_extra = $detalle->horas_extra * ($valorHoraNormal * 1.25);
            } else {
                $detalle->monto_horas_extra = 0;
            }

            // Calcular total de ingresos
            $detalle->total_ingresos = $detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos;

            // Recalcular deducciones de ley
            $baseISSSEmpleado = min($detalle->total_ingresos, 1000);
            $detalle->isss_empleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
            $detalle->isss_patronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;
            $detalle->afp_empleado = $detalle->total_ingresos * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
            $detalle->afp_patronal = $detalle->total_ingresos * PlanillaConstants::DESCUENTO_AFP_PATRONO;

            // Calcular renta ajustada
            $baseRenta = $detalle->total_ingresos - $detalle->isss_empleado - $detalle->afp_empleado;
            $baseRentaAnualizada = $baseRenta;

            if ($planilla->tipo_planilla !== 'mensual') {
                $baseRentaAnualizada = $baseRenta * $factorAjuste;
            }

            $detalle->renta = $this->calcularRentaAjustada($baseRentaAnualizada, $planilla->tipo_planilla, $factorAjuste);

            // Calcular total de deducciones
            $detalle->total_descuentos = $detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->otros_descuentos +
                $detalle->descuentos_judiciales;

            // Calcular sueldo neto
            $detalle->sueldo_neto = $detalle->total_ingresos - $detalle->total_descuentos;

            // Guardar cambios
            $detalle->save();

            // Actualizar totales de la planilla
            $this->updatePayrollTotals($planilla->id);

            DB::commit();

            return response()->json([
                'message' => 'Detalle actualizado exitosamente',
                'detalle' => $detalle->fresh(['empleado']),
                'planilla' => $planilla->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el detalle: ' . $e->getMessage()
            ], 500);
        }
    }


    public function approvePayroll($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Planilla::findOrFail($id);

            if ($planilla->estado != PlanillaConstants::PLANILLA_BORRADOR) {
                return response()->json([
                    'error' => 'Solo se pueden aprobar planillas en estado borrador'
                ], 422);
            }

            $planilla->estado = PlanillaConstants::PLANILLA_APROBADA; // Aprobada
            $planilla->save();

            DB::commit();

            return response()->json([
                'message' => 'Planilla aprobada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
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
            Log::info('Iniciando procesamiento de planilla', [
                'planilla_id' => $id,
                'total_detalles' => $planilla->detalles->count()
            ]);

            if ($planilla->estado != PlanillaConstants::PLANILLA_APROBADA) {
                return response()->json([
                    'error' => 'Solo se pueden pagar planillas aprobadas'
                ], 422);
            }

            // Verificar configuración de correo
            $this->verificarConfiguracionCorreo();

            // Registrar gastos en contabilidad
            $this->registrarGastosPlanilla($planilla);

            $emailsEnviados = 0;
            $errores = [];
            $detallesProcesados = 0;
            $empleadosSinEmail = 0;
            $empleadosInactivos = 0;

            // Enviar boletas por correo a cada empleado
            foreach ($planilla->detalles as $detalle) {
                $detallesProcesados++;

                // Log de cada detalle
                Log::info('Procesando detalle de planilla', [
                    'detalle_id' => $detalle->id,
                    'empleado_id' => $detalle->empleado->id ?? 'No tiene empleado',
                    'estado_detalle' => $detalle->estado,
                    'tiene_empleado' => isset($detalle->empleado),
                    'email_empleado' => $detalle->empleado->email ?? 'No tiene email'
                ]);

                if (!isset($detalle->empleado)) {
                    Log::warning('Detalle sin empleado asociado', ['detalle_id' => $detalle->id]);
                    $errores[] = "Detalle ID {$detalle->id} no tiene empleado asociado";
                    continue;
                }

                if ($detalle->estado == PlanillaConstants::ESTADO_INACTIVO) {
                    $empleadosInactivos++;
                    Log::info('Empleado inactivo en planilla', [
                        'empleado_id' => $detalle->empleado->id,
                        'estado' => $detalle->estado
                    ]);
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
                    Log::info('Intentando enviar correo', [
                        'empleado_email' => $detalle->empleado->email,
                        'empleado_id' => $detalle->empleado->id
                    ]);

                    // Enviar correo de forma síncrona para mejor debugging
                    Mail::to($detalle->empleado->email)
                        ->send(new BoletaPagoMailable(
                            $detalle,
                            $planilla,
                            $planilla->empresa,
                            $periodo
                        ));

                    $emailsEnviados++;

                    Log::info('Correo enviado exitosamente', [
                        'empleado_email' => $detalle->empleado->email,
                        'empleado_id' => $detalle->empleado->id
                    ]);
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

            // Actualizar estado de la planilla
            $planilla->estado = PlanillaConstants::PLANILLA_PAGADA;
            $planilla->save();

            DB::commit();

            // Log final con estadísticas
            Log::info('Finalizado procesamiento de planilla', [
                'detalles_procesados' => $detallesProcesados,
                'emails_enviados' => $emailsEnviados,
                'empleados_sin_email' => $empleadosSinEmail,
                'empleados_inactivos' => $empleadosInactivos,
                'total_errores' => count($errores)
            ]);

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

        Log::info('Configuración de correo verificada', $config);
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

            if ($planilla->estado != 1) {
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
            $planilla = Planilla::with(['detalles.empleado', 'empresa'])
                ->findOrFail($id);

            $pdf = PDF::loadView('pdf.planilla-detalle', [
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
            $detalle->update(['estado' => 1]);

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
            $planilla = Planilla::with(['detalles.empleado', 'empresa', 'sucursal'])
                ->findOrFail($id);

            $pdf = PDF::loadView('pdf.boletas-pago', [
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
            // Obtener o crear la categoría de gastos de planilla
            $categoria = Categoria::firstOrCreate(
                [
                    'nombre' => 'Gastos de Planilla',
                    'id_empresa' => $planilla->id_empresa
                ]
            );

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

            // Agrupar los gastos por tipo
            $gastos = [
                'Sueldos y Salarios' => [
                    'monto' => 0,
                    'concepto' => 'Pago de salarios'
                ],
                'ISSS Empleado' => [
                    'monto' => 0,
                    'concepto' => 'Retención ISSS empleados'
                ],
                'ISSS Patronal' => [
                    'monto' => 0,
                    'concepto' => 'Aporte patronal ISSS'
                ],
                'AFP Empleado' => [
                    'monto' => 0,
                    'concepto' => 'Retención AFP empleados'
                ],
                'AFP Patronal' => [
                    'monto' => 0,
                    'concepto' => 'Aporte patronal AFP'
                ],
                'Renta' => [
                    'monto' => 0,
                    'concepto' => 'Retención de renta'
                ]
            ];

            // Calcular totales
            foreach ($planilla->detalles as $detalle) {
                if ($detalle->estado === 1) {
                    $gastos['Sueldos y Salarios']['monto'] += $detalle->salario_devengado;
                    $gastos['ISSS Empleado']['monto'] += $detalle->isss_empleado;
                    $gastos['ISSS Patronal']['monto'] += $detalle->isss_patronal;
                    $gastos['AFP Empleado']['monto'] += $detalle->afp_empleado;
                    $gastos['AFP Patronal']['monto'] += $detalle->afp_patronal;
                    $gastos['Renta']['monto'] += $detalle->renta;
                }
            }

            // Registrar cada tipo de gasto
            $fecha_pago = now();
            foreach ($gastos as $tipo => $datos) {
                if ($datos['monto'] > 0) {
                    Gasto::create([
                        'fecha' => $fecha_pago,
                        'fecha_pago' => $fecha_pago,
                        'tipo_documento' => 'Planilla',
                        'referencia' => $planilla->codigo,
                        'concepto' => "{$datos['concepto']} - Planilla {$planilla->codigo}",
                        'tipo' => $tipo,
                        'estado' => 'Pagado',
                        'forma_pago' => 'Transferencia',
                        'total' => $datos['monto'],
                        'id_proveedor' => $proveedor->id,
                        'id_categoria' => $categoria->id,
                        'id_usuario' => auth()->id(),
                        'id_empresa' => $planilla->id_empresa,
                        'id_sucursal' => $planilla->id_sucursal,
                        'nota' => "Registro automático de gastos de planilla {$planilla->codigo} período {$planilla->fecha_inicio} al {$planilla->fecha_fin}"
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error registrando gastos de planilla: ' . $e->getMessage());
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
            $pdf = PDF::loadView('pdf.boleta-individual', [
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
        $filePath = public_path('storage/plantillas/plantilla_importacion_planillas.xlsx');

        if (file_exists($filePath)) {
            return response()->download($filePath, 'plantilla_importacion_planillas.xlsx');
        } else {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }
    }
}
