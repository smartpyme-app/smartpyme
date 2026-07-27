<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionLiquidacion;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComisionLiquidacionService
{
    /** @return EloquentCollection<int, ComisionPeriodo> */
    public function listarPeriodos(int $idEmpresa, ?string $estado = null): EloquentCollection
    {
        $query = ComisionPeriodo::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['liquidaciones.vendedor'])
            ->orderByDesc('fecha_inicio');

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        return $query->get();
    }

    public function cerrarPeriodo(int $idEmpresa, int $idPeriodo): ComisionPeriodo
    {
        return DB::transaction(function () use ($idEmpresa, $idPeriodo) {
            $periodo = ComisionPeriodo::query()
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->findOrFail($idPeriodo);

            if ($periodo->estado !== ComisionPeriodo::ESTADO_ABIERTO) {
                throw ValidationException::withMessages([
                    'periodo' => ['Solo se pueden cerrar períodos en estado abierto.'],
                ]);
            }

            $totalesPorVendedor = ComisionMovimiento::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_periodo', $periodo->id)
                ->selectRaw('id_vendedor, SUM(monto_comision) as total_comision')
                ->groupBy('id_vendedor')
                ->get();

            foreach ($totalesPorVendedor as $fila) {
                ComisionLiquidacion::query()->updateOrCreate(
                    [
                        'id_empresa' => $idEmpresa,
                        'id_periodo' => $periodo->id,
                        'id_vendedor' => (int) $fila->id_vendedor,
                    ],
                    [
                        'total_comision' => round((float) $fila->total_comision, 4),
                    ]
                );
            }

            $periodo->update(['estado' => ComisionPeriodo::ESTADO_CERRADO]);

            return $periodo->fresh(['liquidaciones.vendedor']);
        });
    }

    public function marcarLiquidacionPagada(int $idEmpresa, int $idLiquidacion): ComisionLiquidacion
    {
        return DB::transaction(function () use ($idEmpresa, $idLiquidacion) {
            $liquidacion = ComisionLiquidacion::query()
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->findOrFail($idLiquidacion);

            if ($liquidacion->pagado_at !== null) {
                throw ValidationException::withMessages([
                    'liquidacion' => ['La liquidación ya está marcada como pagada.'],
                ]);
            }

            $liquidacion->update(['pagado_at' => Carbon::now()]);

            $periodo = ComisionPeriodo::query()
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->findOrFail($liquidacion->id_periodo);

            $pendientes = ComisionLiquidacion::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_periodo', $periodo->id)
                ->whereNull('pagado_at')
                ->exists();

            if (! $pendientes && $periodo->estado === ComisionPeriodo::ESTADO_CERRADO) {
                $periodo->update(['estado' => ComisionPeriodo::ESTADO_PAGADO]);
            }

            return $liquidacion->fresh(['vendedor', 'periodo']);
        });
    }
}
