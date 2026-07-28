<?php

namespace App\Services\Suscripcion;

use App\Models\SuscripcionBaja;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BajasMensualesAgregador
{
    /**
     * @return array{
     *   mes: Carbon,
     *   detalle: Collection<int, SuscripcionBaja>,
     *   resumen_mes: array{total:int, mensuales:float, trimestrales:float, anuales:float, por_motivo:array<string,int>},
     *   historico_12m: array<int, array{mes:Carbon, etiqueta:string, total:int, mensuales:float, trimestrales:float, anuales:float}>,
     *   proyeccion: array{
     *     meses_restantes:int,
     *     mrr_mensual_ytd:float,
     *     impacto_mensual_restante:float,
     *     trimestral_ytd:float,
     *     impacto_trimestral_restante:float,
     *     anual_ytd:float,
     *     total_orientativo:float,
     *     filas_meses: array<int, array{etiqueta:string, mensuales:float, trimestrales:float, anuales:float, nota:string}>
     *   }
     * }
     */
    public function construir(Carbon $mesReferencia): array
    {
        Carbon::setLocale('es');
        $inicioMes = $mesReferencia->copy()->startOfMonth()->startOfDay();
        $finMes = $mesReferencia->copy()->endOfMonth()->endOfDay();

        $detalle = SuscripcionBaja::query()
            ->whereBetween('fecha_baja', [$inicioMes, $finMes])
            ->orderBy('fecha_baja')
            ->get();

        $resumenMes = $this->resumirColeccion($detalle);

        $historico = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $mesReferencia->copy()->subMonths($i)->startOfMonth();
            $ini = $m->copy()->startOfMonth()->startOfDay();
            $fin = $m->copy()->endOfMonth()->endOfDay();
            $cols = SuscripcionBaja::query()
                ->whereBetween('fecha_baja', [$ini, $fin])
                ->get();
            $r = $this->resumirColeccion($cols);
            $historico[] = [
                'mes' => $m->copy(),
                'etiqueta' => mb_convert_case($m->translatedFormat('F Y'), MB_CASE_TITLE, 'UTF-8'),
                'total' => $r['total'],
                'mensuales' => $r['mensuales'],
                'trimestrales' => $r['trimestrales'],
                'anuales' => $r['anuales'],
            ];
        }

        $proyeccion = $this->construirProyeccion($mesReferencia);

        return [
            'mes' => $inicioMes,
            'detalle' => $detalle,
            'resumen_mes' => $resumenMes,
            'historico_12m' => $historico,
            'proyeccion' => $proyeccion,
        ];
    }

    /**
     * @param  Collection<int, SuscripcionBaja>  $bajas
     * @return array{total:int, mensuales:float, trimestrales:float, anuales:float, por_motivo:array<string,int>}
     */
    public function resumirColeccion(Collection $bajas): array
    {
        $porMotivo = [
            SuscripcionBaja::MOTIVO_CANCELACION_VOLUNTARIA => 0,
            SuscripcionBaja::MOTIVO_FALTA_PAGO => 0,
            SuscripcionBaja::MOTIVO_INACTIVIDAD => 0,
        ];
        foreach ($bajas as $b) {
            if (isset($porMotivo[$b->motivo])) {
                $porMotivo[$b->motivo]++;
            }
        }

        return [
            'total' => $bajas->count(),
            'mensuales' => round((float) $bajas->filter(function ($b) {
                return $this->esTipo($b, 'mensual');
            })->sum('monto'), 2),
            'trimestrales' => round((float) $bajas->filter(function ($b) {
                return $this->esTipo($b, 'trimestral');
            })->sum('monto'), 2),
            'anuales' => round((float) $bajas->filter(function ($b) {
                return $this->esTipo($b, 'anual');
            })->sum('monto'), 2),
            'por_motivo' => $porMotivo,
        ];
    }

    /**
     * Proyección del resto del año calendario a partir de bajas YTD hasta fin del mes de referencia.
     *
     * @return array{
     *   meses_restantes:int,
     *   mrr_mensual_ytd:float,
     *   impacto_mensual_restante:float,
     *   trimestral_ytd:float,
     *   impacto_trimestral_restante:float,
     *   anual_ytd:float,
     *   total_orientativo:float,
     *   filas_meses: array<int, array{etiqueta:string, mensuales:float, trimestrales:float, anuales:float, nota:string}>
     * }
     */
    private function construirProyeccion(Carbon $mesReferencia): array
    {
        $anio = (int) $mesReferencia->year;
        $inicioAnio = Carbon::create($anio, 1, 1)->startOfDay();
        $finMesRef = $mesReferencia->copy()->endOfMonth()->endOfDay();

        $ytd = SuscripcionBaja::query()
            ->whereBetween('fecha_baja', [$inicioAnio, $finMesRef])
            ->get();

        $mrrMensualYtd = round((float) $ytd->filter(function ($b) {
            return $this->esTipo($b, 'mensual');
        })->sum('monto'), 2);
        $trimestralYtd = round((float) $ytd->filter(function ($b) {
            return $this->esTipo($b, 'trimestral');
        })->sum('monto'), 2);
        $anualYtd = round((float) $ytd->filter(function ($b) {
            return $this->esTipo($b, 'anual');
        })->sum('monto'), 2);

        $mesActualNum = (int) $mesReferencia->month;
        $mesesRestantes = max(0, 12 - $mesActualNum);

        // Ciclos trimestrales restantes aproximados en el año (0–3) según mes de referencia.
        // ponytail: aproximación por mes calendario (no por fecha_proximo_pago de cada cliente).
        $ciclosTrimestralesRestantes = (int) floor((12 - $mesActualNum) / 3);
        $impactoTrimestral = round($trimestralYtd * $ciclosTrimestralesRestantes, 2);
        $impactoMensual = round($mrrMensualYtd * $mesesRestantes, 2);
        $totalOrientativo = round($impactoMensual + $impactoTrimestral + $anualYtd, 2);

        $filas = [];
        for ($m = $mesActualNum + 1; $m <= 12; $m++) {
            $mes = Carbon::create($anio, $m, 1)->locale('es');
            $filas[] = [
                'etiqueta' => mb_convert_case($mes->translatedFormat('F Y'), MB_CASE_TITLE, 'UTF-8'),
                'mensuales' => $mrrMensualYtd,
                'trimestrales' => 0.0, // se reporta agregado en totales; por mes solo MRR mensual recurrente
                'anuales' => 0.0,
                'nota' => 'Proyección (MRR mensual perdido YTD)',
            ];
        }

        return [
            'meses_restantes' => $mesesRestantes,
            'mrr_mensual_ytd' => $mrrMensualYtd,
            'impacto_mensual_restante' => $impactoMensual,
            'trimestral_ytd' => $trimestralYtd,
            'impacto_trimestral_restante' => $impactoTrimestral,
            'anual_ytd' => $anualYtd,
            'total_orientativo' => $totalOrientativo,
            'filas_meses' => $filas,
        ];
    }

    private function esTipo(SuscripcionBaja $baja, string $clave): bool
    {
        $t = mb_strtolower(trim((string) $baja->tipo_plan));

        return strpos($t, $clave) !== false;
    }
}
