<?php

namespace App\Services\Admin;

use App\Models\Admin\Empresa;
use App\Models\EmpresaConfiguracion;
use App\Models\EmpresaConfiguracionPlanilla;
use App\Services\Planilla\PlanillaTemplatesService;

class EmpresaConfiguracionService
{
    public function get(int $empresaId, string $modulo, ?string $pais = null): ?array
    {
        $pais = $pais ?? $this->paisEmpresa($empresaId);
        $row = $this->find($empresaId, $modulo, $pais);

        return $row ? $row->configuracion : null;
    }

    public function find(int $empresaId, string $modulo, ?string $pais = null): ?EmpresaConfiguracion
    {
        $pais = $pais ?? $this->paisEmpresa($empresaId);

        return EmpresaConfiguracion::query()
            ->where('empresa_id', $empresaId)
            ->where('modulo', $modulo)
            ->where('pais', $pais)
            ->first();
    }

    public function set(int $empresaId, string $modulo, array $configuracion, ?string $pais = null): EmpresaConfiguracion
    {
        $pais = $pais ?? $this->paisEmpresa($empresaId);

        return EmpresaConfiguracion::updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'pais' => $pais,
                'modulo' => $modulo,
            ],
            [
                'configuracion' => $configuracion,
            ]
        );
    }

    /**
     * Copia la plantilla central de nóminas (overwrite total).
     */
    public function importarBasePlanilla(int $empresaId, ?string $codPais = null): EmpresaConfiguracion
    {
        $codPais = $codPais ?? $this->paisEmpresa($empresaId);
        $plantilla = PlanillaTemplatesService::getConfiguracionPorPais($codPais);

        return $this->set($empresaId, EmpresaConfiguracion::MODULO_PLANILLAS, $plantilla, $codPais);
    }

    /**
     * Config de planillas: nueva tabla, fallback lectura a empresa_configuracion_planilla.
     */
    public function getPlanilla(int $empresaId): ?EmpresaConfiguracion
    {
        $pais = $this->paisEmpresa($empresaId);
        $row = $this->find($empresaId, EmpresaConfiguracion::MODULO_PLANILLAS, $pais);

        if ($row) {
            return $row;
        }

        // ponytail: fallback un release; drop tabla vieja después
        $old = EmpresaConfiguracionPlanilla::obtenerConfiguracion($empresaId);
        if (!$old) {
            return null;
        }

        return new EmpresaConfiguracion([
            'empresa_id' => $empresaId,
            'pais' => $old->cod_pais ?? $pais,
            'modulo' => EmpresaConfiguracion::MODULO_PLANILLAS,
            'configuracion' => $old->configuracion,
        ]);
    }

    public function paisEmpresa(int $empresaId): string
    {
        // cod_pais suele venir NULL; el resolver mapea desde el nombre (mismo criterio que el resto de planilla)
        return EmpresaConfiguracionPlanilla::resolverCodigoPaisEmpresa(Empresa::find($empresaId));
    }
}
