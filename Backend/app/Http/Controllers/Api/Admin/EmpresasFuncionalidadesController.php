<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use App\Models\Admin\Funcionalidad;
use App\Models\Admin\EmpresaFuncionalidad;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Admin\EmpresasFuncionalidades\ActualizarFuncionalidadRequest;
use App\Http\Requests\Admin\EmpresasFuncionalidades\ActualizarMultipleFuncionalidadesRequest;

class EmpresasFuncionalidadesController extends Controller
{

    public function index()
    {
        $empresas = Empresa::where('activo', 1)->orderBy('nombre')->get();
        $funcionalidades = Funcionalidad::orderBy('orden')->get();

        return view('admin.empresas-funcionalidades.index', compact('empresas', 'funcionalidades'));
    }

    public function getEmpresaFuncionalidades($id)
    {
        try {
            $empresa = Empresa::findOrFail($id);

            // Obtener todas las funcionalidades
            $funcionalidades = Funcionalidad::orderBy('orden')->get();

            // Obtener las funcionalidades asignadas a esta empresa
            $asignadas = EmpresaFuncionalidad::where('id_empresa', $id)
                ->pluck('activo', 'id_funcionalidad')
                ->toArray();

            // Preparar respuesta con estado de activación
            $resultado = $funcionalidades->map(function ($funcionalidad) use ($asignadas, $id) {
                $funcionalidad->asignada = isset($asignadas[$funcionalidad->id]) ? $asignadas[$funcionalidad->id] : false;
                $empresaFunc = EmpresaFuncionalidad::where('id_empresa', $id)
                    ->where('id_funcionalidad', $funcionalidad->id)
                    ->first();

                $funcionalidad->configuracion = $empresaFunc ? $empresaFunc->configuracion : null;

                return $funcionalidad;
            });

            return response()->json([
                'empresa' => $empresa,
                'funcionalidades' => $resultado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener funcionalidades: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarFuncionalidad(ActualizarFuncionalidadRequest $request)
    {
        try {

            $empresaFunc = EmpresaFuncionalidad::updateOrCreate(
                [
                    'id_empresa' => $request->id_empresa,
                    'id_funcionalidad' => $request->id_funcionalidad
                ],
                [
                    'activo' => $request->activo,
                    'configuracion' => $request->configuracion ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Funcionalidad actualizada correctamente',
                'data' => $empresaFunc
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar funcionalidad: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarMultiple(ActualizarMultipleFuncionalidadesRequest $request)
    {
        try {

            DB::beginTransaction();

            foreach ($request->funcionalidades as $func) {
                EmpresaFuncionalidad::updateOrCreate(
                    [
                        'id_empresa' => $request->id_empresa,
                        'id_funcionalidad' => $func['id']
                    ],
                    [
                        'activo' => $func['activo'],
                        'configuracion' => $func['configuracion'] ?? null
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Funcionalidades actualizadas correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error al actualizar funcionalidades: ' . $e->getMessage()
            ], 500);
        }
    }


    public function verificarAcceso(Request $request, $slug)
    {
        try {
            // Intentar obtener el usuario y empresa desde el token de autenticación
            $user = null;

            // Verificar si hay un token válido
            if ($request->bearerToken()) {
                try {
                    $user = Auth::guard('api')->user();
                } catch (\Exception $e) {
                    Log::warning("Token inválido al verificar acceso: " . $e->getMessage());
                }
            }

            // Si no hay usuario o empresa, devolver falso
            if (!$user || !$user->id_empresa) {
                return response()->json(['acceso' => false]);
            }

            $idEmpresa = $user->id_empresa;

            // Buscar la funcionalidad por su slug
            $funcionalidad = Funcionalidad::where('slug', $slug)->first();

            if (!$funcionalidad) {
                return response()->json(['acceso' => false]);
            }

            // Verificar si la empresa tiene acceso a la funcionalidad
            $tieneAcceso = EmpresaFuncionalidad::where('id_empresa', $idEmpresa)
                ->where('id_funcionalidad', $funcionalidad->id)
                ->where('activo', 1)
                ->exists();

            return response()->json(['acceso' => $tieneAcceso]);
        } catch (\Exception $e) {
            Log::error("Error al verificar acceso a funcionalidad: " . $e->getMessage());
            return response()->json(['acceso' => false]);
        }
    }

    /**
     * Obtiene la configuración de una funcionalidad para la empresa del usuario
     *
     * @param Request $request
     * @param string $slug Identificador único de la funcionalidad
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerConfiguracion(Request $request, $slug)
    {
        try {
            $user = Auth::guard('api')->user();
            $idEmpresa = $user->id_empresa ?? null;

            if (!$idEmpresa) {
                return response()->json(['configuracion' => null]);
            }

            $funcionalidad = Funcionalidad::where('slug', $slug)->first();

            if (!$funcionalidad) {
                return response()->json(['configuracion' => null]);
            }

            $empresaFuncionalidad = EmpresaFuncionalidad::where('id_empresa', $idEmpresa)
                ->where('id_funcionalidad', $funcionalidad->id)
                ->where('activo', 1)
                ->first();

            if (!$empresaFuncionalidad) {
                return response()->json(['configuracion' => null]);
            }

            return response()->json([
                'configuracion' => $empresaFuncionalidad->configuracion
            ]);
        } catch (\Exception $e) {
            Log::error("Error al obtener configuración de funcionalidad: " . $e->getMessage());
            return response()->json(['configuracion' => null]);
        }
    }
}
