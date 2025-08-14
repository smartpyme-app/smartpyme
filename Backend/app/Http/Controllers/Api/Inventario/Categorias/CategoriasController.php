<?php

namespace App\Http\Controllers\Api\Inventario\Categorias;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Compras\Detalle as DetalleCompra;

use App\Imports\Categorias;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CategoriasController extends Controller
{

    public function index(Request $request)
    {
        try {
            $categorias = Categoria::with(['cuentas' => function($q) use ($request) {
                    if ($request->id_sucursal) {
                        $q->where('id_sucursal', $request->id_sucursal);
                    }
                }])
                ->when($request->id_sucursal, function ($q) use ($request) {
                    $q->whereHas('cuentas', function ($subQ) use ($request) {
                        $subQ->where('id_sucursal', $request->id_sucursal);
                    });
                })
                ->when($request->nombre, function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->nombre . '%');
                })
                ->when($request->buscador, function ($q) use ($request) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('nombre', 'like', '%' . $request->buscador . '%')
                                ->orWhere('descripcion', 'like', '%' . $request->buscador . '%');
                    });
                })
                ->when($request->estado !== null, function ($q) use ($request) {
                    $q->where('enable', !!$request->estado);
                })
                ->when($request->id_empresa, function ($q) use ($request) {
                    $q->where('id_empresa', $request->id_empresa);
                })
                ->orderBy('enable', 'desc')
                ->orderBy($request->orden ?? 'nombre', $request->direccion ?? 'asc')
                ->paginate($request->paginate ?? 10);

            return response()->json($categorias, 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener las categorias: ' . $e->getMessage());
            return response()->json(['error' => 'Ha ocurrido un error al obtener las categorias'], 500);
        }
    }

    public function list() {

        $categorias = Categoria::where('enable', true)
                                ->orderBy('nombre', 'asc')
                                ->get();

        return Response()->json($categorias, 200);

    }


    public function read($id) {

        $categoria = Categoria::findOrFail($request->id);
        return Response()->json($categoria, 200);

    }

    public function filter(Request $request) {

        $categorias = Categoria::when($request->estado, function($query) use ($request){
                                return $query->where('enable', $request->estado);
                            })
                            ->orderBy('nombre', 'asc')
                            ->orderBy('enable', 'desc')
                            ->get();

        return Response()->json($categorias, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'descripcion'   => 'sometimes|max:255',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $categoria = Categoria::findOrFail($request->id);
        else
            $categoria = new Categoria;

//        dd($request);

        $categoria->fill($request->all());
        $categoria->save();

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

    public function subcategorias(){

        $categorias = Categoria::where('enable', true)->where('subcategoria', 1)
            ->orderBy('nombre', 'asc')
            ->get();

        return Response()->json($categorias, 200);

    }

    public function categoriasPadre(){

        $categorias = Categoria::where('enable', true)->where('subcategoria', 0)
            ->orderBy('nombre', 'asc')
            ->get();

        return Response()->json($categorias, 200);

    }


}
