<?php

namespace App\Http\Controllers\Api\Inventario\Traslados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Traslados\Traslado;
use App\Models\Inventario\Traslados\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Inventario;
use App\Models\Admin\Empresa;
use App\Http\Requests\Inventario\Traslados\StoreTrasladoRequest;

class TrasladosController extends Controller
{
    

    public function index(Request $request) {
       
        $traslados = Traslado::with('detalles')->when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->whereHas('origen', function($q) use ($request){
                                    $q->where('id_bodega_de', $request->id_bodega_de);
                                });
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->whereHas('destino', function($q) use ($request){
                                    $q->where('id_bodega', $request->id_bodega_para);
                                });
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                });
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden, $request->direccion)
                            ->paginate($request->paginate);


        return Response()->json($traslados, 200);
    }


    public function read($id) {

        $traslado = Traslado::where('id', $id)->with('detalles')->firstOrFail();
        return Response()->json($traslado, 200);

    }


    public function store(StoreTrasladoRequest $request)
    {

        if($request->id)
            $traslado = Traslado::findOrFail($request->id);
        else
            $traslado = new Traslado;

        $traslado->fill($request->all());
        $traslado->save();

        // Detalles
        foreach ($request->detalles as $det) {
            if(isset($det['id']))
                $detalle = Detalle::findOrFail($det['id']);
            else
                $detalle = new Detalle;

            $det['id_traslado'] = $traslado->id;
            $detalle->fill($det);
            $detalle->save();
        }

        // Afectar Inventario
        if ($request->estado == 'Confirmado') {
            foreach ($request->detalles as $i => $detalle) {
                // Actualizar inventario
                    $producto = Producto::findOrFail($detalle['id_producto']);

                    // Disminuir origen
                    $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega_de)->first();
                    $origen->stock -= $detalle['cantidad'];
                    $origen->save();
                    $origen->kardex($traslado, $detalle['cantidad'] * -1);

                    // Aumentar destino
                    $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega)->first();
                    $destino->stock += $detalle['cantidad'];
                    $destino->save();
                    $destino->kardex($traslado, $detalle['cantidad']);


            }
        }


        return Response()->json($traslado, 200);

    }

    public function delete($id)
    {
        $traslado = Traslado::findOrFail($id);
        $traslado->delete();

        return Response()->json($traslado, 201);

    }

    public function generarDoc($id) {

        $traslado = Traslado::where('id', $id)->with('detalles', 'origen', 'destino')->firstOrFail();
        $empresa = Empresa::find(1);

        $reportes = \PDF::loadView('reportes.inventario.traslado', compact('traslado', 'empresa'));
        return $reportes->stream();

    }


    public function requisicion($id_bodega_de, $id_bodega){

        $productos = Producto::with('inventarios')->whereHas('inventarios', function($q) use($id_bodega){
                                    $q->where('id_bodega', $id_bodega)->whereRaw('stock < stock_max');
                                })->get();
        
        $traslados = collect();

        foreach ($productos as $producto) {

            $origen = $producto->inventarios()->where('id_bodega', $id_bodega_de)->first();
            $destino = $producto->inventarios()->where('id_bodega', $id_bodega)->first();

            if ($destino->stock >= $destino->stock_max) {
                $cantidad = 0;
            }else{
                $cantidad = $destino->stock_max - $destino->stock;
            }

            if ($cantidad >= $origen->stock) {
                $cantidad = $origen->stock;
                $disponible = false;
            }else{
                $disponible = true;
            }

            $traslados->push([
                'id_producto'       => $producto->id,
                'disponible'        => $disponible,
                'existencia'        => $origen->stock,
                'stock'             => $destino->stock,
                'stock_min'         => $destino->stock_min,
                'stock_max'         => $destino->stock_max,
                'cantidad'          => $cantidad,
                'nombre_producto'          => $producto->nombre,
                'medida'            => $producto->medida,
                'nombre_categoria'         => $producto->categoria()->first()->nombre,
            ]);
        }


        return Response()->json($traslados, 201);

    }

    public function bodega(){

        $productos = Producto::where('id_categoria', '!=', 1)->with('inventarios')->whereHas('inventarios', function($q){
                                    $q->where('id_bodega', 1)->whereRaw('stock < stock_max');
                                })->get();
        
        $traslados = collect();

        foreach ($productos as $producto) {

            $bodega = $producto->inventarios()->where('id_bodega', 1)->first();
            $Bventa = $producto->inventarios()->where('id_bodega', 2)->first();

            $cantidad = $bodega->stock_max - $bodega->stock;
            if ($cantidad > $Bventa->stock) {
                $cantidad = $Bventa->stock;
                $disponible = false;
            }else{
                $disponible = true;
            }


            $traslados->push([
                'id_producto'       => $producto->id,
                'disponible'        => $disponible,
                'proveedor'         => $producto->id_proveedor,
                'existencia'        => $Bventa->stock,
                'stock'             => $bodega->stock,
                'stock_min'         => $bodega->stock_min,
                'stock_max'         => $bodega->stock_max,
                'cantidad'          => $cantidad,
                'producto'          => $producto->nombre,
                'medida'            => $producto->medida,
                'categoria'         => $producto->categoria()->first()->nombre,
            ]);
        }


        return Response()->json($traslados, 201);

    }

    public function bodegaFiltrar(Request $request){

        $productos = Producto::where('id_categoria', '!=', 1)->with('inventarios', 'categoria')
                                ->when($request->id_categoria, function($query) use ($request){
                                    return $query->whereHas('categoria', function($query) use ($request){
                                        return $query->where('id_categoria', $request->id_categoria);
                                    });
                                })
                                ->whereHas('inventarios', function($q){
                                    $q->where('id_bodega', 1)->whereRaw('stock < stock_max');
                                })->get();
        
        $traslados = collect();

        foreach ($productos as $producto) {

            $bodega = $producto->inventarios()->where('id_bodega', 1)->first();

            if ($request->id_proveedor) {

                if ($request->id_proveedor == $producto->id_proveedor) {
                    $traslados->push([
                        'id_producto'       => $producto->id,
                        'stock'             => $bodega->stock,
                        'stock_min'         => $bodega->stock_min,
                        'stock_max'         => $bodega->stock_max,
                        'cantidad'          => $bodega->stock_max - $bodega->stock,
                        'producto'          => $producto->nombre,
                        'medida'            => $producto->medida,
                        'categoria'         => $producto->categoria()->first()->nombre,
                    ]);
                }
                
            }else{
                $traslados->push([
                    'id_producto'       => $producto->id,
                    'stock'             => $bodega->stock,
                    'stock_min'         => $bodega->stock_min,
                    'stock_max'         => $bodega->stock_max,
                    'cantidad'          => $bodega->stock_max - $bodega->stock,
                    'producto'          => $producto->nombre,
                    'medida'            => $producto->medida,
                    'categoria'         => $producto->categoria()->first()->nombre,
                ]);
            }
        }


        return Response()->json($traslados, 201);

    }



}
