<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Http\Request;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use FFI\Exception;
use Illuminate\Support\Facades\Log;

class KardexFiltradoExport implements FromCollection, WithHeadings, WithMapping, WithChunkReading
{
    /**
     * @return \Illuminate\Support\Collection
     */
    private $request;

    public function filter(Request $request)
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
            'Fecha',
            'Producto',
            'Inventario',
            'Detalle',
            'N° Documento',
            'Entrada',
            'Salida',
            'Stock',
            'Costo U',
            'Costo Total',
            'Usuario',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        // Log para debug
        // Log::info('KardexFiltradoExport - Filtros recibidos:', $request->all());

        // Usar producto_ids que vienen del frontend
        $productoIds = $request->producto_ids ?? [];

        // Convertir a array si viene como string separado por comas
        if (is_string($productoIds)) {
            $productoIds = explode(',', $productoIds);
        }

        // Asegurar que todos los IDs sean enteros
        $productoIds = array_map('intval', $productoIds);

        // Log::info('KardexFiltradoExport - IDs de productos recibidos:', ['producto_ids' => $productoIds]);

        if (empty($productoIds)) {
            // Log::info('KardexFiltradoExport - No se recibieron producto_ids');
            return collect([]);
        }

        // Obtener kardex de los productos filtrados
        $kardexQuery = Kardex::whereIn('id_producto', $productoIds)
            ->when($request->inicio, function ($q) use ($request) {
                $q->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($q) use ($request) {
                $q->where('fecha', '<=', $request->fin);
            })
            ->when($request->detalle, function ($q) use ($request) {
                return $q->where('detalle', 'like', '%' . $request->detalle . '%');
            })
            ->with(['producto.categoria', 'producto.empresa', 'inventario.sucursal', 'usuario'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        $kardex = $kardexQuery->get();
        // Log::info('KardexFiltradoExport - Kardex encontrados:', ['count' => $kardex->count()]);

        // Cargar relaciones manualmente para asegurar que estén disponibles
        $kardex->load(['inventario.sucursal', 'producto.categoria', 'producto.empresa', 'usuario']);

        return $kardex;
    }

    public function map($row): array
    {
        // Log para debug del primer registro
        static $firstRow = true;
        if ($firstRow) {
            // Log::info('KardexFiltradoExport - Primer registro MAP:', [
            //     'fecha' => $row->fecha,
            //     'producto' => $row->producto->nombre ?? 'NULL',
            //     'inventario_exists' => isset($row->inventario) ? 'SI' : 'NO',
            //     'bodega_exists' => isset($row->inventario) ? 'SI' : 'NO',
            //     'sucursal_exists' => isset($row->inventario->sucursal) ? 'SI' : 'NO',
            //    'inventario_bodega' => $row->inventario->nombre ?? 'NULL',
            //    'inventario_sucursal' => $row->inventario->sucursal->nombre ?? 'NULL',
            //     'detalle' => $row->detalle,
            //     'referencia' => $row->referencia
            // ]);
            $firstRow = false;
        }

        // Obtener la sucursal directamente desde la relación inventario (que apunta a Bodega)
        $sucursalNombre = '';
        try {
            if (isset($row->inventario) && isset($row->inventario->sucursal)) {
                $sucursalNombre = $row->inventario->sucursal->nombre;
            } else {
                $sucursalNombre = 'SIN SUCURSAL';
            }
        } catch (Exception $e) {
            $sucursalNombre = 'ERROR: ' . $e->getMessage();
        }

        // Obtener el nombre completo del producto (nombre + nombre_variante si aplica)
        $nombreProducto = $row->producto->nombre ?? '';
        if ($row->producto && $row->producto->empresa && $row->producto->empresa->shopify_store_url && $row->producto->nombre_variante) {
            $nombreProducto = $row->producto->nombre . ' ' . $row->producto->nombre_variante;
        }

        return [
            $row->fecha,
            $nombreProducto,
            $sucursalNombre,
            $row->detalle,
            $row->referencia,
            $row->entrada_cantidad ?? 0,
            $row->salida_cantidad ?? 0,
            $row->total_cantidad ?? 0,
            $row->costo_unitario ?? 0,
            $row->total_valor ?? 0,
            $row->usuario->name ?? '',
        ];
    }
}
