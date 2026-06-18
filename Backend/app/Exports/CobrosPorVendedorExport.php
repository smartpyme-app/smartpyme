<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'Sucursal'
        ];
    }

    /**
     * Un solo valor escalar (evita arrays duplicados en query string GET).
     *
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

    public function collection()
    {
        $request = $this->request;

        $query = Venta::with([
            'vendedor',
            'cliente',
            'detalles.vendedor',
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

        $ventas = $query
            ->where('cotizacion', 0)
            ->orderBy('id_vendedor')
            ->orderBy('fecha', 'desc')
            ->get();

        return $ventas->flatMap(function (Venta $venta) use ($idVendedor) {
            $grupos = VentaMontosPorVendedorService::montosPorVendedor($venta);

            if ($idVendedor !== null && $idVendedor !== '' && (int) $idVendedor > 0) {
                $idV = (int) $idVendedor;
                $grupos = array_values(array_filter(
                    $grupos,
                    static fn (array $grupo) => (int) $grupo['vendedor_id'] === $idV
                ));
            }

            return collect($grupos)->map(function (array $grupo) use ($venta) {
                return (object) [
                    'venta' => $venta,
                    'grupo' => $grupo,
                ];
            });
        });
    }

    public function map($row): array
    {
        /** @var Venta $venta */
        $venta = $row->venta;
        /** @var array $grupo */
        $grupo = $row->grupo;

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

        $diasVencimiento = $fechaActual->diffInDays($fechaVencimiento, false);
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
        $share = (float) ($grupo['share'] ?? 1);

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
            $grupo['vendedor_nombre'],
            $nombreCliente,
            $venta->fecha,
            $venta->correlativo,
            $nombreDocumento,
            round($grupo['total'], 2),
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
