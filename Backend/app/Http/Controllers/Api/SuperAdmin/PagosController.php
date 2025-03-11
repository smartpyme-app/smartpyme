<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrdenPago as Pago;

class PagosController extends Controller
{

    public function index(Request $request) {
       
        $pagos = Pago::paginate();

        return Response()->json($pagos, 200);

    }

    public function read($id) {
        
        $pago = Pago::where('id', $id)->firstOrFail();
        return Response()->json($pago, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'          => 'required|max:255',
            'precio'          => 'required',
            'id_producto'     => 'required',
        ]);

        if($request->id)
            $pago = Pago::findOrFail($request->id);
        else
            $pago = new Pago;

        $pago->fill($request->all());
        $pago->save();

        return Response()->json($pago, 200);

    }


    public function generarVenta($id)
    {
        $pago = Pago::where('id', $id)->firstOrFail();
        $venta = $pago->generarVenta();

        return Response()->json($venta, 200);

    }

    public function delete($id)
    {
       
        $pago = Pago::findOrFail($id);
        $pago->delete();

        return Response()->json($pago, 201);

    }



}
