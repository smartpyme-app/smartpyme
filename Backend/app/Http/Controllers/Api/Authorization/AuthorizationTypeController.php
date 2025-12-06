<?php

namespace App\Http\Controllers\Api\Authorization;

use App\Http\Controllers\Controller;
use App\Models\Authorization\AuthorizationType;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\Authorization\StoreAuthorizationTypeRequest;
use App\Http\Requests\Authorization\AssignUsersRequest;

class AuthorizationTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = AuthorizationType::where('active', true)
            ->withCount('users')
            ->paginate($request->paginate ?? 15);

        return response()->json($types);
    }

    public function store(StoreAuthorizationTypeRequest $request)
    {

        $type = AuthorizationType::create($request->all());

        return response()->json([
            'ok' => true,
            'message' => 'Tipo de autorización creado exitosamente',
            'data' => $type
        ]);
    }

    public function assignUsers(AssignUsersRequest $request, $typeId)
    {

        $type = AuthorizationType::findOrFail($typeId);
        $type->users()->sync($request->user_ids);

        return response()->json([
            'ok' => true,
            'message' => 'Usuarios asignados exitosamente'
        ]);
    }

    public function getUsers($typeId)
    {
        $type = AuthorizationType::with('users')->findOrFail($typeId);

        return response()->json([
            'ok' => true,
            'data' => $type->users
        ]);
    }

    public function availableUsers($typeId)
    {
        // Solo administradores pueden ser autorizadores
        $adminRoles = ['admin', 'super_admin', 'usuario_supervisor'];
        
        $users = User::whereHas('roles', function ($q) use ($adminRoles) {
            $q->whereIn('name', $adminRoles);
        })->get();

        return response()->json([
            'ok' => true,
            'data' => $users
        ]);
    }
}