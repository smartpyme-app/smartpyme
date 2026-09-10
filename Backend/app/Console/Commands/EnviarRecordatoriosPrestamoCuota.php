<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Services\PrestamosEmpresa\PrestamoRecordatorioService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatoriosPrestamoCuota extends Command
{
    protected $signature = 'prestamos:recordatorios-vencimiento {--dias=2 : Días antes del vencimiento} {--dry-run : No enviar correos ni escribir caché}';

    protected $description = 'Envía correos de recordatorio de cuotas de préstamo próximas a vencer';

    public function handle(PrestamoRecordatorioService $service): int
    {
        $dias = max(0, (int) $this->option('dias'));
        $dryRun = (bool) $this->option('dry-run');

        $fechaObjetivo = Carbon::today()->addDays($dias);
        $this->info("Recordatorios de cuotas de préstamo ({$dias} día(s) antes del vencimiento)...");
        $this->line("Fecha de vencimiento buscada: {$fechaObjetivo->toDateString()}");
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: no se enviarán correos.');
        }

        $cuotas = $service->cuotasPorVencerEn($dias);
        if ($cuotas->isEmpty()) {
            $this->info("No hay cuotas por vencer el {$fechaObjetivo->toDateString()}.");

            return 0;
        }

        $enviados = 0;
        $omitidos = 0;
        $errores = 0;

        /** @var Collection<int, \Illuminate\Support\Collection<int, \App\Models\PrestamosEmpresa\PrestamoCuota>> $porEmpresa */
        $porEmpresa = $cuotas->groupBy(fn ($c) => $c->prestamo->id_empresa);

        foreach ($porEmpresa as $idEmpresa => $cuotasEmpresa) {
            $empresa = Empresa::find($idEmpresa);
            if (! $empresa) {
                $omitidos++;
                continue;
            }

            $destinatarios = $service->destinatariosPorEmpresa((int) $idEmpresa);
            if ($destinatarios->isEmpty()) {
                $this->line("Sin destinatarios para empresa {$idEmpresa} ({$cuotasEmpresa->count()} cuota(s))");
                $omitidos++;
                continue;
            }

            $cacheKey = $service->cacheKeyCorreoEmpresa((int) $idEmpresa, $dias);
            if (! $dryRun && ! Cache::add($cacheKey, true, now()->addDay())) {
                $this->line("Omitido (ya enviado hoy): empresa {$idEmpresa}");
                $omitidos++;
                continue;
            }

            $fechaTexto = $fechaObjetivo->locale('es')->translatedFormat('d \d\e F \d\e Y');
            $asunto = "Recordatorio: cuota(s) de préstamo vencen el {$fechaTexto} — {$empresa->nombre}";
            $emails = $destinatarios->pluck('email')->filter()->unique()->values()->all();

            $datos = [
                'empresa' => $empresa,
                'cuotas' => $cuotasEmpresa,
                'dias' => $dias,
                'fecha_vencimiento_texto' => $fechaTexto,
                'app_url' => rtrim((string) config('app.url'), '/'),
            ];

            try {
                if (! $dryRun) {
                    Mail::send('mails.prestamo-cuota-recordatorio', $datos, function ($message) use ($emails, $asunto) {
                        $message->to($emails)->subject($asunto);
                    });
                }

                $enviados++;
                $this->line('OK empresa '.$idEmpresa.' ('.$cuotasEmpresa->count().' cuota(s)) → '.implode(', ', $emails));
                Log::info('Recordatorio cuota préstamo enviado', [
                    'id_empresa' => $idEmpresa,
                    'emails' => $emails,
                    'cuotas' => $cuotasEmpresa->pluck('id')->all(),
                    'dias' => $dias,
                    'dry_run' => $dryRun,
                ]);
            } catch (\Throwable $e) {
                $errores++;
                Cache::forget($cacheKey);
                Log::error('Error enviando recordatorio cuota préstamo', [
                    'id_empresa' => $idEmpresa,
                    'emails' => $emails,
                    'error' => $e->getMessage(),
                ]);
                $this->error('Error empresa '.$idEmpresa.': '.$e->getMessage());
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Simulaciones: {$enviados} | Omitidos: {$omitidos} | Errores: {$errores}");
        } else {
            $this->info("Enviados: {$enviados} | Omitidos: {$omitidos} | Errores: {$errores}");
        }

        return $errores > 0 ? 1 : 0;
    }
}
