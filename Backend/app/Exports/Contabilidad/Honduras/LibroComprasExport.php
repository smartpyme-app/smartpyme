<?php

namespace App\Exports\Contabilidad\Honduras;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Libro de compras - Formato Honduras (SAR).
 * Columnas según formato oficial.
 */
class LibroComprasExport implements FromCollection, WithMapping, WithHeadings, WithEvents
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
                $event->sheet->setCellValue('A1', 'LIBRO DE COMPRAS');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')) . ' - Año: ' . Carbon::parse($this->request->inicio)->format('Y'));
            },
        ];
    }

    public function headings(): array
    {
        return [
            'Fecha de Documento',
            'Fecha de Contabilización',
            'Documento / DUA Importación',
            'Documento de adquisiciones FYDUCA',
            'Proveedor',
            'Registro Tributario Nacional del proveedor',
            'Descripción de la compra',
            'No. de Factura de la compra',
            'Importe Compra Exenta',
            'Importe Compra Gravada',
            'Impuesto Sobre Ventas',
            'Importe Importación',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn($c) => (object) ['registro' => $c, 'mult' => 1]);

        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn($g) => (object) ['registro' => $g, 'mult' => 1]);

        $devoluciones = DevolucionCompra::with(['proveedor'])
            ->where('enable', true)
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(fn($d) => (object) ['registro' => $d, 'mult' => -1]);

        return $compras->merge($gastos)->merge($devoluciones)->sortBy(fn($x) => $x->registro->fecha)->values();
    }

    public function map($item): array
    {
        $r = $item->registro;
        $m = $item->mult;
        $proveedor = $r->proveedor ?? $r->proveedor()->first();
        $rtn = $proveedor ? ($proveedor->nit ?? $proveedor->ncr ?? '') : '';

        $tipo = $r->tipo_documento ?? '';
        $esImportacion = stripos($tipo, 'Importación') !== false;
        $esSujetoExcluido = $tipo === 'Sujeto excluido';

        $fechaDoc = $r->fecha;
        $fechaContab = isset($r->created_at) ? $r->created_at : $r->fecha;

        $importeExenta = $esSujetoExcluido ? (float) $r->total * $m : ($r->iva == 0 ? (float) $r->sub_total * $m : 0);
        $importeGravada = !$esSujetoExcluido ? (float) $r->sub_total * $m : 0;
        $impuestoVentas = !$esSujetoExcluido ? (float) $r->iva * $m : 0;
        $importeImportacion = $esImportacion ? (float) $r->total * $m : 0;

        return [
            Carbon::parse($fechaDoc)->format('d/m/Y'),
            Carbon::parse($fechaContab)->format('d/m/Y'),
            $esImportacion ? ($r->referencia ?? '') : '',
            '', // Documento FYDUCA
            $r->nombre_proveedor ?? '',
            $rtn,
            $tipo,
            $r->referencia ?? '',
            round($importeExenta, 2),
            round($importeGravada, 2),
            round($impuestoVentas, 2),
            round($importeImportacion, 2),
        ];
    }
}
