<?php

namespace App\Support\Inventario;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use App\Support\Restaurante\PresentacionPos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queries y mapeo compartidos entre catálogo POS restaurante y POS facturación.
 */
final class PosMenuCatalog
{
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
    public static function mapProductos(Collection $productos, bool $incluirPresentaciones = false, ?callable $mapPrecio = null): array
    {
        $out = [];
        foreach ($productos as $p) {
            $out[] = self::mapFichaProducto($p, null, null, null, $mapPrecio);
            if (! $incluirPresentaciones) {
                continue;
            }
            foreach ($p->presentaciones ?? [] as $pres) {
                $out[] = self::mapFichaProducto(
                    $p,
                    (int) $pres->id,
                    PresentacionPos::nombreMostrar($pres->nombre_comercial ?? null, $p->nombre),
                    $pres->precio_venta,
                    $mapPrecio
                );
            }
        }

        return $out;
    }

    private static function mapFichaProducto(Producto $p, ?int $idPresentacion, ?string $nombre, $precio, ?callable $mapPrecio = null): array
    {
        $precioBase = $precio ?? $p->precio;
        $precioTile = $mapPrecio ? $mapPrecio($p, $precioBase) : $precioBase;

        return [
            'id' => $p->id,
            'id_presentacion' => $idPresentacion,
            'nombre' => $nombre ?? $p->nombre,
            'precio' => $precioTile,
            'img' => $p->img,
            'tipo' => $p->tipo,
            'genera_comanda' => (bool) $p->genera_comanda,
        ];
    }
}
