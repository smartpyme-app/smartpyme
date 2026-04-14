<?php

namespace App\Exports;

use App\Models\Inventario\Categorias\Categoria;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategoriasExport implements FromCollection, WithHeadings, WithMapping
{
    private ?Request $request = null;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return ['ID', 'Nombre', 'Descripción', 'Activo', 'Subcategoría', 'ID categoría padre'];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->nombre,
            $row->descripcion,
            $row->enable,
            $row->subcategoria,
            $row->id_cate_padre,
        ];
    }

    public function collection()
    {
        $request = $this->request;
        if (!$request) {
            return collect();
        }

        return Categoria::query()
            ->when($request->nombre, function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->nombre . '%');
            })
            ->when($request->buscador, function ($q) use ($request) {
                $q->where(function ($subQuery) use ($request) {
                    $subQuery->where('nombre', 'like', '%' . $request->buscador . '%')
                        ->orWhere('descripcion', 'like', '%' . $request->buscador . '%');
                });
            })
            ->when($request->estado !== null, function ($q) use ($request) {
                $q->where('enable', !!$request->estado);
            })
            ->when($request->id_empresa, function ($q) use ($request) {
                $q->where('id_empresa', $request->id_empresa);
            })
            ->orderBy('enable', 'desc')
            ->orderBy($request->orden ?? 'nombre', $request->direccion ?? 'asc')
            ->get();
    }
}
