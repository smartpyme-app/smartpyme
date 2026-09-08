<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\PrestamosEmpresa\PrestamoCuota;
use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ResumenPrestamos
{
    public static function de(Builder $base): array
    {
        $activos = (clone $base)->where('estado', 'activo');

        $deuda = (float) (clone $activos)->sum('saldo');
        $institucion = (float) (clone $activos)->where('tipo_acreedor', 'institucion')->sum('saldo');
        $persona = (float) (clone $activos)->where('tipo_acreedor', 'persona')->sum('saldo');

        $ids = (clone $activos)->pluck('id');
        $hoy = Carbon::today();

        return [
            'deuda' => round($deuda, 2),
            'institucion' => round($institucion, 2),
            'persona' => round($persona, 2),
            'proximos_7' => self::proximos($ids, $hoy, 7),
            'proximos_30' => self::proximos($ids, $hoy, 30),
        ];
    }

    public static function marcarAtrasadas(): void
    {
        PrestamoCuota::query()
            ->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', Carbon::today()->toDateString())
            ->update(['estado' => 'atrasada']);
    }

    private static function proximos($ids, Carbon $hoy, int $dias): float
    {
        if ($ids->isEmpty()) {
            return 0.0;
        }

        return (float) PrestamoCuota::query()
            ->whereIn('id_prestamo', $ids)
            ->where('estado', '!=', 'pagada')
            ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $hoy->copy()->addDays($dias)->toDateString()])
            ->sum('total');
    }
}
