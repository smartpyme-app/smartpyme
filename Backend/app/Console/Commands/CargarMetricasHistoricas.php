<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class CargarMetricasHistoricas extends Command
{
    protected $signature = 'metricas:historico {fecha_inicio} {--empresa=} {--solo-empresas} {--solo-sucursales}';
    
    protected $description = 'Carga métricas históricas desde una fecha inicial hasta el mes actual';
    
    public function handle()
    {
        $fechaInicio = Carbon::parse($this->argument('fecha_inicio'))->startOfMonth();
        $fechaActual = Carbon::now()->startOfMonth();
        
        $fechaIteracion = $fechaInicio->copy();
        $totalMeses = $fechaInicio->diffInMonths($fechaActual) + 1;
        
        $this->info("Se procesarán métricas para {$totalMeses} meses desde {$fechaInicio->format('Y-m')} hasta {$fechaActual->format('Y-m')}");
        
        $bar = $this->output->createProgressBar($totalMeses);
        $bar->start();
        
        $empresaParam = $this->option('empresa') ? '--empresa='.$this->option('empresa') : '';
        
        while ($fechaIteracion->lte($fechaActual)) {
            $fechaStr = $fechaIteracion->format('Y-m-d');
            
            // Procesar métricas de empresas si no se especificó solo-sucursales
            if (!$this->option('solo-sucursales')) {
                $this->info("\nProcesando empresas para {$fechaIteracion->format('Y-m')}");
                $this->call('metricas:empresas', [
                    '--fecha' => $fechaStr,
                    '--actualizar-historico' => true,
                    '--empresa' => $this->option('empresa')
                ]);
            }
            
            // Procesar métricas de sucursales si no se especificó solo-empresas
            if (!$this->option('solo-empresas')) {
                $this->info("\nProcesando sucursales para {$fechaIteracion->format('Y-m')}");
                $this->call('metricas:sucursales', [
                    '--fecha' => $fechaStr,
                    '--actualizar-historico' => true,
                    '--empresa' => $this->option('empresa')
                ]);
            }
            
            $fechaIteracion->addMonth();
            $bar->advance();
        }
        
        $bar->finish();
        $this->info("\n¡Carga histórica de métricas completada!");
        
        return 0;
    }
}