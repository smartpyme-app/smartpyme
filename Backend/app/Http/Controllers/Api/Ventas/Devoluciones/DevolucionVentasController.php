<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;

use App\Models\Creditos\Credito;

use Illuminate\Support\Facades\DB;


class DevolucionVentasController extends Controller
{
    

    public function index() {
       
        $ventas = Devolucion::orderBy('id','desc')->paginate(10);
       
        return Response()->json($ventas, 200);

    }



    public function read($id) {

        $venta = Devolucion::where('id', $id)->with('detalles', 'cliente')->first();
        return Response()->json($venta, 200);

    }

    public function search($txt) {

        $ventas = Devolucion::whereHas('cliente', function($query) use ($txt) {
                                    $query->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('correlativo', 'like', '%'.$txt.'%')
                                ->orwhere('tipo_documento', 'like', '%'.$txt.'%')
                                ->orwhere('estado', 'like', '%'.$txt.'%')
                                ->orwhere('forma_de_pago', 'like', '%'.$txt.'%')
                                ->orwhere('referencia', 'like', '%'.$txt.'%')
                                ->paginate(10);

        return Response()->json($ventas, 200);

    }

    public function filter(Request $request) {


        $ventas = Devolucion::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($ventas, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            // 'cliente_id'        => 'required',
            'usuario_id'        => 'required',
        ]);

        if($request->id)
            $venta = Devolucion::findOrFail($request->id);
        else
            $venta = new Devolucion;
        
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
            'tipo_documento'    => 'required|max:255',
            // 'cliente_id'           => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            'subcosto'          => 'required|numeric',
            'subtotal'          => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'required|max:255',
            'venta_id'          => 'required|numeric',
            // 'caja_id'           => 'required|numeric',
            // 'corte_id'          => 'required|numeric',
            'usuario_id'        => 'required|numeric',
            'sucursal_id'       => 'required|numeric',
        ]);

        DB::beginTransaction();
         
        try {
        
        // Guardamos la venta
            if($request->id)
                $venta = Devolucion::findOrFail($request->id);
            else
                $venta = new Devolucion;
            
            $venta->fill($request->all());
            $venta->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['devolucion_id'] = $venta->id;
                $detalle->fill($det);
                $detalle->save();

                // Actualizar inventario
                $producto = Producto::where('id', $det['producto_id'])->with('composiciones')->firstOrFail();

                // Inventario compuestos
                foreach ($producto->composiciones as $comp) {
                    $productoCompuesto = $comp->compuesto()->first();
                    if ($productoCompuesto->bodega_venta) {
                        $inventario = Inventario::where('producto_id', $comp->compuesto_id)->where('bodega_id', $venta->venta->bodega_id)->first();
                        if ($inventario) {
                            $inventario->stock += $det['cantidad'] * $comp->cantidad;
                            $inventario->save();
                            $inventario->kardex($venta, ($det['cantidad'] * $comp->cantidad));
                        }
                    }
                }
                // Inventario individual
                if ($producto->bodega_venta) {
                    $inventario = Inventario::where('producto_id', $producto->id)->where('bodega_id', $venta->venta->bodega_id)->first();
                    if ($inventario) {
                        $inventario->stock += $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($venta, $det['cantidad']);
                    }
                }
                
            }
            
        
        DB::commit();
        return Response()->json($venta, 200);

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

        $empresa = Empresa::find(1);

        $partes = explode('.', strval( number_format($venta->total, 2) ));

        $venta->total_letras = \NumeroALetras::convertir($partes[0], 'Dolares con ') . $partes[1].'/100';

        if ($venta->tipo_documento == 'Factura') {

            return view('reportes.factura', compact('venta', 'empresa'));
        }
        elseif ($venta->tipo_documento == 'Credito Fiscal') {

            return view('reportes.credito', compact('venta', 'empresa'));

        }elseif ($venta->tipo_documento == 'Ticket') {

            return view('reportes.ticket-devolucion', compact('venta', 'empresa'));
        }
        else{
            return "Venta sin tipo";
        }

    }


}
