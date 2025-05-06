<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class LibroConsumidoresExport implements FromCollection, WithHeadings, WithMapping
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
            'Ventas Exentas',
            'Ventas Gravadas',
            'Ventas No Sujetas',
            'Exportaciones',
            'Total',
            'Venta a Cuenta de Terceros',
        ];
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->whereHas('documento', function ($q) {
                            $q->where('nombre', 'Factura')
                                ->orWhere('nombre', 'Factura de exportación');
                        })
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
            $this->index++,
            $venta->fecha,
            $venta->correlativo,
            $venta->exenta,
            $venta->documento->nombre === 'Factura de exportación' ? '0' : $venta->sub_total,
            $venta->no_sujeta,
            $venta->documento->nombre === 'Factura de exportación' ? $venta->sub_total : '0',
            $venta->sub_total,
            $venta->cuenta_a_terceros,
        ];
    }
}
