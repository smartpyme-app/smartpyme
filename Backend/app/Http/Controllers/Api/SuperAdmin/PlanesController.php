<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;

class PlanesController extends Controller
{

    public function index(Request $request) {
       
        $planes = Plan::paginate();

        return Response()->json($planes, 200);

    }

    public function read($id) {
        
        $plan = Plan::where('id', $id)->firstOrFail();
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
            $plan = Plan::findOrFail($request->id);
        else
            $plan = new Plan;

        $plan->fill($request->all());
        $plan->save();

        return Response()->json($plan, 200);

    }

    public function delete($id)
    {
       
        $plan = Plan::findOrFail($id);
        $plan->delete();

        return Response()->json($plan, 201);

    }



}
