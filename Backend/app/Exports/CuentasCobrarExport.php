<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Http\Request;

class CuentasCobrarExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $request;
    protected $totalSaldo = 0;

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
            'Saldo pendiente',
            'Vendedor',
            'Sucursal',
        ];
    }

    public function collection()
    {
        $request = $this->request;
        $fechaCorte = $request->fecha_corte ?? null;
        $orden = $request->orden ?? 'fecha';
        $direccion = $request->direccion ?? 'desc';

        $query = Venta::query()
            ->where('estado', 'Pendiente')
            ->when($fechaCorte, function ($q) use ($fechaCorte) {
                $q->where('fecha', '<=', $fechaCorte);
            })
            ->when(!$fechaCorte && $request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when(!$fechaCorte && $request->fin, function ($query) use ($request) {
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

        return $query;
    }

    public function map($row): array
    {
        $fechaCorte = $this->request->fecha_corte ?? null;
        $fechaRef = $fechaCorte ? Carbon::parse($fechaCorte)->setTime(0, 0, 0) : Carbon::now()->setTime(0, 0, 0);

        $fechaVence = $row->fecha_pago
            ? Carbon::parse($row->fecha_pago)->setTime(0, 0, 0)
            : Carbon::parse($row->fecha)->addDays(30)->setTime(0, 0, 0);

        $dias = (int) $fechaRef->diffInDays($fechaVence, false);
        $estado = $dias >= 0 ? 'Vigente' : 'Vencido';

        $documento = ($row->nombre_documento ?? $row->tipo_documento ?? '') . ' #' . ($row->correlativo ?? '');
        $abonado = round((float) ($row->abonos_sum_total ?? 0), 2);
        $devoluciones = round((float) ($row->devoluciones_sum_total ?? 0), 2);
        $saldo = round((float) $row->total - $abonado - $devoluciones, 2);
        $this->totalSaldo += $saldo;

        return [
            $row->nombre_cliente ?? 'Consumidor Final',
            trim($documento),
            $row->fecha ? Carbon::parse($row->fecha)->format('d/m/Y') : '',
            $row->fecha_pago ? Carbon::parse($row->fecha_pago)->format('d/m/Y') : '',
            $dias,
            $estado,
            round((float) $row->total, 2),
            $abonado,
            $saldo,
            $row->nombre_vendedor ?? '-',
            $row->nombre_sucursal ?? '-',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $totalRow = $lastRow + 1;
                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->setCellValue('I' . $totalRow, round($this->totalSaldo, 2));
                $sheet->getStyle('A' . $totalRow . ':K' . $totalRow)->getFont()->setBold(true);
            },
        ];
    }
}
