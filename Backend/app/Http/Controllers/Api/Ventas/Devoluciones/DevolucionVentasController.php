<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Ventas\Venta;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Devoluciones\DetalleCompuesto;
use App\Exports\DevolucionesVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use JWTAuth;
use Auth;

class DevolucionVentasController extends Controller
{
    

    public function index(Request $request) {
       
        $ventas = Devolucion::when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->estado !== null, function($q) use ($request){
                            $q->where('enable', !!$request->estado);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->when($request->buscador, function($query) use ($request){
                        return $query->whereHas('cliente', function($q) use ($request){
                                    $q->where('nombre', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nombre_empresa', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('ncr', 'like' ,"%" . $request->buscador . "%")
                                    ->orwhere('nit', 'like' ,"%" . $request->buscador . "%");
                                 })->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ventas, 200);

    }



    public function read($id) {

        $venta = Devolucion::where('id', $id)->with('detalles.composiciones', 'detalles.producto', 'venta', 'cliente')->first();
        return Response()->json($venta, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'enable'            => 'required',
            'observaciones'            => 'required',
            // 'id_cliente'        => 'required',
            'id_usuario'        => 'required',
        ]);

        if($request->id)
            $venta = Devolucion::findOrFail($request->id);
        else
            $venta = new Devolucion;

            // Ajustar stocks
            foreach ($venta->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();
                                        
                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_sucursal', $venta->venta()->pluck('id_sucursal')->first())->first();
                
                // Anular y regresar stock
                if(($venta->enable != '0') && ($request['enable'] == '0')){

                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad * -1);
                    }

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                                    ->where('id_sucursal', $venta->id_sucursal)->first();

                        if ($inventario) {
                            $inventario->stock -= $detalle->cantidad * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad) * -1);
                        }
                    }

                }
                // Cancelar anulación y descargar stock
                if(($venta->enable == '0') && ($request['enable'] != '0')){
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($venta, $detalle->cantidad);
                    }

                    // Inventario compuestos
                    foreach ($detalle->composiciones()->get() as $comp) {

                        $inventario = Inventario::where('id_producto', $comp->id_producto)
                                    ->where('id_sucursal', $venta->id_sucursal)->first();

                        if ($inventario) {
                            $inventario->stock += $detalle->cantidad * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
                        }
                    }

                }
            }
        
        $venta->fill($request->all());
        $venta->save();        

        return Response()->json($venta, 200);

    }

    public function delete($id)
    {
        $venta = Devolucion::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }
        $venta->delete();

        return Response()->json($venta, 201);

    }


    public function facturacion(Request $request){

        $request->validate([
            'fecha'             => 'required',
            'tipo'              => 'required|max:255',
            // 'id_documento'      => 'required|max:255',
            // 'id_cliente'        => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'observaciones'     => 'required|max:255',
            'id_venta'          => 'required|numeric',
            // 'id_caja'           => 'required|numeric',
            // 'id_corte'          => 'required|numeric',
            'id_usuario'        => 'required|numeric',
            'id_bodega'       => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
            'id_empresa'       => 'required|numeric',
        ],[
            'detalles.required' => 'Tienes que ingresar los detalles a devolver.'
        ]);

        DB::beginTransaction();
         
        try {
        
        // Guardamos la devolucion
            if($request->id)
                $devolucion = Devolucion::findOrFail($request->id);
            else
                $devolucion = new Devolucion;
            
            $devolucion->fill($request->all());
            $devolucion->save();

            // $venta = Venta::findOrFail($request['id_venta']);
            // $venta->estado = 'Anulada';
            // $venta->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['id_devolucion_venta'] = $devolucion->id;
                $detalle->fill($det);
                $detalle->save();

                // Si es compuesto
                if (isset($det['composiciones'])) {
                    foreach ($det['composiciones'] as $item) {
                        $cd = new DetalleCompuesto;
                        $cd->id_producto = $item['id_producto'];
                        $cd->cantidad   = $item['cantidad'];
                        $cd->id_detalle = $detalle->id;
                        $cd->save();

                    }
                }

                $inventario = Inventario::where('id_producto', $det['id_producto'])
                                    ->where('id_bodega', $request->id_bodega)->first();

                if ($inventario) {
                    $inventario->stock += $det['cantidad'];
                    $inventario->save();
                    $inventario->kardex($devolucion, $det['cantidad']);
                }


                // Inventario compuestos
                if (isset($det['composiciones'])) {
                    foreach ($det['composiciones'] as $comp) {

                        $inventario = Inventario::where('id_producto', $comp['id_producto'])
                                    ->where('id_sucursal', $devolucion->id_sucursal)->first();

                        if ($inventario) {
                            $inventario->stock += $det['cantidad'] * $comp['cantidad'];
                            $inventario->save();
                            $inventario->kardex($devolucion, ($det['cantidad'] * $comp['cantidad']));
                        }
                    }
                }

                // Si es paquete cambiar estado
                $paquetes = Paquete::where('id_venta', $devolucion->id_venta)->get();
                foreach ($paquetes as $paquete) {
                    $paquete->estado = 'En bodega';
                    $paquete->id_venta = NULL;
                    $paquete->id_venta_detalle = NULL;
                    $paquete->save();
                }
                
            }
            
        // Incrementar el correlarivo
        if ($devolucion->id_documento) {
            Documento::where('id', $devolucion->id_documento)->increment('correlativo');
        }
        
        DB::commit();
        return Response()->json($devolucion, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        

    }

    public function generarDoc($id){
        $venta = Devolucion::where('id', $id)->with('detalles', 'cliente')->firstOrFail();

        if(Auth::user()->id_empresa == 187 && $venta->nombre_documento == "Nota de crédito"){//187  OK V2

            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.NC-Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
            $pdf->setPaper('US Letter', 'portrait'); 
        }else{
            $pdf = PDF::loadView('reportes.facturacion.nota-credito', compact('venta'));
        }

        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('nota-credito-' . $venta->id . '.pdf');

    }

    public function export(Request $request){
        $ventas = new DevolucionesVentasExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas.xlsx');
    }


}
