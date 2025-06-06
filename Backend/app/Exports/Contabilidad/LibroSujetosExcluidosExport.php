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

class LibroSujetosExcluidosExport implements FromCollection, WithMapping, WithHeadings, WithEvents
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

                $event->sheet->setCellValue('A1', 'LIBRO DE COMPRAS A SUJETOS EXCLUIDOS ');
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
            'TIPO DE DOCUMENTO',
            'NUMERO DE NIT, DUI, OTRO DOCUMENTO',
            'NOMBRE, RAZÓN SOCIAL O DENOMINACIÓN',
            'FECHA DE EMISIÓN DEL DOCUMENTO',
            'NUMERO DE SERIE DEL DOCUMENTO',
            'NUMERO DE DOCUMENTO',
            'MONTO DE LA OPERACIÓN',
            'MONTO DE LA RETENCIÓN IVA 13%',
            'TIPO DE OPERACIÓN',
            'CLASIFICACIÓN',
            'SECTOR',
            'TIPO DE COSTO / GASTO',
            'NUMERO DE ANEXO',
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
            ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Sujeto excluido')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get()
            ->map(function ($compra) {
                $compra->origen = 'compra';
                return $compra;
            });

        // Obtener los gastos
        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Sujeto excluido')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($compra) {
                $compra->origen = 'compra';
                return $compra;
            });

        $libroCompras = $compras->merge($gastos)->sortBy('fecha');

        return $libroCompras;
    }

    public function map($compra): array
    {
        $proveedor = optional($compra->proveedor()->first());
        
        $data = [
            'tipo_documento' => $proveedor->nit ? 'NIT' : 'DUI',  // A - TIPO DE DOCUMENTO
            'num_documento' => $proveedor->nit ? $proveedor->nit : $proveedor->dui,  // B - NUMERO DE NIT, DI-II, IJ OTRO DOCUMENTO
            'proveedor' => $compra->nombre_proveedor,  // C - NOMBRE, RAZ N SOCIAL O DENOMINACI N
            'fecha' => $compra->fecha,  // D - FECHA DE EMISI N DEL DOCUMENTO
            'serie' => $compra->num_serie,  // E - NUMERO DE SERIE DEL DOCUMENTO
            'referencia' => $compra->referencia,  // F - NUMERO DE DOCUMENTO
            'total' => $compra->total,  // G - MONTO DE LA OPERACIÖN
            'iva' => $compra->iva,  // H - MONTO DE LA RETENCIÖN IVA 13%
            'tipo_operacion' => $this->tipoOperacion($compra->tipo_operacion),  // I - TIPO DE OPERACIÖN
            'clasificacion' =>  $this->tipoClasificacion($compra->tipo_clasificacion),  // J - CLASIFICACI Costo gasto
            'sector' => $this->tipoSector($compra->tipo_sector),  // K - SECTOR
            'tipo' =>   $this->tipoCostoGasto($compra->tipo_costo_gasto),  // L - TIPO DE COSTO / GASTO
            'num_anexo' => 5,  // M - NUMERO DE ANEXO
        ];

        return $data;
    }


    function tipoOperacion($operacion) {
        switch ($operacion) {
            case 'Gravada': return 1;
            case 'No Gravada': return 2;
            case 'Excluido': return 3;
            case 'Mixta': return 4;
            default: return '0';
        }
    }

    function tipoClasificacion($sector) {
        switch ($sector) {
            case 'Costo': return 1;
            case 'Gasto': return 2;
            default: return '0';
        }
    }

    function tipoSector($sector) {
        switch ($sector) {
            case 'Industria': return 1;
            case 'Comercio': return 2;
            case 'Agropecuaria': return 3;
            case 'Servicios, profesiones, artes y oficios': return 4;
            default: return '0';
        }
    }

    function tipoCostoGasto($tipo) {
        switch ($tipo) {
            case 'Gastos de venta sin donación': return 1;
            case 'Gastos de administración sin donación': return 2;
            case 'Gastos financieros sin donación': return 3;
            case 'Costo artículos producidos/comprados importaciones/internaciones': return 4;
            case 'Costo artículos producidos/comprados interno': return 5;
            case 'Costos indirectos de fabricación': return 6;
            case 'Mano de obra': return 7;
            default: return '0';
        }
    }

}
