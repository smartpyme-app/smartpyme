<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoPercepcion1Export implements FromCollection, WithMapping, WithCustomCsvSettings
{

    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $request = $this->request;
        
        $compras = Compra::with(['proveedor'])
                        ->where('estado', '!=', 'Anulada')
                        ->where('percepcion', '>', 0)
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();
        return $compras;
        
    }

    public function map($compra): array{
        setlocale(LC_NUMERIC, 'C');
        $documento = $compra->documento;
        $proveedor = optional($compra->proveedor);

        $tipo = '03'; //CCF

        if ($compra->tipo_documento == 'Nota de crédito') {
            $tipo = '05';
        }

        if ($compra->tipo_documento == 'Nota de débito') {
            $tipo = '06';
        }

        if ($compra->tipo_documento == 'Declaración de mercancía') {
            $tipo = '12';
        }

        return [
            $compra->proveedor->nit ?? '', //A nit agente
            \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y'), // B fecha
            $tipo, // C Tipo
            $compra->serie, // D serie o sello
            str_replace('-', '', $compra->referencia), // E numero de documento o codigo de generacion
            number_format($compra->sub_total, 2, '.', ''), // F monto sujeto
            number_format($compra->percepcion, 2, '.', ''), // G monto percepcion
            $compra->proveedor->dui ?? '', // H Dui agente
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
