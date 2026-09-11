<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Support\Inventario\PosMenuCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo táctil de restaurante: categorías -> (subcategorías | productos).
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

        $incluir = $this->incluirPresentaciones();
        $query = self::queryProductosDeCategoria($idEmpresa, $categoria->id);
        if ($incluir) {
            $query->with('presentaciones');
        }
        $productos = $query->get();

        return response()->json(['modo' => 'productos', 'items' => self::mapProductos($productos, $incluir)]);
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

        $incluir = $this->incluirPresentaciones();
        $query = self::queryProductosDeSubcategoria($idEmpresa, $subcategoria->id);
        if ($incluir) {
            $query->with('presentaciones');
        }
        $productos = $query->get();

        return response()->json(self::mapProductos($productos, $incluir));
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

        $incluir = $this->incluirPresentaciones();
        $query = self::queryProductos($idEmpresa)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            });
        if ($incluir) {
            $query->with('presentaciones');
        }
        $productos = $query->limit(self::BUSCAR_LIMIT)->get();

        return response()->json(self::mapProductos($productos, $incluir));
    }

    public static function queryCategoriasRaiz(int $idEmpresa): Builder
    {
        return PosMenuCatalog::queryCategoriasRaiz($idEmpresa, true);
    }

    public static function querySubcategorias(int $idEmpresa, int $idCategoria): Builder
    {
        return PosMenuCatalog::querySubcategorias($idEmpresa, $idCategoria, true);
    }

    public static function queryProductosDeCategoria(int $idEmpresa, int $idCategoria): Builder
    {
        return PosMenuCatalog::queryProductosDeCategoria($idEmpresa, $idCategoria, true);
    }

    public static function queryProductosDeSubcategoria(int $idEmpresa, int $idSubcategoria): Builder
    {
        return PosMenuCatalog::queryProductosDeSubcategoria($idEmpresa, $idSubcategoria, true);
    }

    public static function queryProductos(int $idEmpresa): Builder
    {
        return PosMenuCatalog::queryProductos($idEmpresa, true);
    }

    public static function modoContenido(int $subcategoriasCount): string
    {
        return PosMenuCatalog::modoContenido($subcategoriasCount);
    }

    /**
     * @param Collection<int, \App\Models\Inventario\Producto> $productos
     */
    public static function mapProductos(Collection $productos, bool $incluirPresentaciones = false): array
    {
        return PosMenuCatalog::mapProductos($productos, $incluirPresentaciones);
    }

    private function incluirPresentaciones(): bool
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return false;
        }
        $empresa = Empresa::find($idEmpresa);

        return $empresa ? $empresa->isModuloPresentaciones() : false;
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
