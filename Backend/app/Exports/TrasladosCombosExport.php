<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Traslado;
use Carbon\Carbon;

class TrasladosCombosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */

    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Producto',
            'Categoría',
            'De',
            'Para',
            'Cantidad',
            'Detalle / Componente',
            'Cantidad Componente',
            'Usuario',
            'Fecha',
            'Estado',
            'Motivo',
        ];
    }

    public function map($row): array
    {
        $data = [];

        // Información general del producto compuesto
        $data[] = [
            $row->producto->nombre ?? 'N/A',
            $row->producto->categoria->nombre ?? 'N/A',
            $row->origen->nombre ?? 'N/A',
            $row->destino->nombre ?? 'N/A',
            $row->cantidad,
            'Producto Compuesto',
            '',
            $row->usuario->name ?? 'N/A',
            Carbon::parse($row->created_at)->format('d/m/Y'),
            $row->estado,
            $row->concepto,
        ];

        // Detalles de las composiciones (componentes)
        if ($row->producto->composiciones->count() > 0) {
            foreach ($row->producto->composiciones as $composicion) {
                $data[] = [
                    '',
                    '',
                    '',
                    '',
                    '',
                    $composicion->nombre_compuesto ?? 'N/A',
                    $composicion->cantidad,
                    '',
                    '',
                    '',
                    '',
                ];
            }
        }

        return $data;
    }

    public function collection()
    {
        // $request = $this->request;
        $year = Carbon::now()->year;

        return Traslado::where('id_empresa', 324)->with('producto') // Eager load para obtener la relación
                    ->whereHas('producto', function ($query) {
                        $query->whereHas('composiciones');  // Ajusta la condición
                    })
                    ->whereBetween('traslados.created_at', ["{$year}-10-01", "{$year}-12-31"])
                    ->with('producto.composiciones')
                    ->get();
    }
}
