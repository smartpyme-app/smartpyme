<?php

namespace App\Exports\Bancos;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Bancos\Cheque;

class ChequesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{
       return[
            'Fecha',
            'Banco',
            'Cuenta',
            'A nombre de',
            'Concepto',
            'Estado',
            'Total',
            'Usuario',
        ];
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->cuenta()->pluck('nombre_banco')->first(),
              $row->cuenta()->pluck('numero')->first(),
              $row->anombrede,
              $row->concepto,
              $row->estado,
              $row->total,
              $row->usuario()->pluck('name')->first(),
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Cheque::when($request->buscador, function($query) use ($request){
                        return $query->where('anombrede', 'like' ,'%' . $request->buscador . '%');
                    })
                    ->when($request->inicio, function($query) use ($request){
                        return $query->where('fecha', '>=', $request->inicio);
                    })
                    ->when($request->fin, function($query) use ($request){
                        return $query->where('fecha', '<=', $request->fin);
                    })
                    ->when($request->estado, function($query) use ($request){
                        return $query->where('estado', $request->estado);
                    })
                    ->when($request->id_cuenta, function($query) use ($request){
                        return $query->where('id_cuenta', $request->id_cuenta);
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->get();
    }
}
