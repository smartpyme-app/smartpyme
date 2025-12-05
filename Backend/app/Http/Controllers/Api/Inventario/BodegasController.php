<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use JWTAuth;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Inventario\StoreBodegaRequest;

class BodegasController extends Controller
{
    //Este es el index de modulo de bodegas
    public function index(Request $request)
    {
            $bodegas = Bodega::when($request->estado !== null, function ($q) use ($request) {
                $q->where('activo', !!$request->estado);
            })
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function ($query) use ($request) {
                $buscador = $request->buscador;
                $query->where(function($q) use ($buscador) {
                    $q->where('nombre', 'like', '%' . $buscador . '%')
                      ->orWhereHas('sucursal', function($s) use ($buscador) {
                          $s->where('nombre', 'like', '%' . $buscador . '%');
                      });
                });
            })
            ->orderBy($request->orden, $request->direccion)
            ->paginate($request->paginate);

        return Response()->json($bodegas, 200);
    }

    public function list()
    {

        $bodegas = Bodega::orderby('nombre')
            ->where('activo', true)
            ->get();

        return Response()->json($bodegas, 200);
    }


    public function read($id)
    {

        $bodega = Bodega::where('id', $id)->firstOrFail();
        return Response()->json($bodega, 200);
    }

    public function store(StoreBodegaRequest $request)
    {

        if ($request->id)
            $bodega = Bodega::findOrFail($request->id);
        else
            $bodega = new Bodega;

        $bodega->fill($request->all());
        $bodega->save();

        // Configurar inventarios para los productos de forma eficiente
        if (!$request->id) {
            // Obtener todos los IDs de productos de la empresa de una sola vez
            $productoIds = DB::table('productos')
                ->whereIn('tipo', ['Producto', 'Compuesto'])
                ->where('id_empresa', $request->id_empresa)
                ->pluck('id')
                ->toArray();

            // Filtrar productos que ya tienen inventario en esta bodega para evitar procesamiento innecesario
            if (!empty($productoIds)) {
                $existingInventoryIds = DB::table('inventario')
                    ->where('id_bodega', $bodega->id)
                    ->whereIn('id_producto', $productoIds)
                    ->pluck('id_producto')
                    ->toArray();

                $productoIds = array_diff($productoIds, $existingInventoryIds);
            }

            if (!empty($productoIds)) {
                $batchSize = 500;
                $batches = array_chunk($productoIds, $batchSize);
                $now = now()->format('Y-m-d H:i:s');

                foreach ($batches as $batch) {
                    $values = [];
                    $placeholders = [];

                    foreach ($batch as $productoId) {
                        $placeholders[] = "(?, ?, ?, ?, ?)";
                        $values[] = $bodega->id;
                        $values[] = 0; // stock inicial
                        $values[] = $productoId;
                        $values[] = $now; // created_at
                        $values[] = $now; // updated_at
                    }

                    if (!empty($placeholders)) {
                        $placeholdersString = implode(', ', $placeholders);
                        // Usar INSERT IGNORE para evitar duplicados en caso de que ya existan registros
                        DB::statement(
                            "INSERT IGNORE INTO inventario (id_bodega, stock, id_producto, created_at, updated_at) VALUES " .
                                $placeholdersString,
                            $values
                        );
                    }
                }
            }
        }
        return Response()->json($bodega, 200);
    }

    public function delete($id)
    {

        $bodega = Bodega::findOrFail($id);
        if ($bodega->productos()->count() > 0)
            return  Response()->json(['message' => 'La bodega tiene productos', 'code' => 402], 402);
        $bodega->delete();

        return Response()->json($bodega, 201);
    }

    public function reporte($id, $cat)
    {

        $bodega = Bodega::where('id', $id)->firstOrFail();
        $productos = [];

        if ($cat != 'undefined') {
            $subcategorias = explode(',', $cat);
        } else {
            $subcategorias = null;
        }

        $productos = $bodega->productos()->with('producto')
            ->when($subcategorias, function ($query) use ($subcategorias) {
                $query->whereHas('producto', function ($query) use ($subcategorias) {
                    return $query->whereIn('subcategoria_id', $subcategorias);
                });
            })->get();

        $bodega->fecha = Carbon::now();
        $bodega->usuario = JWTAuth::parseToken()->authenticate()->name;
        $empresa = Empresa::find(1);

        $p = collect();


        foreach ($productos as $producto) {
            $prod = $producto->producto()->first();
            $stock = $prod->inventarios()->where('bodega_id', $id)->pluck('stock')->first();
            $p->push([
                'nombre'     => $prod->nombre,
                'categoria'     => $prod->nombre_categoria,
                'subcategoria'  => $prod->nombre_subcategoria,
                'stock'         => $stock,
                'costo'         => $prod->costo,
                'costoTotal'    => $prod->costo * $stock,
                'precio'        => $prod->precio,
                'precioTotal'   => $prod->precio * $stock,
            ]);
        }

        $bodega->productos = $p->sortBy([['categoria', 'asc'], ['nombre', 'asc']]);
        // return $bodega;
        $reportes = \PDF::loadView('reportes.inventario.bodegas', compact('bodega', 'empresa'));
        return $reportes->stream();
    }
}
