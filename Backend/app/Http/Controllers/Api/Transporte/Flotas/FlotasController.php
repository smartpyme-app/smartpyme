<?php

namespace App\Http\Controllers\Api\Transporte\Flotas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Transporte\Flotas\Flota;
use Carbon\Carbon;
use JWTAuth;

class FlotasController extends Controller
{
    
    public function index() {
       
        $flotas = Flota::orderBy('id','desc')->get();

        return Response()->json($flotas, 200);

    }

    public function read($id) {

        $flota = Flota::where('id', $id)->firstOrFail();
        return Response()->json($flota, 200);

    }

    public function search($txt) {

        $flotas = Flota::where('placa', 'like' ,'%' . $txt . '%')
                                ->get();
        return Response()->json($flotas, 200);

    }

    public function filter(Request $request) {

        $flotas = Flota::when($request->tipo, function($query) use ($request){
                                return $query->where('tipo', $request->tipo);
                            })
                            ->orderBy('id','desc')->get();

        return Response()->json($flotas, 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'propietario'   => 'required',
            'placa'         => 'nullable|unique:transporte_flotas,placa,'. $request->id,
            'tipo'          => 'required|max:255',
            'sucursal_id'    => 'required|numeric',
        ]);
        

        if($request->id)
            $flota = Flota::findOrFail($request->id);
        else
            $flota = new Flota;

        $flota->fill($request->all());
        $flota->save();
        
        return Response()->json($flota, 200);

    }

    public function delete($id)
    {
        $flota = Flota::findOrFail($id);
        $flota->delete();

        return Response()->json($flota, 201);

    }

    public function generarDoc($id){
        $venta = Flota::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        $empresa = Empresa::find(1);
    
        return view('reportes.preticket', compact('venta', 'empresa'));

    }

    public function vendedor() {
       
        $flotas = Flota::orderBy('id','desc')->where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)->get();

        return Response()->json($flotas, 200);

    }


    public function vendedorBuscador($txt) {
       
        $flotas = Flota::where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)
                                ->with('cliente', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->get();
        return Response()->json($flotas, 200);

    }


    public function dash(Request $request) {
       
        $flota = Flota::where('id', $request->id)->firstOrFail();

        $mantenimientos = $flota->mantenimientos()->where('estado', 'Completado')->whereBetween('fecha', [$request->inicio, $request->fin])->get();
        $fletes = $flota->fletes()->where('estado', 'Pagado')->whereBetween('fecha', [$request->inicio, $request->fin])->get();

        $flota->total_fletes = $fletes->sum('subtotal');

        $flota->total_combustible = $fletes->sum('combustible');
        $flota->total_mantenimientos = $mantenimientos->where('tipo', 'Preventivo')->sum('total');
        $flota->total_reparaciones = $mantenimientos->where('tipo', 'Correctivo')->sum('total');

        $flota->total_ingresos = $flota->total_fletes;
        $flota->total_egresos = $flota->total_combustible + $flota->total_mantenimientos + $flota->total_reparaciones;
        
        $flota->total_balance = $flota->total_ingresos - $flota->total_egresos;

        return Response()->json($flota, 200);

    }

}
