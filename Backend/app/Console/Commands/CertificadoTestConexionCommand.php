<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CertificadoTestConexionCommand extends Command
{
    protected $signature = 'certificado:test-conexion
                            {--verificar= : Archivo en la raíz remota a comprobar (ej. 06141234561234.crt)}';

    protected $description = 'Verifica la conexión SFTP al disco uploads_ec2 (certificados EC2)';

    public function handle(): int
    {
        // Usar config(), no env(): con `php artisan config:cache` en producción, env() fuera de /config suele devolver null.
        $diskConfig = config('filesystems.disks.uploads_ec2', []);
        $host = $diskConfig['host'] ?? null;
        $user = $diskConfig['username'] ?? null;
        $keyPath = $diskConfig['privateKey'] ?? null;
        $root = $diskConfig['root'] ?? null;

        $hintCache = ' En producción, tras editar .env, ejecute: php artisan config:cache';

        if ($host === null || $host === '') {
            $this->error('EC2_HOST no está configurado (o la caché de config está desactualizada). Defínalo en .env y vuelva a generar la caché.'.$hintCache);

            return self::FAILURE;
        }

        if ($user === null || $user === '') {
            $this->error('EC2_USER no está configurado en .env (o en la caché de config).'.$hintCache);

            return self::FAILURE;
        }

        if ($keyPath === null || $keyPath === '') {
            $this->error('EC2_KEY_PATH no está configurado en .env (o en la caché de config).'.$hintCache);

            return self::FAILURE;
        }

        if (! is_readable($keyPath)) {
            $this->error(sprintf(
                'No se puede leer la llave privada en "%s". Compruebe la ruta en el servidor, que el archivo exista y los permisos (recomendado: chmod 600).',
                $keyPath
            ));

            return self::FAILURE;
        }

        if ($root === null || $root === '') {
            $this->error('EC2_UPLOADS_PATH no está configurado en .env (o en la caché de config).'.$hintCache);

            return self::FAILURE;
        }

        try {
            $disk = Storage::disk('uploads_ec2');
            $files = $disk->files();
            $this->info('Conexión SFTP correcta.');
            $this->line(sprintf('Archivos en "%s" (solo raíz de esa carpeta, sin subcarpetas): %d', $root, count($files)));
            $this->comment('Si el certificado usa un NIT y extensión que ya existían, la subida sobrescribe el archivo: el total puede no aumentar (sigue siendo normal).');

            $verificar = (string) $this->option('verificar');
            if ($verificar !== '') {
                $verificar = ltrim($verificar, '/');
                if ($disk->exists($verificar)) {
                    $mtime = $disk->lastModified($verificar);
                    $this->info(sprintf(
                        'Verificación "%s": existe. Última modificación (remoto): %s',
                        $verificar,
                        date('Y-m-d H:i:s', $mtime).' (hora local PHP / timestamp del servidor remoto)'
                    ));
                } else {
                    $this->warn(sprintf('Verificación "%s": no existe en la raíz del disco.', $verificar));
                }
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('No se pudo conectar o listar el directorio remoto.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }
}
