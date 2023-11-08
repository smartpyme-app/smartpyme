<?php

namespace App\Exports;

use App\Models\Egreso;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EgresosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    private $dateFrom;
    private $dateTo;
    private $id_proveedor;

    public function filter($dateFrom, $dateTo, $id_proveedor)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->id_proveedor = $id_proveedor;
    }

    public function headings():array{
        return[
            'Fecha',
            'Concepto',
            'Categoria',
            'Estado',
            'Forma pago',
            'Referencia',
            'Banco',
            'Vencimiento',
            'Proveedor',
            'NIT',
            'Registro',
            'Monto sin IVA',
            'IVA',
            'Monto total',
            'Nota',
        ];
    }

    public function collection()
    {
        if(!$this->id_proveedor && $this->dateFrom){
            return Egreso::where('id_empresa', Auth::user()->id_empresa)
                        ->whereBetween('fecha', [$this->dateFrom, $this->dateTo])
                        ->get();
        }elseif($this->id_proveedor && !$this->dateFrom){
            return Egreso::where('id_empresa', Auth::user()->id_empresa)
                        ->where('id_proveedor', $this->id_proveedor)
                        ->get();
        }else{
            return Egreso::where('id_empresa', Auth::user()->id_empresa)
                        ->get();
        }
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->concepto,
              $row->tipo,
              $row->estado == 'Confirmado' ? 'Pagado' : $row->estado,
              $row->forma_pago,
              $row->factura,
              $row->detalle_banco,
              $row->vencimiento,
              $row->proveedor()->pluck('nombre')->first(),
              $row->proveedor()->pluck('nit')->first(),
              $row->proveedor()->pluck('ncr')->first(),
              round($row->monto - $row->iva ,2),
              $row->iva,
              $row->monto,
              $row->nota,
         ];
        return $fields;
    }
}
