<?php

namespace App\Services\Clinica;

use App\Models\Clinica\Especie;
use App\Models\Clinica\Raza;
use Illuminate\Database\QueryException;

class ClinicaCatalogoBootstrap
{
    public static function asegurar(int $idEmpresa): void
    {
        if (Especie::withoutGlobalScopes()->where('id_empresa', $idEmpresa)->exists()) {
            return;
        }

        try {
            $canino = self::especie($idEmpresa, 'Canino');
            foreach (['Labrador', 'Mestizo', 'Sin raza definida'] as $raza) {
                self::raza($idEmpresa, $canino->id, $raza);
            }

            $felino = self::especie($idEmpresa, 'Felino');
            foreach (['Siamés', 'Mestizo', 'Sin raza definida'] as $raza) {
                self::raza($idEmpresa, $felino->id, $raza);
            }

            self::especie($idEmpresa, 'Ave');
        } catch (QueryException $e) {
            $codigo = (int) ($e->errorInfo[1] ?? 0);
            if ($codigo !== 1062) {
                throw $e;
            }
        }
    }

    private static function especie(int $idEmpresa, string $nombre): Especie
    {
        return Especie::withoutGlobalScopes()->create([
            'id_empresa' => $idEmpresa,
            'nombre' => $nombre,
        ]);
    }

    private static function raza(int $idEmpresa, int $idEspecie, string $nombre): void
    {
        Raza::withoutGlobalScopes()->create([
            'id_empresa' => $idEmpresa,
            'id_especie' => $idEspecie,
            'nombre' => $nombre,
        ]);
    }
}
