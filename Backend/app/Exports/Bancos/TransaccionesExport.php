<?php

namespace App\Exports\Bancos;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Bancos\Transaccion;

class TransaccionesExport implements FromCollection, WithHeadings, WithMapping
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
            'Concepto',
            'Tipo',
            'Operación',
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
              $row->concepto,
              $row->tipo,
              $row->tipo_operacion,
              $row->estado,
              $row->total,
              $row->usuario()->pluck('name')->first(),
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return $transacciones = Transaccion::with('cuenta')->when($request->buscador, function($query) use ($request){
                        return $query->where('nombre', 'like' ,'%' . $request->buscador . '%');
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
                    ->when($request->tipo, function($query) use ($request){
                        return $query->where('tipo', $request->tipo);
                    })
                    ->when($request->tipo_operacion, function($query) use ($request){
                        return $query->where('tipo_operacion', $request->tipo_operacion);
                    })
                    ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                    ->orderBy('id', 'desc')
                    ->get();
    }
}
