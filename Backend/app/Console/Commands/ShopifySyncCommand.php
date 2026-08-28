<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyTransformer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ShopifySyncCommand extends Command
{
    protected $signature = 'shopify:sync-productos
        {--empresa= : ID de la empresa}
        {--bodega= : ID de bodega para el stock (default: primera bodega activa)}
        {--desactivar-ausentes : Desactivar variantes locales que ya no existen en Shopify}
        {--sin-kardex : No registrar movimientos de kardex durante la sincronización}
        {--dry-run : Solo reporta sin escribir}';

    protected $description = 'Sincroniza productos y stock desde Shopify hacia SmartPyme (upsert + dedupe por variant_id)';

    private const PAGE_SIZE = 250;

    public function handle(ShopifyTransformer $transformer): int
    {
        $empresaId = (int) $this->option('empresa');
        $dryRun = (bool) $this->option('dry-run');
        $desactivarAusentes = (bool) $this->option('desactivar-ausentes');
        $registrarKardex = !(bool) $this->option('sin-kardex');

        if (!$empresaId) {
            $this->error('Debes indicar --empresa=<id>.');
            return self::FAILURE;
        }

        $empresa = Empresa::withoutGlobalScope('empresa')->find($empresaId);
        if (!$empresa || empty($empresa->shopify_store_url) || empty($empresa->shopify_consumer_secret)) {
            $this->error('Empresa sin configuración Shopify (shopify_store_url / shopify_consumer_secret).');
            return self::FAILURE;
        }

        $client = new ShopifyApiClient(
            rtrim($empresa->shopify_store_url, '/'),
            $empresa->shopify_consumer_secret
        );

        $bodega = $this->resolverBodega($empresaId);
        if (!$bodega) {
            $this->error('No se encontró bodega activa para la empresa.');
            return self::FAILURE;
        }

        $usuario = User::withoutGlobalScopes()->where('id_empresa', $empresaId)->first();
        $idUsuario = $usuario ? $usuario->id : null;
        $idSucursal = $usuario ? $usuario->id_sucursal : null;

        $this->info(sprintf(
            'Sincronizando productos Shopify -> SmartPyme (empresa=%d, bodega=%d)%s',
            $empresaId,
            $bodega->id,
            $dryRun ? ' [dry-run]' : ''
        ));

        $productos = $this->obtenerTodosLosProductos($client);
        $this->info('Productos obtenidos de Shopify: ' . count($productos));

        $stats = ['creados' => 0, 'actualizados' => 0, 'stock' => 0, 'variantes' => 0, 'kardex' => 0];
        $vistos = [];

        foreach ($productos as $shopifyProduct) {
            $variantes = $transformer->transformarProductoDesdeShopify(
                $shopifyProduct,
                $empresaId,
                $idUsuario,
                $idSucursal,
                true,  // incluirDrafts
                true   // esImportacionMasiva (evita ciclos)
            );

            foreach ($variantes as $data) {
                $stock = (int) ($data['_stock'] ?? 0);
                unset($data['_stock'], $data['_id_usuario'], $data['_id_sucursal']);

                $variantId = $data['shopify_variant_id'] ?? null;
                if ($variantId) {
                    $vistos[$variantId] = true;
                }

                if (!$dryRun) {
                    $this->upsertVariante($empresaId, $data, $stock, $bodega->id, $idUsuario, $registrarKardex, $stats);
                } else {
                    $this->contarStats($empresaId, $data, $stats);
                }
            }
        }

        // Desactivar variantes locales que ya no están en Shopify
        if ($desactivarAusentes && !$dryRun) {
            $desactivadas = $this->desactivarAusentes($empresaId, array_keys($vistos));
            $this->info("Variantes desactivadas (ausentes en Shopify): {$desactivadas}");
        }

        $this->info(sprintf(
            'Completado. variantes=%d creados=%d actualizados=%d stock_actualizados=%d kardex=%d',
            $stats['variantes'],
            $stats['creados'],
            $stats['actualizados'],
            $stats['stock'],
            $stats['kardex']
        ));

        return self::SUCCESS;
    }

    private function obtenerTodosLosProductos(ShopifyApiClient $client): array
    {
        $productos = [];
        $page = 1;

        do {
            $response = $client->get('products.json', [
                'limit' => self::PAGE_SIZE,
                'page' => $page,
            ]);

            $batch = $response['body']['products'] ?? [];
            if (!is_array($batch) || count($batch) === 0) {
                break;
            }

            $productos = array_merge($productos, $batch);

            if (count($batch) < self::PAGE_SIZE) {
                break;
            }
            $page++;
        } while ($page <= 1000);

        return $productos;
    }

    private function upsertVariante(int $empresaId, array $data, int $stock, int $bodegaId, ?int $idUsuario, bool $registrarKardex, array &$stats): void
    {
        Model::withoutEvents(function () use ($empresaId, $data, $stock, $bodegaId, $idUsuario, $registrarKardex, &$stats) {
            $variantId = $data['shopify_variant_id'] ?? null;

            $producto = null;
            if ($variantId) {
                $producto = Producto::withoutGlobalScopes()
                    ->where('id_empresa', $empresaId)
                    ->where('shopify_variant_id', $variantId)
                    ->first();
            }

            if (!$producto && !empty($data['shopify_sku'])) {
                $producto = Producto::withoutGlobalScopes()
                    ->where('id_empresa', $empresaId)
                    ->where('shopify_sku', $data['shopify_sku'])
                    ->first();
            }

            if ($producto) {
                $producto->update($data);
                $stats['actualizados']++;
            } else {
                $data['id_empresa'] = $empresaId;
                $producto = Producto::create($data);
                $stats['creados']++;
            }

            // Stock por variante
            $inventario = Inventario::withoutGlobalScopes()
                ->where('id_producto', $producto->id)
                ->where('id_bodega', $bodegaId)
                ->first();

            $stockAnterior = $inventario ? (int) $inventario->stock : 0;

            if ($inventario) {
                if ((int) $inventario->stock !== $stock) {
                    $inventario->stock = $stock;
                    $inventario->save();
                    $stats['stock']++;
                }
            } else {
                $inventario = Inventario::create([
                    'id_producto' => $producto->id,
                    'id_bodega' => $bodegaId,
                    'stock' => $stock,
                    'stock_minimo' => 0,
                    'stock_maximo' => 1000,
                ]);
                $stats['stock']++;
            }

            // Reconciliar kardex por el delta (solo si el stock cambió)
            $delta = $stock - $stockAnterior;
            if ($registrarKardex && $delta != 0 && $inventario) {
                $inventario->kardex($producto, $delta, $producto->precio, $producto->costo, null, [
                    'origen' => 'shopify',
                    'id_usuario' => $idUsuario,
                ]);
                $stats['kardex']++;
            }

            $stats['variantes']++;
        });
    }

    private function contarStats(int $empresaId, array $data, array &$stats): void
    {
        $variantId = $data['shopify_variant_id'] ?? null;
        $existe = $variantId
            ? Producto::withoutGlobalScopes()
                ->where('id_empresa', $empresaId)
                ->where('shopify_variant_id', $variantId)
                ->exists()
            : false;

        if ($existe) {
            $stats['actualizados']++;
        } else {
            $stats['creados']++;
        }
        $stats['variantes']++;
    }

    private function desactivarAusentes(int $empresaId, array $vistos): int
    {
        if (empty($vistos)) {
            return 0;
        }

        $locales = Producto::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->whereNotNull('shopify_variant_id')
            ->where('enable', '!=', '0')
            ->get(['id', 'shopify_variant_id', 'enable']);

        $count = 0;
        foreach ($locales as $producto) {
            if (!isset($vistos[$producto->shopify_variant_id])) {
                Model::withoutEvents(function () use ($producto) {
                    $producto->enable = '0';
                    $producto->save();
                });
                $count++;
            }
        }

        return $count;
    }

    private function resolverBodega(int $empresaId): ?Bodega
    {
        $bodegaId = (int) $this->option('bodega');
        if ($bodegaId) {
            return Bodega::where('id_empresa', $empresaId)->where('id', $bodegaId)->first();
        }

        return Bodega::where('id_empresa', $empresaId)
            ->where('activo', true)
            ->orderBy('id')
            ->first();
    }
}
