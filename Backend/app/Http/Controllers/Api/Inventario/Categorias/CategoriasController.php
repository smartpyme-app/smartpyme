<?php

namespace App\Http\Controllers\Api\Inventario\Categorias;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Compras\Detalle as DetalleCompra;

use App\Imports\Categorias;
use Maatwebsite\Excel\Facades\Excel;

class CategoriasController extends Controller
{
    
    public function index() {
       
        $categorias = Categoria::orderBy('nombre', 'asc')->get();

        return Response()->json($categorias, 200);

    }


    public function read($id) {

        $categoria = Categoria::findOrFail($request->id);
        return Response()->json($categoria, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'descripcion'   => 'sometimes|max:255',
            'empresa_id'    => 'required|numeric',
        ]);

        if($request->id)
            $categoria = Categoria::findOrFail($request->id);
        else
            $categoria = new Categoria;

        $categoria->fill($request->all());        
        $categoria->save();

        if ($request->tipo_comision) {
            foreach ($categoria->productos as $producto) {
                $producto->tipo_comision = $request->tipo_comision;
                $producto->comision = $request->comision ? $request->comision : 0;
                $producto->save();
            }
        }

        return Response()->json($categoria, 200);

    }

    public function delete($id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return Response()->json($categoria, 201);

    }


    public function historialVentas(Request $request) {

        $ventas = DetalleVenta::with('producto.categoria')
                        ->whereHas('venta', function($query) use ($request){
                            $query->where('estado', 'Pagada')
                            ->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->get()
                        ->groupBy(function($detalle) {
                            return $detalle->producto()->pluck('categoria_id')->first();
                        });
        
        $movimientos = collect();

        foreach ($ventas as $venta) {
            $movimientos->push([
                'categoria'     => $venta[0]->producto()->first() ? $venta[0]->producto()->first()->nombre_subcategoria : 'Sin categoria',
                'cantidad'      => $venta->count(),
                'total'         => $venta->sum('total'),
                'costo'         => $venta->sum('subcosto'),
                'utilidad'      => $venta->sum('total') - $venta->sum('subcosto'),
                'margen'        => $venta->sum('total') > 0 ? round((($venta->sum('total') - $venta->sum('subcosto')) / $venta->sum('total') * 100), 2) : null
            ]);
        }

        return Response()->json($movimientos, 200);

    }

    public function historialCompras(Request $request) {

        $compras = DetalleCompra::with('producto.categoria')
                        ->whereHas('compra', function($query) use ($request){
                            $query->where('estado', 'Pagada')
                            ->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->get()
                        ->groupBy(function($detalle) {
                            return $detalle->producto()->first()->categoria_id;
                        });
        
        $movimientos = collect();

        foreach ($compras as $compra) {
            $movimientos->push([
                'categoria'     => $compra[0]->producto()->first()->nombre_subcategoria,
                'cantidad'      => $compra->count(),
                'subtotal'      => $compra->sum('subtotal'),
                'iva'           => $compra->sum('iva'),
                'total'         => $compra->sum('total')
            ]);
        }

        return Response()->json($movimientos, 200);

    }


    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ]);

        $import = new Categorias();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){

      $categorias = new CategoriasExport();
      $categorias->filter($request);

      return Excel::download($categorias, 'categorias.xlsx');
    }


}
