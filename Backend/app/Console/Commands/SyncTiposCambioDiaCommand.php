<?php

namespace App\Console\Commands;

use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cachea el tipo de cambio del día en pais_configuracion (modulo=moneda)
 * para cada país con fuente=api.
 */
class SyncTiposCambioDiaCommand extends Command
{
    protected $signature = 'tipos-cambio:sync-dia';

    protected $description = 'Consulta APIs de tipo de cambio y guarda rate_del_dia en pais_configuracion';

    public function handle(CostaRicaTipoCambioService $crService): int
    {
        $rows = PaisConfiguracion::query()
            ->modulo(PaisConfiguracion::MODULO_MONEDA)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No hay filas pais_configuracion modulo=moneda. Corre el seeder.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($rows as $row) {
            $cfg = is_array($row->configuracion) ? $row->configuracion : [];
            if (($cfg['fuente'] ?? '') !== 'api') {
                $this->line("{$row->pais}: fuente=manual, skip");
                continue;
            }

            $provider = $cfg['api']['provider'] ?? null;
            try {
                if ($provider === 'bccr' && $row->pais === 'CR') {
                    $rate = $crService->rateForDate(now('America/Costa_Rica'));
                    $this->info("{$row->pais} (bccr): {$rate}");
                    $ok++;
                    continue;
                }

                $this->warn("{$row->pais}: provider desconocido [{$provider}], skip");
            } catch (\Throwable $e) {
                $fail++;
                $this->error("{$row->pais}: ".$e->getMessage());
                Log::error('tipos-cambio:sync-dia falló', [
                    'pais' => $row->pais,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
