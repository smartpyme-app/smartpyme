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
        $this->bodegas = Bodega::where('id_empresa', $this->request->id_empresa)
            ->when($request->id_bodega, function ($q) use ($request) {
                $q->where('id', $request->id_bodega);
            })
            ->where('activo', true)->get();

        // Precalcula los datos del Kardex agrupados por bodega (id_inventario = id_bodega).
        // Filtrar por bodegas de la empresa para reducir tiempo y memoria.
        $bodegaIds = $this->bodegas->pluck('id')->toArray();
        $this->kardexData = DB::table('kardexs')
            ->select('id_inventario', 'id_producto', 'total_cantidad')
            ->whereIn('id_inventario', $bodegaIds)
            ->whereDate('fecha', '<=', $this->request->fecha)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('id_inventario');
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
        // Obtener la empresa y verificar si tiene shopify_store_url configurado
        $nombreProducto = $producto->nombre;

        // Si la empresa tiene shopify_store_url y el producto tiene nombre_variante, concatenar
        if ($producto->empresa && $producto->empresa->shopify_store_url && $producto->nombre_variante) {
            $nombreProducto = $producto->nombre . ' ' . $producto->nombre_variante;
        }

        $fields = [
            $nombreProducto,
            $producto->nombre_categoria ?? '',
            $producto->codigo ?? '',
            $producto->costo ?? 0,
            $producto->inventarios ? $producto->inventarios->sum('stock') : 0,
        ];

        // Agrupar inventarios por bodegas
        $inventarios = $producto->inventarios ? $producto->inventarios->keyBy('id_bodega') : collect();

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
        $request = $this->request;

        // Usar cursor() en lugar de get() para reducir uso de memoria en empresas con muchos productos
        return Producto::with(['inventarios' => function ($q) use ($request) {
            if ($request->id_bodega) {
                $q->where('id_bodega', $request->id_bodega);
            }
        }, 'empresa'])
            ->where('id_empresa', $this->request->id_empresa)
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            ->where('enable', true)
            ->cursor();
    }
}
