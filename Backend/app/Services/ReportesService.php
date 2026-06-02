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
            $fechaProximoPago  = Carbon::parse($sus->fecha_proximo_pago);
            $tipoNorm   = strtolower(trim($sus->tipo_plan ?? ''));
            $esAnual    = ($tipoNorm === 'anual');
            $diaPago    = $sus->dia_pago ?? $fechaProximoPago->day;

            // Monto: preferir monto de la suscripción; si es 0 usar empresa
            $monto = (float) $sus->monto;
            if ($monto <= 0 && $sus->empresa) {
                $monto = $esAnual
                    ? (float) $sus->empresa->monto_anual
                    : (float) $sus->empresa->monto_mensual;
            }

            // Si es anual, solo cobramos en su fecha_proximo_pago
            if ($esAnual) {
                if ($fechaProximoPago->between($inicio, $fin)) {
                    $esNueva = ($sus->created_at && Carbon::parse($sus->created_at)->format('Y-m') === $fechaProximoPago->format('Y-m'));
                    $this->registrarPago($sus, $fechaProximoPago, $monto, $esAnual, $esNueva, $quincenas, $pivotData);
                }
            } else {
                // Si es mensual (o cualquier otro tipo recurrente mensual), proyectamos para cada mes en el rango
                $mesCursor = $inicio->copy()->startOfMonth();
                while ($mesCursor->lte($fin)) {
                    $daysInMonth = $mesCursor->daysInMonth;
                    $diaActual = min($diaPago, $daysInMonth);
                    $fechaProyectada = $mesCursor->copy()->day($diaActual)->startOfDay();

                    // Solo proyectamos si la fecha proyectada es mayor o igual a la fecha de próximo pago real
                    // y cae dentro del rango de visualización
                    if ($fechaProyectada->gte($fechaProximoPago->startOfDay()) && $fechaProyectada->between($inicio, $fin)) {
                        $esNueva = ($sus->created_at && Carbon::parse($sus->created_at)->format('Y-m') === $fechaProyectada->format('Y-m'));
                        $this->registrarPago($sus, $fechaProyectada, $monto, $esAnual, $esNueva, $quincenas, $pivotData);
                    }
                    $mesCursor->addMonth();
                }
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
     * Helper para registrar un pago proyectado tanto en quincenas como en pivot_data.
     */
    private function registrarPago($sus, Carbon $fechaPago, float $monto, bool $esAnual, bool $esNueva, array &$quincenas, array &$pivotData)
    {
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
            'Tipo Plan'  => $esAnual ? 'Anual' : 'Mensual',
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
