<?php

namespace App\Console\Commands\CostaRica;

use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncBccrTipoCambioCommand extends Command
{
    protected $signature = 'bccr:sync-tipo-cambio {--date=}';

    protected $description = 'Consulta y cachea el tipo de cambio de venta (BCCR indicador 318) para una fecha';

    public function handle(CostaRicaTipoCambioService $service): int
    {
        $dateOption = $this->option('date');
        $date = $dateOption
            ? \Carbon\Carbon::parse($dateOption, 'America/Costa_Rica')
            : now('America/Costa_Rica');

        try {
            $rate = $service->rateForDate($date);
            $this->info("Tipo de cambio BCCR (318) para {$date->toDateString()}: {$rate}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('No se pudo obtener el tipo de cambio BCCR: '.$e->getMessage());
            Log::error('bccr:sync-tipo-cambio falló', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
