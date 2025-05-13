<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;

class LibroAnuladosExport implements FromCollection, WithMapping, WithHeadings, WithEvents
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

                $event->sheet->setCellValue('A1', 'LIBRO DE DOCUMENTOS ANULADOS ');
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
            'Resolucion',
            'Clase',
            'Desde Pre',
            'Hasta Pre',
            'Tipo Documento',
            'Tipo Detalle',
            'Serie',
            'Desde',
            'Hasta',
            'Codigo Generacion',
        ];
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', 'Anulada')
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderByDesc('fecha')
            ->get();

        return $ventas;
        
    }

    public function map($venta): array{

        $documento = $venta->documento;
        $cliente = optional($venta->cliente);

        return [
            // $this->index++,
            $venta->sello_mh ? $venta->dte['identificacion']['numeroControl'] : '',
            $venta->sello_mh ? 4 : 1, // DTE o impreso
            $venta->sello_mh ? '0' : trim($venta->correlativo),
            $venta->sello_mh ? '0' : trim($venta->correlativo),
            $venta->nombre_documento,
            'Documento Anulado',
            $venta->sello_mh ? $venta->dte['sello'] : '',
            $venta->sello_mh ? '0' : trim($venta->correlativo),
            $venta->sello_mh ? '0' : trim($venta->correlativo),
            $venta->sello_mh ? $venta->dte['identificacion']['codigoGeneracion'] : '',
        ];
    }
}
