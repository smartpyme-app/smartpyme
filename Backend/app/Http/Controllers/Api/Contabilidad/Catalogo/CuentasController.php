<?php

namespace App\Http\Controllers\Api\Contabilidad\Catalogo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Catalogo\Cuenta;

class CuentasController extends Controller
{
    

    public function index(Request $request) {
       
        $cuentas = Cuenta::when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                ->orwhere('codigo', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate);

        return Response()->json($cuentas, 200);

    }

    public function list() {
       
        $cuentas = Cuenta::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($cuentas, 200);

    }
    
    public function read($id) {

        $cuenta = Cuenta::where('id', $id)->firstOrFail();
        return Response()->json($cuenta, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'        => 'required|max:255',
            'nombre'        => 'required|max:255',
            'id_cuenta_mayor'   => 'required|numeric',
            'nivel'         => 'required|numeric',
            'tipo'          => 'required|max:255',
            'sub_cuenta'    => 'required|numeric',
            'rubro'         => 'required|max:255',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $cuenta = Cuenta::findOrFail($request->id);
        else
            $cuenta = new Cuenta;
        
        $cuenta->fill($request->all());
        $cuenta->save();

        return Response()->json($cuenta, 200);

    }

    public function delete($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $cuenta->delete();

        return Response()->json($cuenta, 201);

    }

}
