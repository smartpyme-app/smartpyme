<?php

namespace App\Console\Commands;

use App\Services\Ventas\GenerarVentasRecurrentesService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerarVentasRecurrentes extends Command
{
    protected $signature = 'ventas:generar-recurrentes';

    protected $description = 'Genera y emite las ventas recurrentes que corresponden al día de hoy';

    public function handle(GenerarVentasRecurrentesService $service): int
    {
        $hoy = Carbon::now('America/El_Salvador');
        $this->info('Ventas recurrentes para '.$hoy->toDateString());
        $service->ejecutar($hoy);
        $this->info('Listo.');

        return 0;
    }
}
