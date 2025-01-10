<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    /**
     * Mostrar todos los roles y permisos
     */
    public function index(Request $request)
    {
        $roles = Role::with('permissions')
                     ->where('name', 'like', '%' . $request->buscador . '%')
                     ->paginate($request->paginate);

        return response()->json($roles, 200);
    }

    //permissions
    public function permissions(Request $request)
    {
        $permissions = Permission::all();
        return response()->json($permissions, 200);
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
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array'
        ]);

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => 'Rol creado correctamente', 'role' => $role], 201);
    }

  
    public function updateRolePermissions(Request $request)
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
            'permissions' => 'required|array'
        ]);

        $role = Role::findByName($request->role);
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => 'Permisos actualizados correctamente', 'role' => $role], 200);
    }


    public function getUserPermissions($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $rolePermissions = $user->getPermissionsViaRoles()->pluck('name');
            $directPermissions = $user->getDirectPermissions()->pluck('name');

            return response()->json([
                'ok' => true,
                'data' => [
                    'role' => $user->roles->first()->name,
                    'rolePermissions' => $rolePermissions,
                    'directPermissions' => $directPermissions,
                    'allPermissions' => Permission::get(['id', 'name'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al obtener permisos',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function saveUserPermissions(Request $request, $userId)
    {
        try {
            $user = User::findOrFail($userId);
            
            // Validar los permisos
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,name'
            ]);

            // Sincronizar solo los permisos directos (no los del rol)
            $user->syncPermissions($request->permissions);

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
        $modules = Module::with('permissions')->where('name', 'like', '%' . $request->buscador . '%')->paginate($request->paginate);
        return response()->json($modules, 200);
    }

    
}
