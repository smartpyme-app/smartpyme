<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use App\Exports\Concerns\ConsultaClientesExport;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Cliente;

class ClientesTodosExport implements FromQuery, WithHeadings, WithMapping
{
    use ConsultaClientesExport;

    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Tipo',
            'Nombre',
            'Apellido',
            'Nombre empresa',
            'Codigo de cliente',
            'DUI',
            'NIT',
            'NCR',
            'Giro',
            'Tipo_contribuyente',
            'Direccion',
            'Municipio',
            'Distrito',
            'Departamento',
            'Pais',
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
        return $this->consultaClientes(null);
    }

    public function map($row): array
    {
        return [
            $row->tipo,
            $row->nombre,
            $row->apellido,
            $row->nombre_empresa,
            $row->codigo_cliente,
            $row->dui,
            $row->nit,
            $row->ncr,
            $row->giro,
            $row->tipo_contribuyente,
            $row->empresa_direccion ?? $row->direccion,
            $row->municipio,
            $row->distrito,
            $row->departamento,
            $row->pais,
            $row->fecha_cumpleanos,
            $row->telefono,
            $row->correo,
            $row->nota,
            $row->nombre_comercial,
            $row->enable ? 'Activo' : 'Inactivo',
        ];
    }
}
