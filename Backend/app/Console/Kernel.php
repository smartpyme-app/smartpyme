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
        'App\Console\Commands\VerificarSuscripcion',
        'App\Console\Commands\cliente360\CalcularClientes360Command',
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
                'jose.e@smartpyme.sv'
            );

        // Programar la actualización de métricas para todas las sucursales a las 4:00 AM
        $schedule->command('metricas:sucursales')
            ->dailyAt('04:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'jose.e@smartpyme.sv'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('metricas:empresas --actualizar-historico')
            ->monthlyOn(1, '02:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'jose.e@smartpyme.sv'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('metricas:sucursales --actualizar-historico')
            ->monthlyOn(1, '02:30')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'jose.e@smartpyme.sv'
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

        // Actualizar métricas RFM masivamente cada noche a las 1:00 AM
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=rfm')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-rfm.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // Actualizar top productos masivamente cada noche a las 1:15 AM
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=productos')
            ->dailyAt('01:15')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-productos.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // Actualizar categorías preferidas masivamente cada noche a las 1:30 AM
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=categorias')
            ->dailyAt('01:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-categorias.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // Actualizar ventas mensuales masivamente el primer día de cada mes a las 1:45 AM
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=mensuales')
            ->monthlyOn(1, '01:45')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-mensuales.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // Actualizar snapshot fidelización masivamente cada 6 horas
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=fidelizacion')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-fidelizacion.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // Actualizar actividad reciente masivamente cada 4 horas
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=actividad')
            ->cron('0 */4 * * *')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-actividad.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // Actualización completa masiva semanal con force (domingos a las 00:30 AM)
        $schedule->command('cliente360:actualizar-agregados --masivo --tipo=all --force')
            ->weeklyOn(0, '00:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-completo.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // ============================================
        // FIDELIZACIÓN - EXPIRACIÓN DE PUNTOS
        // ============================================
        $schedule->command('fidelizacion:procesar-expiracion-puntos --sync')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/fidelizacion-expiracion-puntos.log'))
            ->emailOutputOnFailure('jose.e@smartpyme.sv');

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
