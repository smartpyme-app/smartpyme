<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Exports\PlantillaInventarioExport;
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
use App\Imports\InventarioImport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WooCommerceExport;
use Illuminate\Support\Facades\Auth;
use App\Imports\TrasladosImport;
use App\Models\Inventario\Traslado;
use Illuminate\Support\Facades\DB;
use App\Exports\PlantillaInventarioMasivoExport;
use Illuminate\Support\Facades\Auth as FacadesAuth;

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


    public function exportarPlantillaTraslado(Request $request)
    {
        $request->request->add(['productos_ids' => explode(',', $request->productos_ids)]);
        $filtros = [
            'id_bodega_origen' => $request->id_bodega_origen,
            'id_bodega_destino' => $request->id_bodega_destino,
            'productos_ids' => $request->productos_ids
        ];

        Log::info($filtros);

        return Excel::download(
            new PlantillaInventarioMasivoExport($filtros),
            'plantilla_traslado_inventario_' . date('Ymd_His') . '.xlsx'
        );
    }

    public function trasladoMasivo(Request $request)
    {
        $request->validate([
            'concepto' => 'required|string',
            'id_bodega_origen' => 'required|numeric',
            'id_bodega_destino' => 'required|numeric|different:id_bodega_origen',
            'id_usuario' => 'required|numeric',
            'productos' => 'required|array',
            'productos.*.id_producto' => 'required|numeric',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
        ]);

        if ($request->id_bodega_origen == $request->id_bodega_destino) {
            return response()->json(['error' => 'Has seleccionado la misma sucursal.', 'code' => 400], 400);
        }

        DB::beginTransaction();

        try {
            $trasladosExitosos = 0;
            $errores = [];

            foreach ($request->productos as $productoData) {
                $idProducto = $productoData['id_producto'];
                $cantidad = $productoData['cantidad'];

                
                if ($cantidad <= 0) {
                    continue;
                }

                $producto = Producto::where('id', $idProducto)->with('composiciones')->first();

                if (!$producto) {
                    $errores[] = "Producto con ID {$idProducto} no encontrado.";
                    continue;
                }

                $origen = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->id_bodega_origen)
                    ->first();

                $destino = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $request->id_bodega_destino)
                    ->first();

               
                if (!$origen) {
                    $errores[] = "Una de las sucursales no tiene inventario para el producto {$producto->nombre}.";
                    continue;
                }

                
                if ($origen->stock < $cantidad) {
                    $errores[] = "La sucursal origen no tiene stock suficiente para el producto {$producto->nombre}.";
                    continue;
                }
                $user = Auth::user();

             
                $traslado = new Traslado();
                $traslado->id_producto = $idProducto;
                $traslado->id_bodega_de = $request->id_bodega_origen;
                $traslado->id_bodega = $request->id_bodega_destino;
                $traslado->concepto = $request->concepto;
                $traslado->cantidad = $cantidad;
                $traslado->id_usuario = $user->id;
                $traslado->id_empresa = $user->id_empresa;
                $traslado->estado = 'Confirmado';
                $traslado->save();

                
                $origen->stock -= $cantidad;
                $origen->save();
                $origen->kardex($traslado, $cantidad * -1);
                if ($destino) {
                    $destino->stock += $cantidad;
                    $destino->save();
                    $destino->kardex($traslado, $cantidad);
                } else {
                    $destino = new Inventario();
                    $destino->id_producto = $idProducto;
                    $destino->id_bodega = $request->id_bodega_destino;
                    $destino->stock = $cantidad;
                    $destino->save();
                    $destino->kardex($traslado, $cantidad);
                }

                
                $composicionesValidas = true;

                foreach ($producto->composiciones as $comp) {
                    $productoCompuesto = Producto::where('id', $comp->id_compuesto)->first();

                    if (!$productoCompuesto) {
                        $errores[] = "Producto compuesto con ID {$comp->id_compuesto} no encontrado.";
                        $composicionesValidas = false;
                        break;
                    }

                    $origenComp = Inventario::where('id_producto', $comp->id_compuesto)
                        ->where('id_bodega', $request->id_bodega_origen)
                        ->first();

                    $destinoComp = Inventario::where('id_producto', $comp->id_compuesto)
                        ->where('id_bodega', $request->id_bodega_destino)
                        ->first();

                    if (!$origenComp || !$destinoComp) {
                        $errores[] = "Una de las sucursales no tiene inventario para la composición {$productoCompuesto->nombre}.";
                        $composicionesValidas = false;
                        break;
                    }

                    $cantidadComp = $cantidad * $comp->cantidad;

                    if ($origenComp->stock < $cantidadComp) {
                        $errores[] = "La sucursal origen no tiene stock suficiente para la composición {$productoCompuesto->nombre}.";
                        $composicionesValidas = false;
                        break;
                    }

                   
                }

               
                if (!$composicionesValidas) {
                    continue;
                }

                // Actualizar inventario de las composiciones
                foreach ($producto->composiciones as $comp) {
                    $origenComp = Inventario::where('id_producto', $comp->id_compuesto)
                        ->where('id_bodega', $request->id_bodega_origen)
                        ->first();

                    $destinoComp = Inventario::where('id_producto', $comp->id_compuesto)
                        ->where('id_bodega', $request->id_bodega_destino)
                        ->first();

                    $cantidadComp = $cantidad * $comp->cantidad;

                    $origenComp->stock -= $cantidadComp;
                    $origenComp->save();
                    $origenComp->kardex($traslado, $cantidadComp * -1);

                    $destinoComp->stock += $cantidadComp;
                    $destinoComp->save();
                    $destinoComp->kardex($traslado, $cantidadComp);
                }

                $trasladosExitosos++;
            }

            if ($trasladosExitosos == 0) {
                DB::rollback();
                return response()->json([
                    'error' => 'No se pudo realizar ningún traslado. Revise los errores.',
                    'detalles' => $errores,
                    'code' => 400
                ], 400);
            }

            DB::commit();

            return response()->json([
                'message' => 'Traslado masivo realizado exitosamente',
                'trasladados' => $trasladosExitosos,
                'errores' => $errores
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage(), 'code' => 400], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage(), 'code' => 400], 400);
        }
    }

    public function importarTrasladosMasivos(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file',
            'concepto' => 'required|string',
            'id_bodega_origen' => 'required|numeric',
            'id_bodega_destino' => 'required|numeric|different:id_bodega_origen',
        ]);

        $importador = new TrasladosImport($request->concepto);
        Excel::import($importador, $request->file('archivo'));

       
        $trasladados = $importador->getTrasladados();
        $errores = $importador->getErrores();

        if ($trasladados > 0) {
            return Response()->json([
                'message' => "Traslado de inventario realizado exitosamente. Se trasladaron {$trasladados} productos.",
                'trasladados' => $trasladados,
                'errores' => $errores
            ], 200);
        } else {
            return Response()->json([
                'message' => 'No se realizó ningún traslado de inventario. Verifica que los datos sean correctos.',
                'trasladados' => 0,
                'errores' => $errores
            ], 200);
        }
    }

    public function exportarPlantilla(Request $request)
    {
        $filtros = [
            'id_bodega' => $request->id_bodega,
            'id_categoria' => $request->id_categoria,
            'buscador' => $request->buscador,
        ];

        return Excel::download(
            new PlantillaInventarioExport($filtros),
            'plantilla_ajuste_inventario_' . date('Ymd_His') . '.xlsx'
        );
    }

    public function importarAjustes(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv',
            'detalle' => 'required|string',
        ]);

        $importador = new InventarioImport($request->detalle);
        Excel::import($importador, $request->file('archivo'));

        // Verificar si se actualizó algún producto
        $actualizados = $importador->getActualizados();

        if ($actualizados > 0) {
            return Response()->json([
                'message' => "Ajuste de inventario realizado exitosamente. Se actualizaron {$actualizados} productos.",
                'actualizados' => $actualizados
            ], 200);
        } else {
            return Response()->json([
                'message' => 'No se realizó ningún cambio en el inventario. Verifica que los datos sean correctos.',
                'actualizados' => 0
            ], 200);
        }
    }


    public function ajusteMasivo(Request $request)
    {
        //return dd($request->all());
        // Validar request
        $request->validate([
            'detalle' => 'required|string|max:255',
            'productos' => 'required|array',
            'productos.*.id_producto' => 'required|exists:productos,id',
            'productos.*.id_bodega' => 'required|exists:sucursal_bodegas,id',
            'productos.*.stock_actual' => 'required|numeric|min:0',
            'productos.*.stock_nuevo' => 'required|numeric|min:0',
            'productos.*.diferencia' => 'required|numeric',
        ]);

        $productosActualizados = 0;

        // Procesar cada producto
        foreach ($request->productos as $item) {
            if ($item['diferencia'] == 0) {
                continue; // No hay cambio, saltamos
            }

            // Buscar el inventario del producto en la bodega específica
            $inventario = Inventario::where('id_producto', $item['id_producto'])
                ->where('id_bodega', $item['id_bodega'])
                ->first();

            if (!$inventario) {
                continue; // Si no existe el inventario, saltamos
            }

            // Actualizar stock
            $inventario->stock = $item['stock_nuevo'];
            $inventario->save();

            $ajuste = new Ajuste();
            $ajuste->concepto = $request->detalle;
            $ajuste->estado = 'Procesado';
            $ajuste->id_producto = $item['id_producto'];
            $ajuste->id_bodega = $item['id_bodega'];
            $ajuste->id_usuario = Auth::id();
            $ajuste->stock_actual = $item['stock_actual'];
            $ajuste->stock_real = $item['stock_nuevo'];
            $ajuste->ajuste = $item['diferencia'];
            $ajuste->id_empresa = Auth::user()->id_empresa;
            $ajuste->save();

            $inventario->kardex($ajuste, $ajuste->ajuste);

            $productosActualizados++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Ajuste masivo procesado correctamente',
            'actualizados' => $productosActualizados
        ]);
    }
    
    public function exportarWooCommerceTemplate(Request $request)
    {
        $user = Auth::user();
        $id_empresa = $user->id_empresa;

        $request->request->add(['id_empresa' => $id_empresa, 'user_id' => $user->id]);

        $productos = new WooCommerceExport();
        $productos->filter($request);

        return Excel::download(
            $productos,
            'productos_woocommerce_' . date('Y-m-d') . '.csv',
            \Maatwebsite\Excel\Excel::CSV,
            [
                'Content-Type' => 'text/csv',
            ]
        );
    }

}
