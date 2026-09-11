<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VentasPorClienteExport implements FromCollection, WithHeadings, WithMapping
{
    public $request;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Fecha venta',
            'Documento',
            'Correlativo',
            'Total',
            'Estado',
            'Fecha vencimiento',
            'Fecha de pago',
            'Saldo pendiente',
            'Vendedor',
            'Sucursal',
        ];
    }

    /**
     * Fecha real de cobro: último abono confirmado, o fecha de venta si quedó pagada al contado.
     */
    public static function fechaCobroParaReporte(string $estado, ?string $fechaVenta, ?string $ultimoAbonoFecha): ?string
    {
        if ($estado !== 'Pagada') {
            return null;
        }
        if ($ultimoAbonoFecha) {
            return Carbon::parse($ultimoAbonoFecha)->format('Y-m-d');
        }
        if ($fechaVenta) {
            return Carbon::parse($fechaVenta)->format('Y-m-d');
        }

        return null;
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
            'cliente',
            'vendedor',
            'documento',
            'sucursal',
            'abonos' => function ($q) {
                $q->where('estado', 'Confirmado')->orderBy('fecha', 'desc')->orderBy('id', 'desc');
            },
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

        $idCliente = $this->primerEscalar($request->input('id_cliente'));
        if ($idCliente !== null && $idCliente !== '' && (int) $idCliente > 0) {
            $query->where('id_cliente', (int) $idCliente);
        }

        $idSucursal = $this->primerEscalar($request->input('id_sucursal'));
        if ($idSucursal !== null && $idSucursal !== '' && (int) $idSucursal > 0) {
            $query->where('id_sucursal', (int) $idSucursal);
        }

        $estado = $this->primerEscalar($request->input('estado'));
        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        return $query
            ->where('cotizacion', 0)
            ->orderBy('id_cliente')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');
    }

    public function collection()
    {
        return $this->buildVentaQuery($this->request)->get();
    }

    public function map($venta): array
    {
        $cliente = $venta->cliente;
        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = $cliente->tipo == 'Empresa'
                ? $cliente->nombre_empresa
                : trim($cliente->nombre . ' ' . $cliente->apellido);
        }

        $fechaVenta = $venta->fecha ? Carbon::parse($venta->fecha) : null;
        $fechaVencimiento = $venta->fecha_pago
            ? Carbon::parse($venta->fecha_pago)
            : ($fechaVenta ? $fechaVenta->copy()->addDays(30) : null);

        $ultimoAbono = $venta->abonos->first();
        $fechaCobro = self::fechaCobroParaReporte(
            (string) $venta->estado,
            $venta->fecha,
            $ultimoAbono?->fecha
        );

        $totalAbonado = round((float) $venta->abonos->sum('total'), 2);
        $totalDevoluciones = round((float) $venta->devoluciones->sum('total'), 2);
        $saldo = round((float) $venta->total - $totalAbonado - $totalDevoluciones, 2);
        if ($venta->estado === 'Pagada') {
            $saldo = 0.0;
        }

        $nombreDocumento = $venta->documento ? $venta->documento->nombre : '';
        $nombreVendedor = $venta->vendedor ? $venta->vendedor->name : '-';
        $nombreSucursal = $venta->sucursal ? $venta->sucursal->nombre : '-';

        return [
            $nombreCliente,
            $fechaVenta ? $fechaVenta->format('Y-m-d') : '',
            $nombreDocumento,
            $venta->correlativo,
            round((float) $venta->total, 2),
            $venta->estado,
            $fechaVencimiento ? $fechaVencimiento->format('Y-m-d') : '',
            $fechaCobro ?? '',
            $saldo,
            $nombreVendedor,
            $nombreSucursal,
        ];
    }
}
