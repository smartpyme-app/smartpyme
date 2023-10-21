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

class TrasladosController extends Controller
{
    

    public function index() {
       
        $traslados = Traslado::orderBy('id','desc')->with('origen', 'destino')->paginate(7);

        return Response()->json($traslados, 200);

    }


    public function read($id) {

        $traslado = Traslado::where('id', $id)->with('detalles')->firstOrFail();
        return Response()->json($traslado, 200);

    }
    
    public function search($txt) {

        $traslados = Traslado::whereHas('cliente', function($query) use ($txt) {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })->paginate(7);

        return Response()->json($traslados, 200);

    }

    public function filter(Request $request) {


        $traslados = Traslado:://whereBetween('created_at', [$star, $end])
                            when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                            })
                            ->when($request->usuario_id, function($query) use ($request){
                                return $query->where('usuario_id', $request->usuario_id);
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('estado', $request->estado);
                            })
                            ->when($request->tipo, function($query) use ($request){
                                return $query->where('origen_id', $request->tipo);
                            })
                            ->with('origen', 'destino')
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($traslados, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required',
            'estado'        => 'required',
            'origen_id'     => 'required|numeric',
            'destino_id'    => 'required|numeric',
            'detalles'     => 'required',
            'usuario_id'    => 'required|numeric'
        ]);

        if($request->id)
            $traslado = Traslado::findOrFail($request->id);
        else
            $traslado = new Traslado;

        $traslado->fill($request->all());
        $traslado->save();

        // Detalles
        foreach ($request->detalles as $i => $value) {
            if (!isset($value['id'])) {
                $detalle = new Detalle;
                $value['traslado_id'] = $traslado->id;
                $detalle->fill($value);
                $detalle->save();
            }
        }

        // Afectar Inventario
        if ($request->estado == 'Aprobado') {
            foreach ($request->detalles as $i => $value) {
                // Actualizar inventario
                    $producto = Producto::findOrFail($value['producto_id']);

                    // Disminuir origen
                    $origen = Inventario::where('producto_id', $producto->id)->where('bodega_id', $traslado->origen_id)->first();
                    $origen->stock -= $value['cantidad'];
                    $origen->save();
                    $origen->kardex($traslado, $value['cantidad'] * -1);

                    // Aumentar destino
                    $destino = Inventario::where('producto_id', $producto->id)->where('bodega_id', $traslado->destino_id)->first();
                    $destino->stock += $value['cantidad'];
                    $destino->save();
                    $destino->kardex($traslado, $value['cantidad']);


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


    public function requisicion($origen_id, $destino_id){

        $productos = Producto::with('inventarios')->whereHas('inventarios', function($q) use($destino_id){
                                    $q->where('bodega_id', $destino_id)->whereRaw('stock < stock_max');
                                })->get();
        
        $traslados = collect();

        foreach ($productos as $producto) {

            $origen = $producto->inventarios()->where('bodega_id', $origen_id)->first();
            $destino = $producto->inventarios()->where('bodega_id', $destino_id)->first();

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
                'producto_id'       => $producto->id,
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

        $productos = Producto::where('categoria_id', '!=', 1)->with('inventarios')->whereHas('inventarios', function($q){
                                    $q->where('bodega_id', 1)->whereRaw('stock < stock_max');
                                })->get();
        
        $traslados = collect();

        foreach ($productos as $producto) {

            $bodega = $producto->inventarios()->where('bodega_id', 1)->first();
            $Bventa = $producto->inventarios()->where('bodega_id', 2)->first();

            $cantidad = $bodega->stock_max - $bodega->stock;
            if ($cantidad > $Bventa->stock) {
                $cantidad = $Bventa->stock;
                $disponible = false;
            }else{
                $disponible = true;
            }


            $traslados->push([
                'producto_id'       => $producto->id,
                'disponible'        => $disponible,
                'proveedor'         => $producto->proveedor_id,
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

        $productos = Producto::where('categoria_id', '!=', 1)->with('inventarios', 'categoria')
                                ->when($request->categoria_id, function($query) use ($request){
                                    return $query->whereHas('categoria', function($query) use ($request){
                                        return $query->where('categoria_id', $request->categoria_id);
                                    });
                                })
                                ->whereHas('inventarios', function($q){
                                    $q->where('bodega_id', 1)->whereRaw('stock < stock_max');
                                })->get();
        
        $traslados = collect();

        foreach ($productos as $producto) {

            $bodega = $producto->inventarios()->where('bodega_id', 1)->first();

            if ($request->proveedor_id) {

                if ($request->proveedor_id == $producto->proveedor_id) {
                    $traslados->push([
                        'producto_id'       => $producto->id,
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
                    'producto_id'       => $producto->id,
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
