<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrdenPago as Transaccion;
use App\Http\Requests\SuperAdmin\Transacciones\StoreTransaccionRequest;

class TransaccionesController extends Controller
{
    

    public function index() {
       
        $transacciones = Transaccion::with('usuario.empresa')->orderBy('id','desc')->paginate(10);

        return Response()->json($transacciones, 200);

    }


    public function read($id) {

        $transaccion = Transaccion::findOrFail($id);
        return Response()->json($transaccion, 200);

    }

    public function store(StoreTransaccionRequest $request)
    {

        if($request->id)
            $transaccion = Transaccion::findOrFail($request->id);
        else
            $transaccion = new Transaccion;

        
        $transaccion->fill($request->all());
        $transaccion->save();

        return Response()->json($transaccion, 200);

    }

    public function delete($id){
        $transaccion = Transaccion::findOrFail($id);
        $transaccion->delete();
        
        return Response()->json($transaccion, 201);

    }


}
