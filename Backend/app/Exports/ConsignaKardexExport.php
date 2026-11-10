<?php

namespace App\Exports;

use App\Models\Inventario\ConsignaMovimiento;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignaKardexExport implements FromCollection, WithHeadings, WithMapping
{
    private Request $request;

    private int $idEmpresa;

    public function __construct(Request $request, int $idEmpresa)
    {
        $this->request = $request;
        $this->idEmpresa = $idEmpresa;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo',
            'Producto',
            'Bodega',
            'Cantidad',
            'Costo unitario',
            'Proveedor ID',
            'Cliente ID',
            'Compra ID',
            'Venta ID',
            'Nota',
        ];
    }

    public function map($row): array
    {
        return [
            $row->fecha,
            $row->tipo,
            $row->producto?->nombre ?? $row->id_producto,
            $row->bodega?->nombre ?? $row->id_bodega,
            $row->cantidad,
            $row->costo_unitario,
            $row->id_proveedor,
            $row->id_cliente,
            $row->id_compra,
            $row->id_venta,
            $row->nota,
        ];
    }

    public function collection()
    {
        $query = ConsignaMovimiento::query()
            ->forEmpresa($this->idEmpresa)
            ->with(['producto:id,nombre', 'bodega:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($this->request->filled('fecha_ini') && $this->request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$this->request->fecha_ini, $this->request->fecha_fin]);
        }

        return $query->limit(10000)->get();
    }
}
