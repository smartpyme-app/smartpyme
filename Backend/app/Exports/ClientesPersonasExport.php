<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use App\Exports\Concerns\ConsultaClientesExport;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Cliente;

class ClientesPersonasExport implements FromQuery, WithHeadings, WithMapping
{
    use ConsultaClientesExport;

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
            'Nombre comercial',
            'Estado',
        ];
    }

    public function query()
    {
        return $this->consultaClientes('Persona');
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
                $row->nombre_comercial,
                $row->enable ? 'Activo' : 'Inactivo',
         ];
        return $fields;
    }
}
