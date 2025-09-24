<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        'App\Console\Commands\Notificaciones',
        'App\Console\Commands\VerificarSuscripcion'
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Comandos existentes
        $schedule->command('generate:notificaciones')->daily();
        // $schedule->command('reporte:ventas-por-vendedor')
        //      ->dailyAt('23:59');
        $schedule->command('reportes:enviar')
             ->everyFiveMinutes()
             ->appendOutputTo(storage_path('logs/reportes-automaticos.log'));

        $schedule->command('metricas:empresas')
            ->dailyAt('03:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                // env('ADMIN_EMAIL')
                'joseespana94@gmail.com'
            );

        // Programar la actualización de métricas para todas las sucursales a las 4:00 AM
        $schedule->command('metricas:sucursales')
            ->dailyAt('04:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'joseespana94@gmail.com'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('metricas:empresas --actualizar-historico')
            ->monthlyOn(1, '02:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'joseespana94@gmail.com'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('metricas:sucursales --actualizar-historico')
            ->monthlyOn(1, '02:30')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'joseespana94@gmail.com'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('empleados:actualizar-estado')
        ->dailyAt('00:01')
        ->appendOutputTo(storage_path('logs/empleados-estado.log'));

        // Agregar el nuevo comando de verificación de suscripciones
        $schedule->command('suscripciones:verificar')
            ->daily()
            ->at('01:00')
            ->appendOutputTo(storage_path('logs/verificar-suscripciones.log'));

        // ============================================
        // ACTUALIZACIÓN DE AGREGADOS CLIENTE360
        // ============================================
        
        // Actualizar métricas RFM cada noche a las 2 AM
        $schedule->command('cliente360:actualizar-agregados --tipo=rfm')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Actualizar top productos cada noche a las 3 AM
        $schedule->command('cliente360:actualizar-agregados --tipo=productos')
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Actualizar ventas mensuales el primer día de cada mes
        $schedule->command('cliente360:actualizar-agregados --tipo=mensuales')
                 ->monthlyOn(1, '04:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Actualizar snapshot fidelización cada 6 horas
        $schedule->command('cliente360:actualizar-agregados --tipo=fidelizacion')
                 ->everySixHours()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Actualizar actividad reciente cada hora
        $schedule->command('cliente360:actualizar-agregados --tipo=actividad')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Actualización completa semanal (domingos a las 1 AM)
        $schedule->command('cliente360:actualizar-agregados --tipo=all --force')
                 ->weeklyOn(0, '01:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->call(function () {
            Log::info('Working');
        })->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
