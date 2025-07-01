<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;

class LibroPercepcion1Export implements FromCollection, WithMapping, WithHeadings, WithEvents
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

                $event->sheet->setCellValue('A1', 'PERCEPCIÓN DE IVA 1% EFECTUADA AL DECLARANTE');
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
            'MONTO DE LA PERCEPCIÓN 1%',
            'DUI DEL AGENTE',
            'NÚMERO DEL ANEXO ',
        ];
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $compras = Compra::with(['proveedor'])
                        ->where('estado', '!=', 'Anulada')
                        ->where('percepcion', '>', 0)
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();
        return $compras;
        
    }

    public function map($compra): array{

        $documento = $compra->documento;
        $proveedor = optional($compra->proveedor);

        return [
            $compra->proveedor->nit ?? '',
            $compra->fecha,
            $compra->tipo_documento,
            $compra->serie,
            $compra->referencia,
            $compra->sub_total,
            $compra->percepcion,
            $compra->proveedor->dui ?? '',
            8,
        ];
    }
}
