<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Venta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;

class LibroRetencion1Export implements FromCollection, WithMapping, WithHeadings, WithEvents
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

                $event->sheet->setCellValue('A1', 'RETENCIÓN DE IVA 1% EFECTUADA AL DECLARANTE');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A3', 'NRC: ' . Auth::user()->empresa()->pluck('ncr')->first());
                $event->sheet->setCellValue('E3', 'Folio N°:');
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')));
                $event->sheet->setCellValue('E4', 'Año: ' . Carbon::parse($this->request->inicio)->format('Y'));

            },
        ];
    }

    public function headings():array{
        return[
            'NIT AGENTE',
            'FECHA DE EMISIÓN',
            'TIPO DE DOCUMENTO',
            'SERIE DE DOCUMENTO',
            'NÚMERO DE DOCUMENTO',
            'MONTO SUJETO',
            'MONTO DE LA RETENCIÓN 1%',
            'DUI DEL AGENTE',
            'NÚMERO DEL ANEXO ',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->where('iva_retenido', '>', 0)
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->orderByDesc('correlativo')
                        ->get();
        return $ventas;

    }

    public function map($venta): array{

        $documento = $venta->documento;
        $cliente = optional($venta->cliente);

        return [
            $venta->cliente->nit ?? '',
            $venta->fecha,
            $venta->nombre_documento,
            $venta->serie,
            $venta->correlativo,
            $venta->sub_total,
            $venta->iva_retenido,
            $venta->cliente->dui ?? '',
            7,
        ];
    }
}
