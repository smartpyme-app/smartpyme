<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class LibroContribuyentesExport implements FromCollection, WithMapping, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $request;
    private $index = 1;

    public function filter(Request $request)
    {
        $this->request = $request;
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
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();

        $devoluciones = DevolucionVenta::with(['cliente'])
            ->where('enable', true)
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

            return [
                $this->index++,
                $venta->fecha ?? 'N/A',
                $venta->correlativo ?? 'N/A',
                $venta->correlativo ?? 'N/A',
                $venta->nombre_cliente ?? 'N/A',
                $cliente->nit ?? $cliente->ncr ?? 'N/A',
                $venta->id_venta ? $venta->exenta * -1 : $venta->exenta,
                $venta->id_venta ? $venta->no_sujeta * -1 : $venta->no_sujeta,
                $venta->id_venta ? $venta->sub_total * -1 : $venta->sub_total,
                $venta->id_venta ? $venta->iva * -1 : $venta->iva,
                0,
                $venta->id_venta ? $venta->cuenta_a_terceros * -1 : $venta->cuenta_a_terceros,
                0,
                $venta->id_venta ? $venta->iva_percibido * -1 : $venta->iva_percibido,
                $venta->id_venta ? $venta->total * -1 : $venta->total,
            ];

    }
}
