<?php

namespace App\Exports\Bancos;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Bancos\Conciliacion;

class ConciliacionExport implements FromCollection, WithHeadings, WithMapping
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
            'Observación',
            'Saldo',
            'Usuario',
        ];
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->cuenta()->pluck('nombre_banco')->first(),
              $row->cuenta()->pluck('numero')->first(),
              $row->nota,
              $row->saldo_actual,
              $row->usuario()->pluck('name')->first(),
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Conciliacion::with('cuenta')->when($request->buscador, function($query) use ($request){
                                    return $query->where('nota', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->id_usuario, function($query) use ($request){
                                    return $query->where('id_usuario', $request->id_usuario);
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
    }
}
