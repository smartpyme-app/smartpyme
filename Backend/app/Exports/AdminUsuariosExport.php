<?php

namespace App\Exports;

use App\Models\User as Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AdminUsuariosExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    protected ?Request $request = null;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Correo',
            'Tipo',
            'Activo',
            'Empresa',
            'ID empresa',
            'Sucursal',
            'Bodega',
            'Teléfono',
            'Último inicio de sesión',
            'Creado',
            'Actualizado',
        ];
    }

    public function query()
    {
        $request = $this->request ?? request();

        return Usuario::query()
            ->with(['empresa', 'sucursal', 'bodega'])
            ->when($request->filled('estado'), function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->id_empresa, function ($q) use ($request) {
                $q->where('id_empresa', $request->id_empresa);
            })
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->tipo, function ($q) use ($request) {
                $q->where('tipo', $request->tipo);
            })
            ->when($request->buscador, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->buscador . '%')
                        ->orWhere('email', 'like', '%' . $request->buscador . '%');
                });
            })
            ->orderBy($request->input('orden', 'id'), $request->input('direccion', 'asc'));
    }

    /**
     * @param  \DateTimeInterface|string|null  $valor
     */
    private function formatoFechaHora($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }
        try {
            return Carbon::parse($valor)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return (string) $valor;
        }
    }

    /**
     * @param  \App\Models\User  $row
     */
    public function map($row): array
    {
        $activo = $row->enable === true || $row->enable === 1 || $row->enable === '1';

        return [
            $row->id,
            $row->name,
            $row->email,
            $row->tipo,
            $activo ? 'Sí' : 'No',
            $row->empresa->nombre ?? '',
            $row->id_empresa,
            $row->sucursal->nombre ?? '',
            $row->bodega->nombre ?? '',
            $row->telefono ?? '',
            $this->formatoFechaHora($row->ultimo_login),
            $this->formatoFechaHora($row->created_at),
            $this->formatoFechaHora($row->updated_at),
        ];
    }
}
