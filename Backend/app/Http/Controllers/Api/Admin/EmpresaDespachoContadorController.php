<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Contadores\ContadorEmpresaInvitacionService;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class EmpresaDespachoContadorController extends Controller
{
    public function __construct(private ContadorEmpresaInvitacionService $invitaciones)
    {
    }

    public function estado(): JsonResponse
    {
        return response()->json($this->invitaciones->estadoParaEmpresaAdmin($this->adminEmpresa()));
    }

    public function aceptarInvitacion(int $id): JsonResponse
    {
        try {
            $payload = $this->invitaciones->aceptarDesdeApp($this->adminEmpresa(), $id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json($payload);
    }

    public function rechazarInvitacion(int $id): JsonResponse
    {
        try {
            $this->invitaciones->rechazarDesdeApp($this->adminEmpresa(), $id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json(['ok' => true]);
    }

    public function revocarDespacho(): JsonResponse
    {
        try {
            $n = $this->invitaciones->revocarDespachoActivo($this->adminEmpresa());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e);
        }

        return response()->json(['ok' => true, 'accesos_revocados' => $n]);
    }

    private function adminEmpresa()
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');
        if (!$user->hasRole(config('constants.ROL_ADMIN', 'admin'))) {
            abort(response()->json(['error' => 'Solo administradores de la empresa.', 'code' => 403], 403));
        }

        return $user;
    }

    private function error(\InvalidArgumentException $e): JsonResponse
    {
        $code = (int) $e->getCode();
        if ($code < 400 || $code >= 600) {
            $code = 422;
        }

        return response()->json(['error' => [$e->getMessage()], 'code' => $code], $code);
    }
}
