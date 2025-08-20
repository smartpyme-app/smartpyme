<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion;
use App\Models\Compras\Gastos\Gasto;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnexoSujetosExcluidosExport implements FromCollection, WithMapping, WithCustomCsvSettings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }


    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)

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

        $gastos = Gasto::with('proveedor')
            // ->where('iva' , '>', 0)
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


        $libroCompras = $compras->merge($compras)->merge($gastos)->sortBy(function ($item) {
                return [$item['fecha']];
            });

        return $libroCompras;

    }

    public function map($compra): array{
            setlocale(LC_NUMERIC, 'C');
            $proveedor = optional($compra->proveedor()->first());

            $data = [
                $proveedor->nit ? 1 : 2,  // A - TIPO DE DOCUMENTO
                $proveedor->nit ? $proveedor->nit : $proveedor->dui,  // B - NUMERO DE NIT, DI-II, IJ OTRO DOCUMENTO
                $compra->nombre_proveedor,  // C - NOMBRE, RAZ N SOCIAL O DENOMINACI N
                \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y'),  // D - FECHA DE EMISI N DEL DOCUMENTO
                $compra->sello_mh ? $compra->sello_mh : $compra->num_serie,  // E - NUMERO DE SERIE DEL DOCUMENTO
                $compra->sello_mh ? $compra->dte['identificacion']['codigoGeneracion'] : $compra->referencia,  // F - NUMERO DE DOCUMENTO
                number_format($compra->total, 2, '.', ''),  // G - MONTO DE LA OPERACIÖN
                number_format($compra->iva, 2, '.', ''),  // H - MONTO DE LA RETENCIÖN IVA 13%
                $this->tipoOperacion($compra->tipo_operacion),  // Q - TIPO DE OPERACIÖN
                $this->tipoClasificacion($compra->tipo_clasificacion),  // R - CLASIFICACI Costo gasto
                $this->tipoSector($compra->tipo_sector),  // S - SECTOR
                $this->tipoCostoGasto($compra->tipo_costo_gasto),  // T - TIPO DE COSTO / GASTO
                5,  // M - NUMERO DE ANEXO
            ];

            return $data;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '',
            'use_bom' => false,
        ];
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
