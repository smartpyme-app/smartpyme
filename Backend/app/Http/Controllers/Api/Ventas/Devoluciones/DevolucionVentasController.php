<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Ventas\Venta;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Admin\Documento;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Exports\DevolucionesVentasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Creditos\Credito;
use Illuminate\Support\Facades\DB;


class DevolucionVentasController extends Controller
{
    

    public function index(Request $request) {
       
        $ventas = Devolucion::when($request->buscador, function($query) use ($request){
                            return $query->where('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('enable', $request->estado);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->whereHas('cliente', function($query) use ($request)
                            {
                                $query->where('id_cliente', $request->id_cliente);

                            });
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ventas, 200);

    }



    public function read($id) {

        $venta = Devolucion::where('id', $id)->with('detalles', 'cliente')->first();
        return Response()->json($venta, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'observaciones'            => 'required',
            // 'id_cliente'        => 'required',
            'id_usuario'        => 'required',
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
            'id_documento'      => 'required|max:255',
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
            'id_sucursal'       => 'required|numeric',
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

            $venta = Venta::findOrFail($request['id_venta']);
            $venta->estado = 'Anulada';
            $venta->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['id_devolucion_venta'] = $devolucion->id;
                $detalle->fill($det);
                $detalle->save();

                $inventario = Inventario::where('id_producto', $det['id_producto'])
                                    ->where('id_sucursal', $request->id_sucursal)->first();

                if ($inventario) {
                    $inventario->stock += $det['cantidad'];
                    $inventario->save();
                    $inventario->kardex($devolucion, $det['cantidad']);
                }
                
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

    public function export(Request $request){
        $ventas = new DevolucionesVentasExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'ventas.xlsx');
    }


}
