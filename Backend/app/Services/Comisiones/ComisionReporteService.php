<?php

namespace App\Services\Comisiones;

use App\Models\Bonos\BonoGenerado;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use App\Models\User;
use App\Services\Incentivos\VendedorConsolidadoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ComisionReporteService
{
    public function __construct(
        private ?VendedorConsolidadoService $consolidadoService = null
    ) {
        $this->consolidadoService ??= app(VendedorConsolidadoService::class);
    }
    public static function etiquetaOrigen(string $origen): string
    {
        return match ($origen) {
            ComisionMovimiento::ORIGEN_VENTA => 'Venta',
            ComisionMovimiento::ORIGEN_REDENCION_GIFT_CARD => 'Redención Gift Card (redencion_gift_card)',
            ComisionMovimiento::ORIGEN_AJUSTE_DEVOLUCION => 'Ajuste devolución',
            default => $origen,
        };
    }
    public function listarMovimientos(int $idEmpresa, Request $request): LengthAwarePaginator
    {
        $query = ComisionMovimiento::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['vendedor', 'categoria', 'subcategoria', 'periodo', 'venta'])
            ->orderByDesc('fecha_evento')
            ->orderByDesc('id');

        if ($request->filled('id_periodo')) {
            $query->where('id_periodo', (int) $request->input('id_periodo'));
        }

        if ($request->filled('id_vendedor')) {
            $query->where('id_vendedor', (int) $request->input('id_vendedor'));
        }

        if ($request->filled('id_categoria')) {
            $query->where('id_categoria', (int) $request->input('id_categoria'));
        }

        if ($request->filled('origen')) {
            $query->where('origen', $request->input('origen'));
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha_evento', '>=', $request->input('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha_evento', '<=', $request->input('hasta'));
        }

        $perPage = min(max((int) $request->input('paginate', 25), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, Collection<int, ComisionMovimiento>>
     */
    public function movimientosAgrupadosPorVendedor(int $idEmpresa, string $desde, string $hasta): Collection
    {
        $movimientos = ComisionMovimiento::query()
            ->where('id_empresa', $idEmpresa)
            ->whereDate('fecha_evento', '>=', $desde)
            ->whereDate('fecha_evento', '<=', $hasta)
            ->with(['vendedor', 'categoria', 'venta'])
            ->orderBy('id_vendedor')
            ->orderBy('fecha_evento')
            ->orderBy('id')
            ->get();

        return $movimientos->groupBy('id_vendedor');
    }

    /**
     * @return array{vendedor: User, periodo: ComisionPeriodo, movimientos: Collection<int, ComisionMovimiento>, total: float}
     */
    public function datosComprobante(int $idEmpresa, int $idVendedor, int $periodoId): array
    {
        $periodo = ComisionPeriodo::query()
            ->where('id_empresa', $idEmpresa)
            ->findOrFail($periodoId);

        $vendedor = User::query()
            ->where('id_empresa', $idEmpresa)
            ->findOrFail($idVendedor);

        $movimientos = ComisionMovimiento::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id_vendedor', $idVendedor)
            ->where('id_periodo', $periodoId)
            ->with(['categoria', 'venta'])
            ->orderBy('fecha_evento')
            ->orderBy('id')
            ->get();

        $total = (float) $movimientos->sum('monto_comision');

        $desde = $periodo->fecha_inicio->toDateString();
        $hasta = $periodo->fecha_fin->toDateString();

        $bonos = $this->consolidadoService->bonosComprobante($idEmpresa, $idVendedor, $desde, $hasta);
        $totalBonosPagables = (float) BonoGenerado::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_vendedor', $idVendedor)
            ->where('periodo_inicio', '<=', $hasta)
            ->where('periodo_fin', '>=', $desde)
            ->whereIn('estado', [BonoGenerado::ESTADO_APROBADO, BonoGenerado::ESTADO_PAGADO])
            ->sum('monto');

        $totalAPagar = [
            'comisiones' => round($total, 2),
            'bonos_aprobados_o_pagados' => round($totalBonosPagables, 2),
            'desglose' => true,
        ];

        return compact('vendedor', 'periodo', 'movimientos', 'total', 'bonos', 'totalAPagar');
    }

    public function validarRangoExport(Request $request): array
    {
        $validated = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        return $validated;
    }

    public function validarComprobante(Request $request): int
    {
        $validated = $request->validate([
            'periodo_id' => 'required|integer|min:1',
        ]);

        return (int) $validated['periodo_id'];
    }
}
