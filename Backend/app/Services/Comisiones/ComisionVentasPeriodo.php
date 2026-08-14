<?php

namespace App\Services\Comisiones;

use App\Services\Bonos\BonoMetaCalculator;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Illuminate\Support\Facades\DB;

class ComisionVentasPeriodo
{
    public function __construct(private ?BonoMetaCalculator $meta = null)
    {
        $this->meta ??= new BonoMetaCalculator();
    }

    public function total(int $idEmpresa, int $idVendedor, string $inicio, string $fin): float
    {
        return $this->meta->ventasVendedorPeriodo($idEmpresa, $idVendedor, $inicio, $fin);
    }

    /** @return list<int> */
    public function idsConVentas(int $idEmpresa, string $inicio, string $fin): array
    {
        $expr = VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v');

        return DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->where('v.id_empresa', $idEmpresa)
            ->where('v.estado', 'Pagada')
            ->whereBetween('v.fecha', [$inicio, $fin])
            ->selectRaw("DISTINCT {$expr} as id_vendedor")
            ->having('id_vendedor', '>', 0)
            ->pluck('id_vendedor')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
