<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use App\Exports\Concerns\ConsultaClientesExport;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Cliente;

class ClientesEmpresasExport implements FromQuery, WithHeadings, WithMapping
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
            'Nombre empresa',
            'NCR',
            'Giro',
            'Tipo_contribuyente',
            'Codigo_cliente_empresa',
            'DUI',
            'NIT',
            'Direccion',
            'Municipio',
            'Distrito',
            'Departamento',
            'Telefono',
            'Correo',
            'Nota',
            'Nombre comercial',
            'Estado',
        ];
    }

    public function query()
    {
        return $this->consultaClientes('Empresa');
    }

    public function map($row): array{
           $fields = [
                $row->nombre_empresa,
                $row->ncr,
                $row->giro,
                $row->tipo_contribuyente,
                $row->codigo_cliente,
                $row->dui,
                $row->nit,
                $row->empresa_direccion ?? $row->direccion,
                $row->municipio,
                $row->distrito,
                $row->departamento,
                $row->telefono,
                $row->correo,
                $row->nota,
                $row->nombre_comercial,
                $row->enable ? 'Activo' : 'Inactivo',
         ];
        return $fields;
    }
}
