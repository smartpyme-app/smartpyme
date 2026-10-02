<?php

namespace App\Services;

use App\Models\Suscripcion;
use Carbon\Carbon;

class ReportesService
{
    /**
     * Calcula la previsión de flujo de efectivo de suscripciones
     *
     * @param string|null $inicioStr
     * @param string|null $finStr
     * @return array
     */
    public function obtenerFlujoEfectivo(?string $inicioStr = null, ?string $finStr = null): array
    {
        $inicioDefault = Carbon::now()->startOfMonth()->format('Y-m-d');
        $finDefault    = Carbon::now()->addMonths(5)->endOfMonth()->format('Y-m-d');

        $inicio = Carbon::parse($inicioStr ?? $inicioDefault)->startOfDay();
        $fin    = Carbon::parse($finStr ?? $finDefault)->endOfDay();

        // Todas las suscripciones activas con próximo pago <= fin
        $suscripciones = Suscripcion::with(['empresa', 'plan'])
            ->where('estado', 'activo')
            ->where('fecha_proximo_pago', '<=', $fin)
            ->get();

        // ── Construir estructura por quincena ──────────────────────────────────
        $quincenas = $this->generarQuincenas($inicio, $fin);

        foreach ($quincenas as &$q) {
            $q['renovaciones_mensual']    = [];
            $q['renovaciones_anual']      = [];
            $q['nuevas_mensual']          = [];
            $q['nuevas_anual']            = [];
        }
        unset($q);

        $pivotData = [];

        foreach ($suscripciones as $sus) {
            $fechaProximoPago = Carbon::parse($sus->fecha_proximo_pago);
            $cadencia = self::cadenciaCobro(
                $sus->empresa?->frecuencia_pago,
                $sus->empresa?->tipo_plan,
                $sus->tipo_plan ?? null
            );

            // Monto: preferir monto de la suscripción; si es 0 usar empresa
            $monto = (float) $sus->monto;
            if ($monto <= 0 && $sus->empresa) {
                $monto = $cadencia === 'anual'
                    ? (float) $sus->empresa->monto_anual
                    : (float) $sus->empresa->monto_mensual;
            }

            foreach (self::fechasCobro($inicio, $fin, $fechaProximoPago, $cadencia) as $fecha) {
                $fechaPago = Carbon::parse($fecha);
                $esNueva = ($sus->created_at && Carbon::parse($sus->created_at)->format('Y-m') === $fechaPago->format('Y-m'));
                $this->registrarPago($sus, $fechaPago, $monto, $cadencia, $esNueva, $quincenas, $pivotData);
            }
        }

        // ── Totales globales ────────────────────────────────────────────────
        $totalNuevas      = 0;
        $totalRenovaciones = 0;
        $countNuevas      = 0;
        $countRenovaciones = 0;
        foreach ($quincenas as $q) {
            foreach ($q['nuevas_mensual'] as $e)       { $totalNuevas += $e['monto']; $countNuevas++; }
            foreach ($q['nuevas_anual'] as $e)         { $totalNuevas += $e['monto']; $countNuevas++; }
            foreach ($q['renovaciones_mensual'] as $e) { $totalRenovaciones += $e['monto']; $countRenovaciones++; }
            foreach ($q['renovaciones_anual'] as $e)   { $totalRenovaciones += $e['monto']; $countRenovaciones++; }
        }

        // ── Calcular totales por quincena ──────────────────────────────────────
        $resultado = [];
        foreach ($quincenas as $q) {
            $q['total_nuevas']        = round(array_sum(array_column($q['nuevas_mensual'], 'monto'))
                                            + array_sum(array_column($q['nuevas_anual'], 'monto')), 2);
            $q['total_renovaciones']  = round(array_sum(array_column($q['renovaciones_mensual'], 'monto'))
                                            + array_sum(array_column($q['renovaciones_anual'], 'monto')), 2);
            $q['total']               = round($q['total_nuevas'] + $q['total_renovaciones'], 2);
            $q['count_nuevas']        = count($q['nuevas_mensual']) + count($q['nuevas_anual']);
            $q['count_renovaciones']  = count($q['renovaciones_mensual']) + count($q['renovaciones_anual']);
            $resultado[] = $q;
        }

        return [
            'periodo'                  => ['inicio' => $inicio->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'total_nuevas'             => round($totalNuevas, 2),
            'total_nuevas_count'       => $countNuevas,
            'total_renovaciones'       => round($totalRenovaciones, 2),
            'total_renovaciones_count' => $countRenovaciones,
            'total_general'            => round($totalNuevas + $totalRenovaciones, 2),
            'total_general_count'      => $countNuevas + $countRenovaciones,
            'quincenas'                => $resultado,
            'pivot_data'               => $pivotData,
        ];
    }

    /**
     * Cadencia de cobro. frecuencia_pago manda: tipo_plan de la suscripción
     * queda en Mensual aunque el cliente pague al año.
     */
    public static function cadenciaCobro(?string $frecuenciaPago, ?string $empresaTipoPlan, ?string $suscripcionTipoPlan): string
    {
        foreach ([$frecuenciaPago, $empresaTipoPlan, $suscripcionTipoPlan] as $candidato) {
            $norm = strtolower(trim((string) $candidato));
            if (in_array($norm, ['mensual', 'trimestral', 'semestral', 'anual'], true)) {
                return $norm;
            }
        }

        return 'sin_frecuencia';
    }

    public static function etiquetaCadencia(string $cadencia): string
    {
        if ($cadencia === 'sin_frecuencia') {
            return 'Sin frecuencia';
        }

        return ucfirst($cadencia);
    }

    /**
     * Fechas de cobro dentro del rango, avanzando desde fecha_proximo_pago.
     *
     * @return string[] Y-m-d
     */
    public static function fechasCobro(Carbon $inicio, Carbon $fin, Carbon $fechaProximoPago, string $cadencia): array
    {
        $diaPago = (int) $fechaProximoPago->day;
        $cursor = $fechaProximoPago->copy()->startOfDay();
        $desde = $inicio->copy()->startOfDay();
        $hasta = $fin->copy()->endOfDay();

        if ($cadencia === 'sin_frecuencia') {
            if ($cursor->gte($desde) && $cursor->lte($hasta)) {
                return [$cursor->format('Y-m-d')];
            }

            return [];
        }

        $meses = self::mesesDeCadencia($cadencia);
        $fechas = [];

        // España Dev: tope de 240 periodos. Una fecha de próximo pago de hace más de 20 años en un plan mensual no alcanza el rango.
        for ($i = 0; $i < 240 && $cursor->lte($hasta); $i++) {
            if ($cursor->gte($desde)) {
                $fechas[] = $cursor->format('Y-m-d');
            }
            $cursor = self::avanzarMeses($cursor, $meses, $diaPago);
        }

        return $fechas;
    }

    private static function mesesDeCadencia(string $cadencia): int
    {
        if ($cadencia === 'trimestral') {
            return 3;
        }
        if ($cadencia === 'semestral') {
            return 6;
        }
        if ($cadencia === 'anual') {
            return 12;
        }

        return 1;
    }

    private static function avanzarMeses(Carbon $desde, int $meses, int $diaPago): Carbon
    {
        $siguiente = $desde->copy()->startOfMonth()->addMonths($meses);
        $dia = min($diaPago, $siguiente->daysInMonth);

        return $siguiente->day($dia)->startOfDay();
    }

    /**
     * Helper para registrar un pago proyectado tanto en quincenas como en pivot_data.
     */
    private function registrarPago($sus, Carbon $fechaPago, float $monto, string $cadencia, bool $esNueva, array &$quincenas, array &$pivotData)
    {
        $esAnual = $cadencia === 'anual';
        $entrada = [
            'id'          => $sus->id,
            'empresa'     => $sus->empresa ? $sus->empresa->nombre : '—',
            'monto'       => round($monto, 2),
            'fecha_pago'  => $fechaPago->format('Y-m-d'),
            'tipo_plan'   => $sus->tipo_plan ?? '',
        ];

        $quincenaLabel = 'Fuera de rango';
        foreach ($quincenas as &$q) {
            $desde = Carbon::parse($q['desde']);
            $hasta = Carbon::parse($q['hasta']);
            if ($fechaPago->between($desde, $hasta)) {
                $quincenaLabel = $q['label'];
                if ($esNueva) {
                    if ($esAnual) {
                        $q['nuevas_anual'][] = $entrada;
                    } else {
                        $q['nuevas_mensual'][] = $entrada;
                    }
                } else {
                    if ($esAnual) {
                        $q['renovaciones_anual'][] = $entrada;
                    } else {
                        $q['renovaciones_mensual'][] = $entrada;
                    }
                }
                break;
            }
        }
        unset($q);

        $pivotData[] = [
            'Empresa'    => $sus->empresa ? $sus->empresa->nombre : '—',
            'Monto'      => round($monto, 2),
            'Fecha Pago' => $fechaPago->format('Y-m-d'),
            'Tipo Plan'  => self::etiquetaCadencia($cadencia),
            'Categoría'  => $esNueva ? 'Nueva suscripción' : 'Renovación',
            'Plan'       => $sus->plan ? $sus->plan->nombre : 'Desconocido',
            'Quincena'   => $quincenaLabel,
        ];
    }

    /**
     * Genera un arreglo de quincenas entre dos fechas.
     * Cada quincena: { label, desde, hasta }
     */
    private function generarQuincenas(Carbon $inicio, Carbon $fin): array
    {
        $quincenas = [];
        $cursor    = $inicio->copy()->startOfMonth();
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        while ($cursor->lte($fin)) {
            $mesLabel = $meses[$cursor->month];

            // Primera quincena: 1-15
            $q1Desde = $cursor->copy()->startOfMonth();
            $q1Hasta = $cursor->copy()->day(15)->endOfDay();
            if ($q1Hasta->gte($inicio) && $q1Desde->lte($fin)) {
                $quincenas[] = [
                    'label'  => $cursor->format('Y-m') . ' Q1 ' . $mesLabel,
                    'desde'  => max($q1Desde, $inicio)->format('Y-m-d'),
                    'hasta'  => min($q1Hasta, $fin)->format('Y-m-d'),
                ];
            }

            // Segunda quincena: 16-fin de mes
            $q2Desde = $cursor->copy()->day(16)->startOfDay();
            $q2Hasta = $cursor->copy()->endOfMonth()->endOfDay();
            if ($q2Hasta->gte($inicio) && $q2Desde->lte($fin)) {
                $quincenas[] = [
                    'label'  => $cursor->format('Y-m') . ' Q2 ' . $mesLabel,
                    'desde'  => max($q2Desde, $inicio)->format('Y-m-d'),
                    'hasta'  => min($q2Hasta, $fin)->format('Y-m-d'),
                ];
            }

            $cursor->addMonth();
        }

        return $quincenas;
    }
}
