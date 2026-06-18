<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;

class VentaMontosPorVendedorService
{
    /**
     * Expresión SQL: vendedor del detalle, o el de la venta si el detalle no tiene uno.
     */
    public static function sqlIdVendedorEfectivo(string $aliasDetalle = 'dv', string $aliasVenta = 'v'): string
    {
        return "COALESCE(NULLIF({$aliasDetalle}.id_vendedor, 0), {$aliasVenta}.id_vendedor)";
    }

    /**
     * Agrupa los montos de una venta por vendedor efectivo (detalle o venta).
     *
     * @return array<int, array{
     *     vendedor_id: int,
     *     vendedor_nombre: string,
     *     total_costo: float,
     *     sub_total: float,
     *     descuento: float,
     *     iva: float,
     *     total_sin_iva: float,
     *     total: float,
     *     utilidad: float,
     *     share: float
     * }>
     */
    public static function montosPorVendedor(Venta $venta): array
    {
        $venta->loadMissing(['vendedor']);
        $detalles = $venta->detalles()->with('vendedor:id,name')->get();

        $grupos = [];

        foreach ($detalles as $detalle) {
            $idVendedor = self::idVendedorEfectivo($detalle, $venta);
            $nombreVendedor = self::nombreVendedorEfectivo($detalle, $venta);

            if (!isset($grupos[$idVendedor])) {
                $grupos[$idVendedor] = self::grupoVacio($idVendedor, $nombreVendedor);
            }

            $iva = (float) ($detalle->iva ?? 0);
            $totalLinea = (float) ($detalle->total ?? 0);
            $costoLinea = (float) ($detalle->total_costo ?? ($detalle->costo * $detalle->cantidad));
            $subTotalLinea = (float) ($detalle->sub_total ?? ($detalle->cantidad * $detalle->precio));

            $grupos[$idVendedor]['total_costo'] += $costoLinea;
            $grupos[$idVendedor]['sub_total'] += $subTotalLinea;
            $grupos[$idVendedor]['descuento'] += (float) ($detalle->descuento ?? 0);
            $grupos[$idVendedor]['iva'] += $iva;
            $grupos[$idVendedor]['total_sin_iva'] += max(0, $totalLinea);
            $grupos[$idVendedor]['total'] += $totalLinea + $iva;
            $grupos[$idVendedor]['utilidad'] += $totalLinea - $costoLinea;
        }

        if ($grupos === []) {
            $idVendedor = (int) ($venta->id_vendedor ?: 0);
            $grupos[$idVendedor] = self::grupoVacio(
                $idVendedor,
                $venta->vendedor?->name ?? 'Sin vendedor'
            );
            $grupos[$idVendedor]['total_costo'] = (float) ($venta->total_costo ?? 0);
            $grupos[$idVendedor]['sub_total'] = (float) ($venta->sub_total ?? 0);
            $grupos[$idVendedor]['descuento'] = (float) ($venta->descuento ?? 0);
            $grupos[$idVendedor]['iva'] = (float) ($venta->iva ?? 0);
            $grupos[$idVendedor]['total_sin_iva'] = max(0, (float) ($venta->sub_total ?? 0) - (float) ($venta->descuento ?? 0));
            $grupos[$idVendedor]['total'] = (float) ($venta->total ?? 0);
            $grupos[$idVendedor]['utilidad'] = (float) ($venta->total ?? 0)
                - (float) ($venta->total_costo ?? 0)
                - (float) ($venta->iva ?? 0);
        }

        $totalGrupos = array_sum(array_column($grupos, 'total'));

        foreach ($grupos as &$grupo) {
            $grupo['share'] = $totalGrupos > 0 ? $grupo['total'] / $totalGrupos : 1.0;
        }
        unset($grupo);

        return array_values($grupos);
    }

    private static function idVendedorEfectivo($detalle, Venta $venta): int
    {
        if ($detalle->id_vendedor !== null && (int) $detalle->id_vendedor > 0) {
            return (int) $detalle->id_vendedor;
        }

        return (int) ($venta->id_vendedor ?? 0);
    }

    private static function nombreVendedorEfectivo($detalle, Venta $venta): string
    {
        if ($detalle->relationLoaded('vendedor') && $detalle->vendedor) {
            return $detalle->vendedor->name;
        }

        if ($detalle->id_vendedor !== null && (int) $detalle->id_vendedor > 0) {
            $nombre = $detalle->vendedor()->value('name');
            if ($nombre) {
                return $nombre;
            }
        }

        return $venta->vendedor?->name ?? 'Sin vendedor';
    }

    /**
     * @return array{
     *     vendedor_id: int,
     *     vendedor_nombre: string,
     *     total_costo: float,
     *     sub_total: float,
     *     descuento: float,
     *     iva: float,
     *     total_sin_iva: float,
     *     total: float,
     *     utilidad: float,
     *     share: float
     * }
     */
    private static function grupoVacio(int $idVendedor, string $nombreVendedor): array
    {
        return [
            'vendedor_id' => $idVendedor,
            'vendedor_nombre' => $nombreVendedor,
            'total_costo' => 0.0,
            'sub_total' => 0.0,
            'descuento' => 0.0,
            'iva' => 0.0,
            'total_sin_iva' => 0.0,
            'total' => 0.0,
            'utilidad' => 0.0,
            'share' => 1.0,
        ];
    }
}
