<?php

namespace App\Services\Contadores;

use Illuminate\Support\Facades\Auth;

/** Ejecuta lógica contable scoped a la empresa cliente del contador (Auth id_empresa temporal). */
final class ContadorEmpresaContext
{
    public static function run(int $idEmpresa, callable $callback): mixed
    {
        $user = Auth::user();
        if (!$user) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        $prev = $user->id_empresa;
        $user->id_empresa = $idEmpresa;

        try {
            return $callback();
        } finally {
            $user->id_empresa = $prev;
        }
    }
}
