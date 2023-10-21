<?php

namespace App\Http\Controllers\Api\Contabilidad\CajaChica;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Contabilidad\CajaChica\CajaChica;

class CajasChicasController extends Controller
{
    

    public function index() {
       
        $cajachica = CajaChica::orderBy('id', 'desc')->paginate(10);

        return Response()->json($cajachica, 200);

    }


    public function read($id) {
        
        $cajachica = CajaChica::where('id', $id)->with('detalles', function($q){
                                        $q->where('fecha', date('Y-m-d'))->orderby('fecha', 'desc')->orderby('id', 'desc');
                                    })->firstOrFail();

        return Response()->json($cajachica, 200);

    }

    public function filter(Request $request) {


        $cajachica = CajaChica::when($request->inicio, function($query) use ($request){
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

        return Response()->json($cajachica, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            // 'apertura'      => 'required',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
        ]);

        if($request->id)
            $cajachica = CajaChica::findOrFail($request->id);
        else
            $cajachica = new CajaChica;
        
        $cajachica->fill($request->all());
        $cajachica->save();

        return Response()->json($cajachica, 200);

    }

    public function delete($id)
    {
       
        $cajachica = CajaChica::findOrFail($id);
        $cajachica->delete();

        return Response()->json($cajachica, 201);

    }

    public function reporte($id)
    {
       
        $cajachica = CajaChica::findOrFail($id);

        $reportes = \PDF::loadView('reportes.cajachica.movimientos', compact('cajachica'))->setPaper('letter', 'landscape');
        return $reportes->stream();

    }


}
