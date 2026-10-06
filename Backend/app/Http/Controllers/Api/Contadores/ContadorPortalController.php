<?php

namespace App\Http\Controllers\Api\Contadores;

use App\Http\Controllers\Controller;
use App\Models\Contadores\ContadorEmpresaAcceso;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ContadorPortalController extends Controller
{
    public function empresas(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $accesos = ContadorEmpresaAcceso::query()
            ->with(['empresa:id,nombre,logo,activo,giro'])
            ->where('id_usuario_contador', $user->id)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->orderBy('id')
            ->get();

        $empresas = $accesos->map(fn (ContadorEmpresaAcceso $acceso) => [
            'acceso_id' => $acceso->id,
            'id' => $acceso->empresa->id,
            'nombre' => $acceso->empresa->nombre,
            'logo' => $acceso->empresa->logo,
            'giro' => $acceso->empresa->giro,
            'permisos' => $acceso->permisos ?? ContadorEmpresaAcceso::permisosPorDefecto(),
        ])->values();

        return response()->json(['empresas' => $empresas], 200);
    }
}
