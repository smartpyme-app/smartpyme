<?php

namespace App\Http\Controllers\Api\Ventas\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Ventas\Venta as Cotizacion;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Detalle;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use JWTAuth;
use Auth;
use App\Exports\CotizacionesExport;
use Maatwebsite\Excel\Facades\Excel;


class CotizacionesController extends Controller
{
    
    public function index(Request $request) {
       
        $ordenes = Cotizacion::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->id_canal, function($query) use ($request){
                            return $query->where('id_canal', $request->id_canal);
                        })
                        ->when($request->id_documento, function($query) use ($request){
                            return $query->where('id_documento', $request->id_documento);
                        })
                        ->when($request->id_proyecto, function($query) use ($request){
                            return $query->where('id_proyecto', $request->id_proyecto);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                    ->where('cotizacion', 1)
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ordenes, 200);

    }

    public function read($id) {

        $orden = Cotizacion::where('id', $id)->with('cliente', 'detalles')->firstOrFail();
        return Response()->json($orden, 200);

    }

    public function search($txt) {

        $ordenes = Cotizacion::with('cliente', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($ordenes, 200);

    }

    public function filter(Request $request) {

            $ordenes = Cotizacion::when($request->fin, function($query) use ($request){
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
            'id_usuario'    => 'required|numeric',
            'id_sucursal'   => 'required|numeric',
        ]);
        

        if($request->id)
            $orden = Cotizacion::findOrFail($request->id);
        else
            $orden = new Cotizacion;

        $orden->fill($request->all());
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
                $orden = Cotizacion::findOrFail($request->id);
            else
                $orden = new Cotizacion;
            
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
        $orden = Cotizacion::findOrFail($id);
        foreach ($orden->detalles as $detalle) {
            $detalle->delete();
        }
        $orden->delete();

        return Response()->json($orden, 201);

    }

    public function generarDoc($id){
        $venta = Cotizacion::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        if(Auth::user()->id_empresa == 420){ //420
            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.cotizacion-inversiones-andre', compact('venta'));
            $pdf->setPaper('US Letter', 'portrait');
        }elseif(Auth::user()->id_empresa == 13){ //13
            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.cotizacion-empresa-13', compact('venta'));
            $pdf->setPaper('US Letter', 'portrait');
        }else{
            $pdf = PDF::loadView('reportes.facturacion.cotizacion', compact('venta'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        return $pdf->stream('cotizacion-' . $venta->id . '.pdf');

    }

    public function vendedor() {
       
        $ordenes = Cotizacion::orderBy('id','desc')->where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)->paginate(10);

        return Response()->json($ordenes, 200);

    }

    public function vendedorBuscador($txt) {
       
        $ordenes = Cotizacion::where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)
                                ->with('cliente', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($ordenes, 200);

    }

    public function export(Request $request){
        $cotizaciones = new CotizacionesExport();
        $cotizaciones->filter($request);

        return Excel::download($cotizaciones, 'cotizaciones.xlsx');
    }

}
