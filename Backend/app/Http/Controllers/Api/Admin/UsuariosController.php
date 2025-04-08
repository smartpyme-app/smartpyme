<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User as Usuario;
use App\Models\User;
use App\Services\WooCommerceApiClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Validation\Rules\Password;
use JWTAuth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UsuariosController extends Controller
{
    

    public function index(Request $request) {
       
        $usuarios = Usuario::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                                ->with('sucursal')
                                ->when($request->estado !== null, function($q) use ($request){
                                    $q->where('enable', !!$request->estado);
                                })
                                ->when($request->id_sucursal, function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal);
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('name', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('email', 'like' ,"%" . $request->buscador . "%");
                                })
                                // ->orderBy('enable', 'desc')
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($usuarios, 200);

    }

    public function list() {
       
        $usuarios = Usuario::where('id_sucursal', JWTAuth::parseToken()->authenticate()->id_sucursal)
                            ->where('enable', true)
                            ->orderBy('name','asc')->get();

        return Response()->json($usuarios, 200);

    }


    public function read($id) {
        
        $usuario = Usuario::where('id', $id)->firstOrFail();
        return Response()->json($usuario, 200);
    }

    public function filter(Request $request) {

        $usuarios = Usuario::where('id_sucursal', JWTAuth::parseToken()->authenticate()->id_sucursal)
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->tipo, function($query) use ($request){
                            return $query->where('tipo', $request->tipo);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($usuarios, 200);

    }

    public function search($txt) {

        $usuarios = Usuario::where('id_sucursal', JWTAuth::parseToken()->authenticate()->id_sucursal)
                            ->where('name', 'like' ,'%' . $txt . '%')->paginate(15);
        return Response()->json($usuarios, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|max:255',
            'email'         => 'required|unique:users,email,'.$request->id,
            'tipo'          => 'required',
            'id_empresa'    => 'required',
            'id_sucursal'   => 'required',
            'id_bodega'     => 'required',
            'password'      => [
                  'required_if:id,null',
                  'confirmed',
                  'min:8',
                  'regex:/[a-z]/',
                  'regex:/[A-Z]/',
                  'regex:/[0-9]/',
                  'regex:/[!@#$%^&*()_+{}\[\]:;<>,.?~\\-]/',
            ],

        ]);

        if($request->id)
            $usuario = Usuario::findOrFail($request->id);
        else
            $usuario = new Usuario;


        if ($request->password) {
            $request['password'] = \Hash::make($request->password);
        }
       
        // if (!$request->id) {
        //     $request['password'] = \Hash::make('smart');
        // }
        
        $usuario->fill($request->all());

        if ($request->hasFile('file')) {
            if ($request->id && $usuario->avatar && $usuario->avatar != 'usuarios/default.jpg') {
                Storage::delete($usuario->avatar);
            }
            $path   = $request->file('file');
            $resize = Image::make($path)->resize(350,350)->encode('jpg', 75);
            $hash = md5($resize->__toString());
            $path = "usuarios/{$hash}.jpg";
            $resize->save(public_path('img/'.$path), 50);
            $usuario->avatar = "/" . $path;
        }

        
        $usuario->save();
        
        if(!$request->id){
            $usuario->bienvenida();
        }


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


    public function saveCredentials(Request $request)
    {
        try {
            $request->validate([
                'store_url' => 'required|url',
                'consumer_key' => 'required|string',
                'consumer_secret' => 'required|string',
                'canal_id' => 'required|numeric'
            ], [
                'store_url.required' => 'La URL de la tienda es obligatoria',
                'store_url.url' => 'La URL de la tienda debe ser una dirección válida',
                'consumer_key.required' => 'La Consumer Key es obligatoria',
                'consumer_secret.required' => 'El Consumer Secret es obligatorio',
                'canal_id.required' => 'El Canal es obligatorio',
                'canal_id.numeric' => 'El Canal debe ser numérico'
            ]);

            $id_usuario = Auth::user()->id;

            $empresa = Empresa::find(Auth::user()->id_empresa);
            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Usuario no tiene empresa asociada'
                ], 422);
            }
            $usuario = User::findOrFail($id_usuario);

            if (empty($empresa->woocommerce_api_key)) {
                $empresa->woocommerce_api_key = Str::random(64);
            }

            $empresa->woocommerce_store_url = $request->store_url;
            $empresa->woocommerce_consumer_key = $request->consumer_key;
            $empresa->woocommerce_consumer_secret = $request->consumer_secret;
            $empresa->woocommerce_status = 'connecting'; // Estado temporal
            $empresa->woocommerce_canal_id = $request->canal_id;
            $empresa->save();


            $client = new WooCommerceApiClient(
                $empresa->woocommerce_store_url,
                $empresa->woocommerce_consumer_key,
                $empresa->woocommerce_consumer_secret
            );

            $response = $client->get('products', ['per_page' => 1]);


            $empresa->woocommerce_status = 'connected';
            $empresa->save();

            $usuario->woocommerce_status = 'connected';
            $usuario->save();

            Log::info('Conexión exitosa con WooCommerce', [
                'empresa_id' => $empresa->id,
                'store_url' => $empresa->woocommerce_store_url
            ]);


            return response()->json([
                'status' => 'success',
                'mensaje' => 'Credenciales guardadas correctamente. Conexión con WooCommerce establecida.',
                'connection_status' => 'connected',
            ], 200);
        } catch (ValidationException $e) {

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error de validación',
                'errors' => $e->errors(),

            ], 422);
        } catch (\Exception $e) {

            if (isset($empresa)) {
                $empresa->woocommerce_status = 'disconnected';
                $empresa->save();

                $usuario->woocommerce_status = 'disconnected';
                $usuario->save();
            }
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Credenciales guardadas, pero no se pudo establecer conexión con WooCommerce: ' . $e->getMessage(),
                'connection_status' => 'disconnected',

            ], 500);
        }
    }

    //disconnectWooCommerce
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

            $usuario->woocommerce_status = 'disconnected';
            $usuario->save();

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



}
