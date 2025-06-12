<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Compras\Detalle as DetalleCompra;
use Illuminate\Support\Facades\Crypt;

use App\Exports\ConsignasExport;
use Maatwebsite\Excel\Facades\Excel;

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


    public function store(Request $request)
    {
        $request->validate([
            'id_producto'       => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
            'stock_actual'      => 'required|numeric',
            'stock_real'        => 'required|numeric',
            'ajuste'            => 'required|numeric',
            'concepto'          => 'required|max:255',
            'id_empresa'        => 'required|numeric',
            'id_usuario'        => 'required|numeric',
        ]);

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


}
