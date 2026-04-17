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

        $schedule->command('suscripciones:enviar-recordatorios-correo')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-recordatorios-correo.log'));

        $schedule->command('suscripciones:reportes-internos-equipo --solo=diario')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reportes-internos-equipo.log'));

        $schedule->command('suscripciones:reportes-internos-equipo --solo=semanal')
            ->weeklyOn(5, '08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reportes-internos-equipo.log'));

        $schedule->command('suscripciones:reporte-flujo-caja-mensual')
            ->monthlyOn(1, '08:15')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reporte-flujo-caja-mensual.log'));

        // ============================================
        // ACTUALIZACIÓN DE AGREGADOS CLIENTE360
        // ============================================

        // Actualizar todos los agregados cada minuto (viable con pocas empresas con fidelización habilitada)
        $schedule->command('cliente360:actualizar-agregados --masivo')
            ->everyMinute()
            ->withoutOverlapping(50)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-agregados.log'))
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
