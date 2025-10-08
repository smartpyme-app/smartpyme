<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User as Usuario;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Validation\Rules\Password;
use JWTAuth;
use App\Traits\Authorization\HasAutoAuthorization;
use Illuminate\Support\Facades\Log;

class UsuariosController extends Controller
{

    use HasAutoAuthorization;
    protected $authModule = 'usuarios';


    public function index(Request $request) {
        $usuarios = Usuario::with('roles')->where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
            ->with('sucursal')
            ->when($request->estado !== null, function($q) use ($request){
                $q->where('enable', !!$request->estado);
            })
            ->when($request->id_sucursal, function($q) use ($request){
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function($query) use ($request){
                return $query->where(function($subQuery) use ($request) {
                    $subQuery->where('name', 'like', '%' . $request->buscador . '%')
                             ->orWhere('email', 'like', '%' . $request->buscador . '%');
                });
            })
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
        
        //$usuario = Usuario::where('id', $id)->firstOrFail();
        $usuario = Usuario::with('roles')->where('id', $id)->firstOrFail();
        return Response()->json($usuario, 200);
    }

    public function filter(Request $request) {

        Log::info("filter");
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

        Log::info("loggg");
        $usuarios = Usuario::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                            ->where('id_sucursal', JWTAuth::parseToken()->authenticate()->id_sucursal)
                            ->where('name', 'like' ,'%' . $txt . '%')->paginate(15);
        return Response()->json($usuarios, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|max:255',
            'email'         => 'required|unique:users,email,'.$request->id,
           // 'tipo'          => 'required',
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

        // Verificar si se está cambiando el rol
        if ($request->id && $request->rol_id) {
            $usuario = Usuario::findOrFail($request->id);
            // Verificar que el usuario tenga roles asignados antes de acceder a ellos
            $currentRole = $usuario->roles->first();
            if ($currentRole && $currentRole->id != $request->rol_id) {
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
    

    public function updateAuthCode(Request $request, $id)
    {
        
        if ($response = $this->checkAuth('change_auth_code', ['id_usuario' => $id])) {
            return $response;
        }

        $request->validate([
            'codigo_autorizacion' => 'required|numeric|digits_between:3,10'
        ]);

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

}
