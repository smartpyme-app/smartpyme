<?php

namespace App\Http\Controllers\Api\Inventario\Composiciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Composiciones\Opcion;
use App\Http\Requests\Inventario\Composiciones\Opciones\StoreOpcionRequest;

class OpcionesController extends Controller
{


    public function store(StoreOpcionRequest $request)
    {
        if($request->id){
            $opcion = Opcion::findOrFail($request->id);
        }
        else{
            $opcion = new Opcion;
        }
        
        $opcion->fill($request->all());
        $opcion->save();

        return Response()->json($opcion, 200);

    }

    public function delete($id)
    {
        $opcion = Opcion::findOrFail($id);
        $opcion->delete();

        return Response()->json($opcion, 201);

    }


}
