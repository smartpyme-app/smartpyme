<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\Contabilidad\SaldoMensual;
use App\Services\Contabilidad\CierreEjercicioService;
use Carbon\Carbon;
use InvalidArgumentException;

class PeriodoContableCerrado
{
    public static function assertAbierto(int $empresaId, string $fecha): void
    {
        if (CierreEjercicioService::fechaEnEjercicioCerrado($empresaId, $fecha)) {
            throw new InvalidArgumentException('El período contable está cerrado.');
        }

        $dia = Carbon::parse($fecha);
        $cerrado = SaldoMensual::where('id_empresa', $empresaId)
            ->where('year', $dia->year)
            ->where('month', $dia->month)
            ->where('estado', 'Cerrado')
            ->exists();

        if ($cerrado) {
            throw new InvalidArgumentException('El período contable está cerrado.');
        }
    }
}
