<?php

namespace App\Console\Commands;

use App\Services\Ventas\EjecutarVentasProgramadasService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EjecutarVentasProgramadas extends Command
{
    protected $signature = 'ventas:ejecutar-programadas';

    protected $description = 'Ejecuta las facturas programadas cuya próxima ejecución ya venció';

    public function handle(EjecutarVentasProgramadasService $service): int
    {
        $n = $service->ejecutar(Carbon::now());
        $this->info("Facturas programadas procesadas: {$n}");

        return 0;
    }
}
