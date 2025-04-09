<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Admin\Funcionalidad;
use App\Models\Admin\EmpresaFuncionalidad;
use Illuminate\Support\Facades\Log;

class VerificarAccesoFuncionalidad
{
    public function handle(Request $request, Closure $next, $slugFuncionalidad)
    {
        // Obtener ID de empresa del usuario autenticado
        $idEmpresa = auth()->user()->id_empresa ?? null;

        // Si no hay empresa asociada al usuario, denegar acceso
        if (!$idEmpresa) {
            return response()->json([
                'error' => 'Acceso no autorizado: Usuario sin empresa asignada'
            ], 403);
        }

        try {
            // Buscar la funcionalidad por su slug
            $funcionalidad = Funcionalidad::where('slug', $slugFuncionalidad)->first();

            // Si la funcionalidad no existe, denegar acceso
            if (!$funcionalidad) {
                return response()->json([
                    'error' => 'Funcionalidad no encontrada'
                ], 404);
            }

            // Verificar si la empresa tiene acceso a la funcionalidad
            $tieneAcceso = EmpresaFuncionalidad::where('id_empresa', $idEmpresa)
                ->where('id_funcionalidad', $funcionalidad->id)
                ->where('activo', 1)
                ->exists();

            // Si no tiene acceso, denegar
            if (!$tieneAcceso) {
                return response()->json([
                    'error' => 'Su empresa no tiene acceso a esta funcionalidad'
                ], 403);
            }

            // Si llegamos aquí, tiene acceso - continuar con la solicitud
            return $next($request);
        } catch (\Exception $e) {
            Log::error("Error al verificar acceso a funcionalidad: " . $e->getMessage());

            return response()->json([
                'error' => 'Error al verificar permisos de acceso'
            ], 500);
        }
    }
}
