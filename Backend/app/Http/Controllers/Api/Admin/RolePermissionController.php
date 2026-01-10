<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\ModulePermission;
use App\Http\Requests\Admin\Roles\AssignRoleToUserRequest;
use App\Http\Requests\Admin\Roles\RemoveRoleFromUserRequest;
use App\Http\Requests\Admin\Roles\AssignPermissionToRoleRequest;
use App\Http\Requests\Admin\Roles\RemovePermissionFromRoleRequest;
use App\Http\Requests\Admin\Roles\AssignPermissionToUserRequest;
use App\Http\Requests\Admin\Roles\RemovePermissionFromUserRequest;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRolePermissionsRequest;
use App\Http\Requests\Admin\Roles\SaveUserPermissionsRequest;
use App\Http\Requests\Admin\Roles\StoreModuleRequest;
use App\Http\Requests\Admin\Roles\UpdateModuleRequest;
use App\Services\Admin\RolePermissionService;
use Illuminate\Support\Facades\Auth;

class RolePermissionController extends Controller
{
    protected $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }

    public function index(Request $request)
    {
        $roles = $this->rolePermissionService->listarRoles($request);
        return response()->json($roles, 200);
    }

    //permissions
    // public function permissions(Request $request)
    // {
    //     Log::info('permissions');

    //     $modules = Module::with([
    //         'permissions' => function ($query) {
    //             $query->with('permission'); // Obtener el permiso relacionado
    //         },
    //         'submodules' => function ($query) {
    //             $query->with(['permissions' => function ($q) {
    //                 $q->with('permission'); // Obtener los permisos de cada submódulo
    //             }]);
    //         }
    //     ])->get();

    //     $permissions = Permission::all();
    //     return response()->json(['modules' => $modules, 'permissions' => $permissions], 200);
    // }

    public function permissions(Request $request)
    {
        Log::info('permissions');
        $data = $this->rolePermissionService->obtenerModulosConPermisos();
        return response()->json($data, 200);
    }

    public function assignRoleToUser(AssignRoleToUserRequest $request)
    {
        $user = $this->rolePermissionService->asignarRolAUsuario($request->user_id, $request->role);
        return response()->json([
            'message' => 'Rol asignado correctamente',
            'user' => $user
        ]);
    }

    public function removeRoleFromUser(RemoveRoleFromUserRequest $request)
    {
        $user = $this->rolePermissionService->removerRolDeUsuario($request->user_id, $request->role);
        return response()->json([
            'message' => 'Rol removido correctamente',
            'user' => $user
        ]);
    }

    public function assignPermissionToRole(AssignPermissionToRoleRequest $request)
    {
        $role = $this->rolePermissionService->asignarPermisoARol($request->role, $request->permission);
        return response()->json([
            'message' => 'Permiso asignado correctamente',
            'role' => $role
        ]);
    }

    public function removePermissionFromRole(RemovePermissionFromRoleRequest $request)
    {
        $role = $this->rolePermissionService->removerPermisoDeRol($request->role, $request->permission);
        return response()->json([
            'message' => 'Permiso removido correctamente',
            'role' => $role
        ]);
    }

    public function assignPermissionToUser(AssignPermissionToUserRequest $request)
    {
        $user = $this->rolePermissionService->asignarPermisoAUsuario($request->user_id, $request->permission);
        return response()->json([
            'message' => 'Permiso asignado correctamente',
            'user' => $user
        ]);
    }

    public function removePermissionFromUser(RemovePermissionFromUserRequest $request)
    {
        $user = $this->rolePermissionService->removerPermisoDeUsuario($request->user_id, $request->permission);
        return response()->json([
            'message' => 'Permiso removido correctamente',
            'user' => $user
        ]);
    }

    //store
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'permissions' => 'required|array',
    //         'is_global' => 'boolean'
    //     ]);

    //     $user = auth()->user();


    //     $role = Role::create(['name' => $request->name]);
    //     $role->syncPermissions($request->permissions);

    //     return response()->json(['message' => 'Rol creado correctamente', 'role' => $role], 201);
    // }

    public function store(StoreRoleRequest $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }
            $role = $this->rolePermissionService->crearRol($request->all(), $user);
            return response()->json([
                'message' => 'Rol creado correctamente',
                'role' => $role
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    // public function updateRolePermissions(Request $request)
    // {
    //     $request->validate([
    //         'role' => 'required|exists:roles,name',
    //         'permissions' => 'required|array'
    //     ]);

    //     $role = Role::findByName($request->role);
    //     $role->syncPermissions($request->permissions);

    //     return response()->json(['message' => 'Permisos actualizados correctamente', 'role' => $role], 200);
    // }

    public function updateRolePermissions(UpdateRolePermissionsRequest $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }
            $role = $this->rolePermissionService->actualizarPermisosRol($request->role, $request->permissions, $user);
            return response()->json([
                'message' => 'Permisos actualizados correctamente',
                'role' => $role
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 404;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }


    // public function getUserPermissions($userId)
    // {
    //     try {
    //         $user = User::findOrFail($userId);
    //         $rolePermissions = $user->getPermissionsViaRoles()->pluck('name');
    //         $directPermissions = $user->getDirectPermissions()->pluck('name');
    //         $modules = Module::with('permissions', 'submodules.permissions')->get();

    //         return response()->json([
    //             'ok' => true,
    //             'data' => [
    //                 'role' => $user->roles->first()->name,
    //                 'rolePermissions' => $rolePermissions,
    //                 'directPermissions' => $directPermissions,
    //                 'allPermissions' => Permission::get(['id', 'name']),
    //                 'modules' => $modules
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'ok' => false,
    //             'message' => 'Error al obtener permisos',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function getUserPermissions($userId)
    // {
    //    // try {
    //         $user = User::findOrFail($userId);

    //         // Obtener permisos del rol
    //         $rolePermissions = $user->getPermissionsViaRoles()->pluck('name');

    //         // Obtener permisos directos
    //         $directPermissions = $user->getDirectPermissions()->pluck('name');

    //         // Obtener permisos revocados
    //         $revokedPermissions = DB::table('permission_revocations')
    //             ->where('user_id', $userId)
    //             ->pluck('permission_name');

    //         // Obtener permisos efectivos
    //         $effectivePermissions = collect($rolePermissions)
    //             ->merge($directPermissions)
    //             ->diff($revokedPermissions);

    //         // Filtrar módulos
    //         $modules = Module::with(['permissions', 'submodules.permissions'])
    //             ->get()
    //             ->map(function ($module) use ($revokedPermissions) {
    //                 $module->permissions = $module->permissions->filter(function ($permission) use ($revokedPermissions) {
    //                     return !$revokedPermissions->contains($permission->permission->name);
    //                 });

    //                 $module->submodules->each(function ($submodule) use ($revokedPermissions) {
    //                     $submodule->permissions = $submodule->permissions->filter(function ($permission) use ($revokedPermissions) {
    //                         return !$revokedPermissions->contains($permission->permission->name);
    //                     });
    //                 });

    //                 return $module;
    //             });

    //         return response()->json([
    //             'ok' => true,
    //             'data' => [
    //                 'role' => $user->roles->first()->name ?? 'Sin rol asignado',
    //                 'rolePermissions' => $rolePermissions,
    //                 'directPermissions' => $directPermissions,
    //                 'revokedPermissions' => $revokedPermissions,
    //                 'effectivePermissions' => $effectivePermissions,
    //                 'modules' => $modules
    //             ]
    //         ]);
    //     // } catch (\Exception $e) {
    //     //     return response()->json([
    //     //         'ok' => false,
    //     //         'message' => 'Error al obtener permisos',
    //     //         'error' => $e->getMessage()
    //     //     ], 500);
    //     // }
    // }

    public function getUserPermissions($userId)
    {
        try {
            $data = $this->rolePermissionService->obtenerPermisosUsuario($userId);
            return response()->json([
                'ok' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener permisos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function saveUserPermissions(Request $request, $userId)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $user = User::findOrFail($userId);

    //         $request->validate([
    //             'added_permissions' => 'present|array',
    //             'removed_permissions' => 'present|array'
    //         ]);

    //         // Manejar permisos a remover
    //         foreach ($request->removed_permissions ?? [] as $permission) {
    //             DB::table('permission_revocations')->updateOrInsert(
    //                 [
    //                     'user_id' => $userId,
    //                     'permission_name' => $permission
    //                 ],
    //                 ['created_at' => now(), 'updated_at' => now()]
    //             );
    //         }

    //         // Manejar permisos a agregar
    //         foreach ($request->added_permissions ?? [] as $permission) {
    //             // Eliminar la revocación si existe
    //             DB::table('permission_revocations')
    //                 ->where('user_id', $userId)
    //                 ->where('permission_name', $permission)
    //                 ->delete();

    //             // Dar el permiso si es necesario
    //             if (!$user->hasDirectPermission($permission)) {
    //                 $user->givePermissionTo($permission);
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'ok' => true,
    //             'message' => 'Permisos actualizados correctamente'
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'ok' => false,
    //             'message' => 'Error al actualizar permisos',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function saveUserPermissions(SaveUserPermissionsRequest $request, $userId)
    {
        try {
            $this->rolePermissionService->guardarPermisosUsuario(
                $userId,
                $request->added_permissions ?? [],
                $request->removed_permissions ?? []
            );
            return response()->json([
                'ok' => true,
                'message' => 'Permisos actualizados correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al actualizar permisos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRolePermissions($roleId)
    {
        try {
            $permissions = $this->rolePermissionService->obtenerPermisosRol($roleId);
            return response()->json([
                'ok' => true,
                'data' => $permissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener permisos del rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function modules(Request $request)
    {
        $modules = $this->rolePermissionService->listarModulos($request);
        return response()->json($modules, 200);
    }

    // public function storeModule(Request $request)
    // {
    //     Log::info($request->all());
    //     dd($request->all());
    // }


    public function storeModule(StoreModuleRequest $request)
    {
        try {
            $module = $this->rolePermissionService->crearModulo($request->all());
            return response()->json([
                'message' => 'Módulo creado exitosamente',
                'module' => $module
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getModule(Request $request)
    {
        $module = Module::with('permissions', 'submodules.permissions')->findOrFail($request->id);
        return response()->json($module, 200);
    }

    public function updateModule(UpdateModuleRequest $request, $id)
    {
        try {
            $module = $this->rolePermissionService->actualizarModulo($id, $request->all());
            return response()->json([
                'message' => 'Módulo actualizado exitosamente',
                'module' => $module
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // public function roles()
    // {
    //     // $tipoToRol = [
    //     //     'Administrador'  => config('constants.ROL_ADMIN'),
    //     //     'Supervisor'   => config('constants.ROL_USUARIO_SUPERVISOR'),
    //     //     'Contador'     => config('constants.ROL_CONTADOR_SUPERIOR'),
    //     //     'Citas'        => config('constants.ROL_USUARIO_CITAS'),
    //     //     'Ventas'       => config('constants.ROL_USUARIO_VENTAS')
    //     // ];

    //     // $roles = Role::whereIn('name', array_values($tipoToRol))->get();
    //     $roles = Role::all();
    //     return response()->json($roles, 200);
    // }

    public function roles()
    {
        $roles = $this->rolePermissionService->obtenerRolesDisponibles();
        return response()->json($roles, 200);
    }

}
