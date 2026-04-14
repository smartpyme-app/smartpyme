<?php

namespace App\Exports;

use App\Models\Inventario\Categorias\SubCategoria;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SubCategoriasExport implements FromCollection, WithHeadings, WithMapping
{
    private ?Request $request = null;

    public function filter(Request $request): void
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return ['ID', 'Nombre', 'Descripción', 'Categoría', 'Total productos'];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->nombre,
            $row->descripcion,
            $row->categoria->nombre ?? '',
            $row->productos_count,
        ];
    }

    public function collection()
    {
        $request = $this->request;
        if (!$request) {
            return collect();
        }

        return SubCategoria::with('categoria')
            ->withCount('productos')
            ->when($request->nombre, function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->nombre . '%');
            })
            ->orderBy('nombre', 'asc')
            ->get();
    }
}
