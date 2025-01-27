<?php

namespace App\Exports;

use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStandardWidth;
use Illuminate\Http\Request;

class VentasAcumuladoExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Reporte de Ventas - Acumulado por producto';
    }

    public function headings(): array
    {
        $sucursales = '';
        if ($this->request->sucursales && count($this->request->sucursales) > 0) {
            // Obtener nombres de sucursales seleccionadas
            $sucursales = implode(', ', DB::table('sucursales')
                ->whereIn('id', $this->request->sucursales)
                ->pluck('nombre')
                ->toArray());
        } else {
            $sucursales = 'Todas';
        }

        return [
            ['Reporte de Ventas - Acumulado por producto'],
            ['Fecha Inicio:', $this->request->inicio ?? 'Todas'],
            ['Fecha Final:', $this->request->fin ?? 'Todas'],
            ['Sucursal:', $sucursales],
            [''],
            [
                'Categoría',
                'Marca',
                'SKU',
                'Unidades Vendidas (#)',
                'Total de Ventas (Sin IVA)',
                'Existencias Disponibles'
            ]
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Detalle::select(
            'productos.categoria_id',
            'productos.marca',
            'productos.nombre as sku',
            DB::raw('SUM(detalles.cantidad) as unidades_vendidas'),
            DB::raw('SUM(detalles.total) as total_ventas'),
            DB::raw('(SELECT SUM(stock) FROM inventarios WHERE inventarios.id_producto = productos.id) as existencias')
        )
            ->join('ventas', 'ventas.id', '=', 'detalles.id_venta')
            ->join('productos', 'productos.id', '=', 'detalles.id_producto')
            ->when($request->inicio, function ($query) use ($request) {
                return $query->whereBetween('ventas.fecha', [$request->inicio, $request->fin]);
            })
            ->when($request->sucursales, function ($query) use ($request) {
                return $query->whereIn('ventas.id_sucursal', $request->sucursales);
            })
            ->when($request->categorias, function ($query) use ($request) {
                return $query->whereIn('productos.categoria_id', $request->categorias);
            })
            ->when($request->marcas, function ($query) use ($request) {
                return $query->whereIn('productos.marca', $request->marcas);
            })
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->groupBy('productos.id', 'productos.categoria_id', 'productos.marca', 'productos.nombre')
            ->get();
    }

    public function map($row): array
    {
        $categoria = DB::table('categorias')->where('id', $row->categoria_id)->value('nombre');

        return [
            $categoria,
            $row->marca,
            $row->sku,
            $row->unidades_vendidas,
            round($row->total_ventas, 2),
            $row->existencias ?? 0
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,  // Categoría
            'B' => 20,  // Marca
            'C' => 30,  // SKU
            'D' => 20,  // Unidades Vendidas
            'E' => 25,  // Total Ventas
            'F' => 25,  // Existencias
        ];
    }
}
