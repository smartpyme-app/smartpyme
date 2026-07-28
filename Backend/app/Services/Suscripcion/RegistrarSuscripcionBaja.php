<?php

namespace App\Services\Suscripcion;

use App\Models\Suscripcion;
use App\Models\SuscripcionBaja;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RegistrarSuscripcionBaja
{
    /**
     * Snapshot inmutable al momento de la baja. Idempotente por suscripcion+motivo+día.
     */
    public function registrar(
        Suscripcion $suscripcion,
        string $motivo,
        ?Carbon $fechaBaja = null,
        ?string $motivoCancelacionTexto = null
    ): ?SuscripcionBaja {
        $motivos = [
            SuscripcionBaja::MOTIVO_CANCELACION_VOLUNTARIA,
            SuscripcionBaja::MOTIVO_FALTA_PAGO,
            SuscripcionBaja::MOTIVO_INACTIVIDAD,
        ];
        if (! in_array($motivo, $motivos, true)) {
            Log::channel('suscripciones')->warning('RegistrarSuscripcionBaja: motivo inválido', [
                'suscripcion_id' => $suscripcion->id,
                'motivo' => $motivo,
            ]);

            return null;
        }

        $suscripcion->loadMissing(['empresa', 'plan']);

        $fecha = ($fechaBaja ?? Carbon::now())->copy();
        // Unique index usa el timestamp exacto; normalizamos al segundo del evento
        // y evitamos duplicados del mismo día+motivo.
        $existente = SuscripcionBaja::query()
            ->where('suscripcion_id', $suscripcion->id)
            ->where('motivo', $motivo)
            ->whereDate('fecha_baja', $fecha->toDateString())
            ->first();

        if ($existente) {
            return $existente;
        }

        $empresa = $suscripcion->empresa;
        $tipoPlan = $this->resolverTipoPlan($suscripcion);
        $monto = $this->resolverMonto($suscripcion, $tipoPlan);

        try {
            $baja = SuscripcionBaja::create([
                'suscripcion_id' => $suscripcion->id,
                'empresa_id' => $suscripcion->empresa_id,
                'usuario_id' => $suscripcion->usuario_id,
                'motivo' => $motivo,
                'fecha_baja' => $fecha,
                'tipo_plan' => $tipoPlan,
                'monto' => $monto,
                'plan_nombre' => $suscripcion->plan->nombre ?? null,
                'empresa_nombre' => $empresa->nombre ?? null,
                'motivo_cancelacion' => $motivoCancelacionTexto
                    ?? $suscripcion->motivo_cancelacion,
            ]);

            Log::channel('suscripciones')->info('Baja de suscripción registrada', [
                'baja_id' => $baja->id,
                'suscripcion_id' => $suscripcion->id,
                'empresa_id' => $suscripcion->empresa_id,
                'motivo' => $motivo,
                'tipo_plan' => $tipoPlan,
                'monto' => $monto,
            ]);

            return $baja;
        } catch (\Throwable $e) {
            // Carrera en unique: releer
            $existente = SuscripcionBaja::query()
                ->where('suscripcion_id', $suscripcion->id)
                ->where('motivo', $motivo)
                ->whereDate('fecha_baja', $fecha->toDateString())
                ->first();
            if ($existente) {
                return $existente;
            }

            Log::channel('suscripciones')->error('Error registrando baja de suscripción', [
                'suscripcion_id' => $suscripcion->id,
                'motivo' => $motivo,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function resolverTipoPlan(Suscripcion $suscripcion): string
    {
        $empresa = $suscripcion->empresa;
        $raw = trim((string) ($suscripcion->tipo_plan ?? ''));
        if ($raw === '' && $empresa) {
            $raw = trim((string) ($empresa->frecuencia_pago ?? $empresa->tipo_plan ?? ''));
        }

        $n = mb_strtolower($raw);
        if (strpos($n, 'anual') !== false || strpos($n, 'year') !== false) {
            return config('constants.FRECUENCIA_PAGO_ANUAL', 'Anual');
        }
        if (strpos($n, 'trimestr') !== false || strpos($n, 'quarter') !== false) {
            return config('constants.FRECUENCIA_PAGO_TRIMESTRAL', 'Trimestral');
        }

        return config('constants.FRECUENCIA_PAGO_MENSUAL', 'Mensual');
    }

    public function resolverMonto(Suscripcion $suscripcion, ?string $tipoPlan = null): float
    {
        $monto = (float) ($suscripcion->monto ?? 0);
        if ($monto > 0) {
            return round($monto, 2);
        }

        $empresa = $suscripcion->empresa;
        if (! $empresa) {
            return 0.0;
        }

        $tipo = mb_strtolower((string) ($tipoPlan ?? $this->resolverTipoPlan($suscripcion)));
        if (strpos($tipo, 'anual') !== false) {
            $anual = (float) ($empresa->monto_anual ?? 0);
            if ($anual > 0) {
                return round($anual, 2);
            }
        }

        $mensual = (float) ($empresa->monto_mensual ?? 0);
        if ($mensual > 0) {
            return round($mensual, 2);
        }

        $total = (float) ($empresa->total ?? 0);
        if ($total > 0) {
            return round($total, 2);
        }

        $precioPlan = (float) ($suscripcion->plan->precio ?? 0);

        return round(max(0, $precioPlan), 2);
    }
}
