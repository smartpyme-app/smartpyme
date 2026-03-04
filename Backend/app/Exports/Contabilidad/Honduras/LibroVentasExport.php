<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Libro de ventas - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroVentasExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public $request;
    private $index = 1;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);
                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS - HONDURAS');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')) . ' - Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
        ];
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'No. de Orden de Compra Exenta',
            'Documento / DUA Exportación',
            'Documento de transferencias de bienes FYDUCA',
            'Notas de Crédito emitidas en el periodo',
            'Fecha de emisión de Factura a la que se aplica la nota de credito',
            'Número de Factura relacionada con la Nota de Crédito',
            'Cliente',
            'RTN del Cliente',
            'Descripción',
            'No. de Factura que respalda la venta',
            'Importe Venta Exenta',
            'Importe Venta Gravada',
            'Importe Venta Exonerada',
            'Impuesto Sobre Ventas',
            'Importe Exportación',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('correlativo')
            ->get();

        $devoluciones = DevolucionVenta::with(['cliente', 'venta'])
            ->where('enable', true)
            ->whereHas('venta', fn($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->get();

        $filasVentas = $ventas->map(function ($v) {
            $docNombre = trim(optional($v->documento)->nombre ?? $v->nombre_documento ?? '');
            $esExportacion = stripos($docNombre, 'exportación') !== false;
            return [
                'fecha' => $v->fecha,
                'num_orden_exenta' => $v->num_orden_exento ?? '',
                'documento_dua_exportacion' => $esExportacion ? trim((string) $v->correlativo) : '',
                'documento_fyduca' => '', // FYDUCA si aplica
                'nota_credito_numero' => '',
                'fecha_factura_relacionada' => '',
                'numero_factura_relacionada' => '',
                'cliente' => $v->nombre_cliente,
                'rtn' => optional($v->cliente)->nit ?? optional($v->cliente)->ncr ?? '',
                'descripcion' => $docNombre,
                'no_factura' => trim((string) $v->correlativo),
                'importe_exenta' => $v->iva == 0 && !$esExportacion ? (float) $v->sub_total : 0,
                'importe_gravada' => $v->iva > 0 ? (float) $v->sub_total : 0,
                'importe_exonerada' => (float) ($v->no_sujeta ?? 0),
                'impuesto_ventas' => (float) $v->iva,
                'importe_exportacion' => $esExportacion ? (float) $v->total : 0,
            ];
        });

        $filasDevoluciones = $devoluciones->map(function ($d) {
            $ventaOriginal = $d->venta;
            return [
                'fecha' => $d->fecha,
                'num_orden_exenta' => '',
                'documento_dua_exportacion' => '',
                'documento_fyduca' => '',
                'nota_credito_numero' => trim((string) $d->correlativo),
                'fecha_factura_relacionada' => $ventaOriginal ? $ventaOriginal->fecha : '',
                'numero_factura_relacionada' => $ventaOriginal ? trim((string) $ventaOriginal->correlativo) : '',
                'cliente' => $d->nombre_cliente,
                'rtn' => optional($d->cliente)->nit ?? optional($d->cliente)->ncr ?? '',
                'descripcion' => 'Nota de crédito',
                'no_factura' => trim((string) $d->correlativo),
                'importe_exenta' => $d->exenta > 0 ? -1 * (float) $d->exenta : 0,
                'importe_gravada' => $d->sub_total > 0 ? -1 * (float) $d->sub_total : 0,
                'importe_exonerada' => 0,
                'impuesto_ventas' => $d->iva > 0 ? -1 * (float) $d->iva : 0,
                'importe_exportacion' => 0,
            ];
        });

        return $filasVentas->merge($filasDevoluciones)->sortBy('fecha')->values();
    }

    public function map($row): array
    {
        return [
            Carbon::parse($row['fecha'])->format('d/m/Y'),
            $row['num_orden_exenta'],
            $row['documento_dua_exportacion'],
            $row['documento_fyduca'],
            $row['nota_credito_numero'],
            $row['fecha_factura_relacionada'] ? Carbon::parse($row['fecha_factura_relacionada'])->format('d/m/Y') : '',
            $row['numero_factura_relacionada'],
            $row['cliente'],
            $row['rtn'],
            $row['descripcion'],
            $row['no_factura'],
            round($row['importe_exenta'], 2),
            round($row['importe_gravada'], 2),
            round($row['importe_exonerada'], 2),
            round($row['impuesto_ventas'], 2),
            round($row['importe_exportacion'], 2),
        ];
    }
}
