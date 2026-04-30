<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Cliente;

class ClientesPersonasExport implements FromCollection, WithHeadings, WithMapping
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
            'Nombre',
            'Apellido',
            'Codigo de cliente',
            'DUI',
            'NIT',
            'Direccion',
            'Municipio',
            'Departamento',
            'Fecha cumpleanos',
            'Telefono',
            'Correo',
            'Nota',
            'Estado',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Cliente::where('id','!=', 1)//->withSum('ventas', 'total')
                    ->when($request->buscador, function($query) use ($request){
                        return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                    ->orwhere('nombre_empresa', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('nit', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('giro', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('telefono', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('ncr', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('dui', 'like',  '%'. $request->buscador .'%');
                    })
                    ->when($request->estado !== null, function($q) use ($request){
                        $q->where('enable', !!$request->estado);
                    })
                    ->where('tipo', 'Persona')
                    ->orderBy($request->orden, $request->direccion)
                    ->get();
        
    }

    public function map($row): array{
           $fields = [
                $row->nombre,
                $row->apellido,
                $row->codigo_cliente,
                $row->dui,
                $row->nit,
                $row->direccion,
                $row->municipio,
                $row->departamento,
                $row->fecha_cumpleanos,
                $row->telefono,
                $row->correo,
                $row->nota,
                $row->enable ? 'Activo' : 'Inactivo',
         ];
        return $fields;
    }
}
