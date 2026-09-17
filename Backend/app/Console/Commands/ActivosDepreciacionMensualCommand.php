<?php

namespace App\Console\Commands;

use App\Services\Contabilidad\ActivosDepreciacionCronService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActivosDepreciacionMensualCommand extends Command
{
    protected $signature = 'activos:depreciacion-mensual
                            {--empresa= : ID de empresa (opcional)}
                            {--periodo= : Período YYYY-MM (default: mes anterior)}
                            {--dry-run : Solo preview, sin ejecutar corrida}
                            {--forzar : Ignorar día de corte configurado}';

    protected $description = 'Ejecuta la corrida mensual de depreciación de activos fijos por empresa';

    public function handle(ActivosDepreciacionCronService $service): int
    {
        $empresaOpt = $this->option('empresa');
        $idEmpresa = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;

        $periodoOpt = $this->option('periodo');
        $periodo = ($periodoOpt !== null && $periodoOpt !== '') ? (string) $periodoOpt : null;

        if ($periodo !== null && ! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $this->error('Período inválido. Use formato YYYY-MM.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');
        $hoy = Carbon::today();

        $this->info('Depreciación mensual de activos fijos');
        $this->line('Fecha: '.$hoy->toDateString());
        $this->line('Período: '.($periodo ?? ActivosDepreciacionCronService::periodoAutomatico($hoy)));
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: no se crearán egresos ni se aplicarán depreciaciones.');
        }
        if ($forzar) {
            $this->warn('FORZAR: se ignorará el día de corte de cada empresa.');
        }

        $resumen = $service->ejecutar($idEmpresa, $periodo, $dryRun, $forzar, $hoy);

        if ($resumen['detalle'] === []) {
            $this->info('No hay empresas elegibles con módulo de activos fijos y contabilidad activos.');

            return self::SUCCESS;
        }

        $filas = collect($resumen['detalle'])->map(function (array $fila) {
            $extra = collect($fila)
                ->except(['empresa_id', 'empresa', 'estado', 'mensaje'])
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v, $k) => "{$k}={$v}")
                ->implode(', ');

            $nota = trim(($fila['mensaje'] ?? '').($extra !== '' ? " ({$extra})" : ''));

            return [
                $fila['empresa_id'],
                $fila['empresa'],
                $fila['estado'],
                $nota !== '' ? $nota : '—',
            ];
        });

        $this->table(['ID', 'Empresa', 'Estado', 'Detalle'], $filas);
        $this->info("Procesadas: {$resumen['procesadas']} | Omitidas: {$resumen['omitidas']} | Errores: {$resumen['errores']}");

        return $resumen['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
