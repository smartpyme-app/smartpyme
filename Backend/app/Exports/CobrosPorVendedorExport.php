<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Ventas\Venta;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CobrosPorVendedorExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Vendedor',
            'Cliente',
            'Fecha Factura',
            'Correlativo',
            'Documento',
            'Total Factura',
            'Saldo Pendiente',
            'Estado',
            'Fecha Vencimiento',
            'Días de Vencimiento',
            'Fecha Último Abono',
            'Días de Crédito hasta Pago',
            'Total Abonado',
            'Sucursal',
        ];
    }

    /**
     * @param  mixed  $value
     * @return mixed|null
     */
    private function primerEscalar($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return count($value) ? reset($value) : null;
        }

        return $value;
    }

    private function buildVentaQuery(Request $request)
    {
        $query = Venta::with([
            'vendedor',
            'cliente',
            'abonos' => function ($q) {
                $q->where('estado', 'Confirmado')->orderBy('fecha', 'desc');
            },
            'documento',
            'sucursal',
            'devoluciones' => function ($q) {
                $q->where('enable', 1);
            },
        ]);

        if (! Auth::check() && $request->filled('id_empresa') && (int) $request->id_empresa > 0) {
            $query->where('ventas.id_empresa', (int) $request->id_empresa);
        }

        if ($request->filled('inicio')) {
            $query->where('fecha', '>=', $request->inicio);
        }
        if ($request->filled('fin')) {
            $query->where('fecha', '<=', $request->fin);
        }

        $idSucursal = $this->primerEscalar($request->input('id_sucursal'));
        if ($idSucursal !== null && $idSucursal !== '' && (int) $idSucursal > 0) {
            $query->where('id_sucursal', (int) $idSucursal);
        }

        $sucursales = $request->input('sucursales');
        if (! empty($sucursales) && is_array($sucursales)) {
            $query->whereIn('ventas.id_sucursal', array_map('intval', $sucursales));
        }

        $idVendedor = $this->primerEscalar($request->input('id_vendedor'));
        if ($idVendedor !== null && $idVendedor !== '' && (int) $idVendedor > 0) {
            $idV = (int) $idVendedor;
            $query->where(function ($q) use ($idV) {
                $q->where('id_vendedor', $idV)
                    ->orWhereHas('detalles', function ($sub) use ($idV) {
                        $sub->where('id_vendedor', $idV);
                    });
            });
        }

        return $query
            ->where('cotizacion', 0)
            ->orderBy('id_vendedor')
            ->orderBy('fecha', 'desc');
    }

    /**
     * Totales por venta y vendedor efectivo desde detalles_venta.
     *
     * @param  \Illuminate\Support\Collection<int, int|string>  $ventaIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function totalesPorVentaVendedor($ventaIds, ?int $idVendedorFiltro = null)
    {
        if ($ventaIds->isEmpty()) {
            return collect();
        }

        $query = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->whereIn('dv.id_venta', $ventaIds)
            ->select(
                'dv.id_venta',
                DB::raw(VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v') . ' as id_vendedor_efectivo'),
                DB::raw('SUM(COALESCE(dv.total, 0) + COALESCE(dv.iva, 0)) as total_vendedor')
            )
            ->groupByRaw('dv.id_venta, ' . VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v'));

        if ($idVendedorFiltro !== null && $idVendedorFiltro > 0) {
            $query->havingRaw(
                VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v') . ' = ?',
                [$idVendedorFiltro]
            );
        }

        return $query->get();
    }

    public function collection()
    {
        $request = $this->request;
        $idVendedor = $this->primerEscalar($request->input('id_vendedor'));
        $idVendedorFiltro = ($idVendedor !== null && $idVendedor !== '' && (int) $idVendedor > 0)
            ? (int) $idVendedor
            : null;

        $ventas = $this->buildVentaQuery($request)->get()->keyBy('id');
        if ($ventas->isEmpty()) {
            return collect();
        }

        $totalesDetalle = $this->totalesPorVentaVendedor($ventas->keys(), $idVendedorFiltro);

        $totalesPorVenta = $totalesDetalle
            ->groupBy('id_venta')
            ->map(fn ($grupos) => (float) $grupos->sum('total_vendedor'));

        $vendedorIds = $totalesDetalle->pluck('id_vendedor_efectivo')
            ->filter()
            ->unique()
            ->values();

        $nombresVendedores = User::query()
            ->whereIn('id', $vendedorIds)
            ->pluck('name', 'id');

        $filas = collect();

        foreach ($totalesDetalle as $totalDetalle) {
            $venta = $ventas->get($totalDetalle->id_venta);
            if (! $venta) {
                continue;
            }

            $totalVentaDetalle = (float) ($totalesPorVenta->get($venta->id) ?? 0);
            $totalVendedor = (float) $totalDetalle->total_vendedor;
            $share = $totalVentaDetalle > 0 ? $totalVendedor / $totalVentaDetalle : 1.0;
            $idVendedorEfectivo = (int) $totalDetalle->id_vendedor_efectivo;

            $filas->push([
                'venta' => $venta,
                'vendedor_nombre' => $nombresVendedores->get($idVendedorEfectivo)
                    ?? $venta->vendedor?->name
                    ?? 'Sin vendedor',
                'total_vendedor' => $totalVendedor,
                'share' => $share,
            ]);
        }

        $ventasConDetalle = $totalesDetalle->pluck('id_venta')->unique();

        foreach ($ventas as $venta) {
            if ($ventasConDetalle->contains($venta->id)) {
                continue;
            }

            $grupos = VentaMontosPorVendedorService::montosPorVendedor($venta);
            foreach ($grupos as $grupo) {
                if ($idVendedorFiltro !== null && (int) $grupo['vendedor_id'] !== $idVendedorFiltro) {
                    continue;
                }

                $filas->push([
                    'venta' => $venta,
                    'vendedor_nombre' => $grupo['vendedor_nombre'],
                    'total_vendedor' => (float) $grupo['total'],
                    'share' => (float) $grupo['share'],
                ]);
            }
        }

        return $filas
            ->sortBy(fn ($row) => $row['vendedor_nombre'] . '|' . $row['venta']->fecha)
            ->values();
    }

    public function map($row): array
    {
        $venta = $row['venta'];
        $share = (float) ($row['share'] ?? 1);

        $fechaActual = Carbon::now();
        $fechaVenta = Carbon::parse($venta->fecha);

        $fechaVencimiento = null;
        if ($venta->fecha_pago) {
            $fechaVencimiento = Carbon::parse($venta->fecha_pago);
        } elseif ($venta->fecha_expiracion) {
            $fechaVencimiento = Carbon::parse($venta->fecha_expiracion);
        } else {
            $fechaVencimiento = $fechaVenta->copy()->addDays(30);
        }

        if ($fechaActual->greaterThan($fechaVencimiento)) {
            $diasVencimiento = $fechaVencimiento->diffInDays($fechaActual);
        } else {
            $diasVencimiento = -$fechaActual->diffInDays($fechaVencimiento);
        }

        $ultimoAbono = $venta->abonos->first();
        $fechaUltimoAbono = $ultimoAbono ? Carbon::parse($ultimoAbono->fecha) : null;

        $diasCreditoHastaPago = null;
        if ($ultimoAbono) {
            $diasCreditoHastaPago = $fechaVenta->diffInDays($fechaUltimoAbono);
        }

        $totalAbonado = $venta->abonos->sum('total');

        $cliente = $venta->cliente;
        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = $cliente->tipo == 'Empresa'
                ? $cliente->nombre_empresa
                : $cliente->nombre . ' ' . $cliente->apellido;
        }

        $nombreDocumento = $venta->documento ? $venta->documento->nombre : 'N/A';
        $nombreSucursal = $venta->sucursal ? $venta->sucursal->nombre : 'N/A';

        return [
            $row['vendedor_nombre'],
            $nombreCliente,
            $venta->fecha,
            $venta->correlativo,
            $nombreDocumento,
            round((float) $row['total_vendedor'], 2),
            round((float) $venta->saldo * $share, 2),
            $venta->estado,
            $fechaVencimiento ? $fechaVencimiento->format('Y-m-d') : 'N/A',
            $diasVencimiento,
            $fechaUltimoAbono ? $fechaUltimoAbono->format('Y-m-d') : 'Sin abonos',
            $diasCreditoHastaPago !== null ? $diasCreditoHastaPago : 'N/A',
            round($totalAbonado * $share, 2),
            $nombreSucursal,
        ];
    }
}
