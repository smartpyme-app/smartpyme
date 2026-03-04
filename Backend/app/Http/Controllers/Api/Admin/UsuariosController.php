<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User as Usuario;
use App\Models\User;
use App\Services\Admin\UsuarioService;
use App\Services\Admin\IntegracionEcommerceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Validation\Rules\Password;
use JWTAuth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Traits\Authorization\HasAutoAuthorization;
use App\Http\Requests\Admin\Usuarios\StoreUsuarioRequest;
use App\Http\Requests\Admin\Usuarios\SaveCredentialsRequest;
use App\Http\Requests\Admin\Usuarios\UpdateAuthCodeRequest;
use App\Http\Requests\Admin\Usuarios\UpdateInfoRequest;
use App\Http\Requests\Admin\Usuarios\UpdateAvatarRequest;

class UsuariosController extends Controller
{

    use HasAutoAuthorization;
    protected $authModule = 'usuarios';

    protected $usuarioService;
    protected $integracionService;

    public function __construct(
        UsuarioService $usuarioService,
        IntegracionEcommerceService $integracionService
    ) {
        $this->usuarioService = $usuarioService;
        $this->integracionService = $integracionService;
    }

    public function index(Request $request)
    {

        $usuarios = Usuario::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
            ->with('sucursal', 'bodega')
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where('name', 'like', '%' . $request->buscador . '%')
                    ->orwhere('email', 'like', "%" . $request->buscador . "%");
            })
            // ->orderBy('enable', 'desc')
            ->orderBy($request->orden, $request->direccion)
            ->paginate($request->paginate);

        return Response()->json($usuarios, 200);
    }

    public function list()
    {
        $usuarios = $this->usuarioService->listarUsuariosActivos();
        return Response()->json($usuarios, 200);
    }

    public function listEditDevolucion()
    {
        $usuarios = $this->usuarioService->listarUsuariosActivosPorSucursal();
        return Response()->json($usuarios, 200);
    }


    public function read($id)
    {
        $usuario = $this->usuarioService->obtenerUsuario($id);
        return Response()->json($usuario, 200);
    }

    public function filter(Request $request)
    {
        $usuarios = $this->usuarioService->filtrarUsuarios($request->all());
        return Response()->json($usuarios, 200);
    }

    public function search($txt)
    {
        $usuarios = $this->usuarioService->buscarUsuarios($txt);
        return Response()->json($usuarios, 200);
    }


    public function store(StoreUsuarioRequest $request)
    {
        // Verificar autorización para cambio de rol
        if ($request->id && $request->rol_id) {
            $usuario = Usuario::findOrFail($request->id);
            $rolActual = optional($usuario->roles->first())->id;

            if ($rolActual != $request->rol_id) {
                if ($response = $this->checkAuth('change_role', ['id_usuario' => $request->id])) {
                    return $response;
                }
            }
        }

        $data = $request->all();
        if ($request->hasFile('file')) {
            $data['file'] = $request->file('file');
        }

        $usuario = $this->usuarioService->crearOActualizarUsuario($data);

        return Response()->json($usuario, 200);
    }

    public function delete($id)
    {
        $usuario = $this->usuarioService->eliminarUsuario($id);
        return Response()->json($usuario, 201);
    }

    public function caja($id)
    {
        $usuarios = $this->usuarioService->obtenerUsuariosPorCaja($id);
        return Response()->json($usuarios, 200);
    }

    public function validar(Request $request)
    {
        $supervisor = $this->usuarioService->validarCodigoSupervisor($request->codigo);

        if (!$supervisor) {
            return Response()->json(['error' => ['Datos incorrectos'], 'code' => 422], 422);
        }

        return Response()->json($supervisor, 200);
    }

    public function auth(Request $request)
    {
        $user = $this->usuarioService->autenticarUsuario($request->username, $request->password);

        if (!$user) {
            return Response()->json(['error' => ['Datos incorrectos'], 'code' => 422], 422);
        }

        return Response()->json($user, 200);
    }


    public function saveCredentials(SaveCredentialsRequest $request)
    {
        try {
            $usuario = User::findOrFail(Auth::user()->id);
            $empresa = Empresa::find($usuario->id_empresa);

            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Usuario no tiene empresa asociada'
                ], 422);
            }

            $credenciales = [
                'store_url' => $request->store_url,
                'consumer_key' => $request->consumer_key ?? null,
                'consumer_secret' => $request->consumer_secret,
                'canal_id' => $request->canal_id ?? null
            ];

            $resultado = $this->integracionService->guardarCredenciales(
                $usuario,
                $empresa,
                $request->tipo,
                $credenciales
            );

            $code = $resultado['status'] === 'success' ? 200 : 500;
            return response()->json($resultado, $code);
        } catch (\Exception $e) {
            Log::error("Error general en saveCredentials", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error interno del servidor: ' . $e->getMessage(),
                'connection_status' => 'disconnected',
            ], 500);
        }
    }

    public function disconnectWooCommerce(Request $request)
    {
        try {
            $usuario = User::find(Auth::user()->id);
            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Usuario no encontrado'
                ], 404);
            }

            $empresa = Empresa::find($usuario->id_empresa);
            $resultado = $this->integracionService->desconectarWooCommerce($usuario, $empresa);

            $code = $resultado['status'] === 'success' ? 200 : 422;
            return response()->json($resultado, $code);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al desactivar la conexión con WooCommerce: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEmail(Request $request, $id)
    {
        $user = $this->usuarioService->actualizarEmail($id, $request->email);
        return Response()->json($user, 200);
    }

    public function updatePassword(Request $request, $id)
    {
        if ($response = $this->checkAuth('change_password', [
            'id_usuario' => $id,
            'password' => $request->password
        ])) {
            return $response;
        }

        $user = $this->usuarioService->actualizarPassword($id, $request->password);
        return Response()->json($user, 200);
    }


    public function updateAuthCode(UpdateAuthCodeRequest $request, $id)
    {
        if ($response = $this->checkAuth('change_auth_code', ['id_usuario' => $id])) {
            return $response;
        }

        $this->usuarioService->actualizarCodigoAutorizacion($id, $request->codigo_autorizacion);
        return response()->json(['message' => 'Código actualizado correctamente']);
    }

    protected function handlePendingAuthorization($data, $authorization)
    {
        return response()->json([
            'ok' => false,
            'requires_authorization' => true,
            'message' => 'Esta acción requiere autorización'
        ], 403);
    }

    public function updateInfo(UpdateInfoRequest $request)
    {
        Log::info('updateInfo', $request->all());
        $request->validate([
            'name' => 'required',
            'telefono'      => 'sometimes|nullable|unique:users,telefono,' . $request->id,
            'tipo' => 'required',
            'codigo' => 'sometimes|nullable',
            'id_sucursal' => 'required',
            'id_bodega' => 'required',
        ]);


        $user = Usuario::findOrFail($request->id);
        $user->fill($request->all());
        $user->save();
        return Response()->json($user, 200);
    }

    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = $this->usuarioService->actualizarAvatar($request->id, $request->file('file'));
        return Response()->json($user, 200);
    }
}
