<?php

namespace App\Helpers;

use Carbon\Carbon;

class EstadoCuentaAntiguedadHelper
{
    public static function plazoDias(Carbon $fechaDoc, Carbon $fechaVence): int
    {
        return (int) $fechaDoc->copy()->startOfDay()->diffInDays($fechaVence->copy()->startOfDay());
    }

    public static function diasMora(Carbon $fechaVence, Carbon $fechaCorte): int
    {
        $vence = $fechaVence->copy()->startOfDay();
        $corte = $fechaCorte->copy()->startOfDay();

        if (! $corte->greaterThan($vence)) {
            return 0;
        }

        return (int) $vence->diffInDays($corte);
    }

    public static function bucket(int $diasMora): string
    {
        if ($diasMora <= 0) {
            return 'sin_vencer';
        }
        if ($diasMora <= 30) {
            return 'dias_30';
        }
        if ($diasMora <= 60) {
            return 'dias_60';
        }
        if ($diasMora <= 90) {
            return 'dias_90';
        }
        if ($diasMora <= 120) {
            return 'dias_120';
        }
        if ($diasMora < 365) {
            return 'mas_120';
        }

        return 'mas_365';
    }
}
