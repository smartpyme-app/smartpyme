<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Compras\Compra;
use App\Models\Compras\DevolucionCompra;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use Illuminate\Support\Facades\DB;

use App\Exports\ComprasExport;
use App\Exports\ComprasDetallesExport;
use Maatwebsite\Excel\Facades\Excel;

class ComprasController extends Controller
{
    

    public function index(Request $request) {
       
        $compras = Compra::when($request->buscador, function($query) use ($request){
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
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->where('estado', '!=', 'Pre-compra')
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->paginate($request->paginate);

        return Response()->json($compras, 200);
           
    }

    public function read($id) {

        $compra = Compra::where('id', $id)->with('detalles', 'proveedor')->first();
        return Response()->json($compra, 200);
 
    }

    public function search($txt) {

        $compras = Compra::whereHas('proveedor', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })
                    ->paginate(10);

        return Response()->json($compras, 200);

    }

    public function filter(Request $request) {

        $compras = Compra::when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                            })
                            ->when($request->referencia, function($query) use ($request){
                                return $query->where('referencia', $request->referencia);
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('estado', $request->estado);
                            })
                            ->when($request->id_proveedor, function($query) use ($request){
                                return $query->whereHas('proveedor', function($query) use ($request)
                                {
                                    $query->where('id_proveedor', $request->id_proveedor);

                                });
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($compras, 200);

    }



    public function store(Request $request)
    {

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'forma_pago'        => 'required',
            'id_proveedor'      => 'required',
            'id_empresa'        => 'required',
            'id_sucursal'       => 'required',
            'id_usuario'        => 'required',
        ]);

        $compra = Compra::where('id', $request->id)->with('detalles')->firstOrFail();

