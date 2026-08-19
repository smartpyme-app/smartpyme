<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use App\Support\Inventario\PosMenuCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo táctil para facturación POS (ventas).
 * Precios en tiles con IVA incluido (motor v2).
 */
class PosMenuVentasController extends Controller
{
    private const BUSCAR_LIMIT = 30;

    public function categorias(Request $request): JsonResponse
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return $this->sinEmpresa();
        }

        $categorias = PosMenuCatalog::queryCategoriasRaiz($idEmpresa)
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
        $subcategorias = PosMenuCatalog::querySubcategorias($idEmpresa, $categoria->id)->get();

        if (PosMenuCatalog::modoContenido($subcategorias->count()) === 'subcategorias') {
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
        $query = PosMenuCatalog::queryProductosDeCategoria($idEmpresa, $categoria->id);
        if ($incluir) {
            $query->with('presentaciones');
        }

        return response()->json([
            'modo' => 'productos',
            'items' => $this->mapProductosTiles($query->get(), $incluir),
        ]);
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
        $query = PosMenuCatalog::queryProductosDeSubcategoria($idEmpresa, $subcategoria->id);
        if ($incluir) {
            $query->with('presentaciones');
        }

        return response()->json($this->mapProductosTiles($query->get(), $incluir));
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
        $query = PosMenuCatalog::queryProductos($idEmpresa)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        if ($incluir) {
            $query->with('presentaciones');
        }

        return response()->json(
            $this->mapProductosTiles($query->limit(self::BUSCAR_LIMIT)->get(), $incluir)
        );
    }

    /** Producto completo para armar detalle v2 (impuestos, inventarios, lotes). */
    public function productoParaVenta(int $id, ProductosController $productosController): JsonResponse
    {
        return $productosController->read($id);
    }

    private function mapProductosTiles($productos, bool $incluirPresentaciones): array
    {
        $ivaEmpresa = $this->ivaEmpresa();

        return PosMenuCatalog::mapProductos(
            $productos,
            $incluirPresentaciones,
            fn (Producto $p, $precioSinIva) => $this->precioConIva($p, (float) $precioSinIva, $ivaEmpresa)
        );
    }

    private function precioConIva(Producto $p, float $precioSinIva, float $ivaEmpresa): float
    {
        $pct = self::resolverPorcentajeImpuesto($p->porcentaje_impuesto, $ivaEmpresa);

        return $pct > 0 ? round($precioSinIva * (1 + $pct / 100), 2) : round($precioSinIva, 2);
    }

    public static function resolverPorcentajeImpuesto($porcentajeProducto, float $ivaEmpresa): float
    {
        if ($porcentajeProducto !== null && $porcentajeProducto !== '') {
            return (float) $porcentajeProducto;
        }

        return $ivaEmpresa;
    }

    private function ivaEmpresa(): float
    {
        $idEmpresa = $this->idEmpresa();
        if (! $idEmpresa) {
            return 0.0;
        }
        $empresa = Empresa::find($idEmpresa);

        return $empresa ? (float) ($empresa->iva ?? 0) : 0.0;
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
