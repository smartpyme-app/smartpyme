<?php

namespace App\Http\Controllers\Api\Transporte\Fletes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Transporte\Fletes\Flete;
use App\Models\Transporte\Fletes\Detalle;
use App\Models\Admin\Empresa;
use App\Models\Admin\Mesa;
use Carbon\Carbon;
use JWTAuth;

class FletesController extends Controller
{
    
    public function index() {
       
        $fletes = Flete::orderBy('id','desc')->paginate(10);

        return Response()->json($fletes, 200);

    }

    public function read($id) {

        $flete = Flete::where('id', $id)->with('cliente', 'detalles')->firstOrFail();
        return Response()->json($flete, 200);

    }

    public function search($txt) {

        $fletes = Flete::wherehas('cliente', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })->orwherehas('motorista', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($fletes, 200);

    }

    public function filter(Request $request) {

            $fletes = Flete::when($request->fin, function($query) use ($request){
                                    return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                                })
                                ->when($request->sucursal_id, function($query) use ($request){
                                    return $query->where('sucursal_id', $request->sucursal_id);
                                })
                                ->when($request->tipo, function($query) use ($request){
                                    return $query->where('tipo', $request->tipo);
                                })
                                ->when($request->motorista_id, function($query) use ($request){
                                    return $query->where('motorista_id', $request->motorista_id);
                                })
                                ->when($request->cliente_id, function($query) use ($request){
                                    return $query->where('cliente_id', $request->cliente_id);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->orderBy('id','asc')->paginate(100000);

            return Response()->json($fletes, 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'fecha'             => 'required',
            'tipo'              => 'required|max:255',
            'estado'            => 'required|max:255',
            'fecha_carga'       => 'required',
            'fecha_descarga'    => 'required',
            'punto_origen'      => 'required',
            'punto_destino'     => 'required',
            // 'tipo_embalaje'     => 'required|max:255',
            // 'descripcion'      => 'required',
            'subtotal'          => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'sometimes|max:255',
            'cliente_id'        => 'required|numeric',
            'motorista_id'      => 'required|numeric',
            'cabezal_id'        => 'required|numeric',
            // 'remolque_id'       => 'required|numeric',
            'usuario_id'        => 'required|numeric',
            'sucursal_id'       => 'required|numeric',
        ]);
        

        if($request->id)
            $flete = Flete::findOrFail($request->id);
        else
            $flete = new Flete;

        $flete->fill($request->all());
        $flete->save();

        foreach($request->detalles as $det){
            if(isset($det['id']))
                $detalle = Detalle::find($det['id']);
            else
                $detalle = new Detalle;

            $det['flete_id'] = $flete->id;
            $detalle->fill($det);
            $detalle->save();
        }
        
        return Response()->json($flete, 200);

    }

    public function pendientes() {
       
        $fletes = Flete::where('estado', 'Pendiente')->get();

        return Response()->json($fletes, 200);

    }

    public function flota($id) {
       
        $fletes = Flete::where('cabezal_id', $id)->orwhere('remolque_id', $id)->paginate(5);

        return Response()->json($fletes, 200);

    }

    public function delete($id)
    {
        $flete = Flete::findOrFail($id);
        $flete->delete();

        return Response()->json($flete, 201);

    }

    public function empleado($id) {
       
        $fletes = Flete::where('motorista_id', $id)->orderBy('fecha','desc')->paginate(10);
        return Response()->json($fletes, 200);

    }

    public function cliente($id) {
       
        $fletes = Flete::where('cliente_id', $id)->orderBy('fecha','desc')->paginate(10);
        return Response()->json($fletes, 200);

    }

    public function ordenDeCarga($id){
        $flete = Flete::where('id', $id)->with('motorista', 'cliente', 'proveedor', 'sucursal.empresa')->firstOrFail();

        $reportes = \PDF::loadView('reportes.transporte.orden-de-carga', compact('flete'))->setPaper('letter', 'landscape');
        return $reportes->stream();

        return view('reportes.transporte.orden-de-carga', compact('flete'));

    }

    public function cartaDePorte($id){
        $flete = Flete::where('id', $id)->with('motorista', 'cliente', 'proveedor', 'sucursal.empresa')->firstOrFail();

        $reportes = \PDF::loadView('reportes.transporte.carta-de-porte', compact('flete'))->setPaper('letter', 'portrait');
        return $reportes->stream();

        return view('reportes.transporte.carta-de-porte', compact('flete'));

    }

    public function manifiestoDeCarga($id){
        $flete = Flete::where('id', $id)->with('motorista', 'cliente', 'proveedor', 'sucursal.empresa')->firstOrFail();

        $reportes = \PDF::loadView('reportes.transporte.manifiesto-de-carga', compact('flete'))->setPaper('letter', 'portrait');
        return $reportes->stream();

        return view('reportes.transporte.orden-de-carga', compact('flete'));

    }



}
