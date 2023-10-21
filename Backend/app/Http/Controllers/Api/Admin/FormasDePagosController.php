<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use App\Models\Admin\FormaDePago;

class FormasDePagosController extends Controller
{
    

    public function index() {
       
        $formasdepagos = FormaDePago::get();

        return Response()->json($formasdepagos, 200);

    }


    public function read($id) {

        $formadepago = FormaDePago::findOrFail($id);
        return Response()->json($formadepago, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'        => 'required|max:255',
            'caja_id'       => 'required|numeric'
        ]);

        if($request->id)
            $formadepago = FormaDePago::findOrFail($request->id);
        else
            $formadepago = new FormaDePago;

        
        $formadepago->fill($request->all());
        $formadepago->save();

        return Response()->json($formadepago, 200);

    }

    public function delete($id){
        $formadepago = FormaDePago::findOrFail($id);
        $formadepago->delete();
        
        return Response()->json($formadepago, 201);

    }


}
