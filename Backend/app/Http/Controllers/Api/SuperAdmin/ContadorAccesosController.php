<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Sucursal;
use App\Models\Contadores\ContadorEmpresaAcceso;
use App\Models\Inventario\Bodega;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ContadorAccesosController extends Controller
{
    private const EMPRESA_PORTAL_HOME = 2;

    public function index(Request $request): JsonResponse
    {
        $accesos = ContadorEmpresaAcceso::query()
            ->with([
                'contador' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'email'),
                'empresa:id,nombre,logo',
            ])
            ->when($request->filled('id_usuario_contador'), fn ($q) => $q->where('id_usuario_contador', (int) $request->id_usuario_contador))
            ->when($request->filled('id_empresa'), fn ($q) => $q->where('id_empresa', (int) $request->id_empresa))
            ->orderByDesc('id')
            ->paginate((int) ($request->paginate ?? 50));

        return response()->json($accesos, 200);
    }

    public function contadoresPortal(): JsonResponse
    {
        $rol = config('constants.ROL_ADMIN_CONTADOR', 'admin_contador');

        $usuarios = User::query()
            ->withoutGlobalScopes()
            ->role($rol)
            ->withCount([
                'contadorEmpresaAccesos as empresas_activas_count' => fn ($q) => $q
                    ->where('estado', ContadorEmpresaAcceso::ESTADO_ACTIVO),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'enable']);

        return response()->json($usuarios, 200);
    }

    public function storeContador(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all(), 'code' => 422], 422);
        }

        [$idSucursal, $idBodega] = $this->defaultsEmpresaPortal(self::EMPRESA_PORTAL_HOME);
        if (!$idSucursal || !$idBodega) {
            return response()->json([
                'error' => ['No hay sucursal/bodega base para crear contadores (empresa SmartPyme).'],
                'code' => 422,
            ], 422);
        }

        $rol = Role::findByName(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'), 'web');

        $usuario = new User();
        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->password = Hash::make($request->password);
        $usuario->id_empresa = self::EMPRESA_PORTAL_HOME;
        $usuario->id_sucursal = $idSucursal;
        $usuario->id_bodega = $idBodega;
        $usuario->enable = true;
        $usuario->save();

        $usuario->roles()->sync([$rol->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $usuario->syncTipoFromRole($rol->id);

        return response()->json($usuario->only(['id', 'name', 'email', 'enable']), 201);
    }

    public function incluirContadorExistente(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all(), 'code' => 422], 422);
        }

        $rol = Role::findByName(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'), 'web');
        $usuario = User::withoutGlobalScopes()->findOrFail((int) $request->id_usuario);

        if (!$usuario->hasRole($rol->name)) {
            $usuario->assignRole($rol);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        // ponytail: no syncTipoFromRole — conserva tipo para entrar a la app PYME; el portal valida por rol.

        return response()->json($usuario->only(['id', 'name', 'email', 'enable']), 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_usuario_contador' => 'required|integer|exists:users,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all(), 'code' => 422], 422);
        }

        $contador = User::withoutGlobalScopes()->findOrFail((int) $request->id_usuario_contador);
        $rol = config('constants.ROL_ADMIN_CONTADOR', 'admin_contador');

        if (!$contador->hasRole($rol)) {
            return response()->json(['error' => ['El usuario no tiene rol de portal contador.'], 'code' => 422], 422);
        }

        $actor = JWTAuth::parseToken()->authenticate();

        $acceso = ContadorEmpresaAcceso::query()->updateOrCreate(
            [
                'id_usuario_contador' => $contador->id,
                'id_empresa' => (int) $request->id_empresa,
            ],
            [
                'estado' => ContadorEmpresaAcceso::ESTADO_ACTIVO,
                'permisos' => ContadorEmpresaAcceso::permisosPorDefecto(),
                'invitado_por_user_id' => $actor->id,
                'revoked_at' => null,
            ]
        );

        $acceso->load([
            'contador' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name', 'email'),
            'empresa:id,nombre,logo',
        ]);

        return response()->json($acceso, 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $acceso = ContadorEmpresaAcceso::findOrFail($id);
        $acceso->estado = ContadorEmpresaAcceso::ESTADO_REVOCADO;
        $acceso->revoked_at = now();
        $acceso->save();

        return response()->json($acceso, 200);
    }

    /** @return array{0: int|null, 1: int|null} */
    private function defaultsEmpresaPortal(int $idEmpresa): array
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
