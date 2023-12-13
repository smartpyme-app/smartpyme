<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Sucursal;
use JWTAuth;

class SucursalesController extends Controller
{
    

    public function index() {
        $sucursales = Sucursal::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)->get();
        return Response()->json($sucursales, 200);

    }

    public function list() {
       
        $sucursales = Sucursal::orderby('nombre')
                                ->where('activo', true)
                                ->get();

        return Response()->json($sucursales, 200);

    }
    
    public function read($id) {

        $sucursal = Sucursal::where('id', $id)->firstOrFail();
        return Response()->json($sucursal, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $sucursal = Sucursal::findOrFail($request->id);
        else
            $sucursal = new Sucursal;
        
        $sucursal->fill($request->all());
        $sucursal->save();

        return Response()->json($sucursal, 200);

    }

    public function delete($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->delete();

        return Response()->json($sucursal, 201);

    }

}
