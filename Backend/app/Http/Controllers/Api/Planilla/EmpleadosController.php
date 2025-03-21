<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Constants\PlanillaConstants;
use App\Helpers\DocumentHelper;
use App\Http\Controllers\Controller;
use App\Models\Planilla\ContactoEmergencia;
use App\Models\Planilla\DocumentoEmpleado;
use App\Models\Planilla\Empleado;
use App\Models\Planilla\HistorialContrato;
use App\Models\Planilla\HistorialBaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmpleadosController extends Controller
{
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
            'nit' => 'required|string',
            'isss' => 'string',
            'afp' => 'string',
            'fecha_nacimiento' => 'required|date',
            'direccion' => 'required|string',
            'telefono' => 'required|string',
            'email' => 'email',
            'salario_base' => 'required|numeric|min:0',
            'tipo_contrato' => 'required',
            'tipo_jornada' => 'required',
            'fecha_ingreso' => 'required|date',
            'id_departamento' => 'required|exists:departamentos_empresa,id',
            'id_cargo' => 'required|exists:cargos_de_empresa,id',
            'contacto_emergencia' => 'nullable|array',
            'contacto_emergencia.nombre' => 'required_with:contacto_emergencia|string',
            'contacto_emergencia.relacion' => 'nullable|string',
            'contacto_emergencia.telefono' => 'required_with:contacto_emergencia|string',
            'contacto_emergencia.direccion' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

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

            DB::commit();
            return $empleado;
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
            'fecha_baja' => 'required|date',
            'tipo_baja' => 'required|in:Renuncia,Despido,Terminación de contrato',
            'motivo' => 'required|string',
            'documento_respaldo' => 'nullable|file|mimes:pdf,doc,docx|max:2048' // 2MB max
        ]);

        try {
            DB::beginTransaction();

            $empleado = Empleado::findOrFail($id);

            // Validar que la fecha de baja no sea anterior a la fecha de ingreso
            if (Carbon::parse($request->fecha_baja)->lt(Carbon::parse($empleado->fecha_ingreso))) {
                Log::info('La fecha de baja no puede ser anterior a la fecha de ingreso');
                return response()->json(['error' => 'La fecha de baja no puede ser anterior a la fecha de ingreso'], 422);
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
                    'fecha_documento' => $request->fecha_baja,
                    'estado' => PlanillaConstants::ESTADO_ACTIVO
                ]);
            }

            // Actualizar empleado
            $empleado->fecha_baja = $request->fecha_baja; // Guardar fecha de baja

            // Decidir si cambiar el estado ahora o dejarlo para la fecha de baja
            $fechaActual = Carbon::now()->startOfDay();
            $fechaBaja = Carbon::parse($request->fecha_baja)->startOfDay();

            if ($fechaBaja->lte($fechaActual)) {
                // Si la fecha de baja es hoy o en el pasado, inactivar inmediatamente
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
                $contratoActual->fecha_fin = $request->fecha_baja;
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
}