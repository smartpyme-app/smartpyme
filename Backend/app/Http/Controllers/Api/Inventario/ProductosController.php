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
use App\Imports\WooCommerceProductosImport;
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
use App\Models\Inventario\Composiciones\Composicion;
use App\Exports\ShopifyExport;
use App\Services\ShopifyTransformer;
use App\Services\ImpuestosService;
use App\Services\Inventario\ShopifyImportService;
use App\Services\Inventario\ProductoService;
use App\Services\Inventario\CategoriaService;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\Inventario\Productos\StoreProductoRequest;
use App\Http\Requests\Inventario\Productos\StoreProductoDesdeComprasRequest;
use App\Http\Requests\Inventario\Productos\StoreProductoCompuestoRequest;
use App\Http\Requests\Inventario\Productos\ImportProductosRequest;
use App\Http\Requests\Inventario\Productos\ImportarAjustesRequest;
use App\Http\Requests\Inventario\Productos\AjusteMasivoRequest;
use App\Http\Requests\Inventario\Productos\TrasladoMasivoRequest;
use App\Http\Requests\Inventario\Productos\ImportarTrasladosMasivosRequest;
use App\Http\Requests\Inventario\Productos\BuscarModalRequest;
use App\Http\Requests\Inventario\Productos\BuscarPorCodigoProveedorRequest;
use App\Http\Requests\Inventario\Productos\BuscarPorNombreRequest;
use App\Http\Requests\Inventario\Productos\BuscarSugerenciasRequest;
use App\Http\Requests\Inventario\Productos\ImportarShopifyRequest;

class ProductosController extends Controller
{
    protected $shopifyTransformer;
    protected $shopifyImportService;
    protected $productoService;
    protected $categoriaService;

    public function __construct(
        ShopifyTransformer $shopifyTransformer,
        ShopifyImportService $shopifyImportService,
        ProductoService $productoService,
        CategoriaService $categoriaService
    ) {
        $this->shopifyTransformer = $shopifyTransformer;
        $this->shopifyImportService = $shopifyImportService;
        $this->productoService = $productoService;
        $this->categoriaService = $categoriaService;
    }

    public function index(Request $request)
    {
        // Obtener la empresa del usuario autenticado
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        $orden = $request->orden ?: 'nombre';
        $direccion = $request->direccion ?: 'desc';

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
            ->when($request->buscador, function ($query) use ($request, $empresa) {
                $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();
                return $query->where(function ($q) use ($request, $incluirComponenteQuimico) {
                    $q->where('nombre', 'like', '%' . $request->buscador . '%')
                        ->orWhere('codigo', 'like', "%" . $request->buscador . "%")
                        ->orWhere('barcode', 'like', "%" . $request->buscador . "%")
                        ->orWhere('etiquetas', 'like', "%" . $request->buscador . "%")
                        ->orWhere('marca', 'like', "%" . $request->buscador . "%")
                        ->orWhere('descripcion', 'like', "%" . $request->buscador . "%");
                    if ($incluirComponenteQuimico) {
                        $q->orWhere('componente_quimico', 'like', '%' . $request->buscador . '%');
                    }
                });
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

        $empresa = Auth::user() ? Empresa::find(Auth::user()->id_empresa) : null;
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $query = Producto::query()
            ->where('enable', true)
            ->whereIn('tipo', $tipos)
            ->where(function ($q) use ($term, $incluirComponenteQuimico) {
                $q->where('nombre', 'LIKE', "%{$term}%");
                if ($incluirComponenteQuimico) {
                    $q->orWhere('componente_quimico', 'LIKE', "%{$term}%");
                }
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
        $empresa = Auth::user() ? Empresa::find(Auth::user()->id_empresa) : null;
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $productos = Producto::where('enable', true)->with('inventarios', 'lotes', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
            ->where(function ($q) use ($txt, $incluirComponenteQuimico) {
                $q->where('nombre', 'like', "%$txt%")
                    ->orWhere('barcode', 'like', "%$txt%")
                    ->orWhere('codigo', 'like', "%$txt%")
                    ->orWhere('etiquetas', 'like', "%$txt%");
                if ($incluirComponenteQuimico) {
                    $q->orWhere('componente_quimico', 'like', "%$txt%");
                }
            })
            ->take(15)
            ->get();

        return Response()->json($productos, 200);
    }

    public function searchForList(Request $request)
    {
        $search = $request->input('search', '');
        $limit = $request->input('limit', 20);

        $query = Producto::where('enable', true)
            ->orderBy('nombre');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'LIKE', '%' . $search . '%')
                ->orWhere('codigo', 'LIKE', '%' . $search . '%')
                ->orWhere('barcode', 'LIKE', '%' . $search . '%');
            });
        }

        $productos = $query->with('inventarios')->limit($limit)->get();

        // Log::info('Búsqueda de productos: ' . $search . ' - Resultados: ' . $productos->count());

        return response()->json($productos, 200);
    }

    public function inventarios($id)
    {
        $producto = Producto::with('inventarios')->find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto->inventarios, 200);
    }

