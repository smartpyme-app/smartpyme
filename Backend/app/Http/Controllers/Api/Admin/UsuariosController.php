<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User as Usuario;
use App\Models\User;
use App\Services\WooCommerceApiClient;
use App\Services\ShopifyApiClient;
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

    public function index(Request $request)
    {

        $usuarios = Usuario::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
            ->with('sucursal', 'roles', 'roles.permissions')
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->buscador . '%')
                        ->orWhere('email', 'like', '%' . $request->buscador . '%')
                        ->orWhereHas('sucursal', function ($sq) use ($request) {
                            $sq->where('nombre', 'like', '%' . $request->buscador . '%');
                        });
                });
            })
            // ->orderBy('enable', 'desc')
            ->orderBy($request->orden, $request->direccion)
            ->paginate($request->paginate);

        return Response()->json($usuarios, 200);
    }

    public function list()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario->tipo == 'Administrador') {
            $usuarios = Usuario::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                ->where('enable', true)->orderBy('name', 'asc')->get();
        } else {
            $usuarios = Usuario::where('id_sucursal', $usuario->id_sucursal)
                ->where('enable', true)
                ->orderBy('name', 'asc')->get();
        }

        return Response()->json($usuarios, 200);
    }

    public function listEditDevolucion()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        // if ($usuario->tipo == 'Administrador') {
        //     $usuarios = Usuario::where('enable', true)->orderBy('name','asc')->get();
        // }else{
        $usuarios = Usuario::where('id_sucursal', $usuario->id_sucursal)
            ->where('enable', true)
            ->orderBy('name', 'asc')->get();
        // }

        return Response()->json($usuarios, 200);
    }


    public function read($id) {

        //$usuario = Usuario::where('id', $id)->firstOrFail();
        $usuario = Usuario::with('roles')->where('id', $id)->firstOrFail();
        return Response()->json($usuario, 200);
    }

    public function filter(Request $request)
    {

        $usuarios = Usuario::where('id_sucursal', JWTAuth::parseToken()->authenticate()->id_sucursal)
            ->when($request->sucursal_id, function ($query) use ($request) {
                return $query->where('sucursal_id', $request->sucursal_id);
            })
            ->when($request->tipo, function ($query) use ($request) {
                return $query->where('tipo', $request->tipo);
            })
            ->orderBy('id', 'desc')->paginate(100000);

        return Response()->json($usuarios, 200);
    }

    public function search($txt)
    {
        $usuarios = Usuario::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                            ->where('id_sucursal', JWTAuth::parseToken()->authenticate()->id_sucursal)
                            ->where('name', 'like' ,'%' . $txt . '%')->paginate(15);
        return Response()->json($usuarios, 200);
    }


    public function store(StoreUsuarioRequest $request)
    {

        if ($request->id && $request->rol_id) {
            $usuario = Usuario::findOrFail($request->id);
            $rolActual = optional($usuario->roles->first())->id;
            
            // Si el rol actual es diferente al nuevo rol (incluyendo cuando no hay rol actual)
            if ($rolActual != $request->rol_id) {
                if ($response = $this->checkAuth('change_role', ['id_usuario' => $request->id])) {
                    return $response;
                }
            }
        }

        if($request->id)
            $usuario = Usuario::findOrFail($request->id);
        else
            $usuario = new Usuario;


        if ($request->password) {
            $request['password'] = Hash::make($request->password);
        }

        $usuario->fill($request->all());

        if ($request->hasFile('file')) {
            if ($request->id && $usuario->avatar && $usuario->avatar != 'usuarios/default.jpg') {
                Storage::delete($usuario->avatar);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(350, 350)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $path = "usuarios/{$hash}.jpg";
            $resize->save(public_path('img/' . $path), 50);
            $usuario->avatar = "/" . $path;
        }


        $usuario->save();

        if (!$request->id) {
            $usuario->bienvenida();
        }

        $usuario->roles()->sync([$request->rol_id]);

        $usuario->load('roles');

        return Response()->json($usuario, 200);
    }

    public function delete($id)
    {

        $usuario = Usuario::findOrFail($id);
        $usuario->delete();

        return Response()->json($usuario, 201);
    }

    public function caja($id)
    {

        $usuarios = Usuario::where('caja_id', $id)->get();

        return Response()->json($usuarios, 200);
    }

    public function validar(Request $request)
    {

        $supervisor = Usuario::where('codigo', $request->codigo)->first();

        if (!$supervisor)
            return Response()->json(['error' => ['Datos incorrectos'], 'code' => 422], 422);

        return Response()->json($supervisor, 200);
    }

    public function auth(Request $request)
    {

        $user = Usuario::where('username', $request->username)->firstOrFail();

        if (!Hash::check($request->password, $user->password))
            return Response()->json(['error' => ['Datos incorrectos'], 'code' => 422], 422);

        return Response()->json($user, 200);
    }


    public function saveCredentials(SaveCredentialsRequest $request)
    {
        try {
            // La validación se maneja automáticamente por el FormRequest

            $id_usuario = Auth::user()->id;
            $empresa = Empresa::find(Auth::user()->id_empresa);

            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Usuario no tiene empresa asociada'
                ], 422);
            }

            $usuario = User::findOrFail($id_usuario);
            $tipo = $request->tipo;

            $otherPlatform = $tipo === 'woocommerce' ? 'shopify' : 'woocommerce';
            $otherStatusField = $otherPlatform . '_status';

            // if ($empresa->$otherStatusField === 'connected') {
            //     return response()->json([
            //         'status' => 'error',
            //         'mensaje' => 'Ya tienes ' . ucfirst($otherPlatform) . ' conectado. Solo puedes tener una integración activa.'
            //     ], 400);
            // }

            if (empty($empresa->woocommerce_api_key)) {
                $empresa->woocommerce_api_key = Str::random(64);
            }
            if ($tipo === 'woocommerce') {
                $empresa->woocommerce_store_url = $request->store_url;
                $empresa->woocommerce_consumer_key = $request->consumer_key;
                $empresa->woocommerce_consumer_secret = $request->consumer_secret;
                $empresa->woocommerce_status = 'connecting';
                $empresa->woocommerce_canal_id = $request->canal_id;
            } else { // shopify
                $empresa->shopify_store_url = $request->store_url;
                $empresa->shopify_consumer_secret = $request->consumer_secret;
                $empresa->shopify_status = 'connecting';
                $empresa->shopify_canal_id = $request->canal_id;
            }

            $empresa->save();

            $connectionResult = $this->testConnection($empresa, $tipo);

            if ($connectionResult['success']) {
                $statusField = $tipo . '_status';
                $empresa->$statusField = 'connected';
                $empresa->save();
                if ($tipo === 'woocommerce') {
                    $usuario->woocommerce_status = 'connected';
                } else {
                    $usuario->shopify_status = 'connected';
                }
                $usuario->save();

                $usuario->update([
                    $statusField => 'connected'
                ]);

                Log::info("Conexión exitosa con {$tipo}", [
                    'empresa_id' => $empresa->id,
                    'tipo' => $tipo,
                    'store_url' => $tipo === 'woocommerce' ? $empresa->woocommerce_store_url : $empresa->shopify_store_url
                ]);

                return response()->json([
                    'status' => 'success',
                    'mensaje' => "Credenciales guardadas correctamente. Conexión con " . ucfirst($tipo) . " establecida.",
                    'connection_status' => 'connected',
                    'platform' => $tipo
                ], 200);
            } else {
                $statusField = $tipo . '_status';
                $empresa->$statusField = 'disconnected';
                $empresa->save();
                if ($tipo === 'woocommerce') {
                    $usuario->woocommerce_status = 'disconnected';
                } else {
                    $usuario->shopify_status = 'disconnected';
                }
                $usuario->save();

                return response()->json([
                    'status' => 'error',
                    'mensaje' => "Credenciales guardadas, pero no se pudo establecer conexión con " . ucfirst($tipo) . ": " . $connectionResult['error'],
                    'connection_status' => 'disconnected',
                    'platform' => $tipo
                ], 500);
            }
        } catch (\Exception $e) {
            if (isset($empresa) && isset($tipo)) {
                $statusField = $tipo . '_status';
                $empresa->$statusField = 'disconnected';
                $empresa->save();
                if ($tipo === 'woocommerce') {
                    $usuario->woocommerce_status = 'disconnected';
                } else {
                    $usuario->shopify_status = 'disconnected';
                }
                $usuario->save();
            }

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

    /**
     * Probar conexión con la plataforma especificada
     */
    private function testConnection($empresa, $tipo)
    {
        try {
            if ($tipo === 'woocommerce') {
                $client = new WooCommerceApiClient(
                    $empresa->woocommerce_store_url,
                    $empresa->woocommerce_consumer_key,
                    $empresa->woocommerce_consumer_secret
                );

                $response = $client->get('products', ['per_page' => 1]);

                if ($response['status'] !== 'success') {
                    throw new \Exception('Respuesta inválida de WooCommerce API');
                }
            } else { // shopify
                $client = new ShopifyApiClient(
                    $empresa->shopify_store_url,
                    $empresa->shopify_consumer_secret
                );

                $response = $client->get('shop.json');
                Log::info("Conexión exitosa con Shopify", [
                    'empresa_id' => $empresa->id,
                    'store_url' => $empresa->shopify_store_url,
                    'response' => $response['body']['shop']
                ]);

                if ($response['status'] !== 'success') {
                    throw new \Exception('Respuesta inválida de Shopify API');
                }

                if (!isset($response['body']['shop'])) {
                    throw new \Exception('No se pudo obtener información de la tienda Shopify');
                }
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error("Error probando conexión con {$tipo}", [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function disconnectWooCommerce(Request $request)
    {
        try {
            $id_usuario = Auth::user()->id;
            $usuario = User::find($id_usuario);
            $empresa = Empresa::find($usuario->id_empresa);
            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Usuario no encontrado'
                ], 404);
            }

            if (empty($empresa->woocommerce_api_key)) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'No tienes configurada la integración con WooCommerce'
                ], 422);
            }

            $empresa->woocommerce_status = 'disconnected';
            $empresa->save();

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Conexión con WooCommerce desactivada'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al desactivar la conexión con WooCommerce: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEmail(Request $request,$id)
    {
        $user = Usuario::findOrFail($id);
        $user->email = $request->email;
        $user->save();
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

        $user = Usuario::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();
        return Response()->json($user, 200);
    }


    public function updateAuthCode(UpdateAuthCodeRequest $request, $id)
    {

        if ($response = $this->checkAuth('change_auth_code', ['id_usuario' => $id])) {
            return $response;
        }

        $user = User::findOrFail($id);
        $user->codigo_autorizacion = $request->codigo_autorizacion;
        $user->save();

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


        $user = Usuario::findOrFail($request->id);
        $user->fill($request->all());
        $user->save();
        return Response()->json($user, 200);
    }

    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = Usuario::findOrFail($request->id);

        if ($request->hasFile('file')) {
            if ($request->id && $user->avatar && $user->avatar != 'usuarios/default.jpg') {
                Storage::delete($user->avatar);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(350, 350)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $path = "usuarios/{$hash}.jpg";
            $resize->save(public_path('img/' . $path), 50);
            $user->avatar = "/" . $path;
        }
        $user->save();
        return Response()->json($user, 200);
    }



}
