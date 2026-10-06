<?php

namespace App\Services\Contadores;

use App\Models\Contadores\ContadorEmpresaAcceso;

class ContadorEmpresaAccesoPermisos
{
    /** @return list<string> */
    public static function porDefecto(): array
    {
        return ContadorEmpresaAcceso::permisosPorDefecto();
    }

    /** @param  list<string>|null  $permisos */
    public static function tiene(?array $permisos, string $permiso): bool
    {
        $set = array_map('strtolower', $permisos ?? self::porDefecto());

        return in_array(strtolower($permiso), $set, true);
    }
}
