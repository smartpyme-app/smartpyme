<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Services\ShopifyApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillShopifyVariantsCommand extends Command
{
    protected $signature = 'shopify:backfill-variantes {--empresa=} {--dry-run} {--with-names}';

    protected $description = 'Rellena columnas de variantes Shopify (shopify_sku, option*_value/name) en productos existentes';

    public function handle(): int
    {
        $empresaId = $this->option('empresa');
        $dryRun = (bool) $this->option('dry-run');
        $withNames = (bool) $this->option('with-names');

        $query = Producto::withoutGlobalScopes()
            ->whereNotNull('shopify_product_id');

        if ($empresaId) {
            $query->where('id_empresa', $empresaId);
        }

        $productos = $query->orderBy('id')->get();

        if ($productos->isEmpty()) {
            $this->info('No hay productos con shopify_product_id para procesar.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Procesando %d productos%s...',
            $productos->count(),
            $dryRun ? ' (dry-run, sin cambios)' : ''
        ));

        $clientesShopify = [];

        $stats = ['sku' => 0, 'opciones' => 0, 'nombres' => 0];

        foreach ($productos as $producto) {
            $empresa = (int) $producto->id_empresa;

            $cambios = [];

            // 1) shopify_sku = SKU de Shopify (igual a codigo). Si codigo está vacío, se deja vacío.
            if (empty($producto->shopify_sku) && !empty($producto->codigo)) {
                $cambios['shopify_sku'] = $producto->codigo;
                $stats['sku']++;
            }

            // 2) option*_value desde nombre_variante (best-effort, secuencial)
            $sinValores = empty($producto->option1_value)
                && empty($producto->option2_value)
                && empty($producto->option3_value);

            if ($sinValores && !empty($producto->nombre_variante)) {
                $partes = array_values(array_filter(
                    array_map('trim', explode(' - ', $producto->nombre_variante)),
                    function ($p) {
                        return $p !== '';
                    }
                ));

                if (!empty($partes)) {
                    $cambios['option1_value'] = $partes[0] ?? null;
                    $cambios['option2_value'] = $partes[1] ?? null;
                    $cambios['option3_value'] = $partes[2] ?? null;
                    $stats['opciones']++;
                }
            }

            // 3) option*_name desde Shopify (solo con --with-names)
            if ($withNames && empty($producto->option1_name) && empty($producto->option2_name)) {
                $nombres = $this->obtenerNombresOpciones($producto, $empresa, $clientesShopify);
                if ($nombres !== null) {
                    $cambios['option1_name'] = $nombres[1] ?? null;
                    $cambios['option2_name'] = $nombres[2] ?? null;
                    $cambios['option3_name'] = $nombres[3] ?? null;
                    $stats['nombres']++;
                }
            }

            if (!empty($cambios)) {
                if (!$dryRun) {
                    $producto->update($cambios);
                }
                $this->line(sprintf(
                    '  [%s] id=%d %s',
                    $dryRun ? 'dry-run' : 'ok',
                    $producto->id,
                    implode(', ', array_keys($cambios))
                ));
            }
        }

        $this->info(sprintf(
            'Completado. sku_asignados=%d opciones_rellenadas=%d nombres_rellenados=%d',
            $stats['sku'],
            $stats['opciones'],
            $stats['nombres']
        ));

        return self::SUCCESS;
    }

    /**
     * Obtiene los nombres de opción desde la API de Shopify (product.options[]).
     */
    private function obtenerNombresOpciones(Producto $producto, int $empresaId, array &$clientes): ?array
    {
        try {
            if (!isset($clientes[$empresaId])) {
                $empresa = Empresa::withoutGlobalScope('empresa')->find($empresaId);
                if (!$empresa || empty($empresa->shopify_store_url) || empty($empresa->shopify_consumer_secret)) {
                    $clientes[$empresaId] = null;
                    return null;
                }
                $clientes[$empresaId] = new ShopifyApiClient(
                    $empresa->shopify_store_url,
                    $empresa->shopify_consumer_secret
                );
            }

            $client = $clientes[$empresaId];
            if ($client === null) {
                return null;
            }

            $response = $client->get("products/{$producto->shopify_product_id}.json");
            $options = $response['body']['product']['options'] ?? [];

            $nombres = [];
            foreach ($options as $option) {
                $pos = (int) ($option['position'] ?? 0);
                if ($pos >= 1 && $pos <= 3) {
                    $nombres[$pos] = $option['name'] ?? null;
                }
            }

            return $nombres;
        } catch (\Exception $e) {
            Log::warning('BackfillShopifyVariants: error obteniendo nombres de opción', [
                'producto_id' => $producto->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
