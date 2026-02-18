<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class CuentasCobrarExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Documento',
            'Fecha de venta',
            'Fecha de pago',
            'Días de vencimiento',
            'Estado',
            'Total',
            'Monto abonado',
            'Vendedor',
            'Sucursal',
        ];
    }

    public function collection()
    {
        $request = $this->request;
        $orden = $request->orden ?? 'fecha';
        $direccion = $request->direccion ?? 'desc';

        return Venta::where('estado', 'Pendiente')
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('id_vendedor', $request->id_vendedor)
                        ->orWhereHas('detalles', function ($q2) use ($request) {
                            $q2->where('id_vendedor', $request->id_vendedor);
                        });
                });
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->buscador, function ($query) use ($request) {
                $buscador = '%' . $request->buscador . '%';
                return $query->where(function ($q) use ($buscador) {
                    $q->whereHas('cliente', function ($qCliente) use ($buscador) {
                        $qCliente->where('nombre', 'like', $buscador)
                            ->orWhere('nombre_empresa', 'like', $buscador)
                            ->orWhere('ncr', 'like', $buscador)
                            ->orWhere('nit', 'like', $buscador);
                    })
                        ->orWhere('correlativo', 'like', $buscador)
                        ->orWhere('estado', 'like', $buscador)
                        ->orWhere('observaciones', 'like', $buscador);
                });
            })
            ->where('cotizacion', 0)
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function map($row): array
    {
        $fechaActual = Carbon::now();
        $fechaActual->setTime(0, 0, 0);

        $fechaVence = $row->fecha_pago
            ? Carbon::parse($row->fecha_pago)->setTime(0, 0, 0)
            : Carbon::parse($row->fecha)->addDays(30)->setTime(0, 0, 0);

        $dias = (int) $fechaActual->diffInDays($fechaVence, false);
        $estado = $dias >= 0 ? 'Vigente' : 'Vencido';

        $documento = ($row->nombre_documento ?? $row->tipo_documento ?? '') . ' #' . ($row->correlativo ?? '');
        $abonado = round((float) ($row->abonos_sum_total ?? 0), 2);

        return [
            $row->nombre_cliente ?? 'Consumidor Final',
            trim($documento),
            $row->fecha ? Carbon::parse($row->fecha)->format('d/m/Y') : '',
            $row->fecha_pago ? Carbon::parse($row->fecha_pago)->format('d/m/Y') : '',
            $dias,
            $estado,
            round((float) $row->total, 2),
            $abonado,
            $row->nombre_vendedor ?? '-',
            $row->nombre_sucursal ?? '-',
        ];
    }
}
