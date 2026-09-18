<?php

namespace App\Console\Commands;

use App\Services\Ventas\FusionarClientesDuplicadosService;
use Illuminate\Console\Command;

class FusionarClientesDuplicadosCommand extends Command
{
    protected $signature = 'clientes:fusionar-duplicados
                            {--empresa= : ID de empresa (obligatorio)}
                            {--ejecutar : Aplica cambios. Sin esto solo muestra el plan}';

    protected $description = 'Fusiona clientes inhabilitados hacia el habilitado con el mismo NIT o DUI y los elimina';

    public function handle(FusionarClientesDuplicadosService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        if ($empresaId <= 0) {
            $this->error('Debe indicar --empresa=ID');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->warn('Dry-run: no se aplicarán cambios. Use --ejecutar para persistir.');
        }

        $plan = $service->planear($empresaId);
        $this->imprimirPlan($plan);

        $resumen = $service->ejecutarPlan($plan, $ejecutar);

        $this->newLine();
        $this->info(sprintf(
            'Fusionados: %d  Saltados: %d  Errores: %d',
            $resumen['fusionados'],
            $resumen['saltados'],
            count($resumen['errores'])
        ));

        foreach ($resumen['errores'] as $error) {
            $this->error(sprintf('Origen %d: %s', $error['origen'], $error['mensaje']));
        }

        return count($resumen['errores']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{sin_documento: array<int, array<string, mixed>>, grupos: array<int, array<string, mixed>>}  $plan
     */
    private function imprimirPlan(array $plan): void
    {
        $filas = [];
        foreach ($plan['grupos'] as $grupo) {
            $filas[] = [
                $grupo['clave'],
                $grupo['accion'],
                $grupo['destino'] ?? '-',
                implode(',', $grupo['origenes']) ?: '-',
                $grupo['ventas_origen'],
                $grupo['razon'] ?: ($grupo['accion'] === 'fusionar' ? 'ok' : ''),
            ];
        }

        if ($filas !== []) {
            $this->table(['Clave', 'Acción', 'Destino', 'Orígenes', 'Ventas', 'Razón'], $filas);
        } else {
            $this->info('No hay grupos con NIT/DUI para evaluar.');
        }

        if ($plan['sin_documento'] !== []) {
            $ids = implode(', ', array_column($plan['sin_documento'], 'id'));
            $this->warn('Sin documento (no se tocan): '.$ids);
        }
    }
}
