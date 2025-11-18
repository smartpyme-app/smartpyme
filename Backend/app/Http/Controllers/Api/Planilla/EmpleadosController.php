<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Helpers\DocumentHelper;
use App\Helpers\RentaHelper;
use App\Http\Controllers\Controller;
use App\Models\Planilla\ContactoEmergencia;
use App\Models\Planilla\DocumentoEmpleado;
use App\Models\Planilla\Empleado;
use App\Models\Planilla\HistorialContrato;
use App\Models\Planilla\HistorialBaja;
use App\Models\Planilla\Planilla;
use App\Models\Planilla\PlanillaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Imports\EmpleadosImport;
use Maatwebsite\Excel\Facades\Excel;

class EmpleadosController extends Controller
{

    protected $planillasController;

    public function __construct(PlanillasController $planillasController)
    {
        $this->planillasController = $planillasController;
    }

    public function index(Request $request)
    {

        $id_sucursal = auth()->user()->id_sucursal;
        $id_empresa = auth()->user()->id_empresa;
        $query = Empleado::with(['departamento', 'cargo'])
            ->where('id_sucursal', $id_sucursal)
            ->where('id_empresa', $id_empresa);

        if ($request->has('buscador')) {
            $busqueda = $request->buscador;
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombres', 'LIKE', "%$busqueda%")
                    ->orWhere('apellidos', 'LIKE', "%$busqueda%")
                    ->orWhere('dui', 'LIKE', "%$busqueda%")
                    ->orWhere('codigo', 'LIKE', "%$busqueda%");
            });
        }

        if ($request->has('estado') && $request->estado != '') {
            $query->where('estado', $request->estado);
        }

        if ($request->has('id_departamento') && $request->id_departamento != '') {
            $query->where('id_departamento', $request->id_departamento);
        }

        if ($request->has('id_cargo') && $request->id_cargo != '') {
            $query->where('id_cargo', $request->id_cargo);
        }

        if ($request->has('tipo_contrato') && $request->tipo_contrato != '') {
            $query->where('tipo_contrato', $request->tipo_contrato);
        }

        if ($request->has('tipo_jornada') && $request->tipo_jornada != '') {
            $query->where('tipo_jornada', $request->tipo_jornada);
        }

        // Ordenamiento
        $orden = $request->orden ?? 'nombres';
        $direccion = $request->direccion ?? 'asc';
        $query->orderBy($orden, $direccion);

        // Paginación
        $perPage = $request->paginate ?? 10;
        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'dui' => 'required|string|unique:empleados,dui,' . $request->id,
            'nit' => 'nullable|string',
            'isss' => 'nullable|string',
            'afp' => 'nullable|string',
            'fecha_nacimiento' => 'required|date',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string',
            'email' => 'required|email',
            'salario_base' => 'required|numeric|min:0',
            'tipo_contrato' => 'required',
            'tipo_jornada' => 'required',
            'fecha_ingreso' => 'required|date',
            'id_departamento' => 'required|exists:departamentos_empresa,id',
            'id_cargo' => 'required|exists:cargos_de_empresa,id',
            'forma_pago' => 'nullable|in:Transferencia,Cheque,Efectivo',

            // Nuevos campos bancarios
            'banco' => 'nullable|string|max:100',
            'tipo_cuenta' => 'nullable|in:Ahorro,Corriente',
            'numero_cuenta' => 'nullable|string|max:50',
            'titular_cuenta' => 'nullable|string|max:100',
            'forma_pago' => 'nullable|string|max:50',

            // Contacto emergencia        
            'contacto_emergencia' => 'nullable|array',
            'contacto_emergencia.nombre' => 'nullable|string',
            'contacto_emergencia.relacion' => 'nullable|string',
            'contacto_emergencia.telefono' => 'nullable|string',
            'contacto_emergencia.direccion' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $salarioAnterior = null;
            if ($request->id) {
                $empleadoExistente = Empleado::find($request->id);
                if ($empleadoExistente) {
                    $salarioAnterior = $empleadoExistente->salario_base;
                }
            }

            // Crear o actualizar empleado
            $empleado = Empleado::updateOrCreate(
                ['id' => $request->id],
                $request->all() + [
                    'id_empresa' => auth()->user()->id_empresa,
                    'id_sucursal' => auth()->user()->id_sucursal,
                    'id_departamento' => $request->id_departamento,
                    'id_cargo' => $request->id_cargo,
                    'tipo_contrato' => intval($request->tipo_contrato),
                    'tipo_jornada' => intval($request->tipo_jornada),
                    'fecha_ingreso' => $request->fecha_ingreso,
                    'fecha_nacimiento' => $request->fecha_nacimiento,
                    'direccion' => $request->direccion,
                    'telefono' => $request->telefono,
                    'email' => $request->email,
                    'salario_base' => $request->salario_base,
                    'estado' => $request->estado ?? PlanillaConstants::ESTADO_EMPLEADO_ACTIVO,
                ]
            );

            // Verificar si hubo cambio en el salario
            $salarioCambiado = $salarioAnterior !== null && $salarioAnterior != $request->salario_base;

            // Crear o actualizar contacto de emergencia si existe
            if ($request->has('contacto_emergencia') && is_array($request->contacto_emergencia)) {
                $contactoData = [
                    'id_empleado' => $empleado->id,
                    'nombre' => $request->contacto_emergencia['nombre'] ?? '',
                    'relacion' => $request->contacto_emergencia['relacion'] ?? '',
                    'telefono' => $request->contacto_emergencia['telefono'] ?? '',
                    'direccion' => $request->contacto_emergencia['direccion'] ?? '',
                    'estado' => 1
                ];

                // Verificar que los campos requeridos tengan valor
                if (!empty($contactoData['nombre']) && !empty($contactoData['telefono'])) {
                    ContactoEmergencia::updateOrCreate(
                        ['id_empleado' => $empleado->id],
                        $contactoData
                    );
                }
            }

            // Registrar historial de contrato si es nuevo empleado o hay cambios relevantes
            if (!$request->id || $empleado->wasChanged(['salario_base', 'tipo_contrato', 'id_cargo'])) {
                HistorialContrato::create([
                    'id_empleado' => $empleado->id,
                    'fecha_inicio' => $request->fecha_ingreso,
                    'tipo_contrato' => intval($request->tipo_contrato),
                    'tipo_jornada' => intval($request->tipo_jornada),
                    'salario' => $request->salario_base,
                    'id_cargo' => $request->id_cargo,
                    'motivo_cambio' => $request->id ?
                        PlanillaConstants::MOTIVO_CAMBIO_CONTRATO_ACTUALIZACION
                        : PlanillaConstants::MOTIVO_CAMBIO_CONTRATO_INICIAL,
                    'estado' => PlanillaConstants::ESTADO_ACTIVO
                ]);
            }

            if ($salarioCambiado) {
                $this->actualizarPlanillasConNuevoSalario($empleado->id, $request->salario_base);
            }    

            DB::commit();
            return $empleado;
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Obtener empleado existente
        $empleado = Empleado::findOrFail($id);
        
        // Validar que el empleado pertenezca a la empresa y sucursal del usuario
        if ($empleado->id_empresa != auth()->user()->id_empresa || 
            $empleado->id_sucursal != auth()->user()->id_sucursal) {
            return response()->json(['error' => 'No tienes permiso para actualizar este empleado'], 403);
        }

        // Preparar reglas de validación para DUI
        // Solo validar unicidad si el DUI viene y es diferente al actual
        $reglasDui = [];
        if ($request->has('dui') && $request->dui !== null && trim($request->dui) !== '') {
            $duiActual = trim($empleado->dui ?? '');
            $duiNuevo = trim($request->dui);
            
            if ($duiNuevo !== $duiActual) {
                // Si el DUI cambió, validar unicidad
                $reglasDui = [
                    'sometimes',
                    'string',
                    Rule::unique('empleados', 'dui')->ignore($id)
                ];
            } else {
                // Si es el mismo DUI, solo validar formato
                $reglasDui = ['sometimes', 'string'];
            }
        }
        // Si no viene DUI, no validar

        // Validación con campos opcionales (sometimes)
        $reglasValidacion = [
            'nombres' => 'sometimes|string|max:100',
            'apellidos' => 'sometimes|string|max:100',
            'nit' => 'nullable|string',
            'isss' => 'nullable|string',
            'afp' => 'nullable|string',
            'fecha_nacimiento' => 'sometimes|date',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string',
            'email' => 'sometimes|email',
            'salario_base' => 'sometimes|numeric|min:0',
            'tipo_contrato' => 'sometimes',
            'tipo_jornada' => 'sometimes',
            'fecha_ingreso' => 'sometimes|date',
            'id_departamento' => 'sometimes|exists:departamentos_empresa,id',
            'id_cargo' => 'sometimes|exists:cargos_de_empresa,id',
            'forma_pago' => 'nullable|in:Transferencia,Cheque,Efectivo',
            'banco' => 'nullable|string|max:100',
            'tipo_cuenta' => 'nullable|in:Ahorro,Corriente',
            'numero_cuenta' => 'nullable|string|max:50',
            'titular_cuenta' => 'nullable|string|max:100',
            'estado' => 'sometimes',
            'contacto_emergencia' => 'nullable|array',
            'contacto_emergencia.nombre' => 'nullable|string',
            'contacto_emergencia.relacion' => 'nullable|string',
            'contacto_emergencia.telefono' => 'nullable|string',
            'contacto_emergencia.direccion' => 'nullable|string'
        ];

        // Agregar reglas de DUI solo si se definieron
        if (!empty($reglasDui)) {
            $reglasValidacion['dui'] = $reglasDui;
        }

        $request->validate($reglasValidacion);

        try {
            DB::beginTransaction();

            // Guardar salario anterior para comparar cambios
            $salarioAnterior = $empleado->salario_base;
            
            // Preparar datos para actualizar (solo campos que vienen en el request)
            $datosActualizar = [];
            
            $camposPermitidos = [
                'nombres', 'apellidos', 'dui', 'nit', 'isss', 'afp',
                'fecha_nacimiento', 'direccion', 'telefono', 'email',
                'salario_base', 'tipo_contrato', 'tipo_jornada',
                'fecha_ingreso', 'id_departamento', 'id_cargo',
                'forma_pago', 'banco', 'tipo_cuenta', 'numero_cuenta',
                'titular_cuenta', 'estado'
            ];

            foreach ($camposPermitidos as $campo) {
                if ($request->has($campo) && $request->$campo !== null) {
                    if (in_array($campo, ['tipo_contrato', 'tipo_jornada'])) {
                        $datosActualizar[$campo] = intval($request->$campo);
                    } else {
                        $datosActualizar[$campo] = $request->$campo;
                    }
                }
            }

            // Actualizar empleado
            $empleado->fill($datosActualizar);
            $empleado->save();

            // Verificar cambios antes de refrescar
            $huboCambiosContrato = $empleado->wasChanged(['salario_base', 'tipo_contrato', 'id_cargo']);
            
            // Refrescar para obtener valores actualizados
            $empleado->refresh();
            
            // Verificar si hubo cambio en el salario
            $salarioNuevo = $empleado->salario_base;
            $salarioCambiado = $salarioAnterior != $salarioNuevo;

            // Crear o actualizar contacto de emergencia si existe
            if ($request->has('contacto_emergencia') && is_array($request->contacto_emergencia)) {
                $contactoData = [
                    'id_empleado' => $empleado->id,
                    'nombre' => $request->contacto_emergencia['nombre'] ?? '',
                    'relacion' => $request->contacto_emergencia['relacion'] ?? '',
                    'telefono' => $request->contacto_emergencia['telefono'] ?? '',
                    'direccion' => $request->contacto_emergencia['direccion'] ?? '',
                    'estado' => 1
                ];

                // Verificar que los campos requeridos tengan valor
                if (!empty($contactoData['nombre']) && !empty($contactoData['telefono'])) {
                    ContactoEmergencia::updateOrCreate(
                        ['id_empleado' => $empleado->id],
                        $contactoData
                    );
                }
            }

            // Registrar historial de contrato si hay cambios relevantes
            if ($huboCambiosContrato) {
                // Usar valores del empleado actualizado o del request
                $fechaInicio = $request->fecha_ingreso ?? $empleado->fecha_ingreso;
                $tipoContrato = $request->tipo_contrato ?? $empleado->tipo_contrato;
                $tipoJornada = $request->tipo_jornada ?? $empleado->tipo_jornada;
                $salario = $salarioNuevo;
                $idCargo = $request->id_cargo ?? $empleado->id_cargo;

                HistorialContrato::create([
                    'id_empleado' => $empleado->id,
                    'fecha_inicio' => $fechaInicio,
                    'tipo_contrato' => intval($tipoContrato),
                    'tipo_jornada' => intval($tipoJornada),
                    'salario' => $salario,
                    'id_cargo' => $idCargo,
                    'motivo_cambio' => PlanillaConstants::MOTIVO_CAMBIO_CONTRATO_ACTUALIZACION,
                    'estado' => PlanillaConstants::ESTADO_ACTIVO
                ]);
            }

            // Actualizar planillas si cambió el salario
            if ($salarioCambiado) {
                $this->actualizarPlanillasConNuevoSalario($empleado->id, $salarioNuevo);
            }

            DB::commit();
            
            // Cargar relaciones para la respuesta
            $empleado->load(['departamento', 'cargo', 'contacto_emergencia']);
            
            return response()->json([
                'message' => 'Empleado actualizado exitosamente',
                'empleado' => $empleado
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al actualizar empleado: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Error al actualizar el empleado',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function actualizarPlanillasConNuevoSalario($idEmpleado, $nuevoSalario)
    {
        // Obtener todas las planillas en estado borrador que contengan al empleado
        $planillasActivas = Planilla::where('estado', PlanillaConstants::PLANILLA_BORRADOR)
            ->whereHas('detalles', function($query) use ($idEmpleado) {
                $query->where('id_empleado', $idEmpleado);
            })
            ->get();
        
        // Si no hay planillas activas, no hay nada que actualizar
        if ($planillasActivas->isEmpty()) {
            return;
        }
        
        Log::info("Actualizando salario en planillas para empleado ID: {$idEmpleado}. Nuevo salario: {$nuevoSalario}");
        
        foreach ($planillasActivas as $planilla) {
            // Obtener el detalle del empleado en esta planilla
            $detalle = PlanillaDetalle::where('id_planilla', $planilla->id)
                ->where('id_empleado', $idEmpleado)
                ->first();
            
            if (!$detalle) {
                continue;
            }
            
            // Guardar salario base anterior para calcular proporción
            $salarioBaseAnterior = $detalle->salario_base;
            
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
            
            // Actualizar salario base en detalle
            $detalle->salario_base = $nuevoSalario;
            
            // Recalcular salario devengado según días laborados
            $salarioBaseAjustado = $planilla->tipo_planilla !== 'mensual' ?
                $nuevoSalario / $factorAjuste : $nuevoSalario;
            $detalle->salario_devengado = ($salarioBaseAjustado / $diasReferencia) * $detalle->dias_laborados;
            
            // Recalcular ISSS y AFP
            $baseISSSEmpleado = min($detalle->salario_devengado, 1000);
            $detalle->isss_empleado = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO;
            $detalle->isss_patronal = $baseISSSEmpleado * PlanillaConstants::DESCUENTO_ISSS_PATRONO;
            $detalle->afp_empleado = $detalle->salario_devengado * PlanillaConstants::DESCUENTO_AFP_EMPLEADO;
            $detalle->afp_patronal = $detalle->salario_devengado * PlanillaConstants::DESCUENTO_AFP_PATRONO;
            
            $empleado = $detalle->empleado;
            $tipoContrato = $empleado ? $empleado->tipo_contrato : null;
            
            $salarioGravado = RentaHelper::calcularSalarioGravado(
                $detalle->salario_devengado,
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
            
            $detalle->total_ingresos = $detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos;
                
            $detalle->total_descuentos = $detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->otros_descuentos +
                $detalle->descuentos_judiciales;
                
            $detalle->sueldo_neto = $detalle->total_ingresos - $detalle->total_descuentos;
            
            $detalle->save();
            
            // Log::info("Actualizado detalle de planilla ID: {$detalle->id}, de salario {$salarioBaseAnterior} a {$nuevoSalario}");
            
            // Actualizar totales de la planilla
            $planilla->actualizarTotales();
        }
        
        Log::info("Finalizada actualización de salario en planillas para empleado ID: {$idEmpleado}");
    }

    public function getDocumentos($id)
    {
        try {
            $empleado = Empleado::findOrFail($id);

            $documentos = DocumentoEmpleado::where('id_empleado', $id)
                ->where('estado', PlanillaConstants::ESTADO_ACTIVO)
                ->orderBy('fecha_documento', 'desc')
                ->paginate(10);

            return response()->json($documentos);
        } catch (\Exception $e) {
            Log::error('Error al obtener documentos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener los documentos'], 500);
        }
    }


    public function show($id)
    {
        return Empleado::with([
            'departamento',
            'cargo',
            'contacto_emergencia',
            'historial_contrato' => function ($query) {
                $query->orderBy('fecha_inicio', 'desc');
            }
        ])->findOrFail($id);
    }

    public function darBaja(Request $request, $id)
    {
        $request->validate([
            'fecha_fin' => 'required|date',     // Fecha de notificación
            'fecha_baja' => 'required|date',    // Fecha efectiva de baja
            'tipo_baja' => 'required|in:Renuncia,Despido,Terminación de contrato',
            'motivo' => 'required|string',
            'documento_respaldo' => 'nullable|file|mimes:pdf,doc,docx|max:2048' // 2MB max
        ]);

        try {
            DB::beginTransaction();

            $empleado = Empleado::findOrFail($id);

            // Validar que la fecha de notificación no sea anterior a la fecha de ingreso
            if (Carbon::parse($request->fecha_fin)->lt(Carbon::parse($empleado->fecha_ingreso))) {
                Log::info('La fecha de notificación no puede ser anterior a la fecha de ingreso');
                return response()->json(['error' => 'La fecha de notificación no puede ser anterior a la fecha de ingreso'], 422);
            }

            // Validar que la fecha efectiva de baja no sea anterior a la fecha de ingreso
            if (Carbon::parse($request->fecha_baja)->lt(Carbon::parse($empleado->fecha_ingreso))) {
                Log::info('La fecha efectiva de baja no puede ser anterior a la fecha de ingreso');
                return response()->json(['error' => 'La fecha efectiva de baja no puede ser anterior a la fecha de ingreso'], 422);
            }

            // Validar que la fecha efectiva no sea anterior a la notificación
            if (Carbon::parse($request->fecha_baja)->lt(Carbon::parse($request->fecha_fin))) {
                Log::info('La fecha efectiva de baja no puede ser anterior a la fecha de notificación');
                return response()->json(['error' => 'La fecha efectiva de baja no puede ser anterior a la fecha de notificación'], 422);
            }

            // Manejar el archivo si existe
            $rutaArchivo = null;
            $tipoDocumento = null;
            $tipoBajaInt = null;

            // Determinar tipo de baja antes del manejo de archivos
            switch ($request->tipo_baja) {
                case 'Renuncia':
                    $tipoDocumento = PlanillaConstants::TIPO_DOCUMENTO_RENUNCIA;
                    $tipoBajaInt = PlanillaConstants::TIPO_BAJA_RENUNCIA;
                    break;
                case 'Despido':
                    $tipoDocumento = PlanillaConstants::TIPO_DOCUMENTO_DESPIDO;
                    $tipoBajaInt = PlanillaConstants::TIPO_BAJA_DESPIDO;
                    break;
                case 'Terminación de contrato':
                    $tipoDocumento = PlanillaConstants::TIPO_DOCUMENTO_TERMINACION;
                    $tipoBajaInt = PlanillaConstants::TIPO_BAJA_TERMINACION_CONTRATO;
                    break;
            }

            if ($request->hasFile('documento_respaldo')) {
                $resultado = DocumentHelper::saveEmployeeDocument(
                    $request->file('documento_respaldo'),
                    auth()->user()->id_empresa,
                    $id
                );

                if (!$resultado['success']) {
                    Log::info('Error al guardar el documento: ' . $resultado['error']);
                    return response()->json(['error' => 'Error al guardar el documento'], 500);
                }

                $documento = DocumentoEmpleado::create([
                    'id_empleado' => $id,
                    'tipo_documento' => $tipoDocumento,
                    'nombre_archivo' => $resultado['nombre'],
                    'ruta_archivo' => $resultado['ruta'],
                    'fecha_documento' => $request->fecha_fin, // Usar fecha de notificación para el documento
                    'estado' => PlanillaConstants::ESTADO_ACTIVO
                ]);
            }

            // Actualizar empleado
            $empleado->fecha_fin = $request->fecha_fin;     // Fecha de notificación
            $empleado->fecha_baja = $request->fecha_baja;   // Fecha efectiva

            // Decidir si cambiar el estado ahora o dejarlo para la fecha de baja
            $fechaActual = Carbon::now()->startOfDay();
            $fechaBaja = Carbon::parse($request->fecha_baja)->startOfDay();

            if ($fechaBaja->lte($fechaActual)) {
                // Si la fecha efectiva de baja es hoy o en el pasado, inactivar inmediatamente
                $empleado->estado = PlanillaConstants::ESTADO_EMPLEADO_INACTIVO;
            }
            // Si la fecha es futura, mantener estado actual (se actualizará mediante un job)

            $empleado->save();

            // Registrar en historial de bajas
            $historialBaja = HistorialBaja::create([
                'id_empleado' => $id,
                'fecha_baja' => $request->fecha_baja,
                'tipo_baja' => $tipoBajaInt,
                'motivo' => $request->motivo,
                'documento_respaldo' => $documento->id ?? null,
                'estado' => PlanillaConstants::ESTADO_ACTIVO
            ]);

            // Cerrar contrato actual
            $contratoActual = HistorialContrato::where('id_empleado', $id)
                ->whereNull('fecha_fin')
                ->first();

            if ($contratoActual) {
                $contratoActual->fecha_fin = $request->fecha_baja; // La fecha fin del contrato debe ser la fecha efectiva
                $contratoActual->save();
            }

            DB::commit();

            // Mensaje personalizado según si se inactivó inmediatamente o se programó
            if ($fechaBaja->lte($fechaActual)) {
                return response()->json(['message' => 'Empleado dado de baja exitosamente']);
            } else {
                return response()->json([
                    'message' => 'Empleado programado para baja el ' . $fechaBaja->format('d/m/Y') .
                        '. Permanecerá activo hasta esa fecha.'
                ]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
            return response()->json(['error' => 'Error al dar de baja el empleado'], 500);
        }
    }

    public function darAlta(Request $request, $id)
    {
        $request->validate([
            'fecha_alta' => 'required|date',
            'documento_respaldo' => 'nullable|file|mimes:pdf,doc,docx|max:2048'
        ]);

        try {
            DB::beginTransaction();

            $empleado = Empleado::findOrFail($id);

            // Verificar si existe una baja
            $ultimaBaja = HistorialBaja::where('id_empleado', $id)
                ->orderBy('fecha_baja', 'desc')
                ->first();

            // Si hay una baja, validar la fecha
            if ($ultimaBaja) {
                if (Carbon::parse($request->fecha_alta)->lte(Carbon::parse($ultimaBaja->fecha_baja))) {
                    Log::info('La fecha de alta no puede ser anterior o igual a la fecha de baja');
                    return response()->json(['error' => 'La fecha de alta no puede ser anterior o igual a la fecha de baja'], 422);
                }
            }
            // Si no hay baja pero el empleado está inactivo, verificar la fecha de creación
            elseif ($empleado->created_at && Carbon::parse($request->fecha_alta)->lte($empleado->created_at)) {
                Log::info('La fecha de alta no puede ser anterior o igual a la fecha de creación del empleado');
                return response()->json(['error' => 'La fecha de alta no puede ser anterior o igual a la fecha de creación del empleado'], 422);
            }

            // Procesar documento si existe
            $documento = null;
            if ($request->hasFile('documento_respaldo')) {
                $resultado = DocumentHelper::saveEmployeeDocument(
                    $request->file('documento_respaldo'),
                    auth()->user()->id_empresa,
                    $id
                );

                if (!$resultado['success']) {
                    throw new \Exception('Error al guardar el documento: ' . $resultado['error']);
                    return response()->json(['error' => 'Error al guardar el documento'], 500);
                }

                $documento = DocumentoEmpleado::create([
                    'id_empleado' => $id,
                    'tipo_documento' => PlanillaConstants::TIPO_DOCUMENTO_ALTA,
                    'nombre_archivo' => $resultado['nombre'],
                    'ruta_archivo' => $resultado['ruta'],
                    'fecha_documento' => $request->fecha_alta,
                    'estado' => 1
                ]);
            }

            // Actualizar empleado
            $empleado->estado = PlanillaConstants::ESTADO_EMPLEADO_ACTIVO;
            $empleado->fecha_fin = null;
            $empleado->save();

            // Crear nuevo registro en historial de contratos
            HistorialContrato::create([
                'id_empleado' => $id,
                'fecha_inicio' => $request->fecha_alta,
                'tipo_contrato' => $empleado->tipo_contrato,
                'tipo_jornada' => $empleado->tipo_jornada,
                'salario' => $empleado->salario_base,
                'id_cargo' => $empleado->id_cargo,
                'motivo_cambio' => $ultimaBaja
                    ? PlanillaConstants::MOTIVO_CAMBIO_CONTRATO_REINGRESO
                    : PlanillaConstants::MOTIVO_CAMBIO_CONTRATO_INICIAL,
                'documento_respaldo' => $documento ? $documento->id : null,
                'estado' => PlanillaConstants::ESTADO_ACTIVO
            ]);

            DB::commit();
            return response()->json(['message' => 'Empleado dado de alta exitosamente']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
            return response()->json(['error' => 'Error al dar de alta el empleado'], 500);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->estado = $request->estado;
        $empleado->save();

        return response()->json(['message' => 'Estado actualizado exitosamente']);
    }

    public function list()
    {
        return Empleado::where('id_empresa', auth()->user()->id_empresa)
            ->where('estado', 'Activo')
            ->select('id', DB::raw("CONCAT(nombres, ' ', apellidos) as nombre"))
            ->orderBy('nombres')
            ->get();
    }

    public function descargarDocumento($id)
    {
        try {
            Log::info('Intentando descargar documento: ' . $id);

            $documento = DocumentoEmpleado::findOrFail($id);

            // Cambiamos la ruta para que apunte a public
            $rutaCompleta = storage_path('app/documents/' . $documento->ruta_archivo);

            Log::info('Ruta del documento: ' . $rutaCompleta);

            if (!file_exists($rutaCompleta)) {
                Log::error('Archivo no encontrado: ' . $rutaCompleta);
                return response()->json([
                    'error' => 'Archivo no encontrado',
                    'ruta' => $rutaCompleta,
                    'ruta_archivo' => $documento->ruta_archivo
                ], 404);
            }

            return response()->download($rutaCompleta, $documento->nombre_archivo);
        } catch (\Exception $e) {
            Log::error('Error al descargar documento: ' . $e->getMessage());
            return response()->json(['error' => 'Error al descargar el documento: ' . $e->getMessage()], 500);
        }
    }

    public function descargarContrato($id)
    {
        try {
            Log::info('Intentando descargar contrato: ' . $id);

            $documento = DocumentoEmpleado::findOrFail($id);

            // Cambiamos la ruta para que apunte a public
            $rutaCompleta = public_path('documents/' . $documento->ruta_archivo);

            Log::info('Ruta del documento: ' . $rutaCompleta);

            if (!file_exists($rutaCompleta)) {
                Log::error('Archivo no encontrado: ' . $rutaCompleta);
                return response()->json([
                    'error' => 'Archivo no encontrado',
                    'ruta' => $rutaCompleta,
                    'ruta_archivo' => $documento->ruta_archivo
                ], 404);
            }

            return response()->download($rutaCompleta, $documento->nombre_archivo);
        } catch (\Exception $e) {
            Log::error('Error al descargar documento: ' . $e->getMessage());
            return response()->json(['error' => 'Error al descargar el documento: ' . $e->getMessage()], 500);
        }
    }

    public function getHistorialesContratos($id)
    {
        $contratos = HistorialContrato::with('cargo', 'documento_empleado')->where('id_empleado', $id)->get();
        return $contratos;
    }

    public function getHistorialesBajas($id)
    {
        $bajas = HistorialBaja::with('documento_empleado')->where('id_empleado', $id)->get();
        return $bajas;
    }

    public function subirDocumentos(Request $request, $id)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx|max:2048',
            'tipo_documento' => 'required',
            'fecha_documento' => 'required|date',
            'fecha_vencimiento' => 'nullable|date'
        ]);

        try {
            DB::beginTransaction();

            $empleado = Empleado::findOrFail($id);

            if (!$empleado) {
                Log::error('Empleado no encontrado: ' . $id);
                return response()->json(['error' => 'Empleado no encontrado'], 404);
            }

            // Guardar el archivo usando el helper
            $resultado = DocumentHelper::saveEmployeeDocument(
                $request->file('archivo'),
                auth()->user()->id_empresa,
                $id
            );

            if (!$resultado['success']) {
                throw new \Exception('Error al guardar el documento: ' . $resultado['error']);
            }

            // Crear registro del documento
            $documento = DocumentoEmpleado::create([
                'id_empleado' => $id,
                'tipo_documento' => $request->tipo_documento,
                'nombre_archivo' => $resultado['nombre'],
                'ruta_archivo' => $resultado['ruta'],
                'fecha_documento' => $request->fecha_documento,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'estado' => 1
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Documento subido exitosamente',
                'documento' => $documento
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error al subir documento: ' . $e->getMessage());
            return response()->json(['error' => 'Error al subir el documento: ' . $e->getMessage()], 500);
        }
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $importData = [
                'empresa_id' => auth()->user()->id_empresa,
                'sucursal_id' => auth()->user()->id_sucursal,
            ];

            $import = new EmpleadosImport($importData);
            Excel::import($import, $request->file('archivo'));

            return response()->json([
                'message' => 'Empleados importados exitosamente',
                'type' => 'success',
                'data' => [
                    'creados' => $import->getEmpleadosCreados(),
                    'actualizados' => $import->getEmpleadosActualizados(),
                    'errores' => $import->getErrores(),
                    'total_errores' => count($import->getErrores())
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error importando empleados: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al importar los empleados: ' . $e->getMessage()
            ], 500);
        }
    }
}