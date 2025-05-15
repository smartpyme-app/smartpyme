<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoAnuladosExport implements FromCollection, WithMapping, WithCustomCsvSettings
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
        
        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', 'Anulada')
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

            $tipo = '01'; //CF

            if ($documento && $documento->nombre == 'Factura de exportación') {
                $tipo = '11';
            }

           $fields = [
                $venta->sello_mh ? $venta->dte['identificacion']['numeroControl'] : '', // A Resolucion
                $venta->sello_mh ? 4 : 1, // B Clase de documento
                $venta->sello_mh ? '0' : trim($venta->correlativo), // C desde pre
                $venta->sello_mh ? '0' : trim($venta->correlativo), // D hasta pre
                $this->tipoDocumento($venta->nombre_documento), // E tipo de documento
                $venta->sello_mh ? 'D' : 'A', //F Detalle
                $venta->sello_mh ? $venta->dte['sello'] : '', // G serie
                $venta->sello_mh ? '0' : trim($venta->correlativo), // H desde
                $venta->sello_mh ? '0' : trim($venta->correlativo), // I hasta
                $venta->sello_mh ? $venta->dte['identificacion']['codigoGeneracion'] : '', // J codigo de generacion
         ];
        return $fields;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '',
            'use_bom' => false,
        ];
    }

    function tipoDocumento($tipo) {
        switch (mb_strtoupper(trim($tipo))) {
            case 'FACTURA':
                return '01';
            case 'FACTURA DE VENTA SIMPLIFICADA':
                return '02';
            case 'CRÉDITO FISCAL':
                return '03';
            case 'NOTA DE REMISIÓN':
                return '04';
            case 'NOTA DE CRÉDITO':
                return '05';
            case 'NOTA DE DÉBITO':
                return '06';
            case 'COMPROBANTE DE RETENCIÓN':
                return '07';
            case 'COMPROBANTE DE LIQUIDACIÓN':
                return '08';
            case 'DOCUMENTO CONTABLE DE LIOUIDACIÓN':
                return '09';
            case 'TIQUETES DE MÁQUINAS REGISTRADORA':
                return '10';
            case 'FACTURA DE EXPORTACIÓN':
                return '11';
            case 'SUJETO EXCLUIDO':
                return '14';
            default:
                return null; // o '00' si deseas un valor por defecto
        }
    }


}
