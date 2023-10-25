<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Precios\Precio;
use App\Models\Inventario\Precios\Usuario;
use Illuminate\Support\Facades\Crypt;
use Auth;

class PreciosController extends Controller
{


    public function store(Request $request){

        $this->validate($request, [
            'precio'      => 'required|numeric',
            'id_producto' => 'required|numeric',
            'usuarios'    => 'required',
        ]);

        $precio = new Precio;
        $precio->fill($request->all());
        $precio->save();

        foreach ($request->usuarios as $usuario) {
            if (isset($usuario['autorizado']) && $usuario['autorizado']) {
                $pUsuario = new Usuario;
                $pUsuario->id_precio =  $precio->id;
                $pUsuario->id_usuario =  $usuario['id'];
                $pUsuario->save();
            }
        }

        $precio = Precio::with('usuarios')->find($precio->id);

        return Response()->json($precio, 200);

    }


    public function delete($id){
        $precio = Precio::findOrfail($id);
        $precio->usuarios()->delete();
        $precio->delete();

        return Response()->json($precio, 201);
    }
}
