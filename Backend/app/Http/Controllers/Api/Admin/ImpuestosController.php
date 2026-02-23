<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Impuesto;

class ImpuestosController extends Controller
{
    

    public function index() {
       
        $impuesto = Impuesto::all();

        return Response()->json($impuesto, 200);

    }


    public function read($id) {

        $impuesto = Impuesto::findOrFail($id);
        return Response()->json($impuesto, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'        => 'required|max:255',
            'porcentaje'        => 'required|numeric',
            'id_empresa'       => 'required|numeric',
            'aplica_ventas'    => 'sometimes|boolean',
            'aplica_gastos'    => 'sometimes|boolean',
            'aplica_compras'   => 'sometimes|boolean'
        ]);

        if($request->id)
            $impuesto = Impuesto::findOrFail($request->id);
        else
            $impuesto = new Impuesto;

        // Convertir valores de checkboxes a booleanos
        $data = $request->all();
        $data['aplica_ventas'] = isset($data['aplica_ventas']) ? (bool)$data['aplica_ventas'] : false;
        $data['aplica_gastos'] = isset($data['aplica_gastos']) ? (bool)$data['aplica_gastos'] : false;
        $data['aplica_compras'] = isset($data['aplica_compras']) ? (bool)$data['aplica_compras'] : false;
        
        $impuesto->fill($data);
        $impuesto->save();

        return Response()->json($impuesto, 200);

    }

    public function delete($id){
        $impuesto = Impuesto::findOrFail($id);
        $impuesto->delete();
        
        return Response()->json($impuesto, 201);

    }


}
