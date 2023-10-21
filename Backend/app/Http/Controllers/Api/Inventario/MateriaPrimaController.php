<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto as MateriaPrima;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as DetalleVenta;

class MateriaPrimaController extends Controller
{
    

    public function index() {
       
        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->with('inventarios', 'sucursales')
                                ->orderBy('id','desc')->paginate(10);

        return Response()->json($materiaPrima, 200);

    }

    public function list() {
       
        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->orderby('nombre')->get();

        return Response()->json($materiaPrima, 200);

    }


    public function porCodigo($codigo) {
       
        // $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->where('codigo', $codigo )->with('inventarios')->first();
        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->where('codigo', $codigo )->with('inventarios')->get();

        return Response()->json($materiaPrima, 200);

    }

    public function read($id) {

        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->where('id', $id)->with('inventarios')->first();
        return Response()->json($materiaPrima, 200);

    }

    public function search($txt) {

        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->with('inventarios')
                                ->where('nombre', 'like' ,'%' . $txt . '%')
                                ->paginate(10);
        return Response()->json($materiaPrima, 200);

    }

    public function filter(Request $request) {

            $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->with('inventarios')
                                ->when($request->categoria_id, function($query) use ($request){
                                    return $query->where('categoria_id', $request->categoria_id);
                                })
                                ->when($request->stock_bodega, function($query) use ($request){
                                    return $query->where('inventario', true)->whereHas('inventarios', function($query){
                                        return $query->where('bodega_id', 1)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->when($request->stock_venta, function($query) use ($request){
                                    return $query->where('inventario', true)->whereHas('inventarios', function($query){
                                        return $query->where('bodega_id', 2)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->when($request->sin_control_inventario, function($query) use ($request){
                                    // return $query->whereDoesntHave('inventarios')->orwhere('inventario', false);
                                    return $query->where('inventario', false);
                                })
                                ->when($request->sin_condigo, function($query) use ($request){
                                    return $query->whereNull('codigo');
                                })
                                ->orderBy('id','desc')->paginate(100000);

            return Response()->json($materiaPrima, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'    => 'required|max:255',
            // 'codigo'     => 'sometimes|unique:productos,codigo,'. $request->id,
            'empresa_id'    => 'required',
        ]);

        if($request->id)
            $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->findOrFail($request->id);
        else
            $materiaPrima = new MateriaPrima;
        
        $materiaPrima->fill($request->all());
        $materiaPrima->save();

        return Response()->json($materiaPrima, 200);

    }

    public function delete($id)
    {
        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->findOrFail($id);
        foreach ($materiaPrima->inventarios as $bodega) {
            $bodega->delete();
        }
        $materiaPrima->delete();

        return Response()->json($materiaPrima, 201);

    }

    public function precios($id)
    {
        $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->findOrFail($id);
        
        
        $ventas = DetalleVenta::where('producto_id', $materiaPrima->id)->get();

        $ventas_precios =  collect();
        $ventas_fechas =  collect();

        foreach ($ventas->unique('precio') as $venta) {
            $ventas_precios->push($venta->precio);
            $ventas_fechas->push($venta->created_at->format('d/m/Y'));
        }
        $materiaPrima->ventas_precios = $ventas_precios;
        $materiaPrima->ventas_fechas = $ventas_fechas;
        $materiaPrima->ventas = count($ventas);

        return Response()->json($materiaPrima, 201);

    }

    public function analisis(Request $request) {


            $materiaPrima = MateriaPrima::where('tipo', 'Materia Prima')->when($request->nombre, function($query) use ($request){
                                        return $query->where('nombre', 'like' ,'%' . $request->nombre . '%');
                                    })
                                    ->when($request->categoria_id, function($query) use ($request){
                                        return $query->where('categoria_id', $request->categoria_id);
                                    })

                                    ->get();

            $movimientos = collect();

            $empresa = Empresa::find(1);

            foreach ($materiaPrima as $materiaPrima) {
                if ($empresa->valor_inventario == 'Promedio') {
                    $materiaPrima->costo = $materiaPrima->costo_promedio;
                }
                $utilidad = $materiaPrima->precio - $materiaPrima->costo;
                $margen = $materiaPrima->precio > 0 ? (round($utilidad / $materiaPrima->costo, 2) * 100) : null;
                $movimientos->push([
                    'nombre'        => $materiaPrima->nombre,
                    'proveedor'     => $materiaPrima->proveedor,
                    'precio'        => $materiaPrima->precio,
                    'costo'         => $materiaPrima->costo,
                    'costo_anterior'         => $materiaPrima->costo_anterior,
                    'utilidad'      => $utilidad,
                    'margen'        =>  $margen
                ]);
            }

            return Response()->json($movimientos, 200);
    }

    public function compras(Request $request, $id) {

        $compras = Compra::whereHas('detalles', function($q) use ($id) {
                                    $q->where('producto_id', $id);
                                })
                                ->orderBy('id','desc')->paginate(10);
        

        return Response()->json($compras, 200);

    }

}
