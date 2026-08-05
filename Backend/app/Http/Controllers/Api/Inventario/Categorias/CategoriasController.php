<?php

namespace App\Http\Controllers\Api\Inventario\Categorias;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Admin\Funcionalidad;
use App\Models\Admin\EmpresaFuncionalidad;

use App\Exports\CategoriasExport;
use App\Imports\Categorias;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManagerStatic as Image;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Inventario\Categorias\StoreCategoriaRequest;
use App\Http\Requests\Inventario\Categorias\ImportCategoriasRequest;
use App\Http\Requests\Inventario\Categorias\HistorialVentasCategoriaRequest;
use App\Http\Requests\Inventario\Categorias\HistorialComprasCategoriaRequest;

class CategoriasController extends Controller
{

    public function index(Request $request)
    {
        try {
            $tieneContabilidad = $this->tieneContabilidadHabilitada();
            
            $query = Categoria::query();
            
            // Solo cargar cuentas si tiene contabilidad habilitada
            if ($tieneContabilidad) {
                $query->with(['cuentas' => function($q) use ($request) {
                    if ($request->id_sucursal) {
                        $q->where('id_sucursal', $request->id_sucursal);
                    }
                }]);
                
                // Solo filtrar por cuentas si tiene contabilidad
                if ($request->id_sucursal) {
                    $query->when($request->id_sucursal, function ($q) use ($request) {
                        $q->whereHas('cuentas', function ($subQ) use ($request) {
                            $subQ->where('id_sucursal', $request->id_sucursal);
                        });
                    });
                }
            }
            
            $categorias = $query
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
        $tieneContabilidad = $this->tieneContabilidadHabilitada();
        
        $query = Categoria::query();
        
        // Solo cargar cuentas si tiene contabilidad habilitada
        if ($tieneContabilidad) {
            $query->with('cuentas');
        }
        
        $categoria = $query->findOrFail($id);
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


    public function store(StoreCategoriaRequest $request)
    {
        if ($request->id) {
            $categoria = Categoria::findOrFail($request->id);
        } else {
            $categoria = new Categoria;
        }

        $categoria->fill($request->except(['file', 'quitar_img']));

        if ($request->boolean('quitar_img')) {
            $this->deleteCategoriaImgFile($categoria->img);
            $categoria->img = null;
        } elseif ($request->hasFile('file')) {
            $categoria->img = $this->handleCategoriaImgUpload($request, $categoria);
        }

        $categoria->save();

        return Response()->json($categoria, 200);
    }

    private function handleCategoriaImgUpload(Request $request, Categoria $categoria): string
    {
        $this->deleteCategoriaImgFile($categoria->img);

        $file = $request->file('file');
        if (! $file) {
            throw new \RuntimeException('No se recibió el archivo de imagen.');
        }

        // Mismo patrón que ImagenesController / logos: encode + write bajo public/img.
        // Prefijo en productos/: esa carpeta ya es servible en producción (default.jpg de categorias
        // existe, pero uploads nuevos a categorias/ no aparecen detrás de nginx — multi-nodo o path).
        $resize = Image::make($file)->resize(750, 750, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode('jpg', 75);
        $hash = md5($resize->__toString());
        $relative = 'productos/categoria_'.$hash.'.jpg';
        $fullPath = public_path('img/'.$relative);
        $dir = dirname($fullPath);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de imágenes.');
        }

        // Intervention save a veces no falla con excepción si el path no es el docroot real;
        // escribir bytes y verificar evita guardar la ruta en BD sin archivo servible.
        $bytes = $resize->__toString();
        if (@file_put_contents($fullPath, $bytes) === false) {
            throw new \RuntimeException('No se pudo guardar la imagen de la categoría en disco.');
        }
        @chmod($fullPath, 0644);

        if (! is_file($fullPath) || filesize($fullPath) < 1) {
            throw new \RuntimeException('La imagen no quedó disponible en: '.$fullPath);
        }

        // Sin slash inicial: el FE arma `{api}/img/{img}` (igual que productos).
        return $relative;
    }

    private function deleteCategoriaImgFile(?string $img): void
    {
        $path = ltrim((string) $img, '/');
        if ($path === '' || str_ends_with($path, 'default.jpg') || str_ends_with($path, 'default.png')) {
            return;
        }
        // Solo borrar fotos de categoría que hayamos creado nosotros.
        if (! str_starts_with($path, 'categorias/') && ! str_starts_with($path, 'productos/categoria_')) {
            return;
        }
        $full = public_path('img/'.$path);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function delete($id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return Response()->json($categoria, 201);

    }


    public function historialVentas(HistorialVentasCategoriaRequest $request) {

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

    public function historialCompras(HistorialComprasCategoriaRequest $request) {

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


    public function import(ImportCategoriasRequest $request){

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

    /**
     * Verifica si la empresa del usuario tiene contabilidad habilitada
     */
    private function tieneContabilidadHabilitada(): bool
    {
        try {
            $idEmpresa = auth()->user()->id_empresa ?? null;
            
            if (!$idEmpresa) {
                return false;
            }

            $funcionalidad = Funcionalidad::where('slug', 'contabilidad')->first();
            
            if (!$funcionalidad) {
                return false;
            }

            return EmpresaFuncionalidad::where('id_empresa', $idEmpresa)
                ->where('id_funcionalidad', $funcionalidad->id)
                ->where('activo', 1)
                ->exists();
        } catch (\Exception $e) {
            Log::error('Error al verificar acceso a contabilidad: ' . $e->getMessage());
            return false;
        }
    }


}
