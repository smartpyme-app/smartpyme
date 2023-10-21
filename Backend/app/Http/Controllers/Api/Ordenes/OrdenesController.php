<?php

namespace App\Http\Controllers\Api\Ordenes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Ventas\Venta as Orden;
use App\Models\Admin\Empresa;
use App\Models\Admin\Mesa;
use App\Models\Ventas\Detalle;
use Carbon\Carbon;
use JWTAuth;

class OrdenesController extends Controller
{
    
    public function index() {
       
        $ordenes = Orden::orderBy('id','desc')->paginate(10);

        return Response()->json($ordenes, 200);

    }

    public function read($id) {

        $orden = Orden::where('id', $id)->with('cliente', 'detalles')->firstOrFail();
        return Response()->json($orden, 200);

    }

    public function search($txt) {

        $ordenes = Orden::with('cliente', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($ordenes, 200);

    }

    public function filter(Request $request) {

            $ordenes = Orden::when($request->fin, function($query) use ($request){
                                    return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                                })
                                ->when($request->sucursal_id, function($query) use ($request){
                                    return $query->where('sucursal_id', $request->sucursal_id);
                                })
                                ->when($request->tipo_servicio, function($query) use ($request){
                                    return $query->where('tipo_servicio', $request->tipo_servicio);
                                })
                                ->when($request->usuario_id, function($query) use ($request){
                                    return $query->where('usuario_id', $request->usuario_id);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->orderBy('id','asc')->paginate(100000);

            return Response()->json($ordenes, 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'fecha'         => 'required',
            'estado'        => 'required|max:255',
            'total'         => 'required|max:255',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
        ]);
        

        if($request->id)
            $orden = Orden::findOrFail($request->id);
        else
            $orden = new Orden;

        $orden->fill($request->all());
        $orden->total = $orden->detalles()->sum('total');
        $orden->save();
        
        return Response()->json($orden, 200);

    }

    public function facturacion(Request $request){

        $request->validate([
            'fecha'         => 'required',
            'estado'        => 'required|max:255',
            'mesa'          => 'required|numeric',
            'cliente'       => 'required',
            'detalles'      => 'required',
            'total'         => 'required|numeric',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
        ]);

        // Guardamos el cliente
        if (isset($request->cliente['id']) || isset($request->cliente['nombre'])) {
            if(isset($request->cliente['id']))
                $cliente = Cliente::findOrFail($request->cliente['id']);
            else
                $cliente = new Cliente;

            $cliente->fill($request->cliente);
            $cliente->save();
            $request['cliente_id'] = $cliente->id;
        }

        // Guardamos la orden
            if($request->id)
                $orden = Orden::findOrFail($request->id);
            else
                $orden = new Orden;
            
            $orden->fill($request->all());
            $orden->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;

                $det['orden_id'] = $orden->id;
                
                $detalle->fill($det);
                $detalle->save();
            }

        
        return Response()->json($orden, 200);

    }


    public function delete($id)
    {
        $orden = Orden::findOrFail($id);
        foreach ($orden->detalles as $detalle) {
            $detalle->delete();
        }
        $orden->delete();

        return Response()->json($orden, 201);

    }

    public function generarDoc($id){
        $venta = Orden::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        $empresa = Empresa::find(1);
    
        return view('reportes.preticket', compact('venta', 'empresa'));

    }

    public function vendedor() {
       
        $ordenes = Orden::orderBy('id','desc')->where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)->paginate(10);

        return Response()->json($ordenes, 200);

    }

    public function vendedorBuscador($txt) {
       
        $ordenes = Orden::where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)
                                ->with('cliente', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($ordenes, 200);

    }

}
