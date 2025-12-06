<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Admin\Bomba;
use App\Models\Admin\Tanque;
use App\Http\Requests\Compras\Detalles\StoreDetalleCompraRequest;

class DetallesController extends Controller
{
    

    public function index() {
       
        $detalles = Detalle::orderBy('created_at','desc')->paginate(10);

        return Response()->json($detalles, 200);

    }


    public function read($id) {
       
        $detalle = Detalle::findOrFail($id);
        return Response()->json($detalle, 200);

    }


    public function store(StoreDetalleCompraRequest $request)
    {
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

        // Actualizar producto
            $producto = Producto::findOrFail($request->producto_id);

            // Si es gasolina aumentar tanque
            if ($producto->categoria_id == 1) {
                $tanque = Tanque::findOrFail($request->tanque_id);
                $tanque->stock += $request->cantidad;
                $tanque->save();
            // Si es producto aumentar bodega
            } else {
                $bodega = Inventario::where('bodega_id', $request->bodega_id)->where('producto_id',$producto->id)->first();
                $bodega->stock += $request->cantidad;
                $bodega->save();
            } 
            if ($request->precio) {
                $producto->precio = $request->precio;
            }
            $producto->costo_anterior = $producto->costo;
            $producto->costo = $request->costo;
            $producto->save();
        }
        
        $detalle->fill($request->all());
        $detalle->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

    public function historial(Request $request) {

        $compras = Detalle::whereHas('compra', function($query) use ($request){
                            $query->where('estado', 'Pagada')
                            ->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->nombre, function($query) use ($request){
                            return $query->whereHas('producto', function($q) use ($request){
                                $q->where('nombre', 'like' ,'%' . $request->nombre . '%');
                            });
                        })
                        ->when($request->categoria_id, function($query) use ($request){
                            return $query->whereHas('producto', function($q) use ($request){
                                $q->where('categoria_id', $request->categoria_id );
                            });
                        })
                        ->get()
                        ->groupBy('producto_id');

        
        $movimientos = collect();

        foreach ($compras as $compra) {
            $movimientos->push([
                'fecha'         => $compra[sizeof($compra) - 1]->compra->fecha,
                'producto'      => $compra[0]->producto_nombre,
                'medida'        => $compra[0]->medida,
                'cantidad'      => $compra->sum('cantidad'),
                'subtotal'      => $compra->sum('subtotal'),
                'iva'           => $compra->sum('iva'),
                'total'         => $compra->sum('total'),
                'detalles'      => $compra
            ]);
        }

        return Response()->json($movimientos, 200);

    }

}
