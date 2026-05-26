<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

/**
 * Libro de ventas - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroVentasExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public $request;
    private $index = 1;

    private const VENTA_COLUMNS = [
        'id',
        'fecha',
        'num_orden_exento',
        'correlativo',
        'nombre_cliente',
        'nombre_documento',
        'id_cliente',
        'id_documento',
        'iva',
        'sub_total',
        'no_sujeta',
        'total',
        'id_sucursal',
    ];

    private const DEVOLUCION_COLUMNS = [
        'id',
        'fecha',
        'correlativo',
        'id_cliente',
        'id_venta',
        'exenta',
        'sub_total',
        'iva',
        'id_sucursal',
        'enable',
    ];

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);
                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')) . ' - Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $sheet->getHighestDataRow();
                if ($last >= 1) {
                    $sheet->getStyle('A' . $last . ':H' . $last)->getFont()->setBold(true);
                }
            },
        ];
    }

    /**
     * Mismas columnas que reportes.contabilidad.honduras.libro-ventas (PDF pantalla libro-iva/general).
     */
    public function headings(): array
    {
        return [
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

    /**
     * Filas detalle; cursor + columnas mínimas (sin dte JSON) para no agotar memoria.
     */
    protected function buildDetailRows(): Collection
    {
        $rows = [];

        foreach ($this->ventasQuery()->cursor() as $venta) {
            $venta->setAppends([]);
            $rows[] = $this->mapVentaRow($venta);
        }

        foreach ($this->devolucionesQuery()->cursor() as $devolucion) {
            $devolucion->setAppends([]);
            $rows[] = $this->mapDevolucionRow($devolucion);
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));

        return collect($rows);
    }

    private function ventasQuery()
    {
        $request = $this->request;

        return Venta::query()
            ->select(self::VENTA_COLUMNS)
            ->with([
                'cliente:id,nit,ncr,tipo,nombre,nombre_empresa,apellido',
                'documento:id,nombre',
            ])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('correlativo');
    }

    private function devolucionesQuery()
    {
        $request = $this->request;

        return DevolucionVenta::query()
            ->select(self::DEVOLUCION_COLUMNS)
            ->with([
                'cliente:id,nit,ncr,tipo,nombre,nombre_empresa,apellido',
                'venta:id,fecha,correlativo,estado',
            ])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha');
    }

    private function mapVentaRow(Venta $v): array
    {
        $docNombre = trim(optional($v->documento)->nombre ?? $v->nombre_documento ?? '');
        $esExportacion = stripos($docNombre, 'exportación') !== false;

        return [
            'fecha' => $v->fecha,
            'num_orden_exenta' => $v->num_orden_exento ?? '',
            'documento_dua_exportacion' => $esExportacion ? trim((string) $v->correlativo) : '',
            'documento_fyduca' => '',
            'nota_credito_numero' => '',
            'fecha_factura_relacionada' => '',
            'numero_factura_relacionada' => '',
            'cliente' => $this->formatNombreCliente($v->cliente),
            'rtn' => optional($v->cliente)->nit ?? optional($v->cliente)->ncr ?? '',
            'descripcion' => $docNombre,
            'no_factura' => trim((string) $v->correlativo),
            'importe_exenta' => $v->iva == 0 && ! $esExportacion ? (float) $v->sub_total : 0,
            'importe_gravada' => $v->iva > 0 ? (float) $v->sub_total : 0,
            'importe_exonerada' => (float) ($v->no_sujeta ?? 0),
            'impuesto_ventas' => (float) $v->iva,
            'importe_exportacion' => $esExportacion ? (float) $v->total : 0,
        ];
    }

    private function mapDevolucionRow(DevolucionVenta $d): array
    {
        $ventaOriginal = $d->venta;

        return [
            'fecha' => $d->fecha,
            'num_orden_exenta' => '',
            'documento_dua_exportacion' => '',
            'documento_fyduca' => '',
            'nota_credito_numero' => trim((string) $d->correlativo),
            'fecha_factura_relacionada' => $ventaOriginal ? $ventaOriginal->fecha : '',
            'numero_factura_relacionada' => $ventaOriginal ? trim((string) $ventaOriginal->correlativo) : '',
            'cliente' => $this->formatNombreCliente($d->cliente),
            'rtn' => optional($d->cliente)->nit ?? optional($d->cliente)->ncr ?? '',
            'descripcion' => 'Nota de crédito',
            'no_factura' => trim((string) $d->correlativo),
            'importe_exenta' => $d->exenta > 0 ? -1 * (float) $d->exenta : 0,
            'importe_gravada' => $d->sub_total > 0 ? -1 * (float) $d->sub_total : 0,
            'importe_exonerada' => 0,
            'impuesto_ventas' => $d->iva > 0 ? -1 * (float) $d->iva : 0,
            'importe_exportacion' => 0,
        ];
    }

    private function formatNombreCliente(?Cliente $cliente): string
    {
        if (! $cliente) {
            return 'Consumidor Final';
        }

        return $cliente->tipo === 'Empresa'
            ? (string) $cliente->nombre_empresa
            : trim((string) $cliente->nombre . ' ' . (string) $cliente->apellido);
    }

    /**
     * Fila resumen para Excel / mismo criterio que PDF.
     */
    public static function filaTotales(Collection $detalle): array
    {
        return [
            'rtn' => '',
            'descripcion' => 'TOTALES',
            'no_factura' => '',
            'importe_exenta' => round((float) $detalle->sum(fn ($r) => $r['importe_exenta']), 2),
            'importe_gravada' => round((float) $detalle->sum(fn ($r) => $r['importe_gravada']), 2),
            'importe_exonerada' => round((float) $detalle->sum(fn ($r) => $r['importe_exonerada']), 2),
            'impuesto_ventas' => round((float) $detalle->sum(fn ($r) => $r['impuesto_ventas']), 2),
            'importe_exportacion' => round((float) $detalle->sum(fn ($r) => $r['importe_exportacion']), 2),
        ];
    }

    public function collection()
    {
        $detalle = $this->buildDetailRows();

        return $detalle->push(self::filaTotales($detalle));
    }

    /**
     * Filas para API / PDF libro-iva/general (mismas claves que espera el frontend).
     */
    public function rowsForApi(): array
    {
        return $this->buildDetailRows()->values()->all();
    }

    public function map($row): array
    {
        return [
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
