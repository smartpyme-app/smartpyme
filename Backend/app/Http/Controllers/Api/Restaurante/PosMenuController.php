<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo táctil de restaurante: categorías -> (subcategorías | productos).
 *
 * Las subcategorías no tienen tabla propia: son filas de `categorias` con
 * `subcategoria = 1` e `id_cate_padre` apuntando a la categoría raíz, y los
 * productos las referencian por `id_subcategoria` (ver App\Imports\Productos
 * y CategoriasController@subcategorias).
 * No filtra por `genera_comanda`: los servicios (tipo Servicio) se listan junto
 * a los productos.
 */
class PosMenuController extends Controller
{
    private const BUSCAR_LIMIT = 30;

    public function categorias(Request $request): JsonResponse
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return $this->sinEmpresa();
        }

        $categorias = self::queryCategoriasRaiz($idEmpresa)
            ->get()
            ->map(fn (Categoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'img' => $c->img,
                'subcategorias_count' => (int) $c->subcategorias_count,
            ]);

        return response()->json($categorias);
    }

    public function contenidoCategoria(Request $request, int $id): JsonResponse
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return $this->sinEmpresa();
        }

        $categoria = Categoria::where('id_empresa', $idEmpresa)->findOrFail($id);

        $subcategorias = self::querySubcategorias($idEmpresa, $categoria->id)->get();

        if (self::modoContenido($subcategorias->count()) === 'subcategorias') {
            return response()->json([
                'modo' => 'subcategorias',
                'items' => $subcategorias->map(fn (Categoria $s) => [
                    'id' => $s->id,
                    'nombre' => $s->nombre,
                    'img' => $s->img,
                ])->values(),
            ]);
        }

        $productos = self::queryProductosDeCategoria($idEmpresa, $categoria->id)->get();

        return response()->json(['modo' => 'productos', 'items' => self::mapProductos($productos)]);
    }

    public function productosSubcategoria(Request $request, int $id): JsonResponse
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return $this->sinEmpresa();
        }

        $subcategoria = Categoria::where('id_empresa', $idEmpresa)
            ->where('subcategoria', 1)
            ->findOrFail($id);

        $productos = self::queryProductosDeSubcategoria($idEmpresa, $subcategoria->id)->get();

        return response()->json(self::mapProductos($productos));
    }

    public function buscar(Request $request): JsonResponse
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return $this->sinEmpresa();
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $productos = self::queryProductos($idEmpresa)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            })
            ->limit(self::BUSCAR_LIMIT)
            ->get();

        return response()->json(self::mapProductos($productos));
    }

    /** Categorías raíz de la empresa con cuántas subcategorías activas tienen. */
    public static function queryCategoriasRaiz(int $idEmpresa): Builder
    {
        return Categoria::query()
            ->where('id_empresa', $idEmpresa)
            ->where('enable', true)
            ->where('subcategoria', 0)
            ->withCount([
                'subcategorias' => fn (Builder $q) => $q
                    ->where('id_empresa', $idEmpresa)
                    ->where('enable', true),
            ])
            ->orderBy('nombre');
    }

    public static function querySubcategorias(int $idEmpresa, int $idCategoria): Builder
    {
        return Categoria::query()
            ->where('id_empresa', $idEmpresa)
            ->where('enable', true)
            ->where('subcategoria', 1)
            ->where('id_cate_padre', $idCategoria)
            ->orderBy('nombre');
    }

    /**
     * Productos colgados directamente de la categoría. Solo se usa cuando la
     * categoría no tiene subcategorías activas, así que no hay riesgo de
     * duplicar los productos que cuelgan de una subcategoría (esos también
     * llevan `id_categoria` del padre).
     */
    public static function queryProductosDeCategoria(int $idEmpresa, int $idCategoria): Builder
    {
        return self::queryProductos($idEmpresa)->where('id_categoria', $idCategoria);
    }

    public static function queryProductosDeSubcategoria(int $idEmpresa, int $idSubcategoria): Builder
    {
        return self::queryProductos($idEmpresa)->where('id_subcategoria', $idSubcategoria);
    }

    /** `imagenes` va eager-loaded porque el accessor `img` de Producto la usa. */
    public static function queryProductos(int $idEmpresa): Builder
    {
        return Producto::query()
            ->with('imagenes')
            ->where('id_empresa', $idEmpresa)
            ->where('enable', true)
            ->orderBy('nombre');
    }

    public static function modoContenido(int $subcategoriasCount): string
    {
        return $subcategoriasCount > 0 ? 'subcategorias' : 'productos';
    }

    /**
     * @param Collection<int, Producto> $productos
     */
    public static function mapProductos(Collection $productos): array
    {
        return $productos->map(fn (Producto $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'precio' => $p->precio,
            'img' => $p->img,
            'tipo' => $p->tipo,
            'genera_comanda' => (bool) $p->genera_comanda,
        ])->all();
    }

    private function idEmpresa(): ?int
    {
        $user = auth()->user();

        return $user && $user->id_empresa ? (int) $user->id_empresa : null;
    }

    private function sinEmpresa(): JsonResponse
    {
        return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
    }
}
