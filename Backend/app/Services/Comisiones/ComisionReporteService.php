<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionMovimiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ComisionReporteService
{
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
}
