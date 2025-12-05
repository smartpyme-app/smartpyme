<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User as Usuario;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Validation\Rules\Password;
use JWTAuth;
use App\Http\Requests\SuperAdmin\StoreUsuarioRequest;

class UsuariosController extends Controller
{
    

    public function index(Request $request) {
       
        $usuarios = Usuario::with('empresa','roles')
                                ->when($request->estado !== null, function($q) use ($request){
                                    $q->where('enable', !!$request->estado);
                                })
                                ->when($request->id_empresa, function($q) use ($request){
                                    $q->where('id_empresa', $request->id_empresa);
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

    public function store(StoreUsuarioRequest $request)
    {

        if($request->id)
            $usuario = Usuario::findOrFail($request->id);
        else
            $usuario = new Usuario;

        $data = $request->all();
        
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        } elseif (!$request->id) {
            // Solo establecer password por defecto si es usuario nuevo Y no se proporcionó password
            $data['password'] = Hash::make('smart');
        } else {
            // Si es actualización y no se proporcionó password, no tocar el campo
            unset($data['password']);
        }
        
        $usuario->fill($data);

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
        
        $usuario->roles()->sync([$request->rol_id]);

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



}
