<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Catálogo SP agrupado por SKU para vista previa y exportación variable a WooCommerce.
 */
class WooCommerceSpExportCatalogService
{
    /** @var WooCommerceTallaNombreParser */
    private $tallaParser;

    public function __construct(WooCommerceTallaNombreParser $tallaParser)
    {
        $this->tallaParser = $tallaParser;
    }

    /**
     * @return Collection<int, Producto>
     */
    public function productosElegibles(User $user, int $bodegaId, bool $soloConStock): Collection
    {
        $empresaId = $user->id_empresa;
        $query = Producto::where('id_empresa', $empresaId)
            ->where('enable', 1)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '');

        if ($soloConStock) {
            $idsConStock = Inventario::where('id_bodega', $bodegaId)
                ->where('stock', '>', 0)
                ->distinct()
                ->pluck('id_producto');
            $query->whereIn('id', $idsConStock);
        } else {
            $idsEnBodega = Inventario::where('id_bodega', $bodegaId)
                ->distinct()
                ->pluck('id_producto');
            $query->whereIn('id', $idsEnBodega);
        }

        return $query->orderBy('codigo')->orderBy('nombre')->get();
    }

    /**
     * @param Collection<int, Producto> $productos
     * @param array<int, float|int> $stocks id_producto => stock
     * @return list<array{sku: string, nombre_padre: string, tipo: string, variantes: list<array<string, mixed>>}>
     */
    public function buildPreviewGroups(Collection $productos, array $stocks): array
    {
        $out = [];
        foreach ($this->groupForExport($productos) as $group) {
            $out[] = $this->mapGroupToPreview($group, $stocks);
        }

        return $out;
    }

    /**
     * @param Collection<int, Producto> $productos
     * @return list<array{sku: string, items: Collection<int, Producto>, variable: bool}>
     */
    public function groupForExport(Collection $productos): array
    {
        $bySku = [];
        foreach ($productos as $producto) {
            $sku = trim((string) $producto->codigo);
            if ($sku === '') {
                continue;
            }
            if (!isset($bySku[$sku])) {
                $bySku[$sku] = collect();
            }
            $bySku[$sku]->push($producto);
        }

        $groups = [];
        foreach ($bySku as $sku => $items) {
            $variable = $this->shouldExportAsVariable($items);
            $groups[] = [
                'sku' => $sku,
                'items' => $items,
                'variable' => $variable,
            ];
        }

        return $groups;
    }

    /**
     * @param Collection<int, Producto> $items
     */
    public function shouldExportAsVariable(Collection $items): bool
    {
        if ($items->count() < 2) {
            return false;
        }
        $withTalla = 0;
        foreach ($items as $p) {
            if ($this->tallaParser->parse((string) $p->nombre)['talla'] !== null) {
                $withTalla++;
            }
        }

        return $withTalla >= 2;
    }

    public function nombrePadreParaGrupo(Collection $items, string $sku): string
    {
        foreach ($items as $p) {
            $nc = trim((string) ($p->nombre_comercial ?? ''));
            if ($nc !== '') {
                return $nc;
            }
        }
        $first = $items->first();
        if ($first) {
            $parsed = $this->tallaParser->parse((string) $first->nombre);
            if ($parsed['base'] !== '') {
                return $parsed['base'];
            }
        }

        return $sku;
    }

    /**
     * @param array{sku: string, items: Collection<int, Producto>, variable: bool} $group
     * @param array<int, float|int> $stocks
     * @return array{sku: string, nombre_padre: string, tipo: string, variantes: list<array<string, mixed>>}
     */
    private function mapGroupToPreview(array $group, array $stocks): array
    {
        /** @var Collection<int, Producto> $items */
        $items = $group['items'];
        $sku = $group['sku'];
        $variable = $group['variable'];
        $nombrePadre = $this->nombrePadreParaGrupo($items, $sku);

        $variantes = [];
        foreach ($items as $p) {
            $parsed = $this->tallaParser->parse((string) $p->nombre);
            $variantes[] = [
                'producto_id' => $p->id,
                'nombre_sp' => $p->nombre,
                'talla' => $parsed['talla'],
                'stock' => (float) ($stocks[$p->id] ?? 0),
                'precio' => (float) $p->precio,
            ];
        }

        return [
            'sku' => $sku,
            'nombre_padre' => $nombrePadre,
            'tipo' => $variable ? 'variable' : 'simple',
            'variantes' => $variantes,
        ];
    }

    /**
     * @return array<int, float>
     */
    public function stocksPorBodega(Collection $productos, int $bodegaId): array
    {
        if ($productos->isEmpty()) {
            return [];
        }

        return Inventario::where('id_bodega', $bodegaId)
            ->whereIn('id_producto', $productos->pluck('id'))
            ->select('id_producto', \Illuminate\Support\Facades\DB::raw('SUM(stock) as total_stock'))
            ->groupBy('id_producto')
            ->pluck('total_stock', 'id_producto')
            ->map(function ($v) {
                return (float) $v;
            })
            ->all();
    }
}
