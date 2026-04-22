<?php

namespace App\Services\Suscripcion;

use App\Models\Suscripcion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FlujoCajaMensualAgregador
{
    /** @return array<int, array<int, string|int|float>> */
    public function construirFilasResumen(Collection $suscripciones, Carbon $mesReferencia, string $etiquetaBloque): array
    {
        $mesTexto = $mesReferencia->copy()->locale('es')->translatedFormat('F Y');

        $filas = [];
        $filas[] = ['BLOQUE: '.$etiquetaBloque];
        $filas[] = ['Mes correspondiente', mb_convert_case($mesTexto, MB_CASE_TITLE, 'UTF-8')];
        $clientesUnicos = $suscripciones->pluck('empresa_id')->filter()->unique()->count();
        $filas[] = ['Número total de clientes (empresas)', $clientesUnicos];
        $filas[] = [];

        $filas[] = ['Desglose por plan', 'Cantidad', 'Monto esperado (USD)'];
        foreach ($this->clavesPlanes() as $clave) {
            $sub = $this->filtrarPorPlan($suscripciones, $clave);
            $filas[] = [$clave, $sub->count(), round((float) $sub->sum('monto'), 2)];
        }
        $filas[] = [];

        $filas[] = ['Método de pago', 'Cantidad', 'Monto esperado (USD)'];
        foreach (['N1CO', 'Transferencia', 'Otro'] as $met) {
            $sub = $this->filtrarPorMetodo($suscripciones, $met);
            if ($met === 'Otro' && $sub->isEmpty()) {
                continue;
            }
            $filas[] = [$met, $sub->count(), round((float) $sub->sum('monto'), 2)];
        }
        $filas[] = [];

        $total = round((float) $suscripciones->sum('monto'), 2);
        $filas[] = ['MONTO TOTAL ESPERADO (sumatoria suscripciones)', '', $total];

        return $filas;
    }

    /**
     * @return array<string>
     */
    private function clavesPlanes(): array
    {
        return [
            config('constants.PLAN_ESTANDAR'),
            config('constants.PLAN_AVANZADO'),
            config('constants.PLAN_PRO'),
            config('constants.PLAN_EMPRENDEDOR'),
            'Otros',
        ];
    }

    private function filtrarPorPlan(Collection $suscripciones, string $clave): Collection
    {
        return $suscripciones->filter(function (Suscripcion $s) use ($clave) {
            $bucket = $this->bucketPlan($s);

            return $bucket === $clave;
        });
    }

    private function bucketPlan(Suscripcion $s): string
    {
        $plan = $s->plan;
        if (!$plan || trim((string) $plan->nombre) === '') {
            return 'Otros';
        }

        $nombre = trim($plan->nombre);
        $orden = [
            config('constants.PLAN_ESTANDAR'),
            config('constants.PLAN_AVANZADO'),
            config('constants.PLAN_PRO'),
            config('constants.PLAN_EMPRENDEDOR'),
        ];
        foreach ($orden as $literal) {
            if (strcasecmp($nombre, $literal) === 0) {
                return $literal;
            }
        }

        $n = mb_strtolower($nombre);
        if (str_contains($n, 'estándar') || str_contains($n, 'estandar')) {
            return config('constants.PLAN_ESTANDAR');
        }
        if (str_contains($n, 'avanzado')) {
            return config('constants.PLAN_AVANZADO');
        }
        if (preg_match('/(^|[\s_-])pro([\s_-]|$)/iu', $nombre)) {
            return config('constants.PLAN_PRO');
        }
        if (str_contains($n, 'emprendedor')) {
            return config('constants.PLAN_EMPRENDEDOR');
        }

        return 'Otros';
    }

    private function filtrarPorMetodo(Collection $suscripciones, string $met): Collection
    {
        return $suscripciones->filter(function (Suscripcion $s) use ($met) {
            $b = $this->bucketMetodo($s);

            return $b === $met;
        });
    }

    private function bucketMetodo(Suscripcion $s): string
    {
        $m = mb_strtolower(trim((string) $s->metodo_pago));
        $n1co = mb_strtolower(config('constants.METODO_PAGO_N1CO'));

        if ($m === '' || $m === '0') {
            return 'Otro';
        }
        // Valores históricos según modelo: 1 = n1co, 2 = transferencia
        if ($m === '1' || str_contains($m, 'n1') || $m === $n1co) {
            return 'N1CO';
        }
        if ($m === '2' || str_contains($m, 'transfer') || str_contains($m, mb_strtolower(config('constants.METODO_PAGO_TRANSFERENCIA')))) {
            return 'Transferencia';
        }

        return 'Otro';
    }
}
