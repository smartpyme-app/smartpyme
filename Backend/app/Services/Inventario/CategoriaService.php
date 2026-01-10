<?php

namespace App\Services\Inventario;

use App\Models\Inventario\Categorias\Categoria;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CategoriaService
{
    /**
     * Obtiene o crea una categoría para un producto
     *
     * @param array $productoData
     * @param int $idEmpresa
     * @return Categoria
     */
    public function obtenerOCrear(array $productoData, int $idEmpresa): Categoria
    {
        // Cache key para evitar consultas repetitivas durante importación masiva
        $cacheKey = "categoria_general_empresa_{$idEmpresa}";

        // Intentar obtener del cache primero
        $categoria = Cache::remember($cacheKey, 300, function () use ($idEmpresa) {
            // Por ahora, usar categoría "General" para todos los productos de Shopify
            // En el futuro se puede implementar lógica para crear categorías basadas en product_type
            $categoria = Categoria::where('nombre', 'General')
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$categoria) {
                $categoria = new Categoria();
                $categoria->nombre = 'General';
                $categoria->descripcion = 'Categoría general para productos importados';
                $categoria->enable = true;
                $categoria->id_empresa = $idEmpresa;
                $categoria->save();

                Log::info("Categoría 'General' creada para empresa", [
                    'id_empresa' => $idEmpresa,
                    'categoria_id' => $categoria->id
                ]);
            }

            return $categoria;
        });

        return $categoria;
    }
}

