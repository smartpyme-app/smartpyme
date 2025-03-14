<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Ventas\VentasController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reportes\VentasPorVendedorController;
use App\Models\Admin\ReporteConfiguracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;


class ReporteConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        $id_empresa = Auth::user()->id_empresa;
        $query = ReporteConfiguracion::where('id_empresa', $id_empresa);

        // Aplicar filtros de búsqueda
        if ($request->has('buscador') && $request->buscador) {
            $query->where(function ($q) use ($request) {
                $q->where('tipo_reporte', 'like', '%' . $request->buscador . '%')
                    ->orWhere('frecuencia', 'like', '%' . $request->buscador . '%')
                    ->orWhere('asunto_correo', 'like', '%' . $request->buscador . '%');
            });
        }

        // Ordenamiento
        $orden = $request->has('orden') ? $request->orden : 'created_at';
        $direccion = $request->has('direccion') ? $request->direccion : 'desc';
        $query->orderBy($orden, $direccion);

        // Paginación
        $paginate = $request->has('paginate') ? $request->paginate : 10;

        return $query->paginate($paginate);
    }


    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'tipo_reporte' => 'required|string',
    //         'frecuencia' => 'required|in:diario,semanal,mensual',
    //         'destinatarios' => 'required|array|min:1',
    //         'destinatarios.*' => 'email',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }


    //     if ($request->frecuencia === 'semanal' && empty($request->dias_semana)) {
    //         return response()->json(['error' => 'Debe seleccionar al menos un día de la semana'], 422);
    //     }

    //     if ($request->frecuencia === 'mensual' && !$request->dia_mes) {
    //         return response()->json(['error' => 'Debe seleccionar un día del mes'], 422);
    //     }


    //     if (!$request->envio_matutino && !$request->envio_mediodia && !$request->envio_nocturno) {
    //         return response()->json(['error' => 'Debe seleccionar al menos un horario de envío'], 422);
    //     }


    //     $datos = $request->all();
    //     $datos['id_empresa'] = Auth::user()->id_empresa;


    //     if (isset($datos['id']) && $datos['id']) {
    //         $configuracion = ReporteConfiguracion::findOrFail($datos['id']);
    //         $configuracion->update($datos);
    //     } else {
    //         $configuracion = ReporteConfiguracion::create($datos);
    //     }

    //     return response()->json($configuracion, 200);
    // }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_reporte' => 'required|string',
            'frecuencia' => 'required|in:diario,semanal,mensual',
            'destinatarios' => 'required|array|min:1',
            'destinatarios.*' => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        if ($request->frecuencia === 'semanal' && empty($request->dias_semana)) {
            return response()->json(['error' => 'Debe seleccionar al menos un día de la semana'], 422);
        }

        if ($request->frecuencia === 'mensual' && !$request->dia_mes) {
            return response()->json(['error' => 'Debe seleccionar un día del mes'], 422);
        }


        if (!$request->envio_matutino && !$request->envio_mediodia && !$request->envio_nocturno) {
            return response()->json(['error' => 'Debe seleccionar al menos un horario de envío'], 422);
        }

        $datos = $request->all();
        $datos['id_empresa'] = Auth::user()->id_empresa;


        if (isset($datos['activo']) && $datos['activo']) {

            $existeConfiguracionActiva = ReporteConfiguracion::where('id_empresa', Auth::user()->id_empresa)
                ->where('tipo_reporte', $datos['tipo_reporte'])
                ->where('activo', true);


            if (isset($datos['id']) && $datos['id']) {
                $existeConfiguracionActiva->where('id', '!=', $datos['id']);
            }

            $configuracionExistente = $existeConfiguracionActiva->first();

            if ($configuracionExistente) {

                $configuracionExistente->activo = false;
                $configuracionExistente->save();
            }
        }

        if (isset($datos['id']) && $datos['id']) {
            $configuracion = ReporteConfiguracion::findOrFail($datos['id']);
            $configuracion->update($datos);
        } else {
            $configuracion = ReporteConfiguracion::create($datos);
        }

        return response()->json($configuracion, 200);
    }
    public function show($id)
    {
        $configuracion = ReporteConfiguracion::findOrFail($id);


        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para ver esta configuración'], 403);
        }

        return response()->json($configuracion, 200);
    }

    // public function updateEstado(Request $request, $id)
    // {
    //     $configuracion = ReporteConfiguracion::findOrFail($id);
    //     if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
    //         return response()->json(['error' => 'No tiene permiso para modificar esta configuración'], 403);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'activo' => 'required|boolean',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()], 422);
    //     }

    //     $configuracion->activo = $request->activo;
    //     $configuracion->save();

    //     return response()->json($configuracion, 200);
    // }


    public function updateEstado(Request $request, $id)
    {
        $configuracion = ReporteConfiguracion::findOrFail($id);
        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para modificar esta configuración'], 403);
        }

        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }


        if ($request->activo) {

            $existeConfiguracionActiva = ReporteConfiguracion::where('id_empresa', Auth::user()->id_empresa)
                ->where('tipo_reporte', $configuracion->tipo_reporte)
                ->where('activo', true)
                ->where('id', '!=', $id)
                ->first();

            if ($existeConfiguracionActiva) {

                $existeConfiguracionActiva->activo = false;
                $existeConfiguracionActiva->save();
            }
        }

        $configuracion->activo = $request->activo;
        $configuracion->save();

        return response()->json($configuracion, 200);
    }


    public function destroy($id)
    {
        $configuracion = ReporteConfiguracion::findOrFail($id);


        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para eliminar esta configuración'], 403);
        }

        $configuracion->delete();

        return response()->json(['message' => 'Configuración eliminada correctamente'], 200);
    }


    public function enviarPrueba(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_configuracion' => 'required|exists:reporte_configuraciones,id',
            'email_prueba' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $configuracion = ReporteConfiguracion::findOrFail($request->id_configuracion);

        if ($configuracion->id_empresa !== Auth::user()->id_empresa) {
            return response()->json(['error' => 'No tiene permiso para usar esta configuración'], 403);
        }

        try {
            switch ($configuracion->tipo_reporte) {
                case 'ventas-por-vendedor':
                    $controller = new VentasController();


                    $destinatarios = $request->email_prueba
                        ? [$request->email_prueba]
                        : $configuracion->destinatarios;


                    $resultado = $controller->enviarReporteProgramadoTest($configuracion, $destinatarios);

                    return response()->json(['message' => 'Reporte enviado correctamente'], 200);



                default:
                    return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al enviar el reporte: ' . $e->getMessage()], 500);
        }
    }
}
