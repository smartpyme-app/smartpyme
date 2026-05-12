<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatoriosSuscripcionCorreo extends Command
{
    protected $signature = 'suscripciones:enviar-recordatorios-correo {--dry-run : No enviar correos ni escribir caché}';

    protected $description = 'Envía correos de recordatorio/alerta según días respecto a fecha_proximo_pago (3, 1, 0, -2)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Iniciando envío de recordatorios por correo (suscripciones)...');
        if ($dryRun) {
            $this->warn('MODO DRY-RUN: no se enviarán correos.');
        }

        $enviados = 0;
        $omitidos = 0;
        $errores = 0;

        $suscripciones = Suscripcion::query()
            ->with(['usuario', 'empresa'])
            ->whereNotNull('fecha_proximo_pago')
            ->whereRaw('LOWER(TRIM(estado)) IN (?, ?, ?)', ['activo', 'pendiente', 'renovado'])
            ->get();

        foreach ($suscripciones as $suscripcion) {
            $usuario = $suscripcion->usuario;
            $empresa = $suscripcion->empresa;

            if (!$usuario || empty($usuario->email)) {
                $omitidos++;
                continue;
            }

            if (!$empresa) {
                $omitidos++;
                continue;
            }

            $dias = $suscripcion->diasFaltantes();
            if ($dias === null) {
                $omitidos++;
                continue;
            }

            $tipo = $this->resolverTipoCorreo($dias);
            if ($tipo === null) {
                continue;
            }

            $cacheKey = sprintf(
                'recordatorio_correo_suscripcion_%d_%s_%s',
                $suscripcion->id,
                $tipo,
                now()->format('Y-m-d')
            );

            if (!$dryRun && ! Cache::add($cacheKey, true, now()->addDay())) {
                $this->line("Omitido (ya enviado hoy): suscripción {$suscripcion->id} — {$tipo}");
                continue;
            }

            $fechaProxima = Carbon::parse($suscripcion->fecha_proximo_pago)
                ->locale('es')
                ->translatedFormat('d \d\e F \d\e Y');

            $asunto = $this->asuntoParaTipo($tipo, $empresa->nombre);
            $datos = [
                'usuario' => $usuario,
                'empresa' => $empresa,
                'suscripcion' => $suscripcion,
                'fecha_proximo_pago_texto' => $fechaProxima,
                'tipo' => $tipo,
            ];

            try {
                if (!$dryRun) {
                    Mail::send('mails.suscripcion-recordatorio', $datos, function ($message) use ($usuario, $asunto) {
                        $message->to($usuario->email, $usuario->name)->subject($asunto);
                    });
                }

                $enviados++;
                $this->line("OK suscripción {$suscripcion->id} ({$tipo}) → {$usuario->email}");
                Log::channel('suscripciones')->info('Recordatorio suscripción enviado', [
                    'suscripcion_id' => $suscripcion->id,
                    'tipo' => $tipo,
                    'email' => $usuario->email,
                    'dry_run' => $dryRun,
                ]);
            } catch (\Throwable $e) {
                $errores++;
                Cache::forget($cacheKey);
                Log::channel('suscripciones')->error('Error enviando recordatorio suscripción', [
                    'suscripcion_id' => $suscripcion->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Error suscripción {$suscripcion->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Simulaciones (sin envío real): {$enviados} | Omitidos (sin email/datos): {$omitidos} | Errores: {$errores}");
        } else {
            $this->info("Enviados: {$enviados} | Omitidos (sin email/datos): {$omitidos} | Errores: {$errores}");
        }

        return $errores > 0 ? 1 : 0;
    }

    /**
     * dias_faltantes del modelo: 3 y 1 = antes; 0 = vence hoy; -2 = 2.º día tras vencimiento (día +2 del flujo).
     */
    private function resolverTipoCorreo(int $dias): ?string
    {
        if ($dias === 3 || $dias === 1) {
            return 'recordatorio_previo';
        }
        if ($dias === 0) {
            return 'alerta_vencimiento';
        }
        if ($dias === -2) {
            return 'advertencia_urgente';
        }

        return null;
    }

    private function asuntoParaTipo(string $tipo, string $nombreEmpresa): string
    {
        switch ($tipo) {
            case 'recordatorio_previo':
                return "Recordatorio de renovación — {$nombreEmpresa}";
            case 'alerta_vencimiento':
                return "Importante: plazo de suscripción — {$nombreEmpresa}";
            case 'advertencia_urgente':
                return "Importante: tu acceso en SmartPyme — {$nombreEmpresa}";
            default:
                return "SmartPyme — {$nombreEmpresa}";
        }
    }
}
