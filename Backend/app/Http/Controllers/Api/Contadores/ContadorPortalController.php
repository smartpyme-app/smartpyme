<?php

namespace App\Http\Controllers\Api\Contadores;

use App\Http\Controllers\Controller;
use App\Models\Contadores\ContadorEmpresaAcceso;
use App\Services\Contadores\ContadorCarteraMetricasService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function cartera(Request $request, ContadorCarteraMetricasService $metricas): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'mes' => 'sometimes|integer|min:1|max:12',
            'anio' => 'sometimes|integer|min:2000|max:2100',
        ]);

        $ref = Carbon::now('America/El_Salvador')->subMonth();
        $mes = (int) $request->input('mes', $ref->month);
        $anio = (int) $request->input('anio', $ref->year);

        $idsEmpresa = ContadorEmpresaAcceso::query()
            ->where('id_usuario_contador', $user->id)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->pluck('id_empresa')
            ->all();

        $porEmpresa = $metricas->metricasPorEmpresas($idsEmpresa, $anio, $mes);

        Carbon::setLocale('es');
        $periodoLabel = Carbon::create($anio, $mes, 1)->translatedFormat('F Y');

        $vencimiento = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/El_Salvador')
            ->addMonth()
            ->day(14)
            ->startOfDay();
        $hoy = Carbon::now('America/El_Salvador')->startOfDay();
        $diasRestantes = (int) $hoy->diffInDays($vencimiento, false);

        $hayPendientes = collect($porEmpresa)->contains(
            fn (array $m) => ($m['por_contabilizar'] ?? 0) > 0
        );
        $ref = Carbon::now('America/El_Salvador')->subMonth();
        $periodoEnCierre = ($mes === (int) $ref->month && $anio === (int) $ref->year && $diasRestantes >= 0)
            || $hayPendientes;

        return response()->json([
            'periodo' => [
                'mes' => $mes,
                'anio' => $anio,
                'label' => ucfirst($periodoLabel),
                'en_cierre' => $periodoEnCierre,
                'vencimiento_iva' => [
                    'fecha' => $vencimiento->toDateString(),
                    'dias_restantes' => $diasRestantes,
                ],
            ],
            'metricas' => $porEmpresa,
        ], 200);
    }

    public function carteraEmpresa(int $idEmpresa, Request $request, ContadorCarteraMetricasService $metricas): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'mes' => 'sometimes|integer|min:1|max:12',
            'anio' => 'sometimes|integer|min:2000|max:2100',
        ]);

        $ref = Carbon::now('America/El_Salvador')->subMonth();
        $mes = (int) $request->input('mes', $ref->month);
        $anio = (int) $request->input('anio', $ref->year);

        $tieneAcceso = ContadorEmpresaAcceso::query()
            ->where('id_usuario_contador', $user->id)
            ->where('id_empresa', $idEmpresa)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->exists();

        if (!$tieneAcceso) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        return response()->json([
            'id_empresa' => $idEmpresa,
            'periodo' => ['mes' => $mes, 'anio' => $anio],
            'detalle' => $metricas->detalleEmpresa($idEmpresa, $anio, $mes),
        ], 200);
    }

    public function contexto(int $idEmpresa): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $acceso = ContadorEmpresaAcceso::query()
            ->with(['empresa:id,nombre,logo,giro,activo'])
            ->where('id_usuario_contador', $user->id)
            ->where('id_empresa', $idEmpresa)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->first();

        if (!$acceso || !$acceso->empresa) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        return response()->json([
            'contexto' => [
                'acceso_id' => $acceso->id,
                'id' => $acceso->empresa->id,
                'nombre' => $acceso->empresa->nombre,
                'logo' => $acceso->empresa->logo,
                'giro' => $acceso->empresa->giro,
                'permisos' => $acceso->permisos ?? ContadorEmpresaAcceso::permisosPorDefecto(),
            ],
        ], 200);
    }
}
