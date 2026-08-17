<?php

namespace App\Console\Commands;

use App\Models\Bonos\BonoEvaluacion;
use App\Services\Bonos\BonoEvaluationService;
use Illuminate\Console\Command;

class EvaluarBonosVendedoresCommand extends Command
{
    protected $signature = 'bonos:evaluar {--empresa=} {--desde=} {--hasta=}';

    protected $description = 'Evalúa bonos de vendedores según reglas activas y ventas del período';

    public function handle(BonoEvaluationService $service): int
    {
        $empresaOpt = $this->option('empresa');
        $idEmpresa = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;

        $desdeOpt = $this->option('desde');
        $hastaOpt = $this->option('hasta');
        $desde = ($desdeOpt !== null && $desdeOpt !== '') ? (string) $desdeOpt : null;
        $hasta = ($hastaOpt !== null && $hastaOpt !== '') ? (string) $hastaOpt : null;

        $this->info('Evaluando bonos de vendedores...');

        $resumen = $service->evaluar($idEmpresa, $desde, $hasta, BonoEvaluacion::ORIGEN_JOB);

        $this->line(json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
