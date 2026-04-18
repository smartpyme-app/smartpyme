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
        $host = env('EC2_HOST');
        $user = env('EC2_USER');
        $keyPath = env('EC2_KEY_PATH');
        $root = env('EC2_UPLOADS_PATH');

        if ($host === null || $host === '') {
            $this->error('EC2_HOST no está configurado. Defínalo en .env para probar la conexión SFTP.');

            return self::FAILURE;
        }

        if ($user === null || $user === '') {
            $this->error('EC2_USER no está configurado en .env.');

            return self::FAILURE;
        }

        if ($keyPath === null || $keyPath === '') {
            $this->error('EC2_KEY_PATH no está configurado en .env.');

            return self::FAILURE;
        }

        if (! is_readable($keyPath)) {
            $this->error(sprintf(
                'No se puede leer la llave privada en "%s". Compruebe la ruta y los permisos (recomendado: chmod 600).',
                $keyPath
            ));

            return self::FAILURE;
        }

        if ($root === null || $root === '') {
            $this->error('EC2_UPLOADS_PATH no está configurado en .env.');

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
