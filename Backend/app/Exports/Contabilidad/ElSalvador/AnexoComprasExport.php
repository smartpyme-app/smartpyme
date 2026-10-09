<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion;
use App\Models\Compras\Gastos\Gasto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;
use App\Services\Contabilidad\LibroIvaMontosHelper;

class AnexoComprasExport implements FromCollection, WithMapping, WithCustomCsvSettings
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

        $tipos = LibroIvaMontosHelper::TIPOS_DOCUMENTO_COMPRA_LIBRO;

        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->whereIn('tipo_documento', $tipos)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get()
            ->map(function ($compra) {
                $compra->origen = 'compra';
                return $compra;
            });

        $gastos = Gasto::with('proveedor', 'detalles')
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->whereIn('tipo_documento', $tipos)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($gasto) {
                $gasto->origen = 'gasto';
                return $gasto;
            });

        $devoluciones = Devolucion::with(['proveedor', 'detalles'])
            ->where('enable', true)
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where(function ($q) {
                LibroIvaMontosHelper::applyFiltroTipoDocumentoCompraLibro($q);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($devolucion) {
                $devolucion->origen = 'devolucion';
                return $devolucion;
            });

        return $compras->merge($gastos)->merge($devoluciones)->sortBy('fecha');

    }

    public function map($compra): array{
            setlocale(LC_NUMERIC, 'C');

            $proveedor = optional($compra->proveedor()->first());
            $multiplier = LibroIvaMontosHelper::multiplicadorDevolucionCompra($compra);
            $tipoDocumento = LibroIvaMontosHelper::tipoDocumentoCompraParaLibro($compra);

            $tipo = '03'; //CCF

            if ($tipoDocumento == 'Factura') {
                $tipo = '01';
            }
            if ($tipoDocumento == 'Nota de crédito') {
                $tipo = '05';
            }

            if ($tipoDocumento == 'Nota de débito') {
                $tipo = '06';
            }

            if ($tipoDocumento == 'Factura de exportación') {
                $tipo = '11';
            }

            $compraExenta = LibroIvaMontosHelper::comprasExentas($compra) * $multiplier;
            $compraGravada = LibroIvaMontosHelper::comprasGravadas($compra) * $multiplier;
            $iva = (float) ($compra->iva ?? 0) * $multiplier;
            $total = (float) ($compra->total ?? 0) * $multiplier;
            $otrosImpuestos = (float) ($compra->total_otros_impuestos ?? 0) * $multiplier;

            $data = [
                \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y'), //A Fecha sin ceros a la izquierda
                strlen($compra->referencia) >= 15 ? '4' : '1', //B Clase DTE o Impreso
                $tipo, //C Tipo
                str_replace('-', '', $compra->referencia), //D Num Documento
                $proveedor->ncr ? $proveedor->ncr : $proveedor->nit,  // E - NIT o NRC
                $compra->nombre_proveedor,  // F - NOMBRE, RAZ N SOCIAL O DENOMINACI N
                number_format($otrosImpuestos, 2, '.', ''),  // G - Compras internas exentas
                number_format($compraExenta, 2, '.', ''),  // H - Internaciones exentas
                '0',  // I - Importaciones exentas
                number_format($compraGravada, 2, '.', ''),  // J - Compras gravadas
                '0',  // K - Internaciones gravadas
                '0',  // l - Importaciones gravadas de bienes
                '0',  // M - Importaciones gravadas de servicios
                number_format($iva, 2, '.', ''),  // N - credito fiscal
                number_format($total, 2, '.', ''),  // O - total
                null,  // P - dui
                $this->tipoOperacion($compra->tipo_operacion),  // Q - TIPO DE OPERACIÖN
                $this->tipoClasificacion($compra->tipo_clasificacion),  // R - CLASIFICACI Costo gasto
                $this->tipoSector($compra->tipo_sector),  // S - SECTOR
                $this->tipoCostoGasto($compra->tipo_costo_gasto),  // T - TIPO DE COSTO / GASTO
                3,  // U - NUMERO DE ANEXO
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
