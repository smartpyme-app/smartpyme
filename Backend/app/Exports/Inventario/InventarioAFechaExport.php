<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\Inventario\Producto;
use App\Models\Admin\Sucursal;
use Illuminate\Support\Facades\DB;

class InventarioAFechaExport implements FromCollection, WithHeadings, WithMapping
{
    private $sucursales;
    private $kardexData;

    public function __construct()
    {
        // Carga las sucursales de la empresa
        $this->sucursales = Sucursal::where('id_empresa', 324)->get();

        // Precalcula los datos del Kardex agrupados por sucursal y producto
        $this->kardexData = DB::table('kardexs')
            ->select('id_inventario', 'id_producto', DB::raw('MAX(fecha) as ultima_fecha'), 'total_cantidad')
            ->whereDate('fecha', '<=', '2024-12-31')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->groupBy('id_inventario', 'id_producto', 'total_cantidad')
            ->get()
            ->groupBy('id_inventario'); // Agrupa por inventario
    }

    public function headings(): array
    {
        $headings = ['Nombre', 'Categoría'];

        foreach ($this->sucursales as $sucursal) {
            $headings[] = $sucursal->nombre;
        }

        return $headings;
    }

    public function map($producto): array
    {
        $fields = [
            $producto->nombre,
            $producto->nombre_categoria,
        ];

        // Agrupar inventarios por sucursal
        $inventarios = $producto->inventarios->keyBy('id_sucursal');

        foreach ($this->sucursales as $sucursal) {
            $stock = 0;

            // Busca el inventario de la sucursal
            $inventario = $inventarios->get($sucursal->id);

            if ($inventario) {
                // Verifica si existe el índice en $kardexData
                if (isset($this->kardexData[$sucursal->id])) {
                    // Busca el Kardex para este producto en el inventario
                    $kardex = $this->kardexData[$sucursal->id]
                        ->where('id_producto', $producto->id)
                        ->first();

                    // Si hay Kardex, toma el total_cantidad; si no, toma el stock del inventario
                    $stock = $kardex ? $kardex->total_cantidad : $inventario->stock;
                } else {
                    // No hay Kardex asociado, usa el stock del inventario
                    $stock = $inventario->stock;
                }
            }

            $fields[] = $stock;
        }


        return $fields;
    }

    public function collection()
    {
        return Producto::with('inventarios')
            ->where('id_empresa', 324)
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            ->where('enable', true)
            ->get();
    }
}
