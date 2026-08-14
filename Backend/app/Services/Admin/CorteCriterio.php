<?php

namespace App\Services\Admin;

class CorteCriterio
{
    public static function esUsuarioVentas(?string $tipo): bool
    {
        return in_array($tipo, ['Ventas', 'Ventas Limitado'], true);
    }

    public static function resolverIdBodega(object $usuario, $idBodegaRequest)
    {
        $idBodega = $idBodegaRequest ?: null;
        if ($idBodega && self::esUsuarioVentas($usuario->tipo ?? null)) {
            return $usuario->id_bodega ?: null;
        }

        return $idBodega;
    }
}
