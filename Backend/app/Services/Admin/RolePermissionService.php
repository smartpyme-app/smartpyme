<?php

namespace App\Services\Admin;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Log;

class RolePermissionService
{
    /**
     * Construye query base para listar roles con filtros
     *
     * @param \Illuminate\Http\Request $request
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function construirQueryRoles($request, User $user)
    {
        $query = Role::with('permissions');

        $query->where(function($q) use ($user) {
            $q->where('id_empresa', $user->id_empresa)
              ->orWhereNull('id_empresa');
        });

        if ($request->buscador) {
            $query->where('name', 'like', '%' . $request->buscador . '%');
        }

        return $query;
    }

    /**
     * Lista roles con paginación
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarRoles($request)
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Usuario no autenticado');
        }
        $query = $this->construirQueryRoles($request, $user);
        return $query->paginate($request->paginate ?? 10);
    }

    /**
     * Obtiene lista simple de roles disponibles
     *
     * @return \Illuminate\Support\Collection
     */
    public function obtenerRolesDisponibles()
    {
        $user = Auth::user();
        if (!$user) {
            throw new \Exception('Usuario no autenticado');
        }

        $rolesQuery = Role::where(function($q) use ($user) {
            $q->where('id_empresa', $user->id_empresa)
              ->orWhereNull('id_empresa');
        });

        // Si NO es super admin, no mostrar el rol de super_admin
        if (!$this->esSuperAdmin($user)) {
            $rolesQuery->where('name', '!=', 'super_admin');
        }

        return $rolesQuery
            ->orderBy('name')
            ->get()
            ->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $this->formatearNombreRol($role->name),
                    'is_global' => is_null($role->id_empresa),
                    'permissions_count' => $role->permissions()->count()
                ];
            });
    }

    /**
     * Crea un nuevo rol
     *
     * @param array $data
     * @param User $user
     * @return Role
     * @throws \Exception
     */
    public function crearRol(array $data, User $user): Role
    {
        // Verificar si el nombre del rol ya existe para esta empresa
        $existingRole = Role::where('name', $data['name'])
            ->where(function($q) use ($user) {
                $q->where('id_empresa', $user->id_empresa)
                  ->orWhereNull('id_empresa');
            })
            ->first();

        if ($existingRole) {
            throw new \Exception('Ya existe un rol con ese nombre');
        }

        $roleData = [
            'name' => $data['name'],
            'guard_name' => 'web'
        ];

        if (isset($data['is_global']) && $data['is_global'] && $this->esSuperAdmin($user)) {
            $roleData['id_empresa'] = null;
        } else {
            $roleData['id_empresa'] = $user->id_empresa;
        }

        $role = Role::create($roleData);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    /**
     * Actualiza permisos de un rol
     *
     * @param string $roleName
     * @param array $permissions
     * @param User $user
     * @return Role
     * @throws \Exception
     */
    public function actualizarPermisosRol(string $roleName, array $permissions, User $user): Role
    {
        $role = Role::where('name', $roleName)
            ->where(function($q) use ($user) {
                $q->where('id_empresa', $user->id_empresa)
                  ->orWhereNull('id_empresa');
            })
            ->first();

        if (!$role) {
            throw new \Exception('Rol no encontrado o sin permisos para modificar');
        }

        if (!$this->puedeModificarRol($role, $user)) {
            throw new \Exception('No tienes permisos para modificar este rol', 403);
        }

        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    /**
     * Asigna un rol a un usuario
     *
     * @param int $userId
     * @param string $roleName
     * @return User
     */
    public function asignarRolAUsuario(int $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);
        $user->assignRole($roleName);
        return $user->load('roles', 'permissions');
    }

    /**
     * Remueve un rol de un usuario
     *
     * @param int $userId
     * @param string $roleName
     * @return User
     */
    public function removerRolDeUsuario(int $userId, string $roleName): User
    {
        $user = User::findOrFail($userId);
        $user->removeRole($roleName);
        return $user->load('roles', 'permissions');
    }

    /**
     * Asigna un permiso a un rol
     *
     * @param string $roleName
     * @param string $permissionName
     * @return Role
     */
    public function asignarPermisoARol(string $roleName, string $permissionName): Role
    {
        $role = Role::findByName($roleName);
        $role->givePermissionTo($permissionName);
        return $role->load('permissions');
    }

    /**
     * Remueve un permiso de un rol
     *
     * @param string $roleName
     * @param string $permissionName
     * @return Role
     */
    public function removerPermisoDeRol(string $roleName, string $permissionName): Role
    {
        $role = Role::findByName($roleName);
        $role->revokePermissionTo($permissionName);
        return $role->load('permissions');
    }

    /**
     * Asigna un permiso directo a un usuario
     *
     * @param int $userId
     * @param string $permissionName
     * @return User
     */
    public function asignarPermisoAUsuario(int $userId, string $permissionName): User
    {
        $user = User::findOrFail($userId);
        $user->givePermissionTo($permissionName);
        return $user->load('permissions');
    }

    /**
     * Remueve un permiso directo de un usuario
     *
     * @param int $userId
     * @param string $permissionName
     * @return User
     */
    public function removerPermisoDeUsuario(int $userId, string $permissionName): User
    {
        $user = User::findOrFail($userId);
        $user->revokePermissionTo($permissionName);
        return $user->load('permissions');
    }

    /**
     * Obtiene todos los permisos de un usuario
     *
     * @param int $userId
     * @return array
     */
    public function obtenerPermisosUsuario(int $userId): array
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

        return [
            'role' => $user->roles->first()->name ?? 'Sin rol asignado',
            'rolePermissions' => $rolePermissions,
            'directPermissions' => $directPermissions,
            'revokedPermissions' => $revokedPermissions,
            'effectivePermissions' => $effectivePermissions,
            'modules' => $modules
        ];
    }

    /**
     * Guarda permisos de un usuario (agregar y remover)
     *
     * @param int $userId
     * @param array $addedPermissions
     * @param array $removedPermissions
     * @return void
     * @throws \Exception
     */
    public function guardarPermisosUsuario(int $userId, array $addedPermissions, array $removedPermissions): void
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);

            // Manejar permisos a remover
            foreach ($removedPermissions as $permission) {
                DB::table('permission_revocations')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'permission_name' => $permission
                    ],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // Manejar permisos a agregar
            foreach ($addedPermissions as $permission) {
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
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene permisos de un rol
     *
     * @param int $roleId
     * @return \Illuminate\Support\Collection
     */
    public function obtenerPermisosRol(int $roleId)
    {
        $role = Role::findOrFail($roleId);
        return $role->permissions->pluck('name');
    }

    /**
     * Obtiene módulos con permisos
     *
     * @return array
     */
    public function obtenerModulosConPermisos(): array
    {
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

        return [
            'modules' => $modules,
            'permissions' => $permissions
        ];
    }

    /**
     * Lista módulos con paginación
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listarModulos($request)
    {
        $query = Module::with('permissions', 'submodules');

        if ($request->buscador) {
            $query->where('name', 'like', '%' . $request->buscador . '%');
        }

        return $query->paginate($request->paginate ?? 10);
    }

    /**
     * Crea un módulo con sus permisos
     *
     * @param array $data
     * @return Module
     * @throws \Exception
     */
    public function crearModulo(array $data): Module
    {
        DB::beginTransaction();
        try {
            // 1. Crear el módulo
            $module = Module::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null
            ]);

            // 2. Crear submódulos si existen
            if (!empty($data['submodules'])) {
                foreach ($data['submodules'] as $submodule) {
                    $module->submodules()->create([
                        'name' => $submodule['name'],
                        'display_name' => $submodule['display_name']
                    ]);
                }
            }

            // 3. Crear permisos base del módulo
            $basePermissions = [
                $data['name'] . '.ver',
                $data['name'] . '.crear',
                $data['name'] . '.editar',
                $data['name'] . '.eliminar'
            ];

            foreach ($basePermissions as $permission) {
                $permissionModel = Permission::firstOrCreate(['name' => $permission]);
                ModulePermission::create([
                    'module_id' => $module->id,
                    'permission_id' => $permissionModel->id,
                    'permission_type' => 'base'
                ]);
            }

            // 4. Crear permisos base de submódulos
            foreach ($module->submodules as $submodule) {
                $subPermissions = [
                    $data['name'] . '.' . $submodule->name . '.ver',
                    $data['name'] . '.' . $submodule->name . '.crear',
                    $data['name'] . '.' . $submodule->name . '.editar',
                    $data['name'] . '.' . $submodule->name . '.eliminar'
                ];

                foreach ($subPermissions as $permission) {
                    $permissionModel = Permission::firstOrCreate(['name' => $permission]);
                    ModulePermission::create([
                        'submodule_id' => $submodule->id,
                        'permission_id' => $permissionModel->id,
                        'permission_type' => 'base'
                    ]);
                }
            }

            // 5. Crear permisos personalizados
            if (!empty($data['custom_permissions'])) {
                $this->crearPermisosPersonalizados($module, $data['custom_permissions'], $data['name']);
            }

            DB::commit();
            return $module->load('submodules');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Actualiza un módulo y sus permisos
     *
     * @param int $id
     * @param array $data
     * @return Module
     * @throws \Exception
     */
    public function actualizarModulo(int $id, array $data): Module
    {
        DB::beginTransaction();
        try {
            // 1. Actualizar el módulo
            $module = Module::findOrFail($id);
            $module->update([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null
            ]);

            // 2. Actualizar o crear submódulos
            $currentSubmoduleIds = collect($data['submodules'] ?? [])->pluck('id')->filter();
            $module->submodules()
                ->whereNotIn('id', $currentSubmoduleIds)
                ->get()
                ->each(function ($submodule) {
                    $submodule->delete();
                });

            foreach ($data['submodules'] ?? [] as $subData) {
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
            $this->eliminarPermisosPersonalizados($module);
            
            if (!empty($data['custom_permissions'])) {
                $this->crearPermisosPersonalizados($module, $data['custom_permissions'], $module->name);
            }

            DB::commit();
            return $module->load(['submodules', 'permissions.permission']);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Crea permisos personalizados para un módulo
     *
     * @param Module $module
     * @param array $customPermissions
     * @param string $moduleName
     * @return void
     */
    private function crearPermisosPersonalizados(Module $module, array $customPermissions, string $moduleName): void
    {
        foreach ($customPermissions as $customPermission) {
            // Si aplica al módulo principal
            if (isset($customPermission['applyToModule']) && $customPermission['applyToModule']) {
                $permissionName = $moduleName . '.' . $customPermission['action'];
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
                            $permissionName = $moduleName . '.' . $submodule->name . '.' . $customPermission['action'];
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

    /**
     * Elimina permisos personalizados de un módulo
     *
     * @param Module $module
     * @return void
     */
    private function eliminarPermisosPersonalizados(Module $module): void
    {
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
    }

    /**
     * Verifica si un usuario es super admin
     *
     * @param User $user
     * @return bool
     */
    public function esSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Verifica si un usuario puede modificar un rol
     *
     * @param Role $role
     * @param User $user
     * @return bool
     */
    public function puedeModificarRol(Role $role, User $user): bool
    {
        // Super admin puede modificar cualquier rol
        if ($this->esSuperAdmin($user)) {
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

    /**
     * Formatea el nombre de un rol para mostrar
     *
     * @param string $name
     * @return string
     */
    public function formatearNombreRol(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
