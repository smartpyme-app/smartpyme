<?php

namespace App\Http\Controllers\Api\Contadores;

use App\Http\Controllers\Controller;
use App\Services\Contadores\ContadorEmpresaInvitacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ContadorPortalInvitacionController extends Controller
{
    public function __construct(private ContadorEmpresaInvitacionService $invitaciones)
    {
    }

    public function index(): JsonResponse
    {
        $user = $this->contadorAutenticado();
        $pendientes = $this->invitaciones->listarPendientesDespacho((int) $user->id_empresa);

        return response()->json([
            'invitaciones' => $pendientes->map(fn ($i) => [
                'id' => $i->id,
                'email_admin' => $i->email_admin,
                'estado' => $i->estado,
                'expira_en' => $i->expira_en?->toIso8601String(),
                'empresa_cliente' => $i->empresaCliente?->only(['id', 'nombre']),
                'created_at' => $i->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);

        try {
            $result = $this->invitaciones->crearYEnviar(
                $this->contadorAutenticado(),
                (string) $request->input('email')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorInvitacion($e);
        }

        $inv = $result['invitacion'];

        return response()->json([
            'ok' => true,
            'enviado' => true,
            'invitacion' => [
                'id' => $inv->id,
                'email_admin' => $inv->email_admin,
                'expira_en' => $inv->expira_en?->toIso8601String(),
                'empresa_cliente' => $inv->empresaCliente?->only(['id', 'nombre']),
            ],
        ], 201);
    }

    public function reenviar(int $id): JsonResponse
    {
        try {
            $inv = $this->invitaciones->reenviar($this->contadorAutenticado(), $id);
        } catch (\InvalidArgumentException $e) {
            return $this->errorInvitacion($e);
        }

        return response()->json(['ok' => true, 'invitacion' => ['id' => $inv->id, 'expira_en' => $inv->expira_en?->toIso8601String()]]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->invitaciones->cancelar($this->contadorAutenticado(), $id);
        } catch (\InvalidArgumentException $e) {
            return $this->errorInvitacion($e);
        }

        return response()->json(['ok' => true]);
    }

    public function vistaPublica(string $token): JsonResponse
    {
        try {
            return response()->json($this->invitaciones->vistaPublica($token));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => ['Invitación no encontrada.'], 'code' => 404], 404);
        }
    }

    public function aceptarPublica(Request $request, string $token): JsonResponse
    {
        $user = null;
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable) {
            // público sin sesión
        }

        try {
            $payload = $this->invitaciones->aceptar($token, $user);
        } catch (\InvalidArgumentException $e) {
            return $this->errorInvitacion($e);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => ['Invitación no encontrada.'], 'code' => 404], 404);
        }

        return response()->json($payload);
    }

    public function rechazarPublica(Request $request, string $token): JsonResponse
    {
        $user = null;
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable) {
        }

        try {
            $payload = $this->invitaciones->rechazar($token, $user);
        } catch (\InvalidArgumentException $e) {
            return $this->errorInvitacion($e);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['error' => ['Invitación no encontrada.'], 'code' => 404], 404);
        }

        return response()->json($payload);
    }

    private function contadorAutenticado()
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');
        if (!$user->hasRole(config('constants.ROL_ADMIN_CONTADOR', 'admin_contador'))) {
            abort(response()->json(['error' => 'Acceso reservado al portal de contadores.', 'code' => 403], 403));
        }

        return $user;
    }

    private function errorInvitacion(\InvalidArgumentException $e): JsonResponse
    {
        $code = (int) $e->getCode();
        if ($code < 400 || $code >= 600) {
            $code = 422;
        }

        return response()->json(['error' => [$e->getMessage()], 'code' => $code], $code);
    }
}
