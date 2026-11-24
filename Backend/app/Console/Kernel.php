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
        'App\Console\Commands\GenerarFacturasSuscripciones',
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
        // ====================================================================
        // Todo el cron de producción vive aquí. En el servidor el crontab tiene
        // UNA sola línea:
        //   * * * * * cd /home/smartpyme/repositories/unificado/Backend && php artisan schedule:run >> /dev/null 2>&1
        // Las horas son de El Salvador (config app.timezone), NO UTC.
        // Al pasar desde el crontab viejo (UTC) se restaron 6 horas.
        // ====================================================================

        // ---------- Notificaciones / reportes ----------
        // Antes en crontab: 0 0 * * * UTC
        $schedule->command('generate:notificaciones')
            ->dailyAt('18:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/notificaciones.log'));

        // Antes en crontab: cada hora (0 * * * *)
        $schedule->command('reportes:enviar')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/reportes-automaticos.log'));

        // Antes en crontab: 0 8 * * * UTC
        $schedule->command('reportes:limpiar-exportaciones')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/reportes-automaticos-limpieza.log'));

        // Se corre a diario: el propio comando decide si hoy es día de corte
        // (EstilosSalonPeriodo: 4 cortes al mes, el último es el último día del mes).
        // Antes en crontab solo los días 7, 15, 22 y 31 (UTC), que no coincidían con los cortes.
        $schedule->command('reporte:ventas-por-categoria-sucursal')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/reporte-ventas-categoria-sucursal.log'));

        // Antes en crontab: 0 9 1 * * UTC
        $schedule->command('auditoria:purge --months=1')
            ->monthlyOn(1, '03:00')
            ->withoutOverlapping();

        // ---------- Colas / procesos frecuentes (antes en crontab) ----------
        $schedule->command('kardex:procesar-cola')
            ->everyThirtyMinutes()
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/kardex-cola.log'));

        $schedule->command('trabajos:procesar --limite=1000')
            ->hourly()
            ->withoutOverlapping(120)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/trabajos-pendientes.log'));

        $schedule->command('inventario:procesar-cola-analisis-ventas')
            ->everyFiveMinutes()
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/analisis-ventas-cola.log'));

        $schedule->command('dte:ingest-spool --limit=50')
            ->everyFiveMinutes()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/dte-ingest-spool.log'));

        // ---------- Suscripciones ----------
        // Antes en crontab: 0 0 * * * UTC
        $schedule->command('suscripciones:procesar-cargos')
            ->dailyAt('18:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-procesar-cargos.log'));

        // Antes en crontab: 0 7 * * * UTC
        $schedule->command('suscripciones:verificar')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/verificar-suscripciones.log'));

        // Antes en crontab: 0 7 1 * * UTC
        $schedule->command('facturas:generar-suscripciones')
            ->monthlyOn(1, '01:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/facturas-generar-suscripciones.log'));

        // Antes en crontab: 0 10 * * * UTC
        $schedule->command('suscripciones:enviar-recordatorios-correo')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-recordatorios-correo.log'));

        // Antes en crontab: 0 11 * * * UTC
        $schedule->command('suscripciones:reportes-internos-equipo --solo=diario')
            ->dailyAt('05:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reportes-internos-equipo.log'));

        // Antes en crontab: 0 0 * * 5 UTC = jueves 18:00 SV
        $schedule->command('suscripciones:reportes-internos-equipo --solo=semanal')
            ->weeklyOn(4, '18:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reportes-internos-equipo.log'));

        // Último día del mes a las 9 pm: el comando toma el mes en curso (el que termina).
        $schedule->command('suscripciones:reporte-flujo-caja-mensual')
            ->lastDayOfMonth('21:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reporte-flujo-caja-mensual.log'));

        $schedule->command('suscripciones:reporte-bajas-mensual')
            ->monthlyOn(1, '08:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/suscripciones-reporte-bajas-mensual.log'));

        // ---------- Ventas ----------
        // Antes en crontab: 0 8 * * * UTC
        $schedule->command('ventas:generar-recurrentes')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/ventas-recurrentes.log'));

        // ---------- Cliente360 / analítica ----------
        // Dos veces al día (antes en crontab: 0 9,12 * * * UTC)
        $schedule->command('cliente360:actualizar-agregados --masivo')
            ->twiceDaily(3, 6)
            ->withoutOverlapping(170)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cliente360-agregados.log'))
            ->emailOutputOnFailure('joseespana94@gmail.com');

        // ETL de sp-analytics (script fuera del repo). Antes en crontab: 0 7 * * * UTC.
        // Solo se programa si el script existe en este servidor.
        $etlAnalitica = config('services.sp_analytics.etl_script');
        if ($etlAnalitica && is_file($etlAnalitica)) {
            $schedule->exec($etlAnalitica)
                ->dailyAt('01:00')
                ->withoutOverlapping(240)
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/sp-analytics-etl.log'));
        }

        $schedule->command('metricas:empresas')
            ->dailyAt('03:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                // env('ADMIN_EMAIL')
                'jose.e@smartpyme.app'
            );

        // Programar la actualización de métricas para todas las sucursales a las 4:00 AM
        $schedule->command('metricas:sucursales')
            ->dailyAt('04:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'jose.e@smartpyme.app'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('metricas:empresas --actualizar-historico')
            ->monthlyOn(1, '02:00')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'jose.e@smartpyme.app'
                // env('ADMIN_EMAIL')
            );

        $schedule->command('metricas:sucursales --actualizar-historico')
            ->monthlyOn(1, '02:30')
            ->runInBackground()
            ->withoutOverlapping()
            ->emailOutputOnFailure(
                'jose.e@smartpyme.app'
                // env('ADMIN_EMAIL')
            );

        // ---------- Planilla / préstamos / contabilidad ----------
        $schedule->command('empleados:actualizar-estado')
            ->dailyAt('00:01')
            ->appendOutputTo(storage_path('logs/empleados-estado.log'));

        $schedule->command('prestamos:recordatorios-vencimiento')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/prestamos-recordatorios-vencimiento.log'));

        $schedule->command('bonos:evaluar')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/bonos-evaluar.log'));

        // Solo actúa el día de corte configurado por cada empresa, sobre el mes anterior.
        $schedule->command('activos:depreciacion-mensual')
            ->dailyAt('05:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/activos-depreciacion.log'));

        // ---------- Fidelización ----------
        $schedule->command('fidelizacion:procesar-expiracion-puntos --sync')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/fidelizacion-expiracion-puntos.log'))
            ->emailOutputOnFailure('jose.e@smartpyme.app');

        // ---------- DTE ----------
        if (config('dte.schedule_enabled')) {
            // Cada madrugada: sube a S3 los DTE con fecha hasta ayer que sigan en la columna
            // (incluye los que se volvieron a llenar tras migrarse) y deja solo la llave.
            $schedule->command('dte:migrate-to-s3 --dias-minimos=1')
                ->dailyAt('01:00')
                ->withoutOverlapping(120)
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/dte-migrate-s3.log'));
        }

        // Sync DTE email accounts every hour (download DTEs from connected mailboxes)
        $schedule->command('dte:sync-accounts --dias=30')
            ->hourly()
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/dte-sync.log'));

        $schedule->command('dte:ingest-purge --days=7')
            ->dailyAt('03:20')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/dte-ingest-purge.log'));

        // Cache TC del día en pais_configuracion (paises con fuente=api, p.ej. BCCR CR)
        $schedule->command('tipos-cambio:sync-dia')
            ->dailyAt('06:00')
            ->timezone('America/Costa_Rica')
            ->withoutOverlapping();

        // Latido: confirma en el log que schedule:run está corriendo
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
