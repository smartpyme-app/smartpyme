<?php

namespace App\Console\Commands;

use App\Constants\PlanillaConstants;
use App\Models\Planilla\Empleado;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActualizarEstadoEmpleados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'empleados:actualizar-estado';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el estado de los empleados que han llegado a su fecha de baja';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fechaActual = Carbon::now()->format('Y-m-d');
        
        $empleadosParaDesactivar = Empleado::where('estado', PlanillaConstants::ESTADO_EMPLEADO_ACTIVO)
            ->whereNotNull('fecha_baja')
            ->where('fecha_baja', '<=', $fechaActual)
            ->get();
        
        $count = 0;
        
        foreach ($empleadosParaDesactivar as $empleado) {
            $empleado->update(['estado' => PlanillaConstants::ESTADO_EMPLEADO_INACTIVO]);
            
            Log::info("Empleado ID:{$empleado->id} ({$empleado->nombres} {$empleado->apellidos}) desactivado automáticamente por fecha de baja alcanzada ({$empleado->fecha_baja}).");
            $count++;
        }
        
        if ($count > 0) {
            $this->info("Se han desactivado {$count} empleados que alcanzaron su fecha de baja.");
        } else {
            $this->info("No se encontraron empleados para desactivar.");
        }
        
        return 0;
    }
}