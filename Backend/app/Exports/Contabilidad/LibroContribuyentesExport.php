<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
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
                        ->where('estado', '!=', 'Pendiente')
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Crédito fiscal');
                            });
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
                $this->index++,
                $venta->fecha ?? 'N/A',
                $venta->correlativo ?? 'N/A',
                $venta->correlativo ?? 'N/A',
                $venta->nombre_cliente ?? 'N/A',
                $cliente->nit ?? $cliente->ncr ?? 'N/A',
                $venta->exenta ?? 0,
                $venta->no_sujeta ?? 0,
                $venta->sub_total ?? 0,
                $venta->iva ?? 0,
                0,
                $venta->cuenta_a_terceros ?? 0,
                0,
                $venta->iva_percibido ?? 0,
                $venta->total ?? 0,
            ];

    }
}
