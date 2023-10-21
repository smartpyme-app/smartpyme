<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User as Usuario;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class UsuariosController extends Controller
{
    

    public function index() {
       
        $usuarios = Usuario::orderBy('id','desc')->paginate(15);

        return Response()->json($usuarios, 200);

    }

    public function list() {
       
        $usuarios = Usuario::where('empleado', true)->orderBy('id','desc')->get();

        return Response()->json($usuarios, 200);

    }


    public function read($id) {
        
        $usuario = Usuario::where('id', $id)->firstOrFail();
        return Response()->json($usuario, 200);
    }

    public function filter(Request $request) {

        $usuarios = Usuario::when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->tipo, function($query) use ($request){
                            return $query->where('tipo', $request->tipo);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($usuarios, 200);

    }

    public function search($txt) {

        $usuarios = Usuario::where('name', 'like' ,'%' . $txt . '%')->paginate(15);
        return Response()->json($usuarios, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|max:255',
            'username'  => 'required|unique:users,username,'.$request->id,
            'tipo'      => 'required',
            'caja_id'   => 'required_if:tipo,"Cajero"',

        ]);

        if ($request->password) {
            $request->validate([
                'password' => 'required|string|min:3|confirmed'
            ]);
        }

        if($request->id)
            $usuario = Usuario::findOrFail($request->id);
        else
            $usuario = new Usuario;


        if ($request->password) {
            $request['password'] = \Hash::make($request->password);
        }
       
        if (!$request->id) {
            $request['password'] = \Hash::make('emple');
        }
        
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
