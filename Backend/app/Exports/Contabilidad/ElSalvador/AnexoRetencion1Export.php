<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoRetencion1Export implements FromCollection, WithMapping, WithCustomCsvSettings
{

    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $request = $this->request;
        
        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->where('iva_retenido', '>', 0)
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->orderByDesc('correlativo')
                        ->get();
        return $ventas;
        
    }

    public function map($venta): array{
        setlocale(LC_NUMERIC, 'C');
        $cliente = optional($venta->cliente);

        $tipo = '03'; //CCF

        if ($venta->tipo_documento == 'Nota de crédito') {
            $tipo = '05';
        }

        if ($venta->tipo_documento == 'Nota de débito') {
            $tipo = '06';
        }

        if ($venta->tipo_documento == 'Declaración de mercancía') {
            $tipo = '12';
        }

        return [
            $venta->cliente->nit ?? '', //A nit agente
            \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), // B fecha
            $tipo, // C Tipo
            $venta->dte['sello'] ?? '', // D serie o sello
            str_replace('-', '', $venta->dte['identificacion']['codigoGeneracion']) ?? '', // E numero de documento o codigo de generacion
            number_format($venta->sub_total, 2, '.', ''), // F monto sujeto
            number_format($venta->percepcion, 2, '.', ''), // G monto percepcion
            $venta->cliente->dui ?? '', // H Dui agente
            8, // numero anexo
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '',
            'use_bom' => false,
        ];
    }

}
