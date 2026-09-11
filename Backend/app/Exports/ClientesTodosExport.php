<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Clientes\Cliente;

class ClientesTodosExport implements FromCollection, WithHeadings, WithMapping
{
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
            'Estado',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Cliente::where('id', '!=', 1)
                    ->when($request->buscador, function ($query) use ($request) {
                        return $query->where('nombre', 'like', '%' . $request->buscador . '%')
                                    ->orwhere('nombre_empresa', 'like', '%' . $request->buscador . '%')
                                    ->orwhere('nit', 'like', '%' . $request->buscador . '%')
                                    ->orwhere('giro', 'like', '%' . $request->buscador . '%')
                                    ->orwhere('telefono', 'like', '%' . $request->buscador . '%')
                                    ->orwhere('ncr', 'like', '%' . $request->buscador . '%')
                                    ->orwhere('dui', 'like', '%' . $request->buscador . '%');
                    })
                    ->when($request->estado !== null, function ($q) use ($request) {
                        $q->where('enable', !!$request->estado);
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->get();
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
            $row->enable ? 'Activo' : 'Inactivo',
        ];
    }
}
