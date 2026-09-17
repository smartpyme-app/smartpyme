<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\Empresa;
use App\Models\Contabilidad\ActivoCategoria;
use App\Models\Contabilidad\PaisActivoCategoriaPlantilla;

class ActivosCategoriasBootstrapService
{
    public const SLUG = 'modulo-activos-fijos';

    public function copiarPlantillasEmpresa(Empresa $empresa, bool $soloSiVacio = true): int
    {
        if ($soloSiVacio && ActivoCategoria::withoutGlobalScopes()
            ->where('id_empresa', $empresa->id)
            ->exists()) {
            return 0;
        }

        $codPais = strtoupper($empresa->cod_pais ?: $empresa->pais ?: 'SV');

        $plantillas = PaisActivoCategoriaPlantilla::query()
            ->where('cod_pais', $codPais)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $copiadas = 0;

        foreach ($plantillas as $plantilla) {
            $categoria = ActivoCategoria::withoutGlobalScopes()->firstOrCreate(
                [
                    'id_empresa' => $empresa->id,
                    'nombre' => $plantilla->nombre,
                ],
                [
                    'plantilla_id' => $plantilla->id,
                    'metodo_depreciacion' => $plantilla->metodo_depreciacion ?: 'linea_recta',
                    'porcentaje_anual' => $plantilla->porcentaje_anual,
                    'vida_util_anios' => $plantilla->vida_util_anios,
                    'valor_residual_default' => $plantilla->valor_residual_default ?? 0,
                    'permite_bien_usado' => (bool) $plantilla->permite_bien_usado,
                    'reglas_bien_usado' => $plantilla->reglas_bien_usado,
                ]
            );

            if ($categoria->wasRecentlyCreated) {
                $copiadas++;
            }
        }

        return $copiadas;
    }
}
