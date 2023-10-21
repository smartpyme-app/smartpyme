<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Promocion;

class PromocionesController extends Controller
{

    public function index()
    {
        $promociones = Promocion::with('producto')->orderBy('id','desc')->get();

        return Response()->json($promociones, 201);

    }
    
    public function store(Request $request)
    {
        $request->validate([
            'producto_id'  => 'required|numeric',
            'precio'       => 'required|numeric',
            // 'inicio'       => 'required|after:' . \Carbon\Carbon::now(),
            'inicio'       => 'required',
            'fin'          => 'required|after:inicio'
        ]);

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
