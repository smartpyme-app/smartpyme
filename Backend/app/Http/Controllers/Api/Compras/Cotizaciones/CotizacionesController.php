<?php

namespace App\Http\Controllers\Api\Compras\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Registros\Cliente;
use App\Models\Compras\Compra as Cotizacion;
use App\Models\Admin\Empresa;
use App\Models\Compras\Detalle;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use JWTAuth;
use App\Exports\OrdenesDeComprasExport;
use Maatwebsite\Excel\Facades\Excel;

class CotizacionesController extends Controller
{
    
    public function index(Request $request) {
       
        $cotizaciones = Cotizacion::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
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
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                    ->where('cotizacion', 1)
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($cotizaciones, 200);

    }

    public function read($id) {

        $cotizacion = Cotizacion::where('id', $id)->with('proveedor', 'detalles')->firstOrFail();
        return Response()->json($cotizacion, 200);

    }

    public function search($txt) {

        $cotizaciones = Cotizacion::with('proveedor', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($cotizaciones, 200);

    }

    public function filter(Request $request) {

            $cotizaciones = Cotizacion::when($request->fin, function($query) use ($request){
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

            return Response()->json($cotizaciones, 200);
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
            $cotizacion = Cotizacion::findOrFail($request->id);
        else
            $cotizacion = new Cotizacion;

        $cotizacion->fill($request->all());
        $cotizacion->save();
        
        return Response()->json($cotizacion, 200);

    }

    public function facturacion(Request $request){

        $request->validate([
            'fecha'         => 'required',
            'estado'        => 'required|max:255',
            'mesa'          => 'required|numeric',
            'proveedor'       => 'required',
            'detalles'      => 'required',
            'total'         => 'required|numeric',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
        ]);

        // Guardamos el proveedor
        if (isset($request->proveedor['id']) || isset($request->proveedor['nombre'])) {
            if(isset($request->proveedor['id']))
                $proveedor = Cliente::findOrFail($request->proveedor['id']);
            else
                $proveedor = new Cliente;

            $proveedor->fill($request->proveedor);
            $proveedor->save();
            $request['proveedor_id'] = $proveedor->id;
        }

        // Guardamos la cotizacion
            if($request->id)
                $cotizacion = Cotizacion::findOrFail($request->id);
            else
                $cotizacion = new Cotizacion;
            
            $cotizacion->fill($request->all());
            $cotizacion->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;

                $det['cotizacion_id'] = $cotizacion->id;
                
                $detalle->fill($det);
                $detalle->save();
            }

        
        return Response()->json($cotizacion, 200);

    }


    public function delete($id)
    {
        $cotizacion = Cotizacion::findOrFail($id);
        foreach ($cotizacion->detalles as $detalle) {
            $detalle->delete();
        }
        $cotizacion->delete();

        return Response()->json($cotizacion, 201);

    }

    public function generarDoc($id){
        $compra = Cotizacion::where('id', $id)->with('detalles', 'proveedor')->firstOrFail();

        $pdf = PDF::loadView('reportes.facturacion.orden-de-compra', compact('compra'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('orden-de-compra-' . $compra->id . '.pdf');

    }

    public function vendedor() {
       
        $cotizaciones = Cotizacion::orderBy('id','desc')->where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)->paginate(10);

        return Response()->json($cotizaciones, 200);

    }

    public function vendedorBuscador($txt) {
       
        $cotizaciones = Cotizacion::where('usuario_id', \JWTAuth::parseToken()->authenticate()->id)
                                ->with('proveedor', function($q) use($txt){
                                    $q->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('estado', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($cotizaciones, 200);

    }

    public function export(Request $request){
        $cotizaciones = new OrdenesDeComprasExport();
        $cotizaciones->filter($request);

        return Excel::download($cotizaciones, 'cotizaciones.xlsx');
    }

}
