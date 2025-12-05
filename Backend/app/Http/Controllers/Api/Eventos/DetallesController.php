<?php

namespace App\Http\Controllers\Api\Eventos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Eventos\Detalle;
use App\Http\Requests\Eventos\StoreDetalleEventoRequest;

class DetallesController extends Controller
{

    public function store(StoreDetalleEventoRequest $request)
    {
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;
        }

        $detalle->fill($request->all());
        $detalle->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
