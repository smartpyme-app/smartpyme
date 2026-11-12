<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Compra;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Inventario;
use Illuminate\Support\Facades\Crypt;

use App\Exports\ConsignasExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Inventario\Consignas\StoreAjusteRequest;
use App\Services\Inventario\ConsignaDisponibleService;

class ConsignasController extends Controller
{
    

    public function index(Request $request)
    {
        $todos = $this->coleccionProductosEnConsigna($request);
        $perPage = min(max((int) ($request->input('paginate', 25)), 1), 100);
        $page = max((int) ($request->input('page', 1)), 1);
        $total = $todos->count();
        $slice = $todos->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $slice,
            'current_page' => $page,
            'last_page' => max((int) ceil($total / $perPage), 1),
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total === 0 ? null : ($page - 1) * $perPage + 1,
            'to' => $total === 0 ? null : min($page * $perPage, $total),
        ], 200);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function coleccionProductosEnConsigna(Request $request)
    {
        $detallesDeVenta = DetalleVenta::query()
            ->whereHas('venta', fn ($q) => $q->where('estado', 'Consigna')->where('cotizacion', 0))
            ->when($request->filled('buscador'), function ($query) use ($request) {
                $t = trim((string) $request->buscador);
                $query->where(function ($w) use ($t) {
                    $w->whereHas('venta', function ($v) use ($t) {
                        $v->where(function ($vq) use ($t) {
                            $vq->where('correlativo', 'like', '%' . $t . '%');
                            if (ctype_digit($t)) {
                                $vq->orWhere('correlativo', (int) $t);
                            }
                            $vq->orWhere('nombre_cliente', 'like', '%' . $t . '%')
                                ->orWhereHas('documento', fn ($d) => $d->where('nombre', 'like', '%' . $t . '%'));
                        });
                    })->orWhereHas('producto', function ($p) use ($t) {
                        $p->where('nombre', 'like', '%' . $t . '%')
                            ->orWhere('codigo', 'like', '%' . $t . '%');
                    });
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $cat = trim((string) $request->categoria);
                if ($cat !== '') {
                    $query->whereHas('producto.categoria', fn ($q) => $q->where('nombre', $cat));
                }
            })
            ->with(['producto.categoria', 'venta.documento'])
            ->get()
            ->groupBy('id_producto');

        $detalles = collect();

        foreach ($detallesDeVenta as $detallesGroup) {
            $ventas = collect();

            foreach ($detallesGroup as $detalle) {
                $v = $detalle->venta;
                $doc = $v->documento?->nombre ?? $v->nombre_documento ?? 'Documento';
                $factura = $v->correlativo ? $doc . ' #' . $v->correlativo : $doc;
                $ventas->push([
                    'fecha' => $v->fecha,
                    'cliente' => $v->nombre_cliente,
                    'cantidad' => $detalle->cantidad,
                    'id' => $v->id,
                    'nombre_documento' => $doc,
                    'correlativo' => $v->correlativo,
                    'factura' => $factura,
                    'fecha_pago' => $v->fecha_pago,
                    'uuid' => Crypt::encrypt($v->id),
                ]);
            }

            $producto = $detallesGroup[0]->producto;
            if ($producto) {
                $detalles->push([
                    'id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'img' => $producto->img,
                    'nombre_categoria' => $producto->nombre_categoria,
                    'precio' => $detallesGroup[0]->precio,
                    'codigo' => $producto->codigo,
                    'stock' => $detallesGroup->sum('cantidad'),
                    'ventas' => $ventas->values(),
                ]);
            }
        }

        return $detalles->sortBy('nombre')->values();
    }


    public function read($id) {

        $ajuste = Ajuste::findOrFail($id);
        return Response()->json($ajuste, 200);

    }

    public function filter(Request $request) {

        $ajustes = Ajuste::when($request->fecha_fin, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->fecha_ini, $request->fecha_fin]);
                            })
                            ->when($request->id_sucursal, function($query) use ($request){
                                return $query->whereHas('inventario', function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal);
                                });
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($ajustes, 200);
    }


    public function store(StoreAjusteRequest $request)
    {

        if($request->id)
            $ajuste = Ajuste::findOrFail($request->id);
        else
            $ajuste = new Ajuste;

        $ajuste->fill($request->all());
        $ajuste->save(); 

        // Actualizar inventario
                        
            $inventario = Inventario::where('id_sucursal', $request['id_sucursal'])->where('id_producto', $ajuste->id_producto)->first();
            if ($inventario) {
                $inventario->stock += $request->ajuste;
                $inventario->save();
                $inventario->kardex($ajuste, $request->ajuste);
            }


        return Response()->json($ajuste, 200);

    }

    public function delete($id)
    {
        $ajuste = Ajuste::findOrFail($id);
        $ajuste->delete();

        return Response()->json($ajuste, 201);

    }


    public function search($txt) {

        $ajustes = Ajuste::whereHas('producto', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orWhere('codigo', 'like' ,'%' . $txt . '%');
                            })
                            ->orwhereHas('bodega', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%');
                            })
                            ->paginate(10);

        return Response()->json($ajustes, 200);

    }

    public function export(Request $request){
        $consignas = new ConsignasExport();
        $consignas->filter($request);

        return Excel::download($consignas, 'productos-en-consigna.xlsx');
    }

    public function ventasPorProducto(int $idProducto)
    {
        $request = request();
        $rows = $this->coleccionProductosEnConsigna($request)
            ->firstWhere('id', $idProducto);

        if (! $rows) {
            return response()->json(['ventas' => [], 'stock' => 0], 200);
        }

        return response()->json([
            'id' => $rows['id'],
            'nombre' => $rows['nombre'],
            'stock' => $rows['stock'],
            'ventas' => $rows['ventas'] ?? [],
        ], 200);
    }

    public function indexCompras()
    {
        $compras = Compra::query()
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->with([
                'proveedor',
                'bodega:id,nombre',
                'sucursal:id,nombre',
                'detalles.producto:id,nombre,codigo',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $rows = $compras->map(function (Compra $compra) {
            return [
                'id' => $compra->id,
                'uuid' => Crypt::encrypt($compra->id),
                'fecha' => $compra->fecha,
                'proveedor' => $compra->nombre_proveedor,
                'id_proveedor' => $compra->id_proveedor,
                'tipo_documento' => $compra->tipo_documento,
                'referencia' => $compra->referencia,
                'fecha_pago' => $compra->fecha_pago,
                'estado' => $compra->estado,
                'bodega' => $compra->bodega?->nombre ?? '',
                'sucursal' => $compra->sucursal?->nombre ?? '',
                'total' => $compra->total,
                'detalles' => $compra->detalles->map(function ($detalle) {
                    return [
                        'id' => $detalle->id,
                        'id_producto' => $detalle->id_producto,
                        'producto' => $detalle->producto?->nombre,
                        'codigo' => $detalle->producto?->codigo,
                        'cantidad' => $detalle->cantidad,
                        'costo' => $detalle->costo,
                        'total' => $detalle->total,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($rows, 200);
    }

    public function exportCompras(Request $request){
        $consignas = new \App\Exports\ConsignasComprasExport();
        $consignas->filter($request);

        return Excel::download($consignas, 'consignas-compras.xlsx');
    }

    public function disponible(Request $request, ConsignaDisponibleService $consignaDisponibleService)
    {
        $request->validate([
            'id_producto' => 'required|integer',
            'id_bodega' => 'required|integer',
            'excluir_venta_id' => 'nullable|integer',
        ]);

        $disponible = $consignaDisponibleService->obtenerResumenStock(
            (int) $request->id_producto,
            (int) $request->id_bodega,
            $request->filled('excluir_venta_id') ? (int) $request->excluir_venta_id : null
        );

        return response()->json($disponible, 200);
    }

    public function ventasConsignaCompra(Request $request, ConsignaDisponibleService $consignaDisponibleService)
    {
        $request->validate([
            'id_producto' => 'required|integer',
            'id_bodega' => 'required|integer',
            'excluir_venta_id' => 'nullable|integer',
        ]);

        $excluirVentaId = $request->filled('excluir_venta_id') ? (int) $request->excluir_venta_id : null;
        $idProducto = (int) $request->id_producto;
        $idBodega = (int) $request->id_bodega;

        return response()->json([
            'cantidad_vendida' => round(
                $consignaDisponibleService->cantidadVendidaDesdeConsignaCompra($idProducto, $idBodega, $excluirVentaId),
                4
            ),
            'ventas' => $consignaDisponibleService->listarVentasDesdeConsignaCompra($idProducto, $idBodega, $excluirVentaId),
        ], 200);
    }


}
