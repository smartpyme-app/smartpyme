<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Abono;

class AbonosController extends Controller
{
    

    public function index() {
       
        $abono = Abono::orderBy('id','desc')->paginate(10);
        return Response()->json($abono, 200);

    }


    public function read($id) {

        $abono = Abono::findOrFail($id);
        return Response()->json($abono, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'fecha'       => 'required|date',
            'concepto'    => 'required|max:255',
            'estado'      => 'required|max:255',
            'metodo_pago' => 'required|max:255',
            'total'       => 'required|numeric',
            'venta_id'    => 'required|numeric',
            'cliente_id'    => 'required|numeric',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'    => 'required|numeric',
        ]);

        if($request->id)
            $abono = Abono::findOrFail($request->id);
        else
            $abono = new Abono;

        
        $abono->fill($request->all());
        $abono->save();

        return Response()->json($abono, 200);

    }

    public function delete($id){
        $abono = Abono::findOrFail($id);
        $abono->delete();
        
        return Response()->json($abono, 201);

    }


}
