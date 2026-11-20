<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use App\Exports\Concerns\ConsultaClientesExport;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Cliente;

class ClientesExtranjerosExport implements FromQuery, WithHeadings, WithMapping
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
         //   'Tipo de Documento',
            'Número de Identificación',
            'Nombre Comercial',
            'Tipo de Persona',
            'País',
            'Dirección',
            'Giro',
            'Correo',
            'Teléfono',
            'nombre_comercial',
            'Estado',
        ];
    }

    public function query()
    {
        return $this->consultaClientes('Extranjero');
    }

    public function map($row): array{

        return [
            $row->nombre,
            $row->apellido,
         //   $row->tipo_documento,
            $row->dui,
            $row->nombre_empresa,
            $row->tipo,
            $row->pais,
            $row->direccion,
            $row->giro,
            $row->correo,
            $row->telefono,
            $row->nombre_comercial,
            $row->enable ? 'Activo' : 'Inactivo',
        ];
    }
}
