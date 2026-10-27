<?php

namespace App\Support;

/** SP-2339: middleware Spatie acepta eliminar (deprecado) o anular. */
final class PermisoAnular
{
    public static function eliminarOAnular(string $permisoEliminar): string
    {
        $anular = preg_replace('/\.eliminar$/', '.anular', $permisoEliminar);

        return $permisoEliminar.'|'.$anular;
    }
}
