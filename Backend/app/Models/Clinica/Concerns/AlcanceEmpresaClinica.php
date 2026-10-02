<?php

namespace App\Models\Clinica\Concerns;

trait AlcanceEmpresaClinica
{
    protected static function bootAlcanceEmpresaClinica(): void
    {
        static::addGlobalScope('empresa_clinica', function ($builder) {
            $usuario = auth()->user();
            if ($usuario === null) {
                return;
            }

            $empresaId = $usuario->id_empresa ?? null;
            if (! $empresaId) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.id_empresa', $empresaId);
        });
    }
}
