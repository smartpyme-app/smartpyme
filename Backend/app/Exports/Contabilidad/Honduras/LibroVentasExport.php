<?php

namespace App\Exports\Contabilidad\Honduras;

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
 * Libro de ventas Honduras (SAR).
 * Mismas consultas que El Salvador (LibroContribuyentesExport / contribuyentes en LibrosIVAController);
 * solo cambia el mapeo de columnas al formato SAR.
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
     * Ventas del periodo — misma consulta que contribuyentes SV (sin filtro por tipo de documento).
     */
    private function ventasDelPeriodo(): Collection
    {
        $request = $this->request;

        return Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderBy('fecha')
            ->orderBy('correlativo')
            ->get();
    }

    /**
     * Devoluciones del periodo — misma consulta que contribuyentes SV.
     */
    private function devolucionesDelPeriodo(): Collection
    {
        $request = $this->request;

        return DevolucionVenta::with(['cliente', 'venta'])
            ->where('enable', true)
            ->whereHas('venta', function ($query) {
                $query->where('estado', '!=', 'Anulada');
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildDetailRowsArray(): array
    {
        $ventas = $this->ventasDelPeriodo()->map(fn (Venta $venta) => $this->mapVentaHonduras($venta));

        $devoluciones = $this->devolucionesDelPeriodo()->map(fn (DevolucionVenta $devolucion) => $this->mapDevolucionHonduras($devolucion));

        return $ventas
            ->merge($devoluciones)
            ->sortBy(fn (array $row) => [$row['fecha'], $row['no_factura'] ?? ''])
            ->values()
            ->all();
    }

    protected function buildDetailRows(): Collection
    {
        return collect($this->buildDetailRowsArray());
    }

    /**
     * Mapeo SAR (Honduras) — equivalente a fila contribuyente SV con columnas distintas.
     */
    private function mapVentaHonduras(Venta $venta): array
    {
        $cliente = optional($venta->cliente);
        $docNombre = trim(optional($venta->documento)->nombre ?? '');
        $esExportacion = stripos($docNombre, 'exportación') !== false;

        return [
            'fecha' => $venta->fecha,
            'rtn' => $cliente->nit ?? $cliente->ncr ?? '',
            'descripcion' => $docNombre,
            'no_factura' => trim((string) $venta->correlativo),
            'importe_exenta' => $venta->iva == 0 && ! $esExportacion ? (float) $venta->sub_total : 0,
            'importe_gravada' => $venta->iva > 0 ? (float) $venta->sub_total : 0,
            'importe_exonerada' => (float) ($venta->no_sujeta ?? 0),
            'impuesto_ventas' => (float) $venta->iva,
            'importe_exportacion' => $esExportacion ? (float) $venta->total : 0,
        ];
    }

    private function mapDevolucionHonduras(DevolucionVenta $devolucion): array
    {
        $cliente = optional($devolucion->cliente);
        $ventaOriginal = $devolucion->venta;

        return [
            'fecha' => $devolucion->fecha,
            'rtn' => $cliente->nit ?? $cliente->ncr ?? '',
            'descripcion' => 'Nota de crédito',
            'no_factura' => trim((string) $devolucion->correlativo),
            'importe_exenta' => $devolucion->exenta > 0 ? -1 * (float) $devolucion->exenta : 0,
            'importe_gravada' => $devolucion->sub_total > 0 ? -1 * (float) $devolucion->sub_total : 0,
            'importe_exonerada' => 0,
            'impuesto_ventas' => $devolucion->iva > 0 ? -1 * (float) $devolucion->iva : 0,
            'importe_exportacion' => 0,
            'fecha_factura_relacionada' => $ventaOriginal ? $ventaOriginal->fecha : '',
            'numero_factura_relacionada' => $ventaOriginal ? trim((string) $ventaOriginal->correlativo) : '',
        ];
    }

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

    public function rowsForApi(): array
    {
        return $this->buildDetailRowsArray();
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
