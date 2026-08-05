<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Categorias\SubCategoria;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo táctil de restaurante: categorías -> (subcategorías | productos).
 * No filtra por `genera_comanda`: los servicios (tipo Servicio) se listan junto a los productos.
 */
class PosMenuController extends Controller
{
    private const BUSCAR_LIMIT = 30;

    public function categorias(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $categorias = Categoria::where('id_empresa', $user->id_empresa)
            ->where('enable', true)
            ->select('categorias.*')
            ->selectSub(
                DB::table('categoria_subcategorias')
                    ->selectRaw('count(*)')
                    ->whereColumn('categoria_subcategorias.categoria_id', 'categorias.id'),
                'subcategorias_count'
            )
            ->orderBy('nombre')
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
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $categoria = Categoria::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $subcategoriasCount = SubCategoria::where('categoria_id', $categoria->id)->count();

        if ($subcategoriasCount > 0) {
            $items = SubCategoria::where('categoria_id', $categoria->id)
                ->orderBy('nombre')
                ->get()
                ->map(fn (SubCategoria $s) => [
                    'id' => $s->id,
                    'nombre' => $s->nombre,
                    'img' => $s->img,
                ]);

            return response()->json(['modo' => 'subcategorias', 'items' => $items]);
        }

        $productos = Producto::where('id_categoria', $categoria->id)
            ->where('enable', true)
            ->orderBy('nombre')
            ->get();

        return response()->json(['modo' => 'productos', 'items' => $this->mapProductos($productos)]);
    }

    public function productosSubcategoria(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        SubCategoria::where('id', $id)
            ->whereHas('categoria', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->firstOrFail();

        $productos = Producto::where('id_subcategoria', $id)
            ->where('enable', true)
            ->orderBy('nombre')
            ->get();

        return response()->json($this->mapProductos($productos));
    }

    public function buscar(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $productos = Producto::where('enable', true)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(self::BUSCAR_LIMIT)
            ->get();

        return response()->json($this->mapProductos($productos));
    }

    /**
     * @param Collection<int, Producto> $productos
     */
    private function mapProductos(Collection $productos): array
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
}
