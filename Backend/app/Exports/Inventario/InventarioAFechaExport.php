<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Bodega;
use Illuminate\Support\Facades\DB;

class InventarioAFechaExport implements FromCollection, WithHeadings, WithMapping
{
    private $request;
    private $bodegas;
    private $kardexData;

    public function filter(Request $request)
    {
        $this->request = $request;

        // Carga las bodegas de la empresa
        $this->bodegas = Bodega::where('id_empresa', $this->request->id_empresa)->where('activo', true)->get();

        // Precalcula los datos del Kardex agrupados por sucursal y producto
        $this->kardexData = DB::table('kardexs')
            ->select('id_inventario', 'id_producto', 'total_cantidad')
            ->whereDate('fecha', '<=', $this->request->fecha)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('id_inventario'); // Agrupa por inventario
    }

    public function headings(): array
    {
        $headings = ['Nombre', 'Categoría', 'Codigo',  'Costo', 'Stock'];

        foreach ($this->bodegas as $sucursal) {
            $headings[] = $sucursal->nombre;
        }

        return $headings;
    }

    public function map($producto): array
    {
        $fields = [
            $producto->nombre,
            $producto->nombre_categoria,
            $producto->codigo,
            $producto->costo,
            $producto->inventarios->sum('stock'),
        ];

        // Agrupar inventarios por bodegas
        $inventarios = $producto->inventarios->keyBy('id_bodega');

        foreach ($this->bodegas as $bodega) {
            $stock = 0;

            // Busca el inventario de la bodega
            $inventario = $inventarios->get($bodega->id);

            if ($inventario) {
                // Verifica si existe el índice en $kardexData
                if (isset($this->kardexData[$bodega->id])) {
                    // Busca el Kardex para este producto en el inventario
                    $kardex = $this->kardexData[$bodega->id]
                        ->where('id_producto', $producto->id)
                        ->first();

                    // Si hay Kardex, toma el total_cantidad; si no asignar 0
                    $stock = $kardex ? $kardex->total_cantidad : '0';
                } else {
                    // No hay Kardex asociado, asignar 0
                    $stock = '0';
                }
            }

            $fields[] = $stock;
        }


        return $fields;
    }

    public function collection()
    {
        return Producto::with('inventarios')
            ->where('id_empresa', $this->request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            ->where('enable', true)
            ->get();
    }
}
