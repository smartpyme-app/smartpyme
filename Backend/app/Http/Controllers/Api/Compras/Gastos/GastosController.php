<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Compras\Gastos\Gasto;

class GastosController extends Controller
{
    

    public function index() {
       
        $gastos = Gasto::orderBy('id', 'desc')->paginate(10);

        return Response()->json($gastos, 200);

    }


    public function read($id) {
        
        $gasto = Gasto::findOrFail($id);
        return Response()->json($gasto, 200);

    }

    public function filter(Request $request) {


        $gastos = Gasto::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->when($request->categoria, function($query) use ($request){
                            return $query->where('categoria', $request->categoria);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($gastos, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'descripcion'   => 'nullable|max:255',
            'categoria_id'     => 'required|max:255',
            'metodo_pago'     => 'required|max:255',
            'estado'     => 'required|max:255',
            'condicion'     => 'required|max:255',
            'fecha_pago'         => 'required|date',
            'total'         => 'required|numeric',
            'proveedor_id'    => 'sometimes|numeric',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
        ]);

        if($request->id)
            $gasto = Gasto::findOrFail($request->id);
        else
            $gasto = new Gasto;
        
        $gasto->fill($request->all());
        $gasto->save();

        return Response()->json($gasto, 200);

    }

    public function delete($id)
    {
       
        $gasto = Gasto::findOrFail($id);
        $gasto->delete();

        return Response()->json($gasto, 201);

    }

    public function dash(Request $request) {

        $datos = new \stdClass();

        $datos->categorias   = Gasto::selectRaw('sum(total) AS total, categoria')
                                    ->groupBy('categoria')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    // ->orderBy('total', 'desc')
                                    ->take(5)
                                    ->get();

        $datos->meses   = Gasto::selectRaw('sum(total) AS total, MONTH(fecha) as mes, MONTHNAME(fecha) as nombre_mes')
                                    ->groupBy('mes', 'nombre_mes')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    ->orderBy('mes', 'desc')
                                    ->take(5)
                                    ->get();


        return Response()->json($datos, 200);
    }


}
