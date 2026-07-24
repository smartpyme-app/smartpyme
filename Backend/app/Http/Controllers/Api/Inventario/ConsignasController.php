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
    

    public function index() {

        $detallesDeVenta = DetalleVenta::whereHas('venta', function($query){
                                $query->where('estado', 'Consigna');
                            })
                            ->with('producto.categoria', 'venta')
                            ->get()
                            ->groupBy('id_producto');


        $detalles = collect();

        foreach ($detallesDeVenta as $detallesGroup) {
            $ventas = collect();
            
            foreach ($detallesGroup as $detalle) {
                $ventas->push([
                    'fecha'         => $detalle->venta->fecha,
                    'cliente'       => $detalle->venta->nombre_cliente,
                    'cantidad'      => $detalle->cantidad,
                    'id'            => $detalle->venta->id,
                    'nombre_documento'            => $detalle->venta->nombre_documento,
                    'correlativo'            => $detalle->venta->correlativo,
                    'fecha_pago'    => $detalle->venta->fecha_pago,
                    'uuid'          => Crypt::encrypt($detalle->venta->id)
                ]);
            }
            $producto = $detallesGroup[0]->producto()->first();

            if ($producto) {
                $detalles->push([
                    'id'                 => $producto->id,
                    'nombre'             => $producto->nombre,
                    'img'                => $producto->img,
                    'nombre_categoria'   => $producto->nombre_categoria,
                    'precio'             => $detallesGroup[0]->precio,
                    'codigo'             => $producto->codigo,
                    'stock'              => $detallesGroup->sum('cantidad'),
                    'ventas'             => $ventas,
                ]); 
            }
        }

        return Response()->json($detalles, 200);
    
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

        return Excel::download($consignas, 'consignas.xlsx');
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
