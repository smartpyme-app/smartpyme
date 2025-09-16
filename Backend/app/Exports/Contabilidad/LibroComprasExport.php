<?php

namespace App\Exports\Contabilidad;

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
use Auth;

class LibroComprasExport implements FromCollection, WithMapping, WithHeadings, WithEvents
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

                $event->sheet->setCellValue('A1', 'LIBRO DE COMPRAS');
                $event->sheet->setCellValue('A2', Auth::user()->empresa()->pluck('nombre')->first());
                $event->sheet->setCellValue('A3', 'NRC: ' . Auth::user()->empresa()->pluck('ncr')->first());
                $event->sheet->setCellValue('E3', 'Folio N°:');
                $event->sheet->setCellValue('A4', 'Mes: ' . ucfirst(Carbon::parse($this->request->inicio)->translatedFormat('F')));
                $event->sheet->setCellValue('E4', 'Año: ' . Carbon::parse($this->request->inicio)->format('Y'));

            },
        ];
    }

    public function headings(): array
    {
        return [
            'N°',
            'FECHA',
            'NÚMERO DE DOCUMENTO',
            'NÚMERO DE REGISTRO DEL CONTRIBUYENTE',
            'NOMBRE DEL PROVEEDOR',
            'COMPRAS EXENTAS INTERNAS',
            'COMPRAS NO SUJETAS',
            'IMPORTACIONES E INTERNACIONES EXENTAS',
            'COMPRAS INTERNAS GRAVADAS',
            'IMPORTACIONES E INTERNACIONES GRAVADAS',
            'CRÉDITO FISCAL',
            'ANTICIPO A CUENTA IVA PERCIBIDO',
            'TOTAL',
            'COMPRAS A SUJETOS EXCLUIDOS',
        ];
    }

    public function collection()
    {
        $request = $this->request; //where('id_empresa', Auth::user()->id_empresa)

        // Obtener las compras
        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        // Obtener los gastos
        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $devoluciones = DevolucionCompra::with(['proveedor'])
            ->where('enable', true)
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();
            
        $libroCompras = $compras->merge($gastos)->merge($devoluciones)->sortBy('fecha');

        return $libroCompras;
    }

    public function map($compra): array
    {
        $proveedor = optional($compra->proveedor()->first());
        $multiplier = isset($compra->id_compra) ? -1 : 1;

        // Valores base
        $data = [
            $this->index++,
            $compra->fecha,
            $compra->referencia,
            $proveedor->nit ?? $proveedor->ncr,
            $compra->nombre_proveedor,
            $compra->total_otros_impuestos ?? 0, // compras_exentas
            0, // compras_no_sujetas
            0, // importaciones_exentas
            0, // compras_gravadas
            0, // importaciones_gravadas
            0, // credito_fiscal
            0, // anticipo_iva_percibido
            0, // total
            0  // sujetos_excluidos
        ];

        if ($compra->tipo_documento == 'Sujeto excluido') {
            $data[13] = $compra->total * $multiplier; // Solo asignar a sujetos excluidos
        } else {
            $data[8] = $compra->sub_total * $multiplier;  // COMPRAS GRAVADAS
            $data[10] = $compra->iva * $multiplier;       // CRÉDITO FISCAL
            $data[11] = ($compra->percepcion ?? 0) * $multiplier; // ANTICIPO IVA
            $data[12] = $compra->total * $multiplier;     // TOTAL
        }

        return $data;
    }
}
