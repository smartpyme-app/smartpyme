<?php

namespace App\Services;

use App\Models\Inventario\Imagen;
use Illuminate\Support\Facades\Log;

class WooCommerceProductImageSyncService
{
    public function __construct(
        private ProductImageStorage $productImageStorage,
        private ShopifyImageService $shopifyImageService
    ) {
    }

    /**
     * Sincroniza imágenes desde el payload REST de WooCommerce hacia S3 y productos_imagenes.
     *
     * @param  array<int, array<string, mixed>>|null  $wooImages
     */
    public function syncFromPayload(int $productoId, ?array $wooImages): void
    {
        if (!is_array($wooImages) || $wooImages === []) {
            return;
        }

        $srcsPayload = [];
        foreach ($wooImages as $wooImage) {
            if (!is_array($wooImage)) {
                continue;
            }
            $src = $this->shopifyImageService->validarUrl($wooImage['src'] ?? null);
            if ($src === null) {
                continue;
            }
            $srcsPayload[] = $src;

            $existente = Imagen::where('id_producto', $productoId)
                ->where('src', $src)
                ->first();

            if ($existente && $this->productImageStorage->exists($existente->img)) {
                continue;
            }

            $contenido = $this->descargarImagen($src);
            if ($contenido === null) {
                continue;
            }

            try {
                $stored = $this->productImageStorage->storeJpgFromBinary($contenido, 50);
            } catch (\Throwable $e) {
                Log::warning('WooCommerceProductImageSyncService: error procesando imagen', [
                    'producto_id' => $productoId,
                    'src' => $src,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($existente) {
                $this->productImageStorage->deleteIfUnreferenced($existente->img, $existente->id);
                $existente->img = $stored['img'];
                $existente->hash = $stored['hash'];
                $existente->save();
            } else {
                Imagen::create([
                    'id_producto' => $productoId,
                    'img' => $stored['img'],
                    'hash' => $stored['hash'],
                    'src' => $src,
                ]);
            }
        }

        if ($srcsPayload === []) {
            return;
        }

        $obsoletas = Imagen::where('id_producto', $productoId)
            ->whereNull('shopify_image_id')
            ->where(function ($q) use ($srcsPayload) {
                $q->whereNull('src')->orWhereNotIn('src', $srcsPayload);
            })
            ->get();

        foreach ($obsoletas as $obsoleta) {
            $this->productImageStorage->deleteIfUnreferenced($obsoleta->img, $obsoleta->id);
            $obsoleta->delete();
        }
    }

    private function descargarImagen(string $url): ?string
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SmartPyme/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $contenido = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200 || empty($contenido)) {
                return null;
            }

            return $contenido;
        } catch (\Throwable) {
            return null;
        }
    }
}
