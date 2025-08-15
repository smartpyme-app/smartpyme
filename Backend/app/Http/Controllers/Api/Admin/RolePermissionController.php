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

class RolePermissionController extends Controller
{
    /**
     * Mostrar todos los roles y permisos
     */
    // public function index(Request $request)
    // {
    //     $roles = Role::with('permissions')
    //         ->where('name', 'like', '%' . $request->buscador . '%')
    //         ->paginate($request->paginate);

    //     return response()->json($roles, 200);
    // }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Role::with('permissions');

        $query->where(function($q) use ($user) {
            $q->where('id_empresa', $user->id_empresa)
              ->orWhereNull('id_empresa');
        });

        if ($request->buscador) {
            $query->where('name', 'like', '%' . $request->buscador . '%');
        }

        $roles = $query->paginate($request->paginate ?? 10);

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

        $modules = Module::with([
            'permissions' => function ($query) {
                $query->with('permission');
            },
            'submodules' => function ($query) {
                $query->with(['permissions' => function ($q) {
                    $q->with('permission');
                }]);
            }
        ])->get();

        $permissions = Permission::all();
        return response()->json(['modules' => $modules, 'permissions' => $permissions], 200);
    }

    /**
     * Asignar rol a usuario
     */
    public function assignRoleToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Rol asignado correctamente',
            'user' => $user->load('roles', 'permissions')
        ]);
    }

    /**
     * Remover rol de usuario
     */
    public function removeRoleFromUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|exists:roles,name'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->removeRole($request->role);

        return response()->json([
            'message' => 'Rol removido correctamente',
            'user' => $user->load('roles', 'permissions')
        ]);
    }

    /**
     * Asignar permiso a rol
     */
    public function assignPermissionToRole(Request $request)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
            'permission' => 'required|exists:permissions,name'
        ]);

        $role = Role::findByName($request->role);
        $role->givePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permiso asignado correctamente',
            'role' => $role->load('permissions')
        ]);
    }

    /**
     * Remover permiso de rol
     */
    public function removePermissionFromRole(Request $request)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
            'permission' => 'required|exists:permissions,name'
        ]);

        $role = Role::findByName($request->role);
        $role->revokePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permiso removido correctamente',
            'role' => $role->load('permissions')
        ]);
    }

    /**
     * Asignar permiso directo a usuario
     */
    public function assignPermissionToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|exists:permissions,name'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->givePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permiso asignado correctamente',
            'user' => $user->load('permissions')
        ]);
    }

    /**
     * Remover permiso directo de usuario
     */
    public function removePermissionFromUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission' => 'required|exists:permissions,name'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->revokePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permiso removido correctamente',
            'user' => $user->load('permissions')
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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'present|array',
            'is_global' => 'boolean'
        ]);

        $user = auth()->user();

        // Verificar si el nombre del rol ya existe para esta empresa
        $existingRole = Role::where('name', $request->name)
            ->where(function($q) use ($user) {
                $q->where('id_empresa', $user->id_empresa)
                  ->orWhereNull('id_empresa');
            })
            ->first();

        if ($existingRole) {
            return response()->json([
                'message' => 'Ya existe un rol con ese nombre'
            ], 422);
        }

        $roleData = [
            'name' => $request->name,
            'guard_name' => 'web'
        ];

        if ($request->is_global && $this->isSuperAdmin($user)) {
            $roleData['id_empresa'] = null;
        } else {
            $roleData['id_empresa'] = $user->id_empresa;
        }

        $role = Role::create($roleData);

        if (!empty($request->permissions)) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Rol creado correctamente',
            'role' => $role->load('permissions')
        ], 201);
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

    public function updateRolePermissions(Request $request)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
            'permissions' => 'required|array'
        ]);

        $user = auth()->user();

        $role = Role::where('name', $request->role)
            ->where(function($q) use ($user) {
                $q->where('id_empresa', $user->id_empresa)
                  ->orWhereNull('id_empresa');
            })
            ->first();

        if (!$role) {
            return response()->json([
                'message' => 'Rol no encontrado o sin permisos para modificar'
            ], 404);
        }

        // Verificar si el usuario puede modificar este rol
        if (!$this->canModifyRole($role, $user)) {
            return response()->json([
                'message' => 'No tienes permisos para modificar este rol'
            ], 403);
        }

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permisos actualizados correctamente',
            'role' => $role->load('permissions')
        ], 200);
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
        $user = User::findOrFail($userId);

        // Obtener permisos del rol
        $rolePermissions = $user->getPermissionsViaRoles()->pluck('name');

        // Obtener permisos directos
        $directPermissions = $user->getDirectPermissions()->pluck('name');

        // Obtener permisos revocados
        $revokedPermissions = DB::table('permission_revocations')
            ->where('user_id', $userId)
            ->pluck('permission_name');

        // Obtener permisos efectivos
        $effectivePermissions = collect($rolePermissions)
            ->merge($directPermissions)
            ->diff($revokedPermissions);

        // Filtrar módulos
        $modules = Module::with(['permissions', 'submodules.permissions'])
            ->get()
            ->map(function ($module) use ($revokedPermissions) {
                $module->permissions = $module->permissions->filter(function ($permission) use ($revokedPermissions) {
                    return !$revokedPermissions->contains($permission->permission->name);
                });

                $module->submodules->each(function ($submodule) use ($revokedPermissions) {
                    $submodule->permissions = $submodule->permissions->filter(function ($permission) use ($revokedPermissions) {
                        return !$revokedPermissions->contains($permission->permission->name);
                    });
                });

                return $module;
            });

        // Obtener el primer rol del usuario
        $firstRole = $user->roles->first();

        return response()->json([
            'ok' => true,
            'data' => [
                'role' => $firstRole ? $firstRole->name : 'Sin rol asignado',
                'rolePermissions' => $rolePermissions,
                'directPermissions' => $directPermissions,
                'revokedPermissions' => $revokedPermissions,
                'effectivePermissions' => $effectivePermissions,
                'modules' => $modules
            ]
        ]);
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

    public function saveUserPermissions(Request $request, $userId)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);

            $request->validate([
                'added_permissions' => 'present|array',
                'removed_permissions' => 'present|array'
            ]);

            // Manejar permisos a remover
            foreach ($request->removed_permissions ?? [] as $permission) {
                DB::table('permission_revocations')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'permission_name' => $permission
                    ],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // Manejar permisos a agregar
            foreach ($request->added_permissions ?? [] as $permission) {
                // Eliminar la revocación si existe
                DB::table('permission_revocations')
                    ->where('user_id', $userId)
                    ->where('permission_name', $permission)
                    ->delete();

                // Dar el permiso si es necesario
                if (!$user->hasDirectPermission($permission)) {
                    $user->givePermissionTo($permission);
                }
            }

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Permisos actualizados correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
            $role = Role::findOrFail($roleId);
            return response()->json([
                'ok' => true,
                'data' => $role->permissions->pluck('name')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener permisos del rol',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //modules
    public function modules(Request $request)
    {
        $modules = Module::with('permissions', 'submodules')->where('name', 'like', '%' . $request->buscador . '%')->paginate($request->paginate);

        return response()->json($modules, 200);
    }

    // public function storeModule(Request $request)
    // {
    //     Log::info($request->all());
    //     dd($request->all());
    // }


    public function storeModule(Request $request)
    {
        DB::beginTransaction();
        try {
            // 1. Crear el módulo
            $module = Module::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description
            ]);

            // 2. Crear submódulos si existen
            if (!empty($request->submodules)) {
                foreach ($request->submodules as $submodule) {
                    $module->submodules()->create([
                        'name' => $submodule['name'],
                        'display_name' => $submodule['display_name']
                    ]);
                }
            }

            // 3. Crear permisos base del módulo
            $basePermissions = [
                $request->name . '.ver',
                $request->name . '.crear',
                $request->name . '.editar',
                $request->name . '.eliminar'
            ];

            foreach ($basePermissions as $permission) {
                Permission::create(['name' => $permission]);
                ModulePermission::create([
                    'module_id' => $module->id,
                    'permission_id' => Permission::where('name', $permission)->first()->id,
                    'permission_type' => 'base'
                ]);
            }

            // 4. Crear permisos base de submódulos
            foreach ($module->submodules as $submodule) {
                $subPermissions = [
                    $request->name . '.' . $submodule->name . '.ver',
                    $request->name . '.' . $submodule->name . '.crear',
                    $request->name . '.' . $submodule->name . '.editar',
                    $request->name . '.' . $submodule->name . '.eliminar'
                ];

                foreach ($subPermissions as $permission) {
                    Permission::create(['name' => $permission]);
                    ModulePermission::create([
                        'submodule_id' => $submodule->id,
                        'permission_id' => Permission::where('name', $permission)->first()->id,
                        'permission_type' => 'base'
                    ]);
                }
            }

            // 5. Crear permisos personalizados
            if (!empty($request->custom_permissions)) {
                foreach ($request->custom_permissions as $customPermission) {
                    // Si aplica al módulo principal
                    if ($customPermission['applyToModule']) {
                        Permission::create([
                            'name' => $request->name . '.' . $customPermission['action']
                        ]);
                        ModulePermission::create([
                            'module_id' => $module->id,
                            'permission_id' => Permission::where('name', $request->name . '.' . $customPermission['action'])->first()->id,
                            'permission_type' => 'custom'
                        ]);
                    }

                    // Si aplica a submódulos
                    if (!empty($customPermission['targets'])) {
                        foreach ($customPermission['targets'] as $submoduleName => $isSelected) {
                            if ($isSelected) {
                                Permission::create([
                                    'name' => $request->name . '.' . $submoduleName . '.' . $customPermission['action']
                                ]);
                                ModulePermission::create([
                                    'submodule_id' => $submodule->id,
                                    'permission_id' => Permission::where('name', $request->name . '.' . $submoduleName . '.' . $customPermission['action'])->first()->id,
                                    'permission_type' => 'custom'
                                ]);
                            }
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Módulo creado exitosamente',
                'module' => $module->load('submodules')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getModule(Request $request)
    {
        $module = Module::with('permissions', 'submodules.permissions')->findOrFail($request->id);
        return response()->json($module, 200);
    }

    public function updateModule(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1. Actualizar el módulo
            $module = Module::findOrFail($id);
            $module->update([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description
            ]);

            // 2. Actualizar o crear submódulos
            // Eliminar submódulos que ya no existen
            $currentSubmoduleIds = collect($request->submodules)->pluck('id')->filter();
            $module->submodules()
                ->whereNotIn('id', $currentSubmoduleIds)
                ->get()
                ->each(function ($submodule) {
                    // También eliminará los permisos asociados por la relación cascade
                    $submodule->delete();
                });

            // Actualizar o crear submódulos
            foreach ($request->submodules as $subData) {
                if (isset($subData['id'])) {
                    $module->submodules()
                        ->where('id', $subData['id'])
                        ->update([
                            'name' => $subData['name'],
                            'display_name' => $subData['display_name']
                        ]);
                } else {
                    $module->submodules()->create([
                        'name' => $subData['name'],
                        'display_name' => $subData['display_name']
                    ]);
                }
            }

            // 3. Actualizar permisos personalizados
            // Primero, eliminar todos los permisos personalizados existentes
            $modulePermissionsToDelete = ModulePermission::where(function ($query) use ($module) {
                $query->where('module_id', $module->id)
                    ->orWhereIn('submodule_id', $module->submodules->pluck('id'));
            })->where('permission_type', 'custom')->get();

            foreach ($modulePermissionsToDelete as $mp) {
                $permission = Permission::find($mp->permission_id);
                $mp->delete();
                if ($permission) {
                    $permission->delete();
                }
            }

            // Crear nuevos permisos personalizados
            if (!empty($request->custom_permissions)) {
                foreach ($request->custom_permissions as $customPermission) {
                    // Si aplica al módulo principal
                    if ($customPermission['applyToModule']) {
                        $permissionName = $module->name . '.' . $customPermission['action'];
                        $permission = Permission::firstOrCreate(['name' => $permissionName]);

                        ModulePermission::create([
                            'module_id' => $module->id,
                            'permission_id' => $permission->id,
                            'permission_type' => 'custom'
                        ]);
                    }

                    // Si aplica a submódulos
                    if (!empty($customPermission['targets'])) {
                        foreach ($customPermission['targets'] as $submoduleName => $isSelected) {
                            if ($isSelected) {
                                $submodule = $module->submodules()
                                    ->where('name', $submoduleName)
                                    ->first();

                                if ($submodule) {
                                    $permissionName = $module->name . '.' . $submodule->name . '.' . $customPermission['action'];
                                    $permission = Permission::firstOrCreate(['name' => $permissionName]);

                                    ModulePermission::create([
                                        'submodule_id' => $submodule->id,
                                        'permission_id' => $permission->id,
                                        'permission_type' => 'custom'
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Módulo actualizado exitosamente',
                'module' => $module->load(['submodules', 'permissions.permission'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
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
        $user = auth()->user();

        $rolesQuery = Role::where(function($q) use ($user) {
            $q->where('id_empresa', $user->id_empresa)
              ->orWhereNull('id_empresa');
        });

        // Si NO es super admin, no mostrar el rol de super_admin
        if (!$this->isSuperAdmin($user)) {
            $rolesQuery->where('name', '!=', 'super_admin');
        }

        $roles = $rolesQuery
            ->orderBy('name')
            ->get()
            ->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $this->formatRoleName($role->name),
                    'is_global' => is_null($role->id_empresa),
                    'permissions_count' => $role->permissions()->count()
                ];
            });

        return response()->json($roles, 200);
    }

    private function isSuperAdmin($user)
    {
        return $user->hasRole('super_admin');
    }

    private function canModifyRole($role, $user)
    {
        // Super admin puede modificar cualquier rol
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // Admin puede modificar roles de su empresa
        if ($user->hasRole('admin') && $role->id_empresa == $user->id_empresa) {
            return true;
        }

        // No puede modificar roles globales si no es super admin
        if (is_null($role->id_empresa)) {
            return false;
        }

        return false;
    }

    private function formatRoleName($name)
    {
        return ucwords(str_replace('_', ' ', $name));
    }

}