            // Ajustar stocks
            foreach ($compra->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();
                                        
                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_sucursal', $compra->id_sucursal)->first();
                
                // Anular compra y regresar stock
                if(($compra->estado != 'Anulada') && ($request['estado'] == 'Anulada')){

                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad * -1);
                    }

                }
                // Cancelar anulación de compra y descargar stock
                if(($compra->estado == 'Anulada') && ($request['estado'] != 'Anulada')){
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad);
                    }

                }
            }
        
        $compra->fill($request->all());
        $compra->save();

        return Response()->json($compra, 200);

    }

    public function delete($id)
    {
        $compra = Compra::where('id', $id)->with('detalles')->firstOrFail();
        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);
    }


    public function facturacion(Request $request){

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'tipo_documento'    => 'required',
            // 'tipo_documento'    => 'required',
            // 'condicion'         => 'required',
            'forma_pago'        => 'required',
            'id_proveedor'      => 'required',
            'detalles'          => 'required',
            // 'cuotas'            => 'required_if:forma_pago,"Crédito"',
            // 'plazo'             => 'required_if:forma_pago,"Crédito"',
            // 'referencia'        => 'required',
            'id_usuario'        => 'required',
            'id_empresa'        => 'required',
        ],[
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'detalles.required' => 'Los detalles son obligatorios.'
        ]);

        DB::beginTransaction();
         
        try {
        

        // Compra
            if($request->id)
                $compra = Compra::findOrFail($request->id);
            else
                $compra = new Compra;

            $compra->fill($request->all());
            $compra->save();


        // Detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['id_compra'] = $compra->id;
                
                $detalle->fill($det);
                
                if ($request->estado != 'Pre-compra') {
                    // Actualizar inventario
                    $inventario = Inventario::where('id_producto', $det['id_producto'])->where('id_sucursal', $compra->id_sucursal)->first();

                    if ($inventario) {
                        $inventario->stock += $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($compra, $det['cantidad']);
                    }

                }

                $detalle->save();
            }

        DB::commit();
        return Response()->json($compra, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        
        return Response()->json($compra, 200);

    }

    public function libroCompras(Request $request) {

        $compras = Compra::whereBetween('fecha', [$request->inicio, $request->fin])
                            ->where('estado', 'Pagada')
                            ->with('proveedor')
                            ->orderBy('fecha','desc')->get();

        // $devoluciones = DevolucionCompra::whereBetween('fecha', [$request->inicio, $request->fin])
        //                     ->with('proveedor')
        //                     ->orderBy('fecha','desc')->get();

        $data = collect();

        foreach ($compras as $compra) {

            $data->push([
                'fecha'         => $compra->fecha,
                'referencia'    => $compra->referencia,
                'registro'      => $compra->proveedor()->first()->registro,
                'nit'           => $compra->proveedor()->first()->nit,
                'proveedor'     => $compra->proveedor()->first()->nombre,

                'inter_exenta'  => $compra->tipo == 'Interna' ? $compra->exenta : 0,
                'impor_exenta'  => $compra->tipo == 'Importacion' ? $compra->exenta : 0,

                'no_sujeta'     => $compra->no_sujeta,

                'inter_gravada' => $compra->tipo == 'Interna' ? $compra->gravada : 0,
                'impor_gravada' => $compra->tipo == 'Importacion' ? $compra->gravada : 0,

                'iva'           => $compra->iva,

                'reb_dev'       => $compra->descuento ? $compra->descuento : 0,
                'reb_dev_iva'   => $compra->descuento * 0.13,

                'iva_retenido'  => $compra->iva_retenido ? $compra->iva_retenido : 0,
                'cesc'          => $compra->cesc ? $compra->cesc : 0,
                'fovial'        => $compra->fovial,
                'cotrans'       => $compra->cotrans,
                'total'         => $compra->total,
            ]);
        }

        // foreach ($devoluciones as $compra) {

        //     $data->push([
        //         'fecha'         => $compra->fecha,
        //         'referencia'    => $compra->referencia,
        //         'registro'      => $compra->proveedor()->first()->registro,
        //         'nit'           => $compra->proveedor()->first()->nit,
        //         'proveedor'     => $compra->proveedor()->first()->nombre,

        //         'inter_exenta'  => 0,
        //         'impor_exenta'  => 0,

        //         'no_sujeta'     => 0,

        //         'inter_gravada' => 0,
        //         'impor_gravada' => 0,

        //         'iva'           => 0,

        //         'reb_dev'       => $compra->subtotal,
        //         'reb_dev_iva'   => $compra->iva,

        //         'iva_retenido'  => 0,
        //         'cesc'          => 0,
        //         'fovial'        => 0,
        //         'cotrans'       => 0,
        //         'total'         => $compra->total,
        //     ]);
        // }

        return Response()->json($data, 200);

    }


    public function detalles($id)
    {
        $compra = Compra::findOrFail($id);

        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);

    }


    public function comprasProveedor($id) {

        $compras = Compra::where('id_proveedor', $id)->orderBy('estado', 'asc')->paginate(10);

        return Response()->json($compras, 200);

    }

    public function cxp() {
       
        $pagos = Compra::where('estado', 'Pendiente')->orderBy('fecha','desc')->paginate(10);

        return Response()->json($pagos, 200);

    }

    public function cxpBuscar($txt) {
       
        $pagos = Compra::where('estado', 'Pendiente')
                        ->whereHas('proveedor', function($query) use ($txt) {
                            $query->where('nombre', 'like' ,'%' . $txt . '%');
                        })
                        ->orderBy('fecha','desc')->paginate(10);

        return Response()->json($pagos, 200);

    }

    public function historial(Request $request) {

        $compras = Compra::where('estado', 'Pagada')->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->get()
                        ->groupBy(function($date) {
                            return Carbon::parse($date->fecha)->format('d-m-Y');
                        });
        
        $movimientos = collect();

        foreach ($compras as $compra) {
            $movimientos->push([
                'cantidad'      => $compra->count(),
                'fecha'         => $compra[0]->fecha,
                'subtotal'      => $compra->sum('subtotal'),
                'iva'           => $compra->sum('iva'),
                'total'         => $compra->sum('total'),
                'detalles'      => $compra
            ]);
        }

        return Response()->json($movimientos, 200);

    }

    public function export(Request $request){
        $compras = new ComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras.xlsx');
    }

    public function exportDetalles(Request $request){
        $compras = new ComprasDetallesExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras-detalles.xlsx');
    }

    public function sinDevolucion(){

        $compras = Compra::where('estado', '!=', 'Anulada')
                        ->whereMonth('fecha', '>=' , date('m') - 1)
                        ->whereYear('fecha', date('Y'))
                        ->whereDoesntHave('devoluciones')
                        ->orderBy('fecha', 'DESC')
                        ->get();

        return Response()->json($compras, 200);
    }


}
