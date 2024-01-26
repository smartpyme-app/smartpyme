<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Compras\Proveedores\Proveedor;

class ProveedoresExport implements FromCollection, WithHeadings, WithMapping
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
            'Ncr',
            'Giro',
            'Tipo',
            'Tipo_contribuyente',
            'Dui',
            'Nit',
            'Nombre empresa',
            'Empresa telefono',
            'Empresa direccion',
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

        return Cliente::where('id','!=', 1)->withSum('ventas', 'total')
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
                    ->orderBy($request->orden, $request->direccion)
                    ->get();
        
    }

    public function map($row): array{
           $fields = [
                $row->nombre,
                $row->apellido,
                $row->ncr,
                $row->giro,
                $row->tipo,
                $row->tipo_contribuyente,
                $row->dui,
                $row->nit,
                $row->nombre_empresa,
                $row->empresa_telefono,
                $row->empresa_direccion,
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
