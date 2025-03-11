<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrdenPago as Pago;

class PagosController extends Controller
{

    public function index(Request $request) {
       
        $planes = Pago::paginate();

        return Response()->json($planes, 200);

    }

    public function read($id) {
        
        $plan = Pago::where('id', $id)->firstOrFail();
        return Response()->json($plan, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'          => 'required|max:255',
            'precio'          => 'required',
            'id_producto'     => 'required',
        ]);

        if($request->id)
            $plan = Pago::findOrFail($request->id);
        else
            $plan = new Pago;

        $plan->fill($request->all());
        $plan->save();

        return Response()->json($plan, 200);

    }

    public function delete($id)
    {
       
        $plan = Pago::findOrFail($id);
        $plan->delete();

        return Response()->json($plan, 201);

    }



}
