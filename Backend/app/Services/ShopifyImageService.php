<?php

namespace App\Services;

use App\Models\Inventario\Imagen;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class ShopifyImageService
{
    /**
     * Sincroniza las imágenes de un producto desde Shopify.
     *
     * - Valida la URL de cada imagen.
     * - Compara la URL (src) y el hash del contenido contra lo ya almacenado.
     * - Descarga/reemplaza únicamente si la URL o el contenido cambiaron.
     *
     * @return array{sin_cambios:int, reemplazadas:int, nuevas:int, invalidas:int, errores:int, eliminadas:int}
     */
    public function sincronizarImagenes(int $productoId, ?array $imagenesShopify): array
    {
        $stats = ['sin_cambios' => 0, 'reemplazadas' => 0, 'nuevas' => 0, 'invalidas' => 0, 'errores' => 0, 'eliminadas' => 0];

        if (!is_array($imagenesShopify) || empty($imagenesShopify)) {
            return $stats;
        }

        $existentes = Imagen::where('id_producto', $productoId)
            ->get()
            ->keyBy('shopify_image_id');

        foreach ($imagenesShopify as $imagenShopify) {
            $shopifyImageId = $imagenShopify['id'] ?? null;
            $src = $this->validarUrl($imagenShopify['src'] ?? null);

            if ($src === null) {
                $stats['invalidas']++;
                Log::warning('ShopifyImageService: URL inválida, imagen omitida', [
                    'producto_id' => $productoId,
                    'shopify_image_id' => $shopifyImageId,
                ]);
                continue;
            }

            $imagen = $shopifyImageId ? ($existentes->get($shopifyImageId) ?? null) : null;

            // Sin cambios: la URL es idéntica y el archivo físico existe
            if ($imagen && $imagen->src === $src && $this->existeArchivo($imagen->img)) {
                $stats['sin_cambios']++;
                continue;
            }

            // Descargar contenido
            $contenido = $this->descargarImagen($src);
            if ($contenido === null) {
                $stats['errores']++;
                continue;
            }

            // Procesar (resize) y calcular hash del contenido final
            $procesado = $this->procesar($contenido);
            if ($procesado === null) {
                $stats['errores']++;
                continue;
            }

            // La URL cambió pero el contenido es idéntico: solo actualizar src
            if ($imagen && $imagen->hash === $procesado['hash']) {
                $imagen->src = $src;
                $imagen->save();
                $stats['sin_cambios']++;
                continue;
            }

            // Contenido nuevo (o imagen nueva): guardar archivo y actualizar registro
            if ($imagen) {
                $this->eliminarArchivo($imagen->img, $imagen->id);
                $imagen->img = '/' . $procesado['path'];
                $imagen->src = $src;
                $imagen->hash = $procesado['hash'];
                $imagen->save();
                $stats['reemplazadas']++;
            } else {
                Imagen::create([
                    'id_producto' => $productoId,
                    'img' => '/' . $procesado['path'],
                    'src' => $src,
                    'hash' => $procesado['hash'],
                    'shopify_image_id' => $shopifyImageId,
                ]);
                $stats['nuevas']++;
            }
        }

        // Eliminar imágenes obsoletas: las que vinieron de Shopify y ya no están en el payload
        $idsShopify = [];
        foreach ($imagenesShopify as $img) {
            if (!empty($img['id'])) {
                $idsShopify[] = $img['id'];
            }
        }

        if (!empty($idsShopify)) {
            $obsoletas = Imagen::where('id_producto', $productoId)
                ->whereNotNull('shopify_image_id')
                ->whereNotIn('shopify_image_id', $idsShopify)
                ->get();

            foreach ($obsoletas as $obsoleta) {
                $this->eliminarArchivo($obsoleta->img, $obsoleta->id);
                $obsoleta->delete();
            }

            $stats['eliminadas'] = $obsoletas->count();
        }

        return $stats;
    }

    /**
     * Valida y normaliza una URL de imagen.
     * Devuelve la URL si es válida (http/https), o null en caso contrario.
     */
    public function validarUrl($url): ?string
    {
        if (empty($url) || !is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * Descarga el contenido de una imagen vía cURL.
     */
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
                Log::warning('ShopifyImageService: no se pudo descargar imagen', [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'error' => $error,
                ]);
                return null;
            }

            return $contenido;
        } catch (\Exception $e) {
            Log::warning('ShopifyImageService: excepción descargando imagen', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Redimensiona la imagen, calcula su hash y la guarda en disco.
     *
     * @return array{path:string, hash:string}|null
     */
    private function procesar(string $contenido): ?array
    {
        try {
            $encoded = Image::make($contenido)->resize(750, 750)->encode('jpg', 50);
            $hash = md5($encoded->__toString());
            $path = "productos/{$hash}.jpg";

            $dir = public_path('img/productos');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $encoded->save(public_path('img/' . $path));

            return ['path' => $path, 'hash' => $hash];
        } catch (\Exception $e) {
            Log::error('ShopifyImageService: error procesando imagen', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Elimina el archivo físico de una imagen, pero solo si ningún otro registro
     * lo referencia (los nombres son por hash, por lo que imágenes con el mismo
     * contenido comparten el mismo archivo).
     */
    private function eliminarArchivo(?string $img, ?int $excludeId = null): void
    {
        if (empty($img) || $img === 'productos/default.jpg') {
            return;
        }

        $query = Imagen::where('img', $img);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            return;
        }

        Storage::delete($img);
    }

    /**
     * Indica si el archivo físico de la imagen existe en disco.
     */
    private function existeArchivo(?string $img): bool
    {
        if (empty($img) || $img === 'productos/default.jpg') {
            return true;
        }

        return file_exists(public_path('img' . $img));
    }
}
