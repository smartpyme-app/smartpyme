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
        'App\Console\Commands\VerificarSuscripciones'
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

        // Agregar el nuevo comando de verificación de suscripciones
        $schedule->command('suscripciones:verificar')
            ->daily()
            ->at('01:00')
            ->appendOutputTo(storage_path('logs/verificar-suscripciones.log'));

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
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
