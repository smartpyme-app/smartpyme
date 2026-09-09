<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Bodega;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventarioAFechaExport implements FromCollection, WithHeadings, WithMapping
{
    private $request;
    private $bodegas;
    private $kardexData = [];

    public function filter(Request $request)
    {
        $this->request = $request;

        // Carga las bodegas de la empresa
        $this->bodegas = Bodega::where('id_empresa', $this->request->id_empresa)
            ->when($request->id_bodega, function ($q) use ($request) {
                $q->where('id', $request->id_bodega);
            })
            ->where('activo', true)->get();

        $bodegaIds = $this->bodegas->pluck('id')->all();
        $this->kardexData = self::indexarUltimoKardexPorFechaId(
            $this->filasUltimoKardexAFecha($bodegaIds, $this->request->fecha)
        );
    }

    /** Última fila por (bodega, producto): fecha DESC, id DESC. */
    public static function indexarUltimoKardexPorFechaId(iterable $rows): array
    {
        $best = [];
        foreach ($rows as $row) {
            $bodegaId = (int) $row->id_inventario;
            $productoId = (int) $row->id_producto;
            $prev = $best[$bodegaId][$productoId] ?? null;
            if ($prev === null || self::kardexEsMasReciente($row, $prev)) {
                $best[$bodegaId][$productoId] = $row;
            }
        }

        $snapshot = [];
        foreach ($best as $bodegaId => $porProducto) {
            foreach ($porProducto as $productoId => $row) {
                $snapshot[$bodegaId][$productoId] = $row->total_cantidad;
            }
        }

        return $snapshot;
    }

    private static function kardexEsMasReciente(object $candidato, object $actual): bool
    {
        $fechaCandidato = (string) ($candidato->fecha ?? '');
        $fechaActual = (string) ($actual->fecha ?? '');
        if ($fechaCandidato !== $fechaActual) {
            return $fechaCandidato > $fechaActual;
        }

        return (int) ($candidato->id ?? 0) > (int) ($actual->id ?? 0);
    }

    private function filasUltimoKardexAFecha(array $bodegaIds, string $fecha): \Illuminate\Support\Collection
    {
        if ($bodegaIds === []) {
            return collect();
        }

        $fechaFin = Carbon::parse($fecha)->endOfDay()->format('Y-m-d H:i:s');
        // ponytail: window still scans kardex in MySQL. If this times out, index (id_inventario, id_producto, fecha, id) or go async (opción B).
        $ranked = DB::table('kardexs')
            ->select('id_inventario', 'id_producto', 'total_cantidad')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY id_inventario, id_producto ORDER BY fecha DESC, id DESC) as rn')
            ->whereIn('id_inventario', $bodegaIds)
            ->where('fecha', '<=', $fechaFin);

        return DB::query()
            ->fromSub($ranked, 'kardex_ranked')
            ->where('rn', 1)
            ->get(['id_inventario', 'id_producto', 'total_cantidad']);
    }

    public function headings(): array
    {
        $headings = ['Nombre', 'Categoría', 'Codigo',  'Costo', 'Stock', 'Tiene fotografía'];

        foreach ($this->bodegas as $sucursal) {
            $headings[] = $sucursal->nombre;
        }

        return $headings;
    }

    public static function tieneFotografiaParaExport($producto): string
    {
        $count = (int) ($producto->imagenes_count ?? 0);

        return $count > 0 ? 'Sí' : 'No';
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
            self::tieneFotografiaParaExport($producto),
        ];

        // Agrupar inventarios por bodegas
        $inventarios = $producto->inventarios ? $producto->inventarios->keyBy('id_bodega') : collect();

        foreach ($this->bodegas as $bodega) {
            $stock = 0;

            // Busca el inventario de la bodega
            $inventario = $inventarios->get($bodega->id);

            if ($inventario) {
                $stock = $this->kardexData[(int) $bodega->id][(int) $producto->id] ?? '0';
            }

            $fields[] = $stock;
        }


        return $fields;
    }

    public function collection()
    {
        $request = $this->request;

        // Usar cursor() en lugar de get() para reducir uso de memoria en empresas con muchos productos
        return Producto::withCount('imagenes')
            ->with(['inventarios' => function ($q) use ($request) {
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
