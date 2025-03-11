<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Categorias\SubCategoria;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Bodega;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as DetalleVenta;

use App\Imports\Productos;
use App\Exports\ProductosExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
//use Auth;

class ProductosController extends Controller
{


    public function index(Request $request)
    {

        $productos = Producto::with(['inventarios' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        }, 'precios'])
            ->when($request->id_categoria, function ($query) use ($request) {
                return $query->where('id_categoria', $request->id_categoria);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orwhere('codigo', 'like', "%" . $request->buscador . "%")
                    ->orwhere('barcode', 'like', "%" . $request->buscador . "%")
                    ->orwhere('etiquetas', 'like', "%" . $request->buscador . "%")
                    ->orwhere('marca', 'like', "%" . $request->buscador . "%")
                    ->orwhere('descripcion', 'like', "%" . $request->buscador . "%");
            })
            ->when($request->sin_stock, function ($query) use ($request) {
                return $query->join('inventario', 'productos.id', '=', 'inventario.id_producto')
                    ->whereRaw('COALESCE(inventario.stock, 0) < COALESCE(inventario.stock_minimo, 0)');
            })
            ->when($request->nombre, function ($q) use ($request) {
                $q->where('nombre', $request->nombre);
            })
            ->when($request->compuestos !== null, function ($q) use ($request) {
                $q->whereHas('composiciones');
            })
            ->when($request->id_proveedor, function ($q) use ($request) {
                $q->whereHas('proveedores', function ($q) use ($request) {
                    return $q->where("id_proveedor", $request->id_proveedor);
                });
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            // ->whereNotIn('id_categoria', [1,2])
            ->orderBy('enable', 'desc')
            ->orderBy($request->orden ? $request->orden : 'nombre', $request->direccion ? $request->direccion : 'desc')
            ->paginate($request->paginate);

        return Response()->json($productos, 200);
    }

    public function list()
    {

        $productos = Producto::orderby('nombre')
            ->with('inventarios')
            ->where('enable', true)
            ->get();

        return Response()->json($productos, 200);
    }

    public function search($txt)
    {

        $productos = Producto::where('enable', true)->with('inventarios', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
            ->where(function ($q) use ($txt) {
                $q->where('nombre', 'like', "%$txt%")
                    ->orWhere('barcode', 'like', "%$txt%")
                    ->orWhere('codigo', 'like', "%$txt%")
                    ->orWhere('etiquetas', 'like', "%$txt%");
            })
            ->take(15)
            ->get();

        return Response()->json($productos, 200);
    }

    public function searchByQuery(Request $request)
    {
        $query = $request->query('query');

        $productos = Producto::where('enable', true)->with('inventarios', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
            ->where(function ($q) use ($query) {
                $q->where('nombre', 'like', "%$query%")
                    ->orWhere('barcode', 'like', "%$query%")
                    ->orWhere('codigo', 'like', "%$query%")
                    ->orWhere('etiquetas', 'like', "%$query%");
            })
            ->take(15)
            ->get();

        return Response()->json($productos, 200);
    }


    public function porCodigo($codigo)
    {

        $producto = Producto::where('codigo', $codigo)
            ->wherehas('sucursales', function ($q) {
                $q->where('sucursal_id', \JWTAuth::parseToken()->authenticate()->sucursal_id)
                    ->where('activo', true);
            })
            ->with('inventarios', 'precios')->get();

        return Response()->json($producto, 200);
    }

    public function read($id)
    {

        $producto = Producto::where('id', $id)
            ->with(
                'inventarios',
                'composiciones.compuesto',
                'composiciones.opciones',
                'precios.usuarios',
                'imagenes',
                'proveedores.proveedor'
            )
            ->firstOrFail();

        return Response()->json($producto, 200);
    }

    public function searchAll($txt)
    {

        $productos = Producto::whereIn('tipo', ['Producto', 'Repuesto'])->with('inventarios')
            ->where('nombre', 'like', '%' . $txt . '%')
            ->orwhere('codigo', 'like', '%' . $txt . '%')
            ->where('enable', true)
            ->paginate(10);
        return Response()->json($productos, 200);
    }

    public function store(Request $request)
    {
        if (empty($request->codigo)) {
            $request['codigo'] = NULL;
        }

        $request->validate([
            'nombre'            => 'required|max:255',
            'precio'            => 'required|numeric',
            'costo'             => 'required|numeric',
            'id_categoria'      => 'required',
            'id_empresa'        => 'required',
        ], [
            // 'nombre.required' => 'Agregue un nombre.',
            'id_categoria.required' => 'El campo categoria es obligatorio.',
            // 'costo.required' => 'Agregue el costo.'
        ]);

        if ($request->id) {
            $producto = Producto::findOrFail($request->id);
            $precioAnterior = $producto->precio;
            $costoAnterior = $producto->costo;
        } else {

            $producto = new Producto;
        }


        $producto->fill($request->all());
        $producto->save();


        // Configurar inventarios para las bodegas
        if (!$request->id && $producto->tipo != 'Servicio') {
            $bodegas = Bodega::all();
            foreach ($bodegas as $bodega) {
                $inventario = new Inventario;
                $inventario->id_producto    = $producto->id;
                $inventario->stock          = 0;
                $inventario->id_bodega    = $bodega->id;
                $inventario->save();
            }
        }

        if ($request->id) {
            if ($precioAnterior != $producto->precio || $costoAnterior != $producto->costo) {
                $inventarios = Inventario::where('id_producto', $producto->id)->get();

                foreach ($inventarios as $inventario) {
                    if ($inventario->stock > 0) {
                        $producto->id_usuario = Auth::id();
                        $inventario->kardex($producto, 0, $producto->precio, $producto->costo);
                    }
                }
            }
        }





        return Response()->json($producto, 200);
    }

    public function storeDesdeCompras(Request $request)
    {
        if (empty($request->codigo)) {
            $request['codigo'] = NULL;
        }

        $request->validate([
            'nombre'    => 'required|max:255',
            // 'codigo'    => 'nullable|unique:productos,codigo,'. $request->id,
            'precio'    => 'required|numeric',
            'costo'     => 'required|numeric',
            'medida'     => 'required',
            'categoria_id' => 'required',
            'subcategoria_id' => 'required',
            'empresa_id'    => 'required',
        ]);

        if ($request->id)
            $producto = Producto::where('tipo', 'Producto')->findOrFail($request->id);
        else
            $producto = new Producto;

        $producto->fill($request->all());
        $producto->save();

        $sucursales = \App\Models\Admin\Sucursal::all();

        foreach ($sucursales as $sucursal) {
            $producto_sucursal = new \App\Models\Inventario\Sucursal();
            $producto_sucursal->producto_id = $producto->id;
            // $producto_sucursal->inventario = true;
            // $producto_sucursal->bodega_venta_id = $sucursal->bodegas()->first()->id;
            $producto_sucursal->activo = true;
            $producto_sucursal->sucursal_id = $sucursal->id;
            $producto_sucursal->save();


            $inventario = new Inventario;
            $inventario->producto_id = $producto->id;
            $inventario->stock = 0;
            $inventario->stock_min = 10;
            $inventario->stock_max = 100;
            $inventario->nota = '';
            $inventario->bodega_id = $sucursal->bodegas()->first()->id;
            // $inventario->sucursal_id = $producto_sucursal->id;
            $inventario->save();
        }

        $producto = Producto::where('tipo', 'Producto')->where('id', $producto->id)->with('inventarios')->first();

        return Response()->json($producto, 200);
    }

    public function delete($id)
    {
        $producto = Producto::findOrFail($id);
        // $producto->inventarios->delete();
        // $producto->delete();
        $producto->enable = false;

        $producto->save();

        return Response()->json($producto, 201);
    }

    public function precios($id)
    {
        $producto = Producto::findOrFail($id);


        $ventas = DetalleVenta::where('producto_id', $producto->id)->get();

        $ventas_precios =  collect();
        $ventas_fechas =  collect();

        foreach ($ventas->unique('precio') as $venta) {
            $ventas_precios->push($venta->precio);
            $ventas_fechas->push($venta->created_at->format('d/m/Y'));
        }
        $producto->ventas_precios = $ventas_precios;
        $producto->ventas_fechas = $ventas_fechas;
        $producto->ventas = count($ventas);

        return Response()->json($producto, 201);
    }


    public function analisis(Request $request)
    {


        $productos = Producto::where('tipo', 'Producto')->when($request->nombre, function ($query) use ($request) {
            return $query->where('nombre', 'like', '%' . $request->nombre . '%');
        })
            ->when($request->categoria_id, function ($query) use ($request) {
                return $query->where('categoria_id', $request->categoria_id);
            })

            ->get();

        $movimientos = collect();

        $empresa = Empresa::find(1);

        foreach ($productos as $producto) {
            if ($empresa->valor_inventario == 'Promedio') {
                $producto->costo = $producto->costo_promedio;
            }
            $utilidad = $producto->precio - $producto->costo;
            $margen = $producto->costo > 0 ? (round($utilidad / $producto->costo, 2) * 100) : null;
            $movimientos->push([
                'nombre'        => $producto->nombre,
                'nombre_categoria'        => $producto->nombre_categoria,
                'nombre_subcategoria'        => $producto->nombre_subcategoria,
                // 'proveedor'     => $producto->proveedor,
                'precio'        => $producto->precio,
                'costo'         => $producto->costo,
                'utilidad'      => $utilidad,
                'margen'        =>  $margen
            ]);
        }

        return Response()->json($movimientos, 200);
    }

    public function compras(Request $request, $id)
    {

        $compras = Compra::whereHas('detalles', function ($q) use ($id) {
            $q->where('producto_id', $id);
        })
            ->orderBy('id', 'desc')->paginate(5);


        return Response()->json($compras, 200);
    }

    public function ajustes(Request $request, $id)
    {

        $ajustes = Ajuste::where('producto_id', $id)->orderBy('id', 'desc')->paginate(5);

        return Response()->json($ajustes, 200);
    }

    public function ventas(Request $request, $id)
    {

        $ventas = Venta::whereHas('detalles', function ($q) use ($id) {
            $q->where('producto_id', $id);
        })
            ->orderBy('id', 'desc')->paginate(5);

        return Response()->json($ventas, 200);
    }

    public function vendedor()
    {

        $productos = Producto::where('tipo', 'Producto')->with('inventarios', 'sucursales')
            // ->whereNull('codigo')
            ->orderBy('id', 'desc')->paginate(12);

        return Response()->json($productos, 200);
    }

    public function vendedorBuscador($txt)
    {

        $productos = Producto::whereIn('tipo', ['Producto', 'Servicio'])->with('inventarios')
            ->where('nombre', 'like', '%' . $txt . '%')
            ->orwhere('codigo', 'like', '%' . $txt . '%')
            ->paginate(12);
        return Response()->json($productos, 200);
    }


    public function import(Request $request)
    {

        $request->validate([
            'file'          => 'required',
        ], [
            'file.required' => 'El documento es obligatorio.'
        ]);

        $import = new Productos();
        Excel::import($import, $request->file);

        return Response()->json($import->getRowCount(), 200);
    }

    public function export(Request $request)
    {
        $productos = new ProductosExport();
        $productos->filter($request);

        return Excel::download($productos, 'productos.xlsx');
    }
}
