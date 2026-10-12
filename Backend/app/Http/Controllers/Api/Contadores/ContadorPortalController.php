<?php

namespace App\Http\Controllers\Api\Contadores;

use App\Http\Controllers\Controller;
use App\Models\Admin\Sucursal;
use App\Models\Contadores\ContadorEmpresaAcceso;
use App\Models\Inventario\Bodega;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Contadores\ContadorCarteraMetricasService;
use App\Services\Contadores\ContadorCumplimientoService;
use App\Services\Contadores\ContadorEmpresaAccesoPermisos;
use App\Services\Contadores\ContadorLibrosIvaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ContadorPortalController extends Controller
{
    private const EMPRESA_DESPACHO_HOME = 2;

    public function __construct(private AuthService $authService)
    {
    }

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

        [$mes, $anio] = $this->periodoContadorDesdeRequest($request);

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

    public function cumplimiento(Request $request, ContadorCumplimientoService $cumplimiento): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'id_empresa' => 'required|integer|min:1',
        ]);

        $idEmpresa = (int) $request->input('id_empresa');
        [$mes, $anio] = $this->periodoContadorDesdeRequest($request);

        $acceso = ContadorEmpresaAcceso::query()
            ->where('id_usuario_contador', $user->id)
            ->where('id_empresa', $idEmpresa)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->first();

        if (!$acceso) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        $permisos = $acceso->permisos ?? ContadorEmpresaAcceso::permisosPorDefecto();

        return response()->json($cumplimiento->vista($idEmpresa, $anio, $mes, $permisos), 200);
    }

    public function cumplimientoDocumento(Request $request, ContadorCumplimientoService $cumplimiento): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'id_empresa' => 'required|integer|min:1',
            'id_documento' => 'nullable|integer|min:1',
            'slug' => 'nullable|string|max:64',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'vence_en' => 'nullable|date',
            'titulo' => 'nullable|string|max:255',
        ]);

        $acceso = $this->accesoContadorEmpresa($user->id, (int) $request->input('id_empresa'));
        if (!$acceso) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        $permisos = $acceso->permisos ?? ContadorEmpresaAcceso::permisosPorDefecto();
        if (!ContadorEmpresaAccesoPermisos::tiene($permisos, 'registrar')) {
            return response()->json(['error' => 'Tu permiso es solo de lectura.', 'code' => 403], 403);
        }

        try {
            $id = $cumplimiento->guardarDocumento(
                (int) $request->input('id_empresa'),
                $request->file('archivo'),
                $user,
                $request->filled('id_documento') ? (int) $request->input('id_documento') : null,
                $request->input('slug'),
                $request->filled('vence_en') ? (string) $request->input('vence_en') : null,
                $request->has('titulo') ? trim((string) $request->input('titulo', '')) : null,
            );
        } catch (\InvalidArgumentException $e) {
            $code = $e->getCode() === 404 ? 404 : 422;

            return response()->json(['error' => [$e->getMessage()], 'code' => $code], $code);
        }

        return response()->json(['ok' => true, 'id' => $id], 200);
    }

    public function cumplimientoPresentado(Request $request, ContadorCumplimientoService $cumplimiento): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'id_empresa' => 'required|integer|min:1',
            'codigo' => 'required|string|max:16',
            'mes' => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000|max:2100',
        ]);

        $acceso = $this->accesoContadorEmpresa($user->id, (int) $request->input('id_empresa'));
        if (!$acceso) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        $permisos = $acceso->permisos ?? ContadorEmpresaAcceso::permisosPorDefecto();
        if (!ContadorEmpresaAccesoPermisos::tiene($permisos, 'aprobar')) {
            return response()->json(['error' => 'Se requiere permiso de aprobar partidas.', 'code' => 403], 403);
        }

        try {
            $cumplimiento->marcarPresentado(
                (int) $request->input('id_empresa'),
                (string) $request->input('codigo'),
                (int) $request->input('mes'),
                (int) $request->input('anio'),
                $user
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => [$e->getMessage()], 'code' => 422], 422);
        }

        return response()->json(['ok' => true], 200);
    }

    private function accesoContadorEmpresa(int $idUsuarioContador, int $idEmpresa): ?ContadorEmpresaAcceso
    {
        return ContadorEmpresaAcceso::query()
            ->where('id_usuario_contador', $idUsuarioContador)
            ->where('id_empresa', $idEmpresa)
            ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO)
            ->whereHas('empresa', fn ($q) => $q->where('activo', true))
            ->first();
    }

    /** @return array{0: int, 1: int} Mes/año del periodo; ignora 0 o query vacía (evita rechazo de validación). */
    private function periodoContadorDesdeRequest(Request $request): array
    {
        $ref = Carbon::now('America/El_Salvador')->subMonth();
        $mes = filter_var($request->input('mes'), FILTER_VALIDATE_INT);
        $anio = filter_var($request->input('anio'), FILTER_VALIDATE_INT);
        if ($mes === false || $mes < 1 || $mes > 12) {
            $mes = (int) $ref->month;
        }
        if ($anio === false || $anio < 2000 || $anio > 2100) {
            $anio = (int) $ref->year;
        }

        return [$mes, $anio];
    }

    public function carteraEmpresa(int $idEmpresa, Request $request, ContadorCarteraMetricasService $metricas): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        [$mes, $anio] = $this->periodoContadorDesdeRequest($request);

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

    public function librosIva(Request $request, ContadorLibrosIvaService $libros): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'id_empresa' => 'required|integer|min:1',
        ]);

        $idEmpresa = (int) $request->input('id_empresa');
        if (!$this->accesoContadorEmpresa($user->id, $idEmpresa)) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        [$mes, $anio] = $this->periodoContadorDesdeRequest($request);

        try {
            return response()->json($libros->vista($idEmpresa, $anio, $mes), 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => [$e->getMessage()], 'code' => 422], 422);
        }
    }

    public function librosIvaInforme(Request $request, ContadorLibrosIvaService $libros): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'id_empresa' => 'required|integer|min:1',
            'informe' => 'required|string|max:64',
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);

        $idEmpresa = (int) $request->input('id_empresa');
        if (!$this->accesoContadorEmpresa($user->id, $idEmpresa)) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        [$mes, $anio] = $this->periodoContadorDesdeRequest($request);
        $limit = (int) $request->input('limit', 50);

        try {
            return response()->json(
                $libros->informe($idEmpresa, $anio, $mes, (string) $request->input('informe'), $limit),
                200
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => [$e->getMessage()], 'code' => 422], 422);
        }
    }

    public function librosIvaExport(Request $request, ContadorLibrosIvaService $libros)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $request->validate([
            'id_empresa' => 'required|integer|min:1',
            'informe' => 'required|string|max:64',
            'formato' => 'required|string|in:pdf,excel,csv',
        ]);

        $idEmpresa = (int) $request->input('id_empresa');
        if (!$this->accesoContadorEmpresa($user->id, $idEmpresa)) {
            return response()->json(['error' => 'No tienes acceso activo a esta empresa.', 'code' => 403], 403);
        }

        [$mes, $anio] = $this->periodoContadorDesdeRequest($request);
        $formato = (string) $request->input('formato');

        try {
            return $libros->exportar($idEmpresa, $anio, $mes, (string) $request->input('informe'), $formato);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => [$e->getMessage()], 'code' => 422], 422);
        }
    }

    public function contexto(int $idEmpresa): JsonResponse
    {
        return $this->aplicarContextoEmpresa($idEmpresa, true);
    }

    public function contextoDespacho(): JsonResponse
    {
        return $this->aplicarContextoEmpresa(self::EMPRESA_DESPACHO_HOME, false);
    }

    private function aplicarContextoEmpresa(int $idEmpresa, bool $exigirAccesoCartera): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');

        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            return response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403);
        }

        $contextoPayload = null;

        if ($exigirAccesoCartera) {
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

            $contextoPayload = [
                'acceso_id' => $acceso->id,
                'id' => $acceso->empresa->id,
                'nombre' => $acceso->empresa->nombre,
                'logo' => $acceso->empresa->logo,
                'giro' => $acceso->empresa->giro,
                'permisos' => $acceso->permisos ?? ContadorEmpresaAcceso::permisosPorDefecto(),
            ];
        } elseif ($idEmpresa !== self::EMPRESA_DESPACHO_HOME) {
            return response()->json(['error' => 'Contexto de despacho no válido.', 'code' => 422], 422);
        }

        [$idSucursal, $idBodega] = $this->defaultsEmpresaOperativa($idEmpresa);
        if (!$idSucursal || !$idBodega) {
            return response()->json([
                'error' => ['No hay sucursal/bodega base para operar en esta empresa.'],
                'code' => 422,
            ], 422);
        }

        User::withoutGlobalScopes()
            ->whereKey($user->id)
            ->update([
                'id_empresa' => $idEmpresa,
                'id_sucursal' => $idSucursal,
                'id_bodega' => $idBodega,
            ]);

        $user = User::withoutGlobalScopes()->findOrFail($user->id);
        $user = $this->authService->cargarDatosUsuario($user);
        if ($user->empresa) {
            $user->empresa->es_empresa_padre = $user->empresa->esEmpresaPadre();
            $user->empresa->es_empresa_hija = $user->empresa->esEmpresaHija();
        }

        $body = ['user' => $user];
        if ($contextoPayload !== null) {
            $body['contexto'] = $contextoPayload;
        }

        return response()->json($body, 200);
    }

    /** @return array{0: int|null, 1: int|null} */
    private function defaultsEmpresaOperativa(int $idEmpresa): array
    {
        $idSucursal = Sucursal::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->orderBy('id')
            ->value('id');

        if (!$idSucursal) {
            return [null, null];
        }

        $idBodega = Bodega::withoutGlobalScopes()
            ->where('id_sucursal', $idSucursal)
            ->orderBy('id')
            ->value('id');

        return [$idSucursal, $idBodega];
    }
}
