<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\EmpresaConfiguracion;
use App\Services\Admin\EmpresaConfiguracionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarEmpresaConfiguracionCommand extends Command
{
    protected $signature = 'empresa-config:migrar {--dry-run : Solo reportar, sin escribir}';

    protected $description = 'Migra empresa_configuracion_planilla y custom_empresa a empresa_configuracion';

    public function handle(EmpresaConfiguracionService $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $planillas = 0;
        $custom = 0;
        $skipped = 0;

        $rows = DB::table('empresa_configuracion_planilla')->where('activo', 1)->get();
        foreach ($rows as $row) {
            $pais = strtoupper($row->cod_pais ?: 'SV');
            $exists = EmpresaConfiguracion::query()
                ->where('empresa_id', $row->empresa_id)
                ->where('pais', $pais)
                ->where('modulo', EmpresaConfiguracion::MODULO_PLANILLAS)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $config = is_string($row->configuracion)
                ? json_decode($row->configuracion, true)
                : (array) $row->configuracion;

            if (!$dry) {
                $service->set(
                    (int) $row->empresa_id,
                    EmpresaConfiguracion::MODULO_PLANILLAS,
                    $config ?: [],
                    $pais
                );
            }
            $planillas++;
        }

        Empresa::query()->orderBy('id')->chunkById(100, function ($empresas) use ($service, $dry, &$custom, &$skipped) {
            foreach ($empresas as $empresa) {
                $pais = strtoupper($empresa->cod_pais ?: 'SV');
                $legacy = $empresa->custom_empresa;
                if (empty($legacy)) {
                    continue;
                }
                if (is_string($legacy)) {
                    $legacy = json_decode($legacy, true);
                }
                if ($legacy instanceof \stdClass) {
                    $legacy = json_decode(json_encode($legacy), true);
                }
                if (!is_array($legacy)) {
                    continue;
                }

                foreach (EmpresaConfiguracion::MODULOS_CUSTOM as $modulo) {
                    if (!isset($legacy[$modulo]) || !is_array($legacy[$modulo])) {
                        continue;
                    }

                    $exists = EmpresaConfiguracion::query()
                        ->where('empresa_id', $empresa->id)
                        ->where('pais', $pais)
                        ->where('modulo', $modulo)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    if (!$dry) {
                        $service->set((int) $empresa->id, $modulo, $legacy[$modulo], $pais);
                    }
                    $custom++;
                }
            }
        });

        $this->info(($dry ? '[dry-run] ' : '') . "planillas={$planillas} custom={$custom} skipped={$skipped}");

        return 0;
    }
}
