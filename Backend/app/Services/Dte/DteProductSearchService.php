<?php

namespace App\Services\Dte;

use App\Models\Inventario\Producto;

class DteProductSearchService
{
    /**
     * Find product by description (fuzzy match).
     * Searches in nombre, descripcion, descripcion_completa, codigo.
     *
     * @param string $description
     * @param int $idEmpresa
     * @return int|null Product ID or null if not found
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
            ->where(function ($q) use ($term, $termLike) {
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
     * Check if all DTE items can be matched to products.
     *
     * @param array $items [['descripcion' => '...'], ...]
     * @param int $idEmpresa
     * @return array{all_matched: bool, unmatched: array}
     */
    public function checkItemsMatch(array $items, int $idEmpresa): array
    {
        $unmatched = [];

        foreach ($items as $index => $item) {
            $desc = $item['descripcion'] ?? '';
            if (empty($desc)) {
                continue;
            }
            $productId = $this->findProductByDescription($desc, $idEmpresa);
            if ($productId === null) {
                $unmatched[] = ['index' => $index, 'descripcion' => $desc];
            }
        }

        return [
            'all_matched' => count($unmatched) === 0,
            'unmatched' => $unmatched,
        ];
    }
}
