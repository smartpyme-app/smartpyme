<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Documento;
use App\Models\Compras\Gastos\Gasto;

use App\Exports\GastosExport;
use Maatwebsite\Excel\Facades\Excel;

class GastosController extends Controller
{
    

    public function index(Request $request) {
       
        $gastos = Gasto::when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
                        })
                    ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                    })
                    ->when($request->recurrente !== null, function($q) use ($request){
                        $q->where('recurrente', !!$request->recurrente);
                    })
                    ->when($request->id_usuario, function($query) use ($request){
                        return $query->where('id_usuario', $request->id_usuario);
                    })
                    ->when($request->id_sucursal, function($query) use ($request){
                        return $query->where('id_sucursal', $request->id_sucursal);
                    })
                    ->when($request->inicio, function($query) use ($request){
                        return $query->where('fecha', '>=', $request->inicio);
                    })
                    ->when($request->fin, function($query) use ($request){
                        return $query->where('fecha', '<=', $request->fin);
                    })
                    ->when($request->id_proyecto, function($query) use ($request){
                        return $query->where('id_proyecto', $request->id_proyecto);
                    })
                    ->when($request->dte && $request->dte == 0, function($query) {
                            return $query->whereNull('sello_mh');
                    })
                    ->when($request->dte && $request->dte == 1, function($query) {
                        return $query->whereNotNull('sello_mh');
                    })
                    ->when($request->buscador, function($query) use ($request){
                    return $query->whereHas('proveedor', function($q) use ($request){
                                $q->where('nombre', 'like' ,"%" . $request->buscador . "%")
                                ->orwhere('nombre_empresa', 'like' ,"%" . $request->buscador . "%")
                                ->orwhere('ncr', 'like' ,"%" . $request->buscador . "%")
                                ->orwhere('nit', 'like' ,"%" . $request->buscador . "%");
                             })->orwhere('referencia', 'like', '%'.$request->buscador.'%')
                                ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                ->orwhere('concepto', 'like', '%'.$request->buscador.'%')
                                ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

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
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
                        })
                        ->when($request->concepto, function($query) use ($request){
                            return $query->where('concepto', $request->concepto);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
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
            'concepto'      => 'sometimes|max:255',
            'tipo_documento'     => 'required|max:255',
            'concepto'     => 'required|max:255',
            'tipo'     => 'required|max:255',
            'forma_pago'     => 'required|max:255',
            'estado'     => 'required|max:255',
            // 'fecha_pago'         => 'required|date',
            'total'         => 'required|numeric',
            'id_proveedor'    => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_sucursal'   => 'required|numeric',
            'id_empresa'   => 'required|numeric',
        ],[
            'tipo.required' => 'El campo categoria es obligatorio.',
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'id_usuario.required' => 'El campo usuario es obligatorio.',
            'id_empresa.required' => 'El campo empresa es obligatorio.'
        ]);

        if($request->id)
            $gasto = Gasto::findOrFail($request->id);
        else
            $gasto = new Gasto;
        
        $gasto->fill($request->all());
        $gasto->save();

        // Incrementar el correlarivo de Sujeto excluido
        if (!$request->id && $request->tipo_documento == 'Sujeto excluido') {
            $documento = Documento::where('nombre', $gasto->tipo_documento)->first();
            $documento->increment('correlativo');
        }

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
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    // ->orderBy('total', 'desc')
                                    ->take(5)
                                    ->get();

        $datos->meses   = Gasto::selectRaw('sum(total) AS total, MONTH(fecha) as mes, MONTHNAME(fecha) as nombre_mes')
                                    ->groupBy('mes', 'nombre_mes')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    ->orderBy('mes', 'desc')
                                    ->take(5)
                                    ->get();


        return Response()->json($datos, 200);
    }

     public function export(Request $request){
        $gastos = new GastosExport();
        $gastos->filter($request);

        return Excel::download($gastos, 'gastos.xlsx');
    }



}
