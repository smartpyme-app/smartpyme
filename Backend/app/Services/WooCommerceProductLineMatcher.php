<?php

namespace App\Services;

use App\Models\Inventario\Producto;

/**
 * Enlaza líneas de pedido WooCommerce con filas de producto SP (incl. variaciones codigo-talla).
 */
final class WooCommerceProductLineMatcher
{
    /** @var WooCommerceTallaNombreParser */
    private $tallaParser;

    public function __construct(WooCommerceTallaNombreParser $tallaParser)
    {
        $this->tallaParser = $tallaParser;
    }

    public function buildVariationSku(string $codigo, string $talla): string
    {
        return trim($codigo) . '-' . strtoupper(trim($talla));
    }

    public function variationSkuForProducto(Producto $producto): ?string
    {
        $codigo = trim((string) $producto->codigo);
        if ($codigo === '') {
            return null;
        }
        $talla = $this->tallaParser->parse((string) $producto->nombre)['talla'];
        if ($talla === null) {
            return null;
        }

        return $this->buildVariationSku($codigo, $talla);
    }

    /**
     * SKUs a probar en WooCommerce REST (variación primero, luego simple compartido).
     *
     * @return list<string>
     */
    public function remoteSkusToTry(Producto $producto): array
    {
        $skus = [];
        $variation = $this->variationSkuForProducto($producto);
        if ($variation !== null) {
            $skus[] = $variation;
        }
        $codigo = trim((string) $producto->codigo);
        if ($codigo !== '' && !in_array($codigo, $skus, true)) {
            $skus[] = $codigo;
        }

        return $skus;
    }

    /**
     * @param array<string, mixed> $item line_item del webhook WooCommerce
     */
    public function findForOrderLine(int $empresaId, array $item): ?Producto
    {
        $variationId = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;
        if ($variationId > 0) {
            $byVariation = Producto::where('id_empresa', $empresaId)
                ->where('woocommerce_id', $variationId)
                ->first();
            if ($byVariation) {
                return $byVariation;
            }
        }

        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        if ($productId > 0 && $variationId <= 0) {
            $byProduct = Producto::where('id_empresa', $empresaId)
                ->where('woocommerce_id', $productId)
                ->whereNull('woocommerce_parent_id')
                ->first();
            if ($byProduct) {
                return $byProduct;
            }
        }

        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku !== '') {
            $byVariationSku = $this->findByVariationSku($empresaId, $sku);
            if ($byVariationSku) {
                return $byVariationSku;
            }

            $byCodigo = Producto::where('id_empresa', $empresaId)
                ->where('codigo', $sku)
                ->get();
            if ($byCodigo->count() === 1) {
                return $byCodigo->first();
            }

            if ($byCodigo->count() > 1) {
                $fromName = $this->matchSharedCodigoByLineName($byCodigo, (string) ($item['name'] ?? ''));
                if ($fromName) {
                    return $fromName;
                }
            }
        }

        $name = (string) ($item['name'] ?? '');
        if ($name !== '' && $sku !== '') {
            $parsedName = $this->tallaParser->parse($name);
            if ($parsedName['talla'] !== null) {
                $byCodigoAndTalla = Producto::where('id_empresa', $empresaId)
                    ->where('codigo', $sku)
                    ->get()
                    ->first(function (Producto $p) use ($parsedName) {
                        return $this->tallaParser->parse((string) $p->nombre)['talla'] === $parsedName['talla'];
                    });
                if ($byCodigoAndTalla) {
                    return $byCodigoAndTalla;
                }
            }
        }

        return null;
    }

    public function findByVariationSku(int $empresaId, string $variationSku): ?Producto
    {
        $variationSku = trim($variationSku);
        if ($variationSku === '') {
            return null;
        }

        $candidates = Producto::where('id_empresa', $empresaId)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->get();

        foreach ($candidates as $producto) {
            $built = $this->variationSkuForProducto($producto);
            if ($built !== null && strcasecmp($built, $variationSku) === 0) {
                return $producto;
            }
        }

        return null;
    }

    /**
     * @param \Illuminate\Support\Collection<int, Producto> $sameCodigo
     */
    private function matchSharedCodigoByLineName($sameCodigo, string $lineName): ?Producto
    {
        $parsed = $this->tallaParser->parse($lineName);
        if ($parsed['talla'] === null) {
            return null;
        }

        return $sameCodigo->first(function (Producto $p) use ($parsed) {
            return $this->tallaParser->parse((string) $p->nombre)['talla'] === $parsed['talla'];
        });
    }
}
