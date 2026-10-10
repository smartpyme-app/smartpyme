<?php

namespace App\Console\Commands;

use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use App\Services\ProductImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateProductImagesToS3Command extends Command
{
    protected $signature = 'imagenes:migrate-to-s3
                            {--dry-run : No escribe en S3 ni borra archivos locales}
                            {--limit= : Máximo de filas a procesar}
                            {--empresa= : Solo productos de esta id_empresa}';

    protected $description = 'Sube imágenes de productos desde el VPS a S3 y elimina copias locales tras éxito.';

    public function handle(ProductImageStorage $storage): int
    {
        $disk = $storage->diskName();
        $dry = (bool) $this->option('dry-run');
        $bucket = config('filesystems.disks.' . $disk . '.bucket');
        if (empty($bucket) && ! $dry) {
            $this->error('Bucket no configurado (AWS_PRODUCT_IMAGES_BUCKET / disco ' . $disk . ').');

            return 1;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $empresaId = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        $processed = 0;
        $uploaded = 0;
        $deletedLocal = 0;
        $skipped = 0;
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
            &$processed,
            &$uploaded,
            &$deletedLocal,
            &$skipped,
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

                if ($localPath === null || ! is_file($localPath) || ! is_readable($localPath)) {
                    $this->warn("Sin archivo local ni S3: imagen#{$imagen->id} {$key}");
                    $errors++;
                    continue;
                }

                $bytes = @file_get_contents($localPath);
                if ($bytes === false || $bytes === '') {
                    $errors++;
                    continue;
                }

                if ($dry) {
                    $this->line("[dry-run] imagen#{$imagen->id} -> s3://{$key}");
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

                $uploaded++;
                $storage->deleteLocalCopy($imagen->img);
                $deletedLocal++;
                $this->line("Subida imagen#{$imagen->id} -> {$key}");
            }
        });

        $this->info("Procesadas: {$processed} | Subidas: {$uploaded} | Local borrado: {$deletedLocal} | Omitidas: {$skipped} | Errores: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
