<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Codigo;
use App\Http\Requests\Inventario\Codigos\StoreCodigoRequest;

class CodigosController extends Controller
{

    public function index()
    {
        $codigos = Codigo::with('producto')->orderBy('id','desc')->paginate(10);

        return Response()->json($codigos, 201);

    }
    
    public function store(StoreCodigoRequest $request)
    {

        if($request->id)
            $promocion = Promocion::findOrFail($request->id);
        else
            $promocion = new Promocion;
        
        $promocion->fill($request->all());
        $promocion->save();

        return Response()->json($promocion, 200);

    }

    public function delete($id)
    {
        $promocion = Promocion::findOrFail($id);
        $promocion->delete();

        return Response()->json($promocion, 201);

    }

    public function deleteAll()
    {
        Promocion::truncate();
        return Response()->json(['status'=>'ok'], 201);

    }


}
