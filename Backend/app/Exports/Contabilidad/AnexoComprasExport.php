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

class AnexoComprasExport implements FromCollection, WithMapping, WithCustomCsvSettings
{

    public $request;

    public function __construct()
    {
        setlocale(LC_NUMERIC, 'en_US.UTF-8');
    }

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
            ->where('iva' , '>', 0)
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get()
            ->map(function ($compra) {
                $compra->origen = 'compra';
                return $compra;
            });

        $gastos = Gasto::with('proveedor')
            ->where('iva' , '>', 0)
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
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

            $proveedor = optional($compra->proveedor()->first());

            $tipo = '03'; //CCF

            if ($compra->tipo_documento == 'Nota de crédito') {
                $tipo = '05';
            }

            if ($compra->tipo_documento == 'Nota de débito') {
                $tipo = '06';
            }

            if ($compra->tipo_documento == 'Factura de exportación') {
                $tipo = '11';
            }

            $data = [
                \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y'), //A Fecha sin ceros a la izquierda
                strlen($compra->referencia) >= 15 ? '4' : '1', //B Clase DTE o Impreso
                $tipo, //C Tipo
                str_replace('-', '', $compra->referencia), //D Num Documento
                $proveedor->ncr ? str_replace('-', '', $proveedor->ncr) : str_replace('-', '', $proveedor->nit),  // E - NIT o NRC
                $compra->nombre_proveedor,  // F - NOMBRE, RAZ N SOCIAL O DENOMINACI N
                '0.00',  // G - Compras internas exentas
                $compra->exenta > 0  ? $compra->exenta : '0.00' ,  // H - Internaciones exentas
                '0.00',  // I - Importaciones exentas
                $compra->sub_total,  // J - Compras gravadas
                '0.00',  // K - Internaciones gravadas
                '0.00',  // l - Importaciones gravadas de bienes
                '0.00',  // M - Importaciones gravadas de servicios
                $compra->iva,  // N - credito fiscal
                $compra->total,  // O - total
                null,  // P - dui
                $this->tipoOperacion($compra->tipo_operacion),  // Q - SECTOR
                $this->tipoClasificacion($compra->clasificacion),  // R - SECTOR
                $this->tipoSector($compra->sector),  // S - SECTOR
                $this->tipo($compra->tipo_gasto),  // T - TIPO DE COSTO / GASTO
                'num_anexo' => 3,  // U - NUMERO DE ANEXO
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
            default: return null;
        }
    }

    function tipoClasificacion($clasificacion) {
        switch ($clasificacion) {
            case 'Costo': return 1;
            case 'Gasto': return 2;
            default: return null;
        }
    }

    function tipoSector($sector) {
        switch ($sector) {
            case 'Industria': return 1;
            case 'Comercio': return 2;
            case 'Agropecuaria': return 3;
            case 'Servicios, profesiones, artes y oficios': return 4;
            default: return null;
        }
    }

    function tipo($tipo) {
        switch ($tipo) {
            case 'Gastos de venta sin donación': return 1;
            case 'Gastos de administración sin donación': return 2;
            case 'Gastos financieros sin donación': return 3;
            case 'Costo artículos producidos/comprados importaciones/internaciones': return 4;
            case 'Costo artículos producidos/comprados interno': return 5;
            case 'Costos indirectos de fabricación': return 6;
            case 'Mano de obra': return 7;
            default: return null;
        }
    }


}
