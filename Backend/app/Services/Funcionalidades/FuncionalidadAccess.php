<?php

namespace App\Services\Funcionalidades;

use App\Models\Admin\EmpresaFuncionalidad;

class FuncionalidadAccess
{
    public static function empresaTieneSlug(int $idEmpresa, string $slug): bool
    {
        return EmpresaFuncionalidad::query()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->whereHas('funcionalidad', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }
}
