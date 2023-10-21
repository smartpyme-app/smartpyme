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
use App\Models\Admin\Tanque;
use Illuminate\Support\Facades\DB;

class ComprasController extends Controller
{
    

    public function index() {
       
        $compras = Compra::orderBy('id','desc')->paginate(10);
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
                    })->paginate(10);

        return Response()->json($compras, 200);

    }

    public function filter(Request $request) {

        $compras = Compra::when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('estado', $request->estado);
                            })
                            ->when($request->proveedor_id, function($query) use ($request){
                                return $query->whereHas('proveedor', function($query) use ($request)
                                {
                                    $query->where('proveedor_id', $request->proveedor_id);

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
            'metodo_pago'       => 'required',
            'proveedor_id'      => 'required',
            'usuario_id'        => 'required',
        ]);

        if($request->id)
            $compra = Compra::findOrFail($request->id);
        else
            $compra = new Compra;
        
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
            'tipo'              => 'required',
            'tipo_documento'    => 'required',
            'condicion'        => 'required',
            'metodo_pago'       => 'required',
            'proveedor_id'      => 'required',
            'detalles'          => 'required',
            'cuotas'            => 'required_if:forma_pago,"Crédito"',
            'plazo'             => 'required_if:forma_pago,"Crédito"',
            'referencia'        => 'required',
            'usuario_id'        => 'required',
            'empresa_id'        => 'required',
        ],[
            'proveedor_id.required' => 'Debe seleccionar un proveedor'
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

                $det['compra_id'] = $compra->id;
                
                $detalle->fill($det);
                
                if (!isset($det['id'])) {
                    // Actualizar inventario
                    $producto = Producto::findOrFail($det['producto_id']);
                    $inventario = Inventario::where('producto_id', $producto->id)->where('bodega_id', $compra->bodega_id)->first();

                    if ($inventario) {
                        $inventario->stock += $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($compra, $det['cantidad']);
                    }
                    $producto->costo_anterior   = $producto->costo;
                    $producto->costo            = isset($det['costo']) ? $det['costo'] : $producto->costo ;
                    $producto->save();

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

        $compras = Compra::where('proveedor_id', $id)->orderBy('estado', 'asc')->paginate(10);

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


}
