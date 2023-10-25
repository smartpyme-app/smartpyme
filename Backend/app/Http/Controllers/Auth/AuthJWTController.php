<?php

namespace App\Http\Controllers\Auth;

use JWTAuth;
use App\Models\User;
use App\Models\Admin\Acceso;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthJWTController extends Controller
{
    

    public function login(Request $request){

        $credentials = $request->only('email', 'password');
        $token = null;

        $token = JWTAuth::attempt($credentials);
        $user = auth()->user();
        

        if (!$token)
            return  Response()->json(['error' => 'Datos incorrectos', 'code' => 401], 401);
        
        if (!$user->enable)
            return  Response()->json(['error' => 'Usuario bloqueado', 'code' => 401], 401);
        
        $user->last_login = Carbon::now();
        $user->save();

        $acceso = new Acceso;
        $acceso->id_usuario = $user->id;
        $acceso->fecha = $user->last_login;
        $acceso->save();

        
        return response()->json(['token' => $token, 'user' => $user], 200);


    }

    public function logout(Request $request){
        // $user = User::findOrFail($request->usuario_id);
        // $user->ultimo_logout = Carbon::now();
        // $user->save();

        // return response()->json(['user' => $user], 200);

    }

    public function register(RegisterRequest $request){

        // Creamos primero el laboratorio vacio.
        $empresa = new Empresa;
        $empresa->nombre         = $request->empresa;
        // $empresa->vencimiento   = Carbon::now()->addMonths(1);
        $empresa->save();

        $user = new User;
        $user->name         = $request->name;
        $user->username        = $request->username;
        $user->password     = bcrypt($request->password);
        // $user->empresa_id     = $empresa->id;
        $user->save();

        if (!$token = JWTAuth::attempt(['username'=> $request->username, 'password' => $request->password])) {
            return  Response()->json(['error' => 'Datos incorrectos', 'code' => 401], 401);
        }

        $user = JWTAuth::authenticate($token);

        return response()->json(['token' => $token, 'user' => $user], 200);       

    }


}
