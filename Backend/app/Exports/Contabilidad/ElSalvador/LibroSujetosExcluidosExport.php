<?php

namespace App\Exports\Contabilidad\ElSalvador;

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
            'SELLO RECEPCIÓN MH (DTE)',
            'CÓDIGO DE GENERACIÓN (DTE)',
            'REFERENCIA / NÚM. CONTROL',
            'MONTO DE LA OPERACIÓN',
            'MONTO DE LA RETENCIÓN IVA 13%',
            'RETENCIÓN RENTA (PAGO A CUENTA)',
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
            // ->where('iva' , '>', 0)
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
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Sujeto excluido')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($gasto) {
                $gasto->origen = 'gasto';
                return $gasto;
            });

        $libroCompras = $compras->merge($gastos)->sortBy('fecha');

        return $libroCompras;
    }

    public function map($compra): array
    {
        $proveedor = optional($compra->proveedor()->first());

        $sello = SujetosExcluidosDteHelper::selloRecepcion($compra);
        $codGen = SujetosExcluidosDteHelper::codigoGeneracion($compra);
        if ($sello === '' && $codGen === '' && $compra->num_serie) {
            $sello = (string) $compra->num_serie;
        }
        if ($codGen === '' && $compra->codigo_generacion) {
            $codGen = strtoupper((string) $compra->codigo_generacion);
        }

        $data = [
            'tipo_documento' => $proveedor->nit ? 'NIT' : 'DUI',
            'num_documento' => $proveedor->nit ? $proveedor->nit : $proveedor->dui,
            'proveedor' => $compra->nombre_proveedor,
            'fecha' => $compra->fecha,
            'sello' => $sello,
            'cod_generacion' => $codGen,
            'referencia' => $compra->referencia,
            'total' => $compra->total,
            'iva' => $compra->iva,
            'renta_retenida' => (float) ($compra->renta_retenida ?? 0),
            'tipo_operacion' => $this->tipoOperacion($compra->tipo_operacion),
            'clasificacion' => $this->tipoClasificacion($compra->tipo_clasificacion),
            'sector' => $this->tipoSector($compra->tipo_sector),
            'tipo' => $this->tipoCostoGasto($compra->tipo_costo_gasto),
            'num_anexo' => 5,
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
