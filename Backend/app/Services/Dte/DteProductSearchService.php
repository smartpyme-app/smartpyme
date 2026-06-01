<?php

namespace App\Services\Dte;

use App\Models\Inventario\Producto;
use App\Services\Inventario\ProductoImportacionDteService;

class DteProductSearchService
{
    public function __construct(
        protected ProductoImportacionDteService $productoImportacionDteService
    ) {
    }

    /**
     * Find product by description (fuzzy match).
     * Kept for backwards compatibility; prefer resolverImportacionDte for DTE items.
     */
    public function findProductByDescription(string $description, int $idEmpresa): ?int
    {
        if (empty(trim($description))) {
            return null;
        }

        $term = trim($description);
        $termLike = '%' . preg_replace('/\s+/', '%', $term) . '%';

        $product = Producto::withoutGlobalScopes()
            ->where('id_empresa', $idEmpresa)
            ->where(function ($q) use ($termLike) {
                $q->where('nombre', 'like', $termLike)
                    ->orWhere('descripcion', 'like', $termLike)
                    ->orWhere('descripcion_completa', 'like', $termLike)
                    ->orWhere('codigo', 'like', $termLike);
            })
            ->where('enable', '1')
            ->first();

        return $product?->id;
    }

    /**
     * Resolve DTE line items to products (same logic as compras JSON import).
     *
     * @param array $items cuerpoDocumento items
     * @return array{all_matched: bool, unmatched: array, resolved: array}
     */
    public function resolveItems(array $items, int $idEmpresa): array
    {
        $payload = [];
        foreach ($items as $index => $item) {
            $payload[] = [
                'numItem' => $item['numItem'] ?? ($index + 1),
                'codigo' => $item['codigo'] ?? null,
                'descripcion' => $item['descripcion'] ?? '',
            ];
        }

        $resultados = $this->productoImportacionDteService->resolverImportacionDte($idEmpresa, $payload);
        $unmatched = [];
        $resolved = [];

        foreach ($resultados as $index => $resultado) {
            $producto = $resultado['producto'] ?? null;
            if ($producto) {
                $resolved[$index] = $producto;
            } else {
                $desc = $items[$index]['descripcion'] ?? '';
                if (!empty(trim($desc))) {
                    $unmatched[] = [
                        'index' => $index,
                        'descripcion' => $desc,
                        'codigo' => $items[$index]['codigo'] ?? null,
                    ];
                }
            }
        }

        return [
            'all_matched' => count($unmatched) === 0,
            'unmatched' => $unmatched,
            'resolved' => $resolved,
        ];
    }

    /**
     * Check if all DTE items can be matched to products.
     *
     * @param array $items [['descripcion' => '...', 'codigo' => '...'], ...]
     * @param int $idEmpresa
     * @return array{all_matched: bool, unmatched: array}
     */
    public function checkItemsMatch(array $items, int $idEmpresa): array
    {
        $result = $this->resolveItems($items, $idEmpresa);

        return [
            'all_matched' => $result['all_matched'],
            'unmatched' => $result['unmatched'],
        ];
    }
}
