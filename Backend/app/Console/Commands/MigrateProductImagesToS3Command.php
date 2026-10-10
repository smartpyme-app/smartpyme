<?php

namespace App\Console\Commands;

use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use App\Services\ProductImageStorage;
use App\Services\ShopifyImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateProductImagesToS3Command extends Command
{
    protected $signature = 'imagenes:migrate-to-s3
                            {--dry-run : No escribe en S3 ni borra archivos locales}
                            {--limit= : Máximo de filas a procesar}
                            {--empresa= : Solo productos de esta id_empresa}
                            {--local-root= : Carpeta img legacy (contiene productos/); override de PRODUCT_IMAGES_LOCAL_ROOT}
                            {--recover-from-src : Si falta el archivo local, intenta descargar desde productos_imagenes.src}
                            {--skip-missing : No contar como error las filas sin archivo local ni S3}';

    protected $description = 'Sube imágenes de productos desde el VPS a S3 y elimina copias locales tras éxito.';

    public function handle(ProductImageStorage $storage): int
    {
        $localRootOption = $this->option('local-root');
        if (is_string($localRootOption) && trim($localRootOption) !== '') {
            config(['product_images.local_root' => trim($localRootOption)]);
        }

        $disk = $storage->diskName();
        $dry = (bool) $this->option('dry-run');
        $this->line('Carpeta local de imágenes: ' . $storage->localImgRoot());
        $bucket = config('filesystems.disks.' . $disk . '.bucket');
        if (empty($bucket) && ! $dry) {
            $this->error('Bucket no configurado (AWS_PRODUCT_IMAGES_BUCKET / disco ' . $disk . ').');

            return 1;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $empresaId = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;
        $recoverFromSrc = (bool) $this->option('recover-from-src');
        $skipMissing = (bool) $this->option('skip-missing');
        $urlValidator = app(ShopifyImageService::class);

        $processed = 0;
        $uploaded = 0;
        $recovered = 0;
        $deletedLocal = 0;
        $skipped = 0;
        $missing = 0;
        $errors = 0;

        $query = Imagen::query()->orderBy('id');
        if ($empresaId !== null) {
            $productoIds = Producto::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresaId)
                ->pluck('id');
            $query->whereIn('id_producto', $productoIds);
        }

        $query->chunkById(50, function ($rows) use (
            $storage,
            $disk,
            $dry,
            $limit,
            $recoverFromSrc,
            $skipMissing,
            $urlValidator,
            &$processed,
            &$uploaded,
            &$recovered,
            &$deletedLocal,
            &$skipped,
            &$missing,
            &$errors
        ) {
            foreach ($rows as $imagen) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $processed++;

                if ($storage->isDefaultImage($imagen->img)) {
                    $skipped++;
                    continue;
                }

                $key = $storage->objectKeyFromImg($imagen->img);
                if ($key === null) {
                    $skipped++;
                    continue;
                }

                $localPath = $storage->localAbsolutePath($imagen->img);
                $onS3 = Storage::disk($disk)->exists($key);

                if ($onS3) {
                    if (! $dry && $localPath && is_file($localPath)) {
                        $storage->deleteLocalCopy($imagen->img);
                        $deletedLocal++;
                        $this->line("Local eliminado (ya en S3): imagen#{$imagen->id} {$key}");
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $bytes = null;
                $fromSrc = false;
                if ($localPath !== null && is_file($localPath) && is_readable($localPath)) {
                    $bytes = @file_get_contents($localPath);
                } elseif ($recoverFromSrc) {
                    $srcUrl = $urlValidator->validarUrl($imagen->src ?? null);
                    if ($srcUrl !== null) {
                        $downloaded = $this->descargarImagen($srcUrl);
                        if ($downloaded !== null && $downloaded !== '') {
                            $bytes = $downloaded;
                            $fromSrc = true;
                        }
                    }
                }

                if ($bytes === null || $bytes === '') {
                    $this->warn("Sin archivo local ni S3: imagen#{$imagen->id} {$key} (buscado: {$localPath})");
                    $missing++;
                    if ($skipMissing) {
                        continue;
                    }
                    $errors++;
                    continue;
                }

                if ($dry) {
                    $suffix = $fromSrc ? ' (recuperar desde src)' : '';
                    $this->line("[dry-run] imagen#{$imagen->id} -> s3://{$key}{$suffix}");
                    continue;
                }

                try {
                    $ok = $storage->putRawForImg($imagen->img, $bytes);
                } catch (\Throwable $e) {
                    Log::error('imagenes:migrate-to-s3 put falló', [
                        'imagen_id' => $imagen->id,
                        'key' => $key,
                        'message' => $e->getMessage(),
                    ]);
                    $this->warn("Fallo S3 imagen#{$imagen->id}: " . $e->getMessage());
                    $errors++;
                    continue;
                }

                if (! $ok) {
                    $errors++;
                    continue;
                }

                if ($fromSrc) {
                    $recovered++;
                    $this->line("Recuperada desde src imagen#{$imagen->id} -> {$key}");
                } else {
                    $uploaded++;
                    $this->line("Subida imagen#{$imagen->id} -> {$key}");
                }

                if (! $fromSrc) {
                    $storage->deleteLocalCopy($imagen->img);
                    $deletedLocal++;
                }
            }
        });

        $this->info("Procesadas: {$processed} | Subidas: {$uploaded} | Recuperadas (src): {$recovered} | Local borrado: {$deletedLocal} | Omitidas: {$skipped} | Sin archivo: {$missing} | Errores: {$errors}");

        return $errors > 0 ? 1 : 0;
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
