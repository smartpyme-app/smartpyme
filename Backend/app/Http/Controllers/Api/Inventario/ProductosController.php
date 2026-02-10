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
use App\Imports\TrasladosImport;
use App\Models\Inventario\Traslado;
use App\Imports\InventarioImport;
use Maatwebsite\Excel\Facades\Excel;
// use Auth;
use App\Exports\WooCommerceExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\PlantillaInventarioMasivoExport;
use App\Exports\ShopifyExport;
use App\Services\ShopifyTransformer;
use App\Services\ImpuestosService;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductosController extends Controller
{
    protected $shopifyTransformer;

    public function __construct(ShopifyTransformer $shopifyTransformer)
    {
        $this->shopifyTransformer = $shopifyTransformer;
    }

    public function index(Request $request)
    {
        // Obtener la empresa del usuario autenticado
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        $productos = Producto::whereIn('tipo', ['Producto', 'Compuesto'])->with(['inventarios' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        }, 'precios', 'lotes' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        }])
            ->when($request->id_categoria, function ($query) use ($request) {
                return $query->where('id_categoria', $request->id_categoria);
            })
            ->when($request->buscador, function ($query) use ($request) {
                //                return $query->where(function ($subQuery) use ($request) {
                //                    $subQuery->where('nombre', 'like', '%' . $request->buscador . '%')
                //                            ->orWhere('codigo', 'like', "%" . $request->buscador . "%")
                //                            ->orWhere('barcode', 'like', "%" . $request->buscador . "%")
                //                            ->orWhere('etiquetas', 'like', "%" . $request->buscador . "%")
                //                            ->orWhere('marca', 'like', "%" . $request->buscador . "%")
                //                            ->orWhere('descripcion', 'like', "%" . $request->buscador . "%");
                //                });
                return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                    ->orwhere('codigo', 'like', "%" . $request->buscador . "%")
                    ->orwhere('barcode', 'like', "%" . $request->buscador . "%")
                    ->orwhere('etiquetas', 'like', "%" . $request->buscador . "%")
                    ->orwhere('marca', 'like', "%" . $request->buscador . "%")
                    ->orwhere('descripcion', 'like', "%" . $request->buscador . "%");
            })
            ->when($request->sin_stock, function ($query) use ($request) {
                return $query->whereHas('inventarios', function ($q) {
                    $q->whereRaw('COALESCE(stock, 0) < COALESCE(stock_minimo, 0)');
                });
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

            ->when($request->marca, function ($query) use ($request) {
                return $query->where('marca', 'like', '%' . $request->marca . '%');
            })
            // Si la empresa tiene Shopify configurado y se ha seleccionado una bodega/sucursal,
            // filtrar solo productos que tengan inventario en esa bodega
            ->when($empresa && $empresa->shopify_store_url && $request->id_bodega, function ($query) use ($request) {
                return $query->whereHas('inventarios', function ($q) use ($request) {
                    $q->where('id_bodega', $request->id_bodega);
                });
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

    public function searchProductos(Request $request)
    {
        $term = $request->get('q', '');
        $limit = $request->get('limit', 15);
        $tipos = $request->get('tipos', ['Producto']);
        $extraFields = $request->get('fields', []);

        if (strlen($term) < 2) {
            return response()->json([], 200);
        }

        $query = Producto::query()
            ->where('enable', true)
            ->whereIn('tipo', $tipos)
            ->where(function ($q) use ($term) {
                $q->where('nombre', 'LIKE', "%{$term}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN nombre LIKE ? THEN 1
                    ELSE 2
                END
            ", [$term . '%'])
            ->orderBy('nombre', 'asc')
            ->limit($limit);

        if (!empty($extraFields) && is_array($extraFields)) {
            $fields = array_merge(['id', 'nombre', 'codigo', 'precio', 'tipo'], $extraFields);
            $productos = $query->get($fields);
        } else {
            $productos = $query->get();
        }

        return response()->json($productos, 200);
    }

    public function search($txt)
    {
        $productos = Producto::where('enable', true)->with('inventarios', 'lotes', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
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

        $productos = Producto::where('enable', true)->with('inventarios', 'lotes', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
            ->where(function ($q) use ($query) {
                $q->where('nombre', 'like', "%$query%")
                    ->orWhere('barcode', 'like', "%$query%")
                    ->orWhere('codigo', 'like', "%$query%")
                    ->orWhere('etiquetas', 'like', "%$query%");
            })
            //->whereIn('tipo', ['Producto', 'Compuesto'])
            ->take(15)
            ->get();

        return Response()->json($productos, 200);
    }

    public function searchByQueryWithBodega(Request $request)
    {
        $query = $request->query('query');
        $id_bodega = $request->query('id_bodega');

        if ($id_bodega) {
            // Si se especifica bodega, filtrar productos que tengan inventario en esa bodega
            $productos = Producto::where('enable', true)
                ->with(['inventarios' => function ($q) use ($id_bodega) {
                    $q->where('id_bodega', $id_bodega);
                }, 'lotes'])
                ->with('composiciones.opciones', 'composiciones.compuesto.inventarios', 'precios')
                ->whereHas('inventarios', function ($q) use ($id_bodega) {
                    $q->where('id_bodega', $id_bodega);
                })
                ->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%$query%")
                        ->orWhere('barcode', 'like', "%$query%")
                        ->orWhere('codigo', 'like', "%$query%")
                        ->orWhere('etiquetas', 'like', "%$query%");
                })
                ->take(15)
                ->get();
        } else {
            // Si no se especifica bodega, usar la búsqueda normal
            $productos = Producto::where('enable', true)->with('inventarios', 'lotes', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
                ->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%$query%")
                        ->orWhere('barcode', 'like', "%$query%")
                        ->orWhere('codigo', 'like', "%$query%")
                        ->orWhere('etiquetas', 'like', "%$query%");
                })
                ->take(15)
                ->get();
        }

        return Response()->json($productos, 200);
    }


    public function porCodigo($codigo)
    {

        $producto = Producto::where('codigo', $codigo)
            ->wherehas('sucursales', function ($q) {
                $q->where('sucursal_id', JWTAuth::parseToken()->authenticate()->sucursal_id)
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

    public function searchByCode($codigo)
    {
        $producto = Producto::where('codigo', $codigo)
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


        // Configurar inventarios para las bodegas (firstOrCreate evita duplicados producto+bodega)
        if (!$request->id && $producto->tipo != 'Servicio') {
            $bodegas = Bodega::all();
            foreach ($bodegas as $bodega) {
                Inventario::firstOrCreate(
                    [
                        'id_producto' => $producto->id,
                        'id_bodega' => $bodega->id,
                    ],
                    [
                        'stock' => 0,
                        'stock_minimo' => 0,
                        'stock_maximo' => 0,
                    ]
                );
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
            DB::table('producto_sucursales')->insert([
                'producto_id' => $producto->id,
                'sucursal_id' => $sucursal->id,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);


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
        try {
            $productos = new ProductosExport();
            $productos->filter($request);

            return Excel::download($productos, 'productos.xlsx');
        } catch (\Exception $e) {
            Log::error('Error al exportar productos: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return Response()->json(['error' => 'No se pudo exportar los productos.'], 500);
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
            'id_bodega' => 'required|integer|exists:sucursal_bodegas,id',
        ]);

        $importador = new InventarioImport($request->detalle, $request->id_bodega);
        Excel::import($importador, $request->file('archivo'));

        // Generar log con estadísticas completas
        $importador->logEstadisticasFinales();

        // Obtener estadísticas completas
        $estadisticas = $importador->getEstadisticas();
        $actualizados = $importador->getActualizados();

        if ($actualizados > 0) {
            return Response()->json([
                'message' => "Ajuste de inventario realizado exitosamente. Se actualizaron {$actualizados} de {$estadisticas['procesados']} productos procesados.",
                'actualizados' => $actualizados,
                'estadisticas' => $estadisticas
            ], 200);
        } else {
            return Response()->json([
                'message' => "No se realizó ningún cambio en el inventario. Se procesaron {$estadisticas['procesados']} productos. Verifica que los datos sean correctos.",
                'actualizados' => 0,
                'estadisticas' => $estadisticas
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

    /**
     * Habilitar inventario por lotes masivamente por categorías
     */
    public function habilitarLotesMasivo(Request $request)
    {
        $request->validate([
            'categorias' => 'required|array',
            'categorias.*' => 'required|exists:categorias,id',
            'habilitar' => 'required|boolean',
        ]);

        $productosActualizados = 0;

        // Obtener todos los productos de las categorías seleccionadas
        $productos = Producto::whereIn('id_categoria', $request->categorias)
            ->where('tipo', '!=', 'Servicio')
            ->get();

        foreach ($productos as $producto) {
            $producto->inventario_por_lotes = $request->habilitar;
            $producto->save();
            $productosActualizados++;
        }

        return response()->json([
            'success' => true,
            'message' => $request->habilitar 
                ? 'Inventario por lotes habilitado masivamente' 
                : 'Inventario por lotes deshabilitado masivamente',
            'productos_actualizados' => $productosActualizados
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

    // Agregar este método a tu ProductoController o el controller que manejes

    public function exportarShopifyTemplate(Request $request)
    {
        $user = Auth::user();
        $id_empresa = $user->id_empresa;

        $request->request->add(['id_empresa' => $id_empresa, 'user_id' => $user->id]);

        $productos = new ShopifyExport();
        $productos->filter($request);

        return Excel::download(
            $productos,
            'productos_shopify_' . date('Y-m-d') . '.csv',
            \Maatwebsite\Excel\Excel::CSV,
            [
                'Content-Type' => 'text/csv',
            ]
        );
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
                    $destino = Inventario::firstOrCreate(
                        [
                            'id_producto' => $idProducto,
                            'id_bodega' => $request->id_bodega_destino,
                        ],
                        ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                    );
                    $destino->stock += $cantidad;
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

    public function getMarcas()
    {
        try {
            $marcasRaw = Producto::where('marca', '!=', '')
                ->whereNotNull('marca')
                ->where('id_empresa', Auth::user()->id_empresa)
                ->where('enable', 1)
                ->where('tipo', 'Producto')
                ->distinct()
                ->orderBy('marca', 'asc')
                ->pluck('marca');

            $marcas = $marcasRaw->map(function ($marca) {
                return [
                    'id' => $marca,
                    'nombre' => $marca
                ];
            });

            return response()->json($marcas);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener las marcas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Búsqueda dinámica para modal de productos
     */
    public function buscarModal(Request $request)
    {
        $request->validate([
            'termino' => 'required|string|min:2',
            'id_empresa' => 'required|integer',
            'limite' => 'nullable|integer|max:50'
        ]);

        $termino = $request->termino;
        $limite = $request->limite ?? 15;

        $productos = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'precios'])
            ->where(function ($q) use ($termino) {
                $q->where('nombre', 'like', "%$termino%")
                    ->orWhere('codigo', 'like', "%$termino%")
                    ->orWhere('barcode', 'like', "%$termino%")
                    ->orWhere('etiquetas', 'like', "%$termino%")
                    ->orWhere('marca', 'like', "%$termino%")
                    ->orWhere('descripcion', 'like', "%$termino%");
            })
            ->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();

        return response()->json($productos, 200);
    }

    /**
     * Búsqueda por código de proveedor
     */
    public function buscarPorCodigoProveedor(Request $request)
    {
        $request->validate([
            'cod_proveed_prod' => 'required|string',
            'id_empresa' => 'required|integer'
        ]);

        $productos = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'lotes', 'precios', 'proveedores.proveedor'])
            ->whereHas('proveedores', function ($q) use ($request) {
                $q->where('cod_proveed_prod', $request->cod_proveed_prod);
            })
            ->get();

        return response()->json($productos, 200);
    }

    /**
     * Búsqueda por nombre específico
     */
    public function buscarPorNombre(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|min:2',
            'id_empresa' => 'required|integer',
            'limite' => 'nullable|integer|max:20'
        ]);

        $limite = $request->limite ?? 5;

        $productos = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'precios'])
            ->where('nombre', 'like', "%{$request->nombre}%")
            ->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();

        return response()->json($productos, 200);
    }

    /**
     * Búsqueda de sugerencias con palabras clave
     */
    public function buscarSugerencias(Request $request)
    {
        $request->validate([
            'termino' => 'required|string|min:2',
            'palabras' => 'nullable|array',
            'id_empresa' => 'required|integer',
            'limite' => 'nullable|integer|max:20'
        ]);

        $limite = $request->limite ?? 10;
        $termino = $request->termino;
        $palabras = $request->palabras ?? [];

        $query = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'lotes', 'precios']);

        // Búsqueda principal por término completo
        $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'like', "%$termino%")
                ->orWhere('codigo', 'like', "%$termino%")
                ->orWhere('barcode', 'like', "%$termino%")
                ->orWhere('etiquetas', 'like', "%$termino%")
                ->orWhere('marca', 'like', "%$termino%")
                ->orWhere('descripcion', 'like', "%$termino%");
        });

        // Si hay palabras específicas, buscar también por ellas
        if (!empty($palabras)) {
            $query->orWhere(function ($q) use ($palabras) {
                foreach ($palabras as $palabra) {
                    if (strlen($palabra) > 2) {
                        $q->orWhere('nombre', 'like', "%$palabra%")
                            ->orWhere('descripcion', 'like', "%$palabra%")
                            ->orWhere('etiquetas', 'like', "%$palabra%");
                    }
                }
            });
        }

        $productos = $query->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();

        return response()->json($productos, 200);
    }

    public function importarShopify(Request $request)
    {
        try {

            $usuario = JWTAuth::parseToken()->authenticate();

            // Validar datos requeridos
            if (empty($request->shopify_store_url) || empty($request->shopify_consumer_secret)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'URL de la tienda y clave secreta de Shopify son requeridos'
                ], 400);
            }

            // Verificar si ya se realizó una importación exitosa
            $empresa = \App\Models\Admin\Empresa::find($request->id_empresa);
            if ($empresa && $empresa->importacion_productos_shopify) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Ya se realizó una importación exitosa de productos desde Shopify. No se puede volver a importar para evitar duplicados.',
                    'codigo_error' => 'IMPORTACION_YA_REALIZADA'
                ], 400);
            }

            // Extraer el nombre de la tienda de la URL
            $storeUrl = $request->shopify_store_url;
            $storeName = $this->extraerNombreTienda($storeUrl);

            if (!$storeName) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'URL de Shopify inválida'
                ], 400);
            }

            // Construir URL base de la API de Shopify
            $baseUrl = "https://{$storeName}.myshopify.com/admin/api/2024-10/products.json";

            Log::info('=== INICIANDO IMPORTACIÓN DESDE SHOPIFY ===', [
                'store_url' => $storeUrl,
                'store_name' => $storeName,
                'base_url' => $baseUrl,
                'id_empresa' => $request->id_empresa,
                'id_usuario' => $request->id_usuario
            ]);

            // Obtener TODOS los productos usando paginación
            $productosShopify = $this->obtenerTodosLosProductosDeShopify($baseUrl, $request->shopify_consumer_secret);

            if (!$productosShopify) {
                Log::error('No se pudieron obtener productos de Shopify', [
                    'base_url' => $baseUrl,
                    'store_name' => $storeName
                ]);
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No se pudieron obtener los productos de Shopify. Verifica las credenciales.'
                ], 400);
            }

            Log::info('Productos obtenidos de Shopify', [
                'total_productos' => count($productosShopify),
                'productos_ids' => array_column($productosShopify, 'id')
            ]);

            // Registrar que se envió la respuesta
            Log::info('=== ENVIANDO RESPUESTA AL CLIENTE ===', [
                'total_productos' => count($productosShopify),
                'fecha_respuesta' => now()->format('Y-m-d H:i:s')
            ]);

            // Crear trabajos pendientes para cada producto
            $trabajosCreados = 0;
            foreach ($productosShopify as $productoShopify) {
                $this->crearTrabajoProducto($productoShopify, $request);
                $trabajosCreados++;
            }

            // Respuesta inmediata
            return response()->json([
                'success' => true,
                'mensaje' => 'Trabajos de importación creados exitosamente',
                'total_productos_shopify' => count($productosShopify),
                'trabajos_creados' => $trabajosCreados,
                'siguiente_paso' => 'Ejecutar comando: php artisan shopify:procesar-trabajos --lote=10 --procesar-productos-shopify',
                'instrucciones' => [
                    '1. Los trabajos están guardados en la base de datos',
                    '2. Puedes procesarlos cuando quieras con el comando',
                    '3. Cada ejecución del comando procesa 10 productos',
                    '4. Repite el comando hasta completar todos los productos'
                ],
                'resumen' => [
                    'productos_originales_shopify' => count($productosShopify),
                    'trabajos_creados' => $trabajosCreados,
                    'fecha_creacion_trabajos' => now()->format('Y-m-d H:i:s'),
                    'estado' => 'trabajos_creados'
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            Log::error('Error al importar productos desde Shopify: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al importar productos: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extraerNombreTienda($url)
    {
        // Extraer nombre de tienda de URLs como: https://1em3xk-pb.myshopify.com/
        if (preg_match('/https?:\/\/([^\.]+)\.myshopify\.com/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function obtenerTodosLosProductosDeShopify($baseUrl, $accessToken)
    {
        $todosLosProductos = [];
        $pageInfo = null;
        $pagina = 1;

        Log::info('Iniciando obtención de todos los productos con paginación');

        do {
            // Construir URL con parámetros de paginación
            $url = $baseUrl . '?limit=250'; // Máximo permitido por Shopify
            if ($pageInfo) {
                $url .= '&page_info=' . $pageInfo;
            }

            Log::info("Obteniendo página {$pagina} de productos", [
                'url' => $url,
                'page_info' => $pageInfo
            ]);

            $resultado = $this->obtenerProductosDeShopifyConPaginacion($url, $accessToken);

            if (!$resultado || !isset($resultado['productos'])) {
                Log::error("Error al obtener página {$pagina}");
                break;
            }

            $productosPagina = $resultado['productos'];
            $todosLosProductos = array_merge($todosLosProductos, $productosPagina);

            Log::info("Página {$pagina} obtenida", [
                'productos_en_pagina' => count($productosPagina),
                'total_acumulado' => count($todosLosProductos),
                'next_page_info' => $resultado['next_page_info'] ?? 'null'
            ]);

            // Obtener el page_info para la siguiente página
            $pageInfo = $resultado['next_page_info'] ?? null;

            // Si no hay next_page_info, es la última página
            if (!$pageInfo) {
                Log::info('Última página alcanzada (no hay next_page_info)');
                break;
            }

            $pagina++;

            // Prevenir bucles infinitos (máximo 100 páginas = 25,000 productos)
            if ($pagina > 100) {
                Log::warning('Límite de páginas alcanzado (100 páginas)');
                break;
            }
        } while (true);

        Log::info('Obtención de productos completada', [
            'total_paginas' => $pagina - 1,
            'total_productos' => count($todosLosProductos)
        ]);

        return $todosLosProductos;
    }

    private function obtenerProductosDeShopifyConPaginacion($apiUrl, $accessToken)
    {
        try {
            Log::info('Haciendo petición a Shopify API con paginación', [
                'url' => $apiUrl,
                'access_token_length' => strlen($accessToken)
            ]);

            $headers = [
                'X-Shopify-Access-Token: ' . $accessToken,
                'Content-Type: application/json'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HEADER, true); // Incluir headers en la respuesta

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Separar headers del body
            $headerString = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            Log::info('Respuesta de Shopify API con paginación', [
                'http_code' => $httpCode,
                'response_length' => strlen($body),
                'curl_error' => $curlError
            ]);

            if ($httpCode !== 200) {
                Log::error("Error en petición a Shopify. HTTP Code: {$httpCode}, Response: {$body}");
                return null;
            }

            $data = json_decode($body, true);
            $products = $data['products'] ?? [];

            // Extraer el page_info del header Link
            $nextPageInfo = $this->extraerNextPageInfo($headerString);

            Log::info('Productos parseados de Shopify con paginación', [
                'total_products' => count($products),
                'next_page_info' => $nextPageInfo,
                'first_product_id' => $products[0]['id'] ?? 'N/A'
            ]);

            return [
                'productos' => $products,
                'next_page_info' => $nextPageInfo
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener productos de Shopify con paginación: ' . $e->getMessage());
            return null;
        }
    }

    private function extraerNextPageInfo($headerString)
    {
        // Buscar el header Link que contiene la información de paginación
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $headerString, $matches)) {
            $nextUrl = $matches[1];
            // Extraer el page_info del URL
            if (preg_match('/[?&]page_info=([^&]+)/', $nextUrl, $pageMatches)) {
                return urldecode($pageMatches[1]);
            }
        }
        return null;
    }

    private function obtenerProductosDeShopify($apiUrl, $accessToken)
    {
        try {
            Log::info('Haciendo petición a Shopify API', [
                'url' => $apiUrl,
                'access_token_length' => strlen($accessToken)
            ]);

            $headers = [
                'X-Shopify-Access-Token: ' . $accessToken,
                'Content-Type: application/json'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            Log::info('Respuesta de Shopify API', [
                'http_code' => $httpCode,
                'response_length' => strlen($response),
                'curl_error' => $curlError
            ]);

            if ($httpCode !== 200) {
                Log::error("Error en petición a Shopify. HTTP Code: {$httpCode}, Response: {$response}");
                return null;
            }

            $data = json_decode($response, true);
            $products = $data['products'] ?? [];

            Log::info('Productos parseados de Shopify', [
                'total_products' => count($products),
                'first_product_sample' => $products[0] ?? null
            ]);

            return $products;
        } catch (\Exception $e) {
            Log::error('Error al hacer petición a Shopify: ' . $e->getMessage(), [
                'exception_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    private function procesarProductosShopify($productosShopify, $idEmpresa, $idUsuario, $idSucursal, $incluirDrafts = false)
    {
        $productosImportados = 0;
        $productosData = [];

        Log::info('Iniciando procesamiento de productos Shopify', [
            'total_productos' => count($productosShopify),
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'id_sucursal' => $idSucursal
        ]);

        // Procesar en lotes de 10 productos para Hostinger Shared
        $loteSize = 10;
        $productosShopifyChunks = array_chunk($productosShopify, $loteSize);
        $totalLotes = count($productosShopifyChunks);

        Log::info("Procesando en {$totalLotes} lotes de {$loteSize} productos cada uno");

        foreach ($productosShopifyChunks as $loteIndex => $loteProductos) {
            Log::info("Procesando lote " . ($loteIndex + 1) . " de {$totalLotes}", [
                'productos_en_lote' => count($loteProductos),
                'lote_actual' => $loteIndex + 1,
                'total_lotes' => $totalLotes
            ]);

            foreach ($loteProductos as $index => $productoShopify) {
                Log::info("Procesando producto Shopify #{$index}", [
                    'producto_id' => $productoShopify['id'],
                    'titulo' => $productoShopify['title'],
                    'variants_count' => count($productoShopify['variants'] ?? [])
                ]);

                // Transformar productos usando ShopifyTransformer
                $productosTransformados = $this->shopifyTransformer->transformarProductoDesdeShopify(
                    $productoShopify,
                    $idEmpresa,
                    $idUsuario,
                    $idSucursal,
                    $incluirDrafts,
                    true // Es importación masiva
                );

                Log::info("Productos transformados para producto #{$productoShopify['id']}", [
                    'variantes_transformadas' => count($productosTransformados),
                    'nombres_variantes' => array_column($productosTransformados, 'nombre')
                ]);

                foreach ($productosTransformados as $variantIndex => $productoData) {
                    try {
                        Log::info("Procesando variante #{$variantIndex}", [
                            'nombre' => $productoData['nombre'],
                            'precio' => $productoData['precio'],
                            'stock' => $productoData['_stock'],
                            'shopify_variant_id' => $productoData['shopify_variant_id']
                        ]);

                        // MODO TEST: Solo capturar datos sin insertar
                        $productoFinal = $this->prepararProductoParaInsertar($productoData, $idEmpresa);

                        $productosData[] = [
                            'producto_original_shopify' => $productoShopify,
                            'producto_transformado' => $productoData,
                            'producto_final' => $productoFinal
                        ];

                        $productosImportados++;

                        Log::info("Producto preparado para inserción", [
                            'nombre_final' => $productoFinal['nombre'],
                            'categoria' => $productoFinal['categoria_nombre'],
                            'precio_final' => $productoFinal['precio'],
                            'stock_final' => $productoFinal['stock_inicial']
                        ]);

                        // MODO PRODUCCIÓN: Descomenta estas líneas para insertar en la base de datos
                        $producto = $this->crearOActualizarProducto($productoData, $idEmpresa);
                        if ($producto) {
                            $this->crearInventarioProducto($producto->id, $productoData, $idEmpresa, $idUsuario);

                            // NUEVO: Crear job para procesar imágenes después
                            $this->crearJobImagenes($producto, $productoShopify, $productoData, $idEmpresa, $idUsuario);

                            $productosImportados++;

                            Log::info("Producto insertado exitosamente", [
                                'producto_id' => $producto->id,
                                'nombre' => $producto->nombre,
                                'precio' => $producto->precio,
                                'stock' => $productoData['_stock'] ?? 0
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error("Error al procesar producto de Shopify: " . $e->getMessage(), [
                            'producto_data' => $productoData,
                            'error_trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                // Pausa entre lotes para evitar timeout
                if ($loteIndex < $totalLotes - 1) {
                    Log::info("Pausa entre lotes para evitar timeout", [
                        'lote_completado' => $loteIndex + 1,
                        'productos_importados_hasta_ahora' => $productosImportados
                    ]);
                    sleep(2); // Pausa de 2 segundos entre lotes
                }
            }

            // Limpiar cache de categorías al finalizar la importación
            $cacheKey = "categoria_general_empresa_{$idEmpresa}";
            \Illuminate\Support\Facades\Cache::forget($cacheKey);

            Log::info('Procesamiento completado', [
                'total_productos_importados' => $productosImportados,
                'total_datos_capturados' => count($productosData)
            ]);

            return [
                'count' => $productosImportados
            ];
        }
    }


    private function prepararProductoParaInsertar($productoData, $idEmpresa)
    {
        // Simular lo que se insertaría sin hacerlo realmente
        $categoria = $this->obtenerOCrearCategoria($productoData, $idEmpresa);

        return [
            'nombre' => $productoData['nombre'],
            'descripcion' => $productoData['descripcion'] ?? '',
            'codigo' => $productoData['codigo'] ?? '',
            'barcode' => $productoData['barcode'] ?? '',
            'precio' => $productoData['precio'] ?? 0,
            'costo' => $productoData['costo'] ?? 0,
            'costo_promedio' => $productoData['costo'] ?? 0,
            'id_categoria' => $categoria->id,
            'categoria_nombre' => $categoria->nombre,
            'id_empresa' => $idEmpresa,
            'enable' => true,
            'tipo' => 'Producto',
            'shopify_product_id' => $productoData['shopify_product_id'] ?? null,
            'shopify_variant_id' => $productoData['shopify_variant_id'] ?? null,
            'shopify_inventory_item_id' => $productoData['shopify_inventory_item_id'] ?? null,
            'stock_inicial' => $productoData['_stock'] ?? 0
        ];
    }

    private function crearOActualizarProducto($productoData, $idEmpresa)
    {
        // MEJORADA: Búsqueda más robusta para prevenir duplicados
        $producto = $this->buscarProductoExistente($productoData, $idEmpresa);

        $esNuevo = !$producto;
        if ($esNuevo) {
            $producto = new Producto();
            Log::info("Creando nuevo producto", [
                'nombre' => $productoData['nombre'],
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A',
                'shopify_product_id' => $productoData['shopify_product_id'] ?? 'N/A'
            ]);
        } else {
            Log::info("Actualizando producto existente", [
                'producto_id' => $producto->id,
                'nombre_anterior' => $producto->nombre,
                'nombre_nuevo' => $productoData['nombre'],
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A'
            ]);
        }

        // Obtener o crear categoría
        $categoria = $this->obtenerOCrearCategoria($productoData, $idEmpresa);

        // Llenar datos del producto
        $producto->nombre = $productoData['nombre'];
        $producto->nombre_variante = $productoData['nombre_variante'] ?? null;
        $producto->descripcion = $productoData['descripcion'] ?? '';
        $producto->codigo = $productoData['codigo'] ?? '';
        $producto->barcode = $productoData['barcode'] ?? '';

        // IMPORTANTE: Los precios de Shopify ya incluyen IVA
        $precioShopify = $productoData['precio'] ?? 0;
        $precioSinIVA = $this->calcularPrecioSinIVA($precioShopify, $idEmpresa);
        $producto->precio = $precioSinIVA;

        Log::info("Precio procesado para producto", [
            'producto_id' => $producto->id ?? 'nuevo',
            'nombre' => $producto->nombre,
            'precio_shopify_con_iva' => $precioShopify,
            'precio_guardado_sin_iva' => $precioSinIVA,
            'diferencia_iva' => round($precioShopify - $precioSinIVA, 2)
        ]);
        $producto->costo = $productoData['costo'] ?? 0;
        $producto->costo_promedio = $productoData['costo'] ?? 0;
        $producto->id_categoria = $categoria->id;
        $producto->id_empresa = $idEmpresa;
        $producto->enable = true;
        $producto->tipo = 'Producto';

        // IMPORTANTE: Marcar que este producto viene de Shopify ANTES de guardar para evitar sincronización de vuelta
        $producto->syncing_from_shopify = true;
        $producto->last_shopify_sync = now();

        // Campos específicos de Shopify
        $producto->shopify_product_id = $productoData['shopify_product_id'] ?? null;
        $producto->shopify_variant_id = $productoData['shopify_variant_id'] ?? null;
        $producto->shopify_inventory_item_id = $productoData['shopify_inventory_item_id'] ?? null;

        $producto->save();

        Log::info($esNuevo ? "Producto creado exitosamente" : "Producto actualizado exitosamente", [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'nombre_variante' => $producto->nombre_variante,
            'precio' => $producto->precio,
            'costo' => $producto->costo
        ]);

        return $producto;
    }

    /**
     * MEJORADA: Búsqueda robusta de productos existentes para prevenir duplicados
     */
    private function buscarProductoExistente($productoData, $idEmpresa)
    {
        // 1. Buscar por shopify_variant_id (más confiable)
        if (!empty($productoData['shopify_variant_id'])) {
            $producto = Producto::where('id_empresa', $idEmpresa)
                ->where('shopify_variant_id', $productoData['shopify_variant_id'])
                ->first();

            if ($producto) {
                Log::info("Producto encontrado por shopify_variant_id", [
                    'producto_id' => $producto->id,
                    'shopify_variant_id' => $productoData['shopify_variant_id'],
                    'nombre' => $producto->nombre
                ]);
                return $producto;
            }
        }

        // 2. Buscar por shopify_product_id (para productos sin variantes)
        if (!empty($productoData['shopify_product_id'])) {
            $producto = Producto::where('id_empresa', $idEmpresa)
                ->where('shopify_product_id', $productoData['shopify_product_id'])
                ->whereNull('shopify_variant_id') // Solo productos sin variantes
                ->first();

            if ($producto) {
                Log::info("Producto encontrado por shopify_product_id", [
                    'producto_id' => $producto->id,
                    'shopify_product_id' => $productoData['shopify_product_id'],
                    'nombre' => $producto->nombre
                ]);
                return $producto;
            }
        }

        // 3. Buscar por nombre exacto + empresa (último recurso) - SOLO para productos sin variantes
        if (empty($productoData['shopify_variant_id'])) {
            $producto = Producto::where('id_empresa', $idEmpresa)
                ->where('nombre', $productoData['nombre'])
                ->where('tipo', 'Producto')
                ->whereNull('shopify_variant_id') // Solo productos sin variantes
                ->first();

            if ($producto) {
                Log::info("Producto encontrado por nombre exacto (sin variantes)", [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'shopify_variant_id_actual' => $producto->shopify_variant_id,
                    'shopify_variant_id_nuevo' => $productoData['shopify_variant_id'] ?? 'N/A'
                ]);
                return $producto;
            }
        }

        // 4. Verificar duplicados potenciales por nombre similar
        $productosSimilares = Producto::where('id_empresa', $idEmpresa)
            ->where('nombre', 'like', '%' . $productoData['nombre'] . '%')
            ->where('tipo', 'Producto')
            ->get();

        if ($productosSimilares->count() > 0) {
            Log::warning("Productos similares encontrados - posible duplicado", [
                'nombre_buscado' => $productoData['nombre'],
                'productos_similares' => $productosSimilares->pluck('nombre')->toArray(),
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A'
            ]);
        }

        Log::info("No se encontró producto existente - será creado como nuevo", [
            'nombre' => $productoData['nombre'],
            'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A',
            'shopify_product_id' => $productoData['shopify_product_id'] ?? 'N/A'
        ]);

        return null;
    }

    /**
     * Crear job para procesar imágenes después de la importación
     */
    private function crearJobImagenes($producto, $productoShopify, $productoData, $idEmpresa, $idUsuario)
    {
        try {
            // Verificar si el producto tiene imágenes
            if (empty($productoShopify['images']) || !is_array($productoShopify['images'])) {
                Log::info("Producto sin imágenes - no se crea job", [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre
                ]);
                return;
            }

            $shopifyVariantId = $productoData['shopify_variant_id'] ?? null;

            // Filtrar imágenes que pertenecen a esta variante específica
            $imagenesVariante = $this->filtrarImagenesPorVariante($productoShopify['images'], $shopifyVariantId);

            if (empty($imagenesVariante)) {
                Log::info("Variante sin imágenes específicas - no se crea job", [
                    'producto_id' => $producto->id,
                    'shopify_variant_id' => $shopifyVariantId
                ]);
                return;
            }

            // Crear trabajo pendiente para procesar imágenes
            $trabajo = new \App\Models\TrabajosPendientes();
            $trabajo->tipo = 'procesar_imagenes_shopify';
            $trabajo->parametros = json_encode([
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'shopify_variant_id' => $shopifyVariantId,
                'shopify_product_id' => $productoShopify['id'],
                'imagenes' => $imagenesVariante,
                'total_imagenes' => count($imagenesVariante)
            ]);
            $trabajo->estado = 'pendiente';
            $trabajo->fecha_creacion = now();
            $trabajo->id_usuario = $idUsuario;
            $trabajo->id_empresa = $idEmpresa;
            $trabajo->save();

            Log::info("Job de imágenes creado exitosamente", [
                'trabajo_id' => $trabajo->id,
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'total_imagenes' => count($imagenesVariante),
                'estado' => 'pendiente'
            ]);
        } catch (\Exception $e) {
            Log::error("Error creando job de imágenes: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Procesar productos en segundo plano después de enviar respuesta al cliente
     */
    private function procesarProductosEnSegundoPlano($productosShopify, $idEmpresa, $idUsuario, $idSucursal)
    {
        try {
            Log::info('=== INICIANDO PROCESAMIENTO EN SEGUNDO PLANO ===', [
                'total_productos' => count($productosShopify),
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'fecha_inicio' => now()->format('Y-m-d H:i:s')
            ]);

            // Procesar productos usando ShopifyTransformer
            $resultado = $this->procesarProductosShopify(
                $productosShopify,
                $idEmpresa,
                $idUsuario,
                $idSucursal,
                true // Siempre incluir drafts
            );

            // Marcar la importación como completada en la empresa
            $empresa = \App\Models\Admin\Empresa::find($idEmpresa);
            if ($empresa) {
                $empresa->importacion_productos_shopify = true;
                $empresa->save();

                Log::info('=== IMPORTACIÓN MARCADA COMO COMPLETADA ===', [
                    'id_empresa' => $idEmpresa,
                    'importacion_productos_shopify' => true,
                    'fecha_marcado' => now()->format('Y-m-d H:i:s')
                ]);
            }

            Log::info('=== PROCESAMIENTO EN SEGUNDO PLANO COMPLETADO ===', [
                'total_productos_shopify' => count($productosShopify),
                'productos_importados' => $resultado['count'],
                'fecha_fin' => now()->format('Y-m-d H:i:s'),
                'tiempo_procesamiento' => 'Completado exitosamente',
                'importacion_marcada_como_completada' => true
            ]);
        } catch (\Exception $e) {
            Log::error('=== ERROR EN PROCESAMIENTO EN SEGUNDO PLANO ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'total_productos' => count($productosShopify),
                'fecha_error' => now()->format('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * MEJORADO: Procesar imágenes específicas de la variante desde Shopify
     * OPTIMIZACIÓN: Solo procesa la primera imagen de cada variante para evitar sobrecarga
     */
    private function procesarImagenesVariante($producto, $productoShopify, $productoData)
    {
        try {
            // Verificar si el producto tiene imágenes
            if (empty($productoShopify['images']) || !is_array($productoShopify['images'])) {
                Log::info("Producto sin imágenes", [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'shopify_product_id' => $productoShopify['id']
                ]);
                return;
            }

            $shopifyVariantId = $productoData['shopify_variant_id'] ?? null;

            // Filtrar imágenes que pertenecen a esta variante específica
            $imagenesVariante = $this->filtrarImagenesPorVariante($productoShopify['images'], $shopifyVariantId);

            if (empty($imagenesVariante)) {
                Log::info("Variante sin imágenes específicas", [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'shopify_variant_id' => $shopifyVariantId,
                    'total_imagenes_producto' => count($productoShopify['images'])
                ]);
                return;
            }

            Log::info("Procesando imágenes específicas de la variante", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'shopify_variant_id' => $shopifyVariantId,
                'imagenes_variante' => count($imagenesVariante),
                'total_imagenes_producto' => count($productoShopify['images'])
            ]);

            // Eliminar imágenes existentes del producto
            $this->eliminarImagenesExistentes($producto->id);

            // OPTIMIZACIÓN: Solo procesar la primera imagen de la variante
            if (!empty($imagenesVariante)) {
                $primeraImagen = $imagenesVariante[0];
                $this->descargarYGuardarImagen($producto, $primeraImagen, 0);

                Log::info("Optimización aplicada - solo primera imagen procesada", [
                    'producto_id' => $producto->id,
                    'total_imagenes_disponibles' => count($imagenesVariante),
                    'imagen_procesada' => $primeraImagen['id'] ?? 'N/A'
                ]);
            }

            Log::info("Imágenes de variante procesadas exitosamente", [
                'producto_id' => $producto->id,
                'total_imagenes_procesadas' => 1,
                'total_imagenes_disponibles' => count($imagenesVariante),
                'optimizacion_aplicada' => true
            ]);
        } catch (\Exception $e) {
            Log::error("Error procesando imágenes de la variante: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A',
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Filtrar imágenes que pertenecen a una variante específica
     */
    private function filtrarImagenesPorVariante($imagenes, $shopifyVariantId)
    {
        $imagenesVariante = [];

        foreach ($imagenes as $imagen) {
            $variantIds = $imagen['variant_ids'] ?? [];

            // Si la imagen no tiene variant_ids específicos, es imagen general del producto
            // Si tiene variant_ids, verificar si incluye nuestra variante
            if (empty($variantIds) || in_array($shopifyVariantId, $variantIds)) {
                $imagenesVariante[] = $imagen;

                Log::info("Imagen asignada a variante", [
                    'shopify_variant_id' => $shopifyVariantId,
                    'imagen_id' => $imagen['id'] ?? 'N/A',
                    'variant_ids_imagen' => $variantIds,
                    'es_imagen_general' => empty($variantIds)
                ]);
            }
        }

        return $imagenesVariante;
    }

    /**
     * Eliminar imágenes existentes del producto
     */
    private function eliminarImagenesExistentes($productoId)
    {
        try {
            $imagenesExistentes = \App\Models\Inventario\Imagen::where('id_producto', $productoId)->get();

            foreach ($imagenesExistentes as $imagen) {
                // Eliminar archivo de S3 si existe
                if ($imagen->img && strpos($imagen->img, 'productos/') !== false) {
                    $s3Path = 'img/' . $imagen->img;
                    \Storage::disk('s3-public')->delete($s3Path);
                }

                // Eliminar registro de la base de datos
                $imagen->delete();
            }

            if ($imagenesExistentes->count() > 0) {
                Log::info("Imágenes existentes eliminadas", [
                    'producto_id' => $productoId,
                    'imagenes_eliminadas' => $imagenesExistentes->count()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error eliminando imágenes existentes: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Descargar y guardar imagen desde Shopify
     */
    private function descargarYGuardarImagen($producto, $imagenShopify, $index)
    {
        try {
            $urlImagen = $imagenShopify['src'] ?? null;
            if (!$urlImagen) {
                Log::warning("Imagen sin URL válida", [
                    'producto_id' => $producto->id,
                    'imagen_index' => $index,
                    'imagen_data' => $imagenShopify
                ]);
                return;
            }

            // Generar nombre único para la imagen
            $extension = pathinfo(parse_url($urlImagen, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = $extension ?: 'jpg'; // Default a jpg si no se puede determinar

            $nombreArchivo = 'producto_' . $producto->id . '_' . $index . '_' . time() . '.' . $extension;
            $s3Path = 'productos/' . $nombreArchivo;

            // Descargar imagen
            $imagenContenido = $this->descargarImagenDesdeUrl($urlImagen);
            if (!$imagenContenido) {
                Log::warning("No se pudo descargar la imagen", [
                    'producto_id' => $producto->id,
                    'url_imagen' => $urlImagen,
                    'imagen_index' => $index
                ]);
                return;
            }

            // Subir archivo a S3
            \Storage::disk('s3-public')->put($s3Path, $imagenContenido);

            // Guardar en base de datos con URL de S3
            $imagen = new \App\Models\Inventario\Imagen();
            $imagen->id_producto = $producto->id;
            $imagen->img = 'productos/' . $nombreArchivo;
            $imagen->shopify_image_id = $imagenShopify['id'] ?? null;
            $imagen->save();

            Log::info("Imagen descargada y guardada exitosamente en S3", [
                'producto_id' => $producto->id,
                'nombre_archivo' => $nombreArchivo,
                's3_path' => $s3Path,
                'url_original' => $urlImagen,
                'shopify_image_id' => $imagenShopify['id'] ?? null,
                's3_url' => $imagen->img
            ]);
        } catch (\Exception $e) {
            Log::error("Error descargando y guardando imagen: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'url_imagen' => $urlImagen ?? 'N/A',
                'imagen_index' => $index,
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Descargar imagen desde URL usando cURL
     */
    private function descargarImagenDesdeUrl($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SmartPyme/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $contenido = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error("Error cURL descargando imagen", [
                    'url' => $url,
                    'error' => $error
                ]);
                return false;
            }

            if ($httpCode !== 200) {
                Log::warning("HTTP error descargando imagen", [
                    'url' => $url,
                    'http_code' => $httpCode
                ]);
                return false;
            }

            if (empty($contenido)) {
                Log::warning("Imagen vacía descargada", [
                    'url' => $url
                ]);
                return false;
            }

            return $contenido;
        } catch (\Exception $e) {
            Log::error("Excepción descargando imagen: " . $e->getMessage(), [
                'url' => $url,
                'error_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Calcular precio sin IVA desde precio con IVA incluido
     */
    private function calcularPrecioSinIVA($precioConIVA, $idEmpresa)
    {
        try {
            // Validar precio de entrada
            if (empty($precioConIVA) || $precioConIVA <= 0) {
                Log::warning("Precio inválido recibido", [
                    'precio_con_iva' => $precioConIVA,
                    'id_empresa' => $idEmpresa
                ]);
                return 0;
            }

            // Obtener configuración de IVA de la empresa
            $empresa = \App\Models\Admin\Empresa::find($idEmpresa);

            if (!$empresa) {
                Log::warning("Empresa no encontrada", [
                    'id_empresa' => $idEmpresa,
                    'precio_original' => $precioConIVA
                ]);
                return $precioConIVA;
            }

            // Verificar si la empresa cobra IVA
            if ($empresa->cobra_iva !== 'Si' || empty($empresa->iva) || $empresa->iva <= 0) {
                Log::info("Empresa no cobra IVA - precio sin modificar", [
                    'id_empresa' => $idEmpresa,
                    'empresa_cobra_iva' => $empresa->cobra_iva,
                    'porcentaje_iva_empresa' => $empresa->iva,
                    'precio_original' => $precioConIVA,
                    'precio_final' => $precioConIVA
                ]);
                return $precioConIVA;
            }

            // Calcular precio sin IVA usando la fórmula: precio_con_iva / (1 + iva_decimal)
            $ivaDecimal = $empresa->iva / 100;
            $precioSinIVA = $precioConIVA / (1 + $ivaDecimal);
            $ivaDescontado = $precioConIVA - $precioSinIVA;

            Log::info("Precio calculado sin IVA exitosamente", [
                'id_empresa' => $idEmpresa,
                'empresa_nombre' => $empresa->nombre ?? 'N/A',
                'precio_con_iva' => $precioConIVA,
                'porcentaje_iva' => $empresa->iva,
                'iva_decimal' => $ivaDecimal,
                'precio_sin_iva' => round($precioSinIVA, 2),
                'iva_descontado' => round($ivaDescontado, 2),
                'verificacion' => round($precioSinIVA * (1 + $ivaDecimal), 2) . ' (debería ser ' . $precioConIVA . ')'
            ]);

            return round($precioSinIVA, 2);
        } catch (\Exception $e) {
            Log::error("Error calculando precio sin IVA: " . $e->getMessage(), [
                'precio_con_iva' => $precioConIVA,
                'id_empresa' => $idEmpresa,
                'error_trace' => $e->getTraceAsString()
            ]);

            // En caso de error, devolver el precio original
            return $precioConIVA;
        }
    }

    private function obtenerOCrearCategoria($productoData, $idEmpresa)
    {
        // Cache key para evitar consultas repetitivas durante importación masiva
        $cacheKey = "categoria_general_empresa_{$idEmpresa}";

        // Intentar obtener del cache primero
        $categoria = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($idEmpresa) {
            // Por ahora, usar categoría "General" para todos los productos de Shopify
            // En el futuro se puede implementar lógica para crear categorías basadas en product_type
            $categoria = \App\Models\Inventario\Categorias\Categoria::where('nombre', 'General')
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$categoria) {
                $categoria = new \App\Models\Inventario\Categorias\Categoria();
                $categoria->nombre = 'General';
                $categoria->descripcion = 'Categoría general para productos importados';
                $categoria->enable = true;
                $categoria->id_empresa = $idEmpresa;
                $categoria->save();
            }

            return $categoria;
        });

        return $categoria;
    }

    private function crearInventarioProducto($productoId, $productoData, $idEmpresa, $idUsuario)
    {
        // Obtener la primera bodega activa de la empresa
        $bodega = Bodega::where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->first();

        if (!$bodega) {
            Log::warning("No se encontró bodega activa para la empresa {$idEmpresa}");
            return;
        }

        // Buscar inventario existente
        $inventario = Inventario::where('id_producto', $productoId)
            ->where('id_bodega', $bodega->id)
            ->first();

        if (!$inventario) {
            $inventario = new Inventario();
            $inventario->id_producto = $productoId;
            $inventario->id_bodega = $bodega->id;
        }

        // Establecer stock desde Shopify
        $stock = $productoData['_stock'] ?? 0;
        $inventario->stock = $stock;
        $inventario->save();

        // Crear ajuste de inventario
        if ($stock > 0) {
            $ajuste = Ajuste::create([
                'concepto' => 'Importación desde Shopify',
                'id_producto' => $productoId,
                'id_bodega' => $bodega->id,
                'stock_actual' => 0,
                'stock_real' => $stock,
                'ajuste' => $stock,
                'estado' => 'Confirmado',
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
            ]);

            $inventario->kardex($ajuste, $ajuste->ajuste);
        }
    }

    private function crearTrabajoProducto($productoShopify, $request)
    {
        try {
            $trabajo = new \App\Models\TrabajosPendientes();
            $trabajo->tipo = 'shopify_import_producto';
            $trabajo->estado = 'pendiente';
            $trabajo->prioridad = 1;
            $trabajo->parametros = json_encode([
                'producto_shopify' => $productoShopify,
                'id_empresa' => $request->id_empresa,
                'id_usuario' => $request->id_usuario,
                'id_sucursal' => $request->id_sucursal,
                'shopify_store_url' => $request->shopify_store_url,
                'shopify_consumer_secret' => $request->shopify_consumer_secret,
                'shopify_consumer_key' => $request->shopify_consumer_key ?? null
            ]);
            $trabajo->datos = json_encode([
                'producto_shopify' => $productoShopify,
                'id_empresa' => $request->id_empresa,
                'id_usuario' => $request->id_usuario,
                'id_sucursal' => $request->id_sucursal,
                'shopify_store_url' => $request->shopify_store_url,
                'shopify_consumer_secret' => $request->shopify_consumer_secret,
                'shopify_consumer_key' => $request->shopify_consumer_key ?? null
            ]);
            $trabajo->intentos = 0;
            $trabajo->max_intentos = 3;
            $trabajo->fecha_creacion = now();
            $trabajo->fecha_procesamiento = null;
            $trabajo->id_usuario = $request->id_usuario;
            $trabajo->id_empresa = $request->id_empresa;
            $trabajo->save();

            Log::info("Trabajo creado para producto Shopify", [
                'trabajo_id' => $trabajo->id,
                'producto_id' => $productoShopify['id'],
                'titulo' => $productoShopify['title']
            ]);
        } catch (\Exception $e) {
            Log::error("Error creando trabajo para producto", [
                'producto_id' => $productoShopify['id'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
        }
    }
}
