<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;

class LibroContribuyentesExport implements FromCollection, WithMapping, WithHeadings, WithEvents
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

                $event->sheet->setCellValue('A1', 'LIBRO DE VENTAS A CONSUMIDORES ');
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
            'N°',
            'Fecha',
            'Correlativo',
            'Número de control interno',
            'Cliente',
            'NIT/NRC',
            'Ventas Exentas',
            'Ventas No Sujetas',
            'Ventas Gravadas',
            'Débito Fiscal',
            'Ventas Exentas a Cuenta de Terceros',
            'Ventas Gravadas a Cuenta de Terceros',
            'Débito Fiscal por Cuenta de Terceros',
            'IVA Retenido',
            'IVA Percibido',
            'Total',
        ];
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)

        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Crédito fiscal');
                            });
                        })
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();

        $devoluciones = DevolucionVenta::with(['cliente'])
            ->where('enable', true)
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $libroVentas = $ventas->merge($ventas)->merge($devoluciones)->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            });

        return $libroVentas;

    }

    public function map($venta): array{

            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            if ($venta->iva > 0) {
                $venta->gravada = $venta->sub_total;
            }else{
                $venta->gravada = 0;
                $venta->exenta = $venta->sub_total;
            }

            return [
                $this->index++,
                $venta->fecha ?? 'N/A',
                $venta->correlativo ?? 'N/A',
                $venta->correlativo ?? 'N/A',
                $venta->nombre_cliente ?? 'N/A',
                $cliente->nit ?? $cliente->ncr ?? 'N/A',
                $venta->exenta > 0 ? $venta->exenta * -1 : $venta->exenta,
                $venta->no_sujeta > 0 ? $venta->no_sujeta * -1 : $venta->no_sujeta,
                $venta->id_venta ? $venta->gravada * -1 : $venta->gravada,
                $venta->id_venta ? $venta->iva * -1 : $venta->iva,
                0,
                $venta->cuenta_a_terceros > 0 ? $venta->cuenta_a_terceros * -1 : $venta->cuenta_a_terceros,
                0,
                $venta->iva_retenido > 0 ? $venta->iva_retenido * -1 : $venta->iva_retenido,
                $venta->iva_percibido > 0 ? $venta->iva_percibido * -1 : $venta->iva_percibido,
                $venta->id_venta ? $venta->total * -1 : $venta->total,
            ];

    }
}