    public function searchByQuery(Request $request)
    {
        $query = $request->query('query');
        $empresa = Auth::user() ? Empresa::find(Auth::user()->id_empresa) : null;
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $productos = Producto::where('enable', true)->with('inventarios', 'lotes', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
            ->where(function ($q) use ($query, $incluirComponenteQuimico) {
                $q->where('nombre', 'like', "%$query%")
                    ->orWhere('barcode', 'like', "%$query%")
                    ->orWhere('codigo', 'like', "%$query%")
                    ->orWhere('etiquetas', 'like', "%$query%");
                if ($incluirComponenteQuimico) {
                    $q->orWhere('componente_quimico', 'like', "%$query%");
                }
            })
            ->take(15)
            ->get();

        return Response()->json($productos, 200);
    }

    public function searchByQueryWithBodega(Request $request)
    {
        $query = $request->query('query');
        $id_bodega = $request->query('id_bodega');
        $empresa = Auth::user() ? Empresa::find(Auth::user()->id_empresa) : null;
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        if ($id_bodega) {
            $productos = Producto::where('enable', true)
                ->with(['inventarios' => function ($q) use ($id_bodega) {
                    $q->where('id_bodega', $id_bodega);
                }, 'lotes'])
                ->with('composiciones.opciones', 'composiciones.compuesto.inventarios', 'precios')
                ->whereHas('inventarios', function ($q) use ($id_bodega) {
                    $q->where('id_bodega', $id_bodega);
                })
                ->where(function ($q) use ($query, $incluirComponenteQuimico) {
                    $q->where('nombre', 'like', "%$query%")
                        ->orWhere('barcode', 'like', "%$query%")
                        ->orWhere('codigo', 'like', "%$query%")
                        ->orWhere('etiquetas', 'like', "%$query%");
                    if ($incluirComponenteQuimico) {
                        $q->orWhere('componente_quimico', 'like', "%$query%");
                    }
                })
                ->take(15)
                ->get();
        } else {
            $productos = Producto::where('enable', true)->with('inventarios', 'lotes', 'composiciones.opciones', 'composiciones.compuesto.inventarios')->with('precios')
                ->where(function ($q) use ($query, $incluirComponenteQuimico) {
                    $q->where('nombre', 'like', "%$query%")
                        ->orWhere('barcode', 'like', "%$query%")
                        ->orWhere('codigo', 'like', "%$query%")
                        ->orWhere('etiquetas', 'like', "%$query%");
                    if ($incluirComponenteQuimico) {
                        $q->orWhere('componente_quimico', 'like', "%$query%");
                    }
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

    public function store(StoreProductoRequest $request)
    {

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

    public function storeDesdeCompras(StoreProductoDesdeComprasRequest $request)
    {

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

    public function storeCompuesto(StoreProductoCompuestoRequest $request){

        DB::beginTransaction();

        if ($request->id)
            $producto = Producto::findOrFail($request->id);
        else
            $producto = new Producto;

        $producto->fill($request->all());
        $producto->save();

        foreach($request->detalles as $detalle){
            $composicion = new Composicion();
            //$composicion->fill($detalle->all()); FUNCION ALL QUEDO EN EL SERVER
            $composicion->cantidad = $detalle["cantidad"];
            $composicion->id_producto = $detalle["id_producto"];
            $composicion->id_compuesto = $producto->id;
            $composicion->save();
        }

        // Configurar inventarios para las bodegas
        if(!$request->id && $producto->tipo != 'Servicio'){
            $bodegas = Bodega::all();
            foreach($bodegas as $bodega){
                $inventario = new Inventario;
                $inventario->id_producto    = $producto->id;
                $inventario->stock          = 0;
                $inventario->id_bodega    = $bodega->id;
                $inventario->save();
            }
        }
        ## se define el inventario del compuesto en la bodega seleccionada
        $inventarioInicial = Inventario::where('id_bodega', $request->id_bodega)->where('id_producto', $producto->id)->first();
        $inventarioInicial->stock = $request->stock;
        $inventarioInicial->save();

        DB::commit();

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


    public function import(ImportProductosRequest $request)
    {

        $import = new Productos();
        Excel::import($import, $request->file);

        return Response()->json($import->getRowCount(), 200);
    }

    public function importarWooCommerce(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ], [
            'file.required' => 'El archivo CSV de WooCommerce es obligatorio.',
            'file.mimes' => 'El archivo debe ser CSV, TXT, XLSX o XLS.',
        ]);

        try {
            $import = new WooCommerceProductosImport();
            Excel::import($import, $request->file('file'));
            $estadisticas = $import->getEstadisticas();

            return response()->json([
                'success' => true,
                'mensaje' => sprintf(
                    'Importación completada: %d creados, %d actualizados, %d omitidos.',
                    $estadisticas['creados'],
                    $estadisticas['actualizados'],
                    $estadisticas['omitidos']
                ),
                'creados' => $estadisticas['creados'],
                'actualizados' => $estadisticas['actualizados'],
                'omitidos' => $estadisticas['omitidos'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al importar productos desde WooCommerce: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al importar el archivo. Verifica que sea un CSV exportado desde WooCommerce.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    public function descargarPlantilla()
    {
        $filePath = public_path('docs/productos-format.xlsx');

        if (file_exists($filePath)) {
            return response()->download($filePath, 'productos-format.xlsx');
        } else {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }
    }

    public function importarAjustes(ImportarAjustesRequest $request)
    {

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


    public function ajusteMasivo(AjusteMasivoRequest $request)
    {

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

    public function trasladoMasivo(TrasladoMasivoRequest $request)
    {

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

    public function importarTrasladosMasivos(ImportarTrasladosMasivosRequest $request)
    {

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
    public function buscarModal(BuscarModalRequest $request)
    {

        $termino = $request->termino;
        $limite = $request->limite ?? 15;

        $empresa = Empresa::find($request->id_empresa);
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $productos = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'precios'])
            ->where(function ($q) use ($termino, $incluirComponenteQuimico) {
                $q->where('nombre', 'like', "%$termino%")
                    ->orWhere('codigo', 'like', "%$termino%")
                    ->orWhere('barcode', 'like', "%$termino%")
                    ->orWhere('etiquetas', 'like', "%$termino%")
                    ->orWhere('marca', 'like', "%$termino%")
                    ->orWhere('descripcion', 'like', "%$termino%");
                if ($incluirComponenteQuimico) {
                    $q->orWhere('componente_quimico', 'like', "%$termino%");
                }
            })
            ->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();

        return response()->json($productos, 200);
    }

    /**
     * Búsqueda por código de proveedor
     */
    public function buscarPorCodigoProveedor(BuscarPorCodigoProveedorRequest $request)
    {

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
    public function buscarPorNombre(BuscarPorNombreRequest $request)
    {

        $limite = $request->limite ?? 5;

        $empresa = Empresa::find($request->id_empresa);
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $productos = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'precios'])
            ->where(function ($q) use ($request, $incluirComponenteQuimico) {
                $q->where('nombre', 'like', "%{$request->nombre}%");
                if ($incluirComponenteQuimico) {
                    $q->orWhere('componente_quimico', 'like', "%{$request->nombre}%");
                }
            })
            ->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();

        return response()->json($productos, 200);
    }

    /**
     * Búsqueda de sugerencias con palabras clave
     */
    public function buscarSugerencias(BuscarSugerenciasRequest $request)
    {

        $limite = $request->limite ?? 10;
        $termino = $request->termino;
        $palabras = $request->palabras ?? [];

        $empresa = Empresa::find($request->id_empresa);
        $incluirComponenteQuimico = $empresa && $empresa->isComponenteQuimicoHabilitado();

        $query = Producto::where('enable', true)
            ->where('id_empresa', $request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->with(['inventarios', 'lotes', 'precios']);

        // Búsqueda principal por término completo
        $query->where(function ($q) use ($termino, $incluirComponenteQuimico) {
            $q->where('nombre', 'like', "%$termino%")
                ->orWhere('codigo', 'like', "%$termino%")
                ->orWhere('barcode', 'like', "%$termino%")
                ->orWhere('etiquetas', 'like', "%$termino%")
                ->orWhere('marca', 'like', "%$termino%")
                ->orWhere('descripcion', 'like', "%$termino%");
            if ($incluirComponenteQuimico) {
                $q->orWhere('componente_quimico', 'like', "%$termino%");
            }
        });

        // Si hay palabras específicas, buscar también por ellas
        if (!empty($palabras)) {
            $query->orWhere(function ($q) use ($palabras, $incluirComponenteQuimico) {
                foreach ($palabras as $palabra) {
                    if (strlen($palabra) > 2) {
                        $q->orWhere('nombre', 'like', "%$palabra%")
                            ->orWhere('descripcion', 'like', "%$palabra%")
                            ->orWhere('etiquetas', 'like', "%$palabra%");
                        if ($incluirComponenteQuimico) {
                            $q->orWhere('componente_quimico', 'like', "%$palabra%");
                        }
                    }
                }
            });
        }

        $productos = $query->orderBy('nombre', 'asc')
            ->take($limite)
            ->get();

        return response()->json($productos, 200);
    }

    public function importarShopify(ImportarShopifyRequest $request)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();

            $resultado = $this->shopifyImportService->importarProductos($request->all());

            if (!$resultado['success']) {
                $statusCode = isset($resultado['codigo_error']) && $resultado['codigo_error'] === 'IMPORTACION_YA_REALIZADA' ? 400 : 500;
                return response()->json($resultado, $statusCode, [], JSON_UNESCAPED_UNICODE);
            }

            return response()->json($resultado, 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            Log::error('Error al importar productos desde Shopify: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al importar productos: ' . $e->getMessage()
            ], 500);
        }
    }

    // Métodos movidos a ShopifyImportService y ProductoService

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

            // Procesar productos usando ShopifyImportService
            $resultado = $this->shopifyImportService->procesarProductosShopify(
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
                // Eliminar archivo físico si existe
                $rutaImagen = public_path('img' . $imagen->img);
                if (file_exists($rutaImagen)) {
                    unlink($rutaImagen);
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

            // Crear directorio si no existe
            $directorioProductos = public_path('img/productos');
            if (!file_exists($directorioProductos)) {
                mkdir($directorioProductos, 0755, true);
            }

            // Generar nombre único para la imagen
            $extension = pathinfo(parse_url($urlImagen, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = $extension ?: 'jpg'; // Default a jpg si no se puede determinar

            $nombreArchivo = 'producto_' . $producto->id . '_' . $index . '_' . time() . '.' . $extension;
            $rutaCompleta = $directorioProductos . '/' . $nombreArchivo;

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

            // Guardar archivo
            if (file_put_contents($rutaCompleta, $imagenContenido) === false) {
                Log::error("Error guardando archivo de imagen", [
                    'producto_id' => $producto->id,
                    'ruta_archivo' => $rutaCompleta,
                    'url_imagen' => $urlImagen
                ]);
                return;
            }

            // Guardar en base de datos
            $imagen = new \App\Models\Inventario\Imagen();
            $imagen->id_producto = $producto->id;
            $imagen->img = '/productos/' . $nombreArchivo;
            $imagen->shopify_image_id = $imagenShopify['id'] ?? null;
            $imagen->save();

            Log::info("Imagen descargada y guardada exitosamente", [
                'producto_id' => $producto->id,
                'nombre_archivo' => $nombreArchivo,
                'url_original' => $urlImagen,
                'shopify_image_id' => $imagenShopify['id'] ?? null,
                'tamaño_archivo' => filesize($rutaCompleta)
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

    // Métodos movidos a ProductoService, CategoriaService y ShopifyImportService
}
