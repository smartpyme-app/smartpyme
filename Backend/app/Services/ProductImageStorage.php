<?php

namespace App\Services;

use App\Models\Inventario\Imagen;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

class ProductImageStorage
{
    public function diskName(): string
    {
        return (string) config('product_images.disk', 's3_productos');
    }

    /**
     * Ruta relativa en disco/S3 sin barra inicial (ej. productos/abc.jpg).
     */
    public function objectKeyFromImg(?string $img): ?string
    {
        if ($img === null || $img === '') {
            return null;
        }

        $normalized = ltrim((string) $img, '/');
        if ($normalized === '' || $this->isDefaultImage($normalized)) {
            return null;
        }

        return $normalized;
    }

    public function isDefaultImage(?string $img): bool
    {
        if ($img === null || $img === '') {
            return true;
        }

        $normalized = ltrim((string) $img, '/');

        return $normalized === 'productos/default.jpg';
    }

    /**
     * @return array{path: string, hash: string, img: string}
     */
    public function storeJpgFromBinary(string $binary, int $quality = 75): array
    {
        $encoded = Image::make($binary)->resize(750, 750)->encode('jpg', $quality);
        $hash = md5($encoded->__toString());
        $path = "productos/{$hash}.jpg";

        Storage::disk($this->diskName())->put($path, (string) $encoded, [
            'ContentType' => 'image/jpeg',
        ]);

        return [
            'path' => $path,
            'hash' => $hash,
            'img' => '/' . $path,
        ];
    }

    /**
     * @return array{path: string, hash: string, img: string}
     */
    public function storeJpgFromUploadedFile($file, int $quality = 75): array
    {
        $encoded = Image::make($file)->resize(750, 750)->encode('jpg', $quality);

        return $this->storeEncodedJpg($encoded, $quality);
    }

    /**
     * @return array{path: string, hash: string, img: string}
     */
    public function storeEncodedJpg($encoded, int $quality = 75): array
    {
        $hash = md5($encoded->__toString());
        $path = "productos/{$hash}.jpg";

        Storage::disk($this->diskName())->put($path, (string) $encoded, [
            'ContentType' => 'image/jpeg',
        ]);

        return [
            'path' => $path,
            'hash' => $hash,
            'img' => '/' . $path,
        ];
    }

    public function exists(?string $img): bool
    {
        $key = $this->objectKeyFromImg($img);
        if ($key === null) {
            return $this->isDefaultImage($img);
        }

        if (Storage::disk($this->diskName())->exists($key)) {
            return true;
        }

        return is_file($this->localAbsolutePath($img));
    }

    public function get(?string $img): ?string
    {
        $key = $this->objectKeyFromImg($img);
        if ($key === null) {
            return null;
        }

        if (Storage::disk($this->diskName())->exists($key)) {
            return Storage::disk($this->diskName())->get($key);
        }

        $local = $this->localAbsolutePath($img);
        if (is_file($local) && is_readable($local)) {
            $contents = @file_get_contents($local);

            return $contents !== false ? $contents : null;
        }

        return null;
    }

    public function url(?string $img): ?string
    {
        if ($img === null || $img === '') {
            return null;
        }

        if ($this->isDefaultImage($img)) {
            return null;
        }

        if (preg_match('#^https?://#i', (string) $img)) {
            return (string) $img;
        }

        $key = $this->objectKeyFromImg($img);
        if ($key === null) {
            return null;
        }

        $base = rtrim((string) config('product_images.url', ''), '/');
        if ($base !== '') {
            return $base . '/' . $key;
        }

        return Storage::disk($this->diskName())->url($key);
    }

    /**
     * Elimina objeto S3 (y opcionalmente local) si ningún otro registro referencia la misma ruta img.
     */
    public function deleteIfUnreferenced(?string $img, ?int $excludeImagenId = null): void
    {
        if ($this->isDefaultImage($img)) {
            return;
        }

        $query = Imagen::where('img', $img);
        if ($excludeImagenId !== null) {
            $query->where('id', '!=', $excludeImagenId);
        }
        if ($query->exists()) {
            return;
        }

        $key = $this->objectKeyFromImg($img);
        if ($key !== null && Storage::disk($this->diskName())->exists($key)) {
            Storage::disk($this->diskName())->delete($key);
        }

        $local = $this->localAbsolutePath($img);
        if (is_file($local)) {
            @unlink($local);
        }
    }

    public function localAbsolutePath(?string $img): ?string
    {
        $key = $this->objectKeyFromImg($img);
        if ($key === null) {
            return null;
        }

        return public_path('img/' . $key);
    }

    /**
     * Sube bytes crudos a S3 bajo la clave derivada de img (migración).
     */
    public function putRawForImg(string $img, string $bytes): bool
    {
        $key = $this->objectKeyFromImg($img);
        if ($key === null) {
            return false;
        }

        return Storage::disk($this->diskName())->put($key, $bytes, [
            'ContentType' => 'image/jpeg',
        ]);
    }

    public function deleteLocalCopy(?string $img): void
    {
        $local = $this->localAbsolutePath($img);
        if ($local !== null && is_file($local)) {
            @unlink($local);
        }
    }
}
