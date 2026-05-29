<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarReportesInternosSuscripcionEquipo extends Command
{
    protected $signature = 'suscripciones:reportes-internos-equipo {--solo=diario : diario (A+B) o semanal (viernes)} {--dry-run : No enviar correos}';

    protected $description = 'Reportes internos al equipo SmartPyme: vencen hoy, acceso limitado mañana, o vencen la próxima semana (viernes)';

    public function handle(): int
    {
        $solo = $this->option('solo') ?: 'diario';
        if (! in_array($solo, ['diario', 'semanal'], true)) {
            $this->error('Use --solo=diario o --solo=semanal');

            return 1;
        }
        $dryRun = (bool) $this->option('dry-run');

        $destinatarios = config('constants.MAIL_EQUIPO_REPORTES_SUSCRIPCION', []);
        if (! is_array($destinatarios) || $destinatarios === []) {
            $this->error('No hay destinatarios configurados (constants.MAIL_EQUIPO_REPORTES_SUSCRIPCION).');

            return 1;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: no se enviarán correos.');
        }

        try {
            if ($solo === 'semanal') {
                return $this->enviarReporteSemanal($destinatarios, $dryRun) ? 0 : 1;
            }

            $okA = $this->enviarReporteDiarioA($destinatarios, $dryRun);
            $okB = $this->enviarReporteDiarioB($destinatarios, $dryRun);

            return ($okA && $okB) ? 0 : 1;
        } catch (\Throwable $e) {
            Log::channel('suscripciones')->error('Reportes internos suscripción', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return 1;
        }
    }

    /**
     * Reporte A: suscripciones con fecha_proximo_pago hoy (día 0).
     */
    private function enviarReporteDiarioA(array $destinatarios, bool $dryRun): bool
    {
        $hoy = Carbon::today();
        $filas = $this->queryBaseSuscripcionesSeguimiento()
            ->whereDate('fecha_proximo_pago', $hoy)
            ->orderBy('fecha_proximo_pago')
            ->get();

        $datos = $this->mapearFilasParaCorreo($filas);

        return $this->enviarCorreoLista(
            $destinatarios,
            '[SmartPyme] Reporte diario A — Vencen hoy ('.$hoy->format('d/m/Y').')',
            'Reporte diario A: clientes cuya suscripción vence hoy (fecha de próximo pago).',
            $datos,
            $dryRun,
            'reporte_diario_a'
        );
    }

    /**
     * Reporte B: clientes que al día siguiente quedarían con acceso limitado/suspendido
     * (último día de gracia: dias_faltantes = -3 → mañana pasa a -4 con prórroga de 3 días).
     */
    private function enviarReporteDiarioB(array $destinatarios, bool $dryRun): bool
    {
        $umbral = max(1, (int) config('constants.DIAS_PRORROGA_SUSCRIPCION'));
        $diaUltimoGracia = -$umbral;

        $filas = $this->queryBaseSuscripcionesSeguimiento()
            ->get()
            ->filter(function (Suscripcion $s) use ($diaUltimoGracia) {
                $d = $s->diasFaltantes();

                return $d !== null && $d === $diaUltimoGracia;
            })
            ->values();

        $datos = $this->mapearFilasParaCorreo($filas);

        return $this->enviarCorreoLista(
            $destinatarios,
            '[SmartPyme] Reporte diario B — Acceso limitado mañana ('.Carbon::now()->format('d/m/Y').')',
            'Reporte diario B: clientes en último día de gracia; mañana podría aplicarse suspensión de acceso por saldos pendientes con el sistema (Tomar las medidas pertinentes).',
            $datos,
            $dryRun,
            'reporte_diario_b'
        );
    }

    /**
     * Reporte semanal (viernes): próxima semana calendario (lunes a domingo siguiente a la semana actual).
     */
    private function enviarReporteSemanal(array $destinatarios, bool $dryRun): bool
    {
        $inicio = Carbon::now()->addWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fin = Carbon::now()->addWeek()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $filas = $this->queryBaseSuscripcionesSeguimiento()
            ->whereBetween('fecha_proximo_pago', [$inicio, $fin])
            ->orderBy('fecha_proximo_pago')
            ->get();

        $datos = $this->mapearFilasParaCorreo($filas);

        $rango = $inicio->format('d/m/Y').' — '.$fin->format('d/m/Y');

        return $this->enviarCorreoLista(
            $destinatarios,
            '[SmartPyme] Reporte semanal — Vencen la próxima semana ('.$rango.')',
            'Reporte semanal: suscripciones con fecha de próximo pago entre el '.$inicio->format('d/m/Y').' y el '.$fin->format('d/m/Y').' (próxima semana).',
            $datos,
            $dryRun,
            'reporte_semanal'
        );
    }

    private function queryBaseSuscripcionesSeguimiento()
    {
        return Suscripcion::query()
            ->with(['usuario', 'empresa', 'plan'])
            ->whereNotNull('fecha_proximo_pago')
            ->whereRaw('LOWER(TRIM(estado)) IN (?, ?, ?)', ['activo', 'pendiente', 'renovado']);
    }

    private function mapearFilasParaCorreo($filas): array
    {
        $out = [];
        foreach ($filas as $s) {
            $empresa = $s->empresa;
            $usuario = $s->usuario;
            $plan = $s->plan;
            $dias = $s->diasFaltantes();

            $out[] = [
                'empresa' => $empresa ? $empresa->nombre : '—',
                'fecha_proximo_pago' => $s->fecha_proximo_pago
                    ? Carbon::parse($s->fecha_proximo_pago)->format('d/m/Y H:i')
                    : '—',
                'estado' => $s->estado ?? '—',
                'plan' => $plan ? $plan->nombre : ($s->tipo_plan ?? '—'),
                'contacto' => $usuario ? $usuario->email : '—',
                'dias_faltantes' => $dias !== null ? (string) $dias : '—',
            ];
        }

        return $out;
    }

    private function enviarCorreoLista(
        array $destinatarios,
        string $asunto,
        string $intro,
        array $filas,
        bool $dryRun,
        string $tipoLog
    ): bool {
        $this->info("{$tipoLog}: ".count($filas).' registro(s).');

        if ($dryRun) {
            return true;
        }

        try {
            Mail::send('mails.reporte-interno-suscripciones-equipo', [
                'intro' => $intro,
                'filas' => $filas,
                'generado' => Carbon::now()->format('d/m/Y H:i:s'),
            ], function ($message) use ($destinatarios, $asunto) {
                $message->to($destinatarios)->subject($asunto);
            });

            Log::channel('suscripciones')->info('Reporte interno equipo enviado', [
                'tipo' => $tipoLog,
                'filas' => count($filas),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('suscripciones')->error('Error enviando reporte interno', [
                'tipo' => $tipoLog,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
