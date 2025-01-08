<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    /**
     * Mostrar todos los roles y permisos
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();
        $users = User::with('roles', 'permissions')->get();
        
        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users
        ]);
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
}