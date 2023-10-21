<?php

namespace App\Http\Controllers\Api\Transporte\Mantenimientos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Transporte\Mantenimientos\Detalle;

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


    public function store(Request $request)
    {
        $request->validate([
            'producto_id'    => 'required',
            'cantidad'    => 'required',
            'precio'    => 'required',
            'costo'    => 'required',
            'venta_id'    => 'required'
        ]);
        
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

        // Actualizar inventario
            $producto = Producto::findOrFail($request->producto_id);
            if ($producto->inventario) {
                // Si es gasolina disminuir tanque
                if ($producto->categoria_id == 1) {
                    $bomba = Bomba::findOrFail($request->bomba_id);
                    
                    $tanque = Tanque::findOrFail($bomba->tanque_id);
                    $tanque->stock -= $request->cantidad;
                    $bomba->lectura += $request->cantidad;
                    $bomba->save();
                    $tanque->save();
                // Si es producto disminuir bodega
                } else {
                    $bodega = Inventario::where('bodega_id', 2)->where('producto_id',$producto->id)->first();
                    $bodega->stock -= $request->cantidad;
                    $bodega->save();
                }            
            }
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

        $ventas = Detalle::whereHas('venta', function($query) use ($request){
                            $query->where('estado', 'Cobrada')
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

        foreach ($ventas as $venta) {
            $ventaTotal = $venta->sum('total');
            $costoTotal = $venta->sum('subcosto');
            $movimientos->push([
                'fecha'         => $venta[0]->venta->fecha,
                'nombre_producto'      => $venta[0]->nombre_producto,
                'medida'        => $venta[0]->medida,
                'precio'        => $venta[0]->precio,
                'costo'        => $venta[0]->costo,
                'cantidad'      => $venta->sum('cantidad'),
                'total'         => $ventaTotal,
                'costo'         => $costoTotal,
                'utilidad'      => $ventaTotal - $costoTotal,
                'margen'        => $ventaTotal > 0 ? round((($ventaTotal - ($costoTotal)) / $ventaTotal * 100), 2) : null
            ]);
        }

        $movimientos = $movimientos->sortBy('nombre_producto')->values()->all();

        return Response()->json($movimientos, 200);

    }


}
