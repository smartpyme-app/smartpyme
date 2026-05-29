<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Ventas\Venta;
use App\Models\Compras\Compra;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Comprobante de retención - Formato Honduras (SAR).
 * Incluye retenciones en ventas (iva_retenido) y percepciones en compras (percepcion).
 */
class LibroRetencionesExport implements FromCollection, WithMapping, WithHeadings, WithEvents
{
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $event->sheet->insertNewRowBefore(1, 4);
                $event->sheet->setCellValue('A1', 'COMPROBANTE DE RETENCIÓN');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')) . ' - Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
        ];
    }

    public function headings(): array
    {
        return [
            'Fecha de Comprobante de Retención',
            'Número de Comprobante de Retención',
            'Fecha de Factura',
            'Factura relacionada con Comprobante',
            'Nombre del Agente Retenedor',
            'Registro Tributario Nacional',
            'Importe Base de Retención',
            'Impuesto Retenido',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $ventas = Venta::with(['cliente'])
            ->where('estado', '!=', 'Anulada')
            ->where('iva_retenido', '>', 0)
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('correlativo')
            ->get()
            ->map(fn($v) => (object) ['registro' => $v, 'origen' => 'venta']);

        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->where('percepcion', '>', 0)
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->get()
            ->map(fn($c) => (object) ['registro' => $c, 'origen' => 'compra']);

        return $ventas->merge($compras)->sortBy(fn($x) => $x->registro->fecha)->values();
    }

    public function map($item): array
    {
        $r = $item->registro;
        $esVenta = $item->origen === 'venta';

        if ($esVenta) {
            $fecha = $r->fecha;
            $numComprobante = trim((string) ($r->numero_control ?? $r->correlativo ?? ''));
            $numFactura = trim((string) $r->correlativo);
            $agenteRetenedor = $r->nombre_cliente ?? '';
            $rtn = optional($r->cliente)->nit ?? optional($r->cliente)->ncr ?? '';
            $baseRetencion = (float) $r->sub_total;
            $impuestoRetenido = (float) $r->iva_retenido;
        } else {
            $fecha = $r->fecha;
            $numComprobante = $r->referencia ?? '';
            $numFactura = $r->referencia ?? '';
            $agenteRetenedor = $r->nombre_proveedor ?? '';
            $rtn = optional($r->proveedor)->nit ?? optional($r->proveedor)->ncr ?? '';
            $baseRetencion = (float) $r->sub_total;
            $impuestoRetenido = (float) $r->percepcion;
        }

        return [
            Carbon::parse($fecha)->format('d/m/Y'),
            $numComprobante,
            Carbon::parse($fecha)->format('d/m/Y'),
            $numFactura,
            $agenteRetenedor,
            $rtn,
            round($baseRetencion, 2),
            round($impuestoRetenido, 2),
        ];
    }
}
