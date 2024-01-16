<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Proveedor;
use Illuminate\Support\Facades\Crypt;
use Auth;

class ProveedorController extends Controller
{


    public function store(Request $request){

        $this->validate($request, [
            'id_proveedor'  => 'required|numeric',
            'id_producto'   => 'required|numeric',
        ]);

        $proveedor = new Proveedor;
        $proveedor->fill($request->all());
        $proveedor->save();

        $proveedor = Proveedor::with('proveedor')->where('id', $proveedor->id)->firstOrFail();

        return Response()->json($proveedor, 200);

    }


    public function delete($id){
        $proveedor = Proveedor::findOrfail($id);
        $proveedor->delete();

        return Response()->json($proveedor, 201);
    }
}
