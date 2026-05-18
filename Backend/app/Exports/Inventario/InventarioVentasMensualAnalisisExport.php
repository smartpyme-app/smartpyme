<?php

namespace App\Exports\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventarioVentasMensualAnalisisExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    private const MESES_CORTOS = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
        5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
        9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    /** @var array<int, Carbon> */
    private array $months = [];

    /** @var array<string, float|int> cantidades por producto y mes ({id}_{Y-m}) */
    private array $ventasPorProductoYm = [];

    private Empresa $empresa;

    public function prepare(Request $request, Empresa $empresa): void
    {
        $this->empresa = $empresa;
        $anchor = Carbon::parse($request->input('fecha', Carbon::today()->toDateString()))->startOfDay();

        $year = (int) $anchor->year;
        $lastMonth = (int) $anchor->month;
        $this->months = [];
        for ($month = 1; $month <= $lastMonth; $month++) {
            $this->months[] = Carbon::createFromDate($year, $month, 1)->startOfDay();
        }

        if (count($this->months) === 0) {
            return;
        }

        $inicio = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $fin = $anchor->copy()->endOfDay();

        $rows = DB::table('detalles_venta')
            ->join('ventas', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->where('ventas.id_empresa', $empresa->id)
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->whereDate('ventas.fecha', '>=', $inicio->toDateString())
            ->whereDate('ventas.fecha', '<=', $fin->toDateString())
            ->select([
                'detalles_venta.id_producto',
                DB::raw("DATE_FORMAT(ventas.fecha, '%Y-%m') as ym"),
                DB::raw('SUM(detalles_venta.cantidad) as qty'),
            ])
            ->groupBy('detalles_venta.id_producto', DB::raw("DATE_FORMAT(ventas.fecha, '%Y-%m')"))
            ->get();

        foreach ($rows as $row) {
            $key = ((int) $row->id_producto) . '_' . $row->ym;
            $this->ventasPorProductoYm[$key] = (float) $row->qty;
        }
    }

    public function query()
    {
        return Producto::query()
            ->with([
                'categoria:id,nombre',
                'proveedores' => static function ($q) {
                    $q->with('proveedor:id,nombre,apellido,tipo,nombre_empresa')
                        ->orderBy('producto_proveedores.id');
                },
                'empresa:id,shopify_store_url,valor_inventario',
                'inventarios' => static function ($q) {
                    $q->whereHas('bodega', static function ($bq) {
                        $bq->where('activo', 1);
                    });
                },
            ])
            ->where('id_empresa', $this->empresa->id)
            ->whereIn('tipo', ['Producto', 'Compuesto'])
            ->where('enable', true)
            ->orderBy('id');
    }

    public function headings(): array
    {
        $headings = ['DIVISION', 'NOMBRE'];
        foreach ($this->months as $m) {
            $headings[] = self::MESES_CORTOS[(int) $m->month] ?? $m->format('M');
        }
        array_push($headings, 'Vendidos', 'Inventario', 'VALOR', 'COSTO', 'UTILIDAD', 'Categoría', 'Provee', 'VENTA PROMEDIO', 'MES INV');

        return $headings;
    }

    /**
     * @param Producto $producto
     */
    public function map($producto): array
    {
        $monthQtys = [];
        $monthsConVenta = 0;
        $totalVendido = 0;

        foreach ($this->months as $m) {
            $ym = $m->format('Y-m');
            $qty = (int) round($this->ventasPorProductoYm[$producto->id . '_' . $ym] ?? 0);
            $monthQtys[] = $qty;
            $totalVendido += $qty;
            if ($qty > 0) {
                $monthsConVenta++;
            }
        }

        $stock = $producto->inventarios ? (int) round($producto->inventarios->sum('stock')) : 0;
        $unitCost = $this->unitCostoProducto($producto);
        $precioUnit = (float) ($producto->precio ?? 0);

        $valorInventario = round($precioUnit * $stock, 2);
        $costoInventario = round($unitCost * $stock, 2);
        $utilidad = round($valorInventario - $costoInventario, 2);

        $ventaPromedio = $monthsConVenta > 0 ? (int) round($totalVendido / $monthsConVenta) : 0;
        $mesesInv = '';
        if ($ventaPromedio > 0) {
            $mesesInv = round($stock / $ventaPromedio, 2);
        }

        $nombre = $producto->nombre ?? '';
        if ($producto->empresa && $producto->empresa->shopify_store_url && $producto->nombre_variante) {
            $nombre = $nombre . ' ' . $producto->nombre_variante;
        }

        $categoriaNombre = $producto->categoria ? (string) $producto->categoria->nombre : '';
        $proveedorNombre = '';
        $firstRel = $producto->proveedores->first();
        if ($firstRel) {
            $proveedorNombre = (string) $firstRel->nombre_proveedor;
        }

        $row = [
            (string) ($producto->codigo ?? ''),
            $nombre,
        ];
        foreach ($monthQtys as $q) {
            $row[] = $q;
        }
        $row[] = $totalVendido;
        $row[] = $stock;
        $row[] = $valorInventario;
        $row[] = $costoInventario;
        $row[] = $utilidad;
        $row[] = $categoriaNombre;
        $row[] = $proveedorNombre;
        $row[] = $ventaPromedio;
        $row[] = $mesesInv;

        return $row;
    }

    private function unitCostoProducto(Producto $producto): float
    {
        $vi = strtolower((string) ($this->empresa->valor_inventario ?? ''));
        if ($vi === 'promedio' && (float) ($producto->costo_promedio ?? 0) > 0) {
            return (float) $producto->costo_promedio;
        }

        return (float) ($producto->costo ?? 0);
    }

    public function styles(Worksheet $sheet)
    {
        $ncol = max(2 + count($this->months) + 9, 1);
        $lastColLetter = Coordinate::stringFromColumnIndex($ncol);
        $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'F2F2F2'],
            ],
        ]);

        $lastRow = $sheet->getHighestRow();
        if ($lastRow > 50000) {
            $lastRow = 50000;
        }
        $sheet->getStyle('A1:' . $lastColLetter . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        return [];
    }
}
