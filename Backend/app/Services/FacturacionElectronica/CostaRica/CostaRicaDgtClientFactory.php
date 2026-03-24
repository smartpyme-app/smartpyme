<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use DazzaDev\DgtCr\Client;
use Illuminate\Support\Facades\Storage;

final class CostaRicaDgtClientFactory
{
    /**
     * Certificado: primer archivo .p12 en storage/app/fe-cr/{id_empresa}/.
     * Contraseña del .p12: mh_pwd_certificado. Credenciales ATV: mh_usuario / mh_contrasena.
     * Ambiente: fe_ambiente '00' pruebas, '01' producción (mismo criterio que MH El Salvador).
     */
    public function make(Empresa $empresa): Client
    {
        $sandbox = (string) ($empresa->fe_ambiente ?? '') === '00';
        $client = new Client($sandbox);

        $basePath = storage_path('app/fe-cr/'.$empresa->id);
        if (! is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
        $client->setFilePath($basePath);

        $rel = $this->resolverRutaCertificadoP12($empresa);
        if (! $rel) {
            throw new \InvalidArgumentException(
                'Guarde el certificado digital (.p12) en storage/app/fe-cr/'.$empresa->id.'/ (un solo archivo .p12 por carpeta).'
            );
        }

        $certPath = storage_path('app/'.$rel);
        if (! is_readable($certPath)) {
            throw new \InvalidArgumentException('No se puede leer el archivo de certificado FE Costa Rica.');
        }

        $client->setCertificate([
            'path' => $certPath,
            'password' => (string) ($empresa->mh_pwd_certificado ?? ''),
        ]);

        $usuario = $empresa->mh_usuario;
        $clave = $empresa->mh_contrasena;
        if (! $usuario || ! $clave) {
            throw new \InvalidArgumentException(
                'Configure usuario y contraseña de acceso (ATV / comprobantes electrónicos) en los mismos campos que usa facturación electrónica.'
            );
        }

        $client->setCredentials([
            'username' => $usuario,
            'password' => $clave,
        ]);

        return $client;
    }

    private function resolverRutaCertificadoP12(Empresa $empresa): ?string
    {
        $disk = Storage::disk('local');
        $dir = 'fe-cr/'.$empresa->id;
        if (! $disk->exists($dir)) {
            return null;
        }
        foreach ($disk->files($dir) as $file) {
            if (str_ends_with(strtolower($file), '.p12')) {
                return $file;
            }
        }

        return null;
    }
}
