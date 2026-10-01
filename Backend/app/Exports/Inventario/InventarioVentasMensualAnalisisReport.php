<?php

namespace App\Exports\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioVentasMensualAnalisisReport
{
    public const MESES_CORTOS = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
        5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
        9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    private Empresa $empresa;

    private int $anio;

    /** @var array<int, Carbon> */
    private array $months = [];

    private Carbon $inicio;

    private Carbon $fin;

    private string $agruparPor;

    private string $mostrarDatos;

    private bool $todosProductos;

    private string $clienteLayout;

    /** @var array<string, mixed> */
    private array $filters;

    /** @var array<int, Producto> */
    private array $productosById = [];

    /** @var array<int, object{id: int, nombre: string}> */
    private array $categoriasById = [];

    /** @var array<int, float> */
    private array $costoRetaceoPorProducto = [];

    /** @var array<string, array<string, array{qty: float, descuento: float, valor: float, costo: float, kqty: float}>> */
    private array $monthlyByKey = [];

    /** @var array<string, array{descuento: float, valor: float, costo: float, kqty: float}> */
    private array $totalsByKey = [];

    /** @var array<string, array{id_cliente?: int, cliente?: string, id_vendedor?: int, vendedor?: string}> */
    private array $metaByKey = [];

    /** @var array<int, string> */
    private array $clienteNombreById = [];

    /** @var array<int, string> */
    private array $vendedorNombreById = [];

    public function __construct(Empresa $empresa, Request $request)
    {
        $this->empresa = $empresa;
        $this->parseRequest($request);
    }

    /**
     * @return array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>
     */
    public function buildSheets(): array
    {
        $this->loadCatalog();
        $this->loadCostoRetaceo();
        $this->loadAggregates();
        $this->hydrateMetaLabels();

        $rows = $this->buildRows();

        if ($this->agruparPor === 'cliente' && $this->clienteLayout === 'separadas') {
            return $this->splitRowsByCliente($rows);
        }

        return [[
            'title' => 'Reporte',
            'headings' => $this->headings(),
            'rows' => $rows,
        ]];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float|string, 4: float|string}
     */
    public static function metricasDesdeKardex(
        float $salidaValor,
        float $descuento,
        float $salidaCantidad,
        float $costoSalida,
        ?float $costoRetaceo
    ): array {
        $valor = round($salidaValor - $descuento, 2);
        $costo = round($costoSalida, 2);
        $utilidad = round($valor - $costo, 2);
        $precioPromedio = $salidaCantidad > 0 ? round($valor / $salidaCantidad, 4) : '';
        $costoUnidad = $costoRetaceo === null ? '' : round($costoRetaceo, 4);

        return [$valor, $costo, $utilidad, $precioPromedio, $costoUnidad];
    }

    private function parseRequest(Request $request): void
    {
        $today = Carbon::today();
        if ($request->filled('anio')) {
            $this->anio = (int) $request->input('anio');
        } elseif ($request->filled('fecha')) {
            $this->anio = (int) Carbon::parse($request->input('fecha'))->year;
        } else {
            $this->anio = (int) $today->year;
        }

        $this->months = [];
        for ($month = 1; $month <= 12; $month++) {
            $this->months[] = Carbon::createFromDate($this->anio, $month, 1)->startOfDay();
        }

        $this->inicio = Carbon::createFromDate($this->anio, 1, 1)->startOfDay();
        if ($this->anio === (int) $today->year) {
            $this->fin = $today->copy()->endOfDay();
        } else {
            $this->fin = Carbon::createFromDate($this->anio, 12, 31)->endOfDay();
        }

        $this->agruparPor = (string) $request->input('agrupar_por', 'producto');
        $this->mostrarDatos = (string) $request->input('mostrar_datos', 'unidades');
        $this->todosProductos = filter_var($request->input('todos_productos', true), FILTER_VALIDATE_BOOLEAN);
        $this->clienteLayout = (string) $request->input('cliente_layout', 'unica');

        $this->filters = [
            'id_vendedor' => $request->input('id_vendedor') ? (int) $request->input('id_vendedor') : null,
            'id_cliente' => $request->input('id_cliente') ? (int) $request->input('id_cliente') : null,
            'id_categoria' => $request->input('id_categoria') ? (int) $request->input('id_categoria') : null,
            'id_proveedor' => $request->input('id_proveedor') ? (int) $request->input('id_proveedor') : null,
            'codigo' => trim((string) $request->input('codigo', '')),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $headings = ['DIVISION', 'NOMBRE'];
        foreach ($this->months as $m) {
            $headings[] = self::MESES_CORTOS[(int) $m->month] ?? $m->format('M');
        }
        $headings = array_merge($headings, [
            'Vendidos',
            'valor kardex',
            'costo',
            'utilidad kardex',
            'Inventario',
            'valor esperado',
            'costo2',
            'utilidad esperada',
            'Categoría',
            'Provee',
            'VENTA PROMEDIO',
            'MES INV',
            'precio venta promedio',
            'costo por unidad',
        ]);

        if ($this->agruparPor === 'cliente') {
            $headings[] = 'Cliente';
        }
        if ($this->agruparPor === 'vendedor') {
            $headings[] = 'Vendedor';
        }

        return $headings;
    }

    private function loadCatalog(): void
    {
        $query = Producto::query()
            ->with([
                'categoria:id,nombre',
                'proveedores' => static function ($q) {
                    $q->with('proveedor:id,nombre,apellido,tipo,nombre_empresa,nombre_comercial')
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
            ->where('enable', true);

        if ($this->filters['id_categoria']) {
            $query->where('id_categoria', $this->filters['id_categoria']);
        }
        if ($this->filters['codigo'] !== '') {
            $query->where('codigo', 'like', '%' . $this->filters['codigo'] . '%');
        }
        if ($this->filters['id_proveedor']) {
            $idProveedor = $this->filters['id_proveedor'];
            $query->whereHas('proveedores', static function ($q) use ($idProveedor) {
                $q->where('id_proveedor', $idProveedor);
            });
        }

        foreach ($query->get() as $producto) {
            $this->productosById[(int) $producto->id] = $producto;
            if ($producto->categoria) {
                $this->categoriasById[(int) $producto->categoria->id] = $producto->categoria;
            }
        }
    }

    private function loadCostoRetaceo(): void
    {
        $rows = DB::table('retaceo_distribucion')
            ->join('retaceos', 'retaceos.id', '=', 'retaceo_distribucion.id_retaceo')
            ->where('retaceos.id_empresa', $this->empresa->id)
            ->where('retaceos.estado', 'Aplicado')
            ->where('retaceo_distribucion.cantidad', '>', 0)
            ->select([
                'retaceo_distribucion.id_producto',
                DB::raw('SUM(retaceo_distribucion.costo_retaceado * retaceo_distribucion.cantidad) / SUM(retaceo_distribucion.cantidad) as costo_unitario'),
            ])
            ->groupBy('retaceo_distribucion.id_producto')
            ->get();

        foreach ($rows as $row) {
            $this->costoRetaceoPorProducto[(int) $row->id_producto] = (float) $row->costo_unitario;
        }
    }

    private function loadAggregates(): void
    {
        if ($this->productosById === []) {
            return;
        }
        $this->mergeMonthlyRows($this->fetchDetalleMonthly(), 'detalle');
        $this->mergeMonthlyRows($this->fetchKardexMonthly(), 'kardex');
        $this->rollupTotalsFromMonthly();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchDetalleMonthly()
    {
        $ymExpr = "DATE_FORMAT(ventas.fecha, '%Y-%m')";
        $group = $this->detalleGroupColumns();

        return $this->baseDetalleQuery()
            ->select(array_merge($group['select'], [
                DB::raw("{$ymExpr} as ym"),
                DB::raw('SUM(detalles_venta.cantidad) as qty'),
                DB::raw('SUM(detalles_venta.descuento) as descuento'),
            ]))
            ->groupBy(array_merge($group['groupBy'], [DB::raw($ymExpr)]))
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchKardexMonthly()
    {
        $ymExpr = "DATE_FORMAT(kardexs.fecha, '%Y-%m')";
        $group = $this->kardexGroupColumns();

        return $this->baseKardexQuery()
            ->select(array_merge($group['select'], [
                DB::raw("{$ymExpr} as ym"),
                DB::raw('SUM(kardexs.salida_cantidad) as kqty'),
                DB::raw('SUM(kardexs.salida_valor) as valor'),
                DB::raw('SUM(kardexs.salida_cantidad * kardexs.costo_unitario) as costo'),
            ]))
            ->groupBy(array_merge($group['groupBy'], [DB::raw($ymExpr)]))
            ->get();
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows
     */
    private function mergeMonthlyRows($rows, string $source): void
    {
        foreach ($rows as $row) {
            $key = $this->makeKeyFromRow($row);
            if ($key === null) {
                continue;
            }
            $ym = (string) $row->ym;
            if (!isset($this->monthlyByKey[$key][$ym])) {
                $this->monthlyByKey[$key][$ym] = [
                    'qty' => 0.0,
                    'descuento' => 0.0,
                    'valor' => 0.0,
                    'costo' => 0.0,
                    'kqty' => 0.0,
                ];
            }
            if ($source === 'detalle') {
                $this->monthlyByKey[$key][$ym]['qty'] += (float) $row->qty;
                $this->monthlyByKey[$key][$ym]['descuento'] += (float) $row->descuento;
                $this->storeMetaFromRow($key, $row);
            } else {
                $this->monthlyByKey[$key][$ym]['valor'] += (float) $row->valor;
                $this->monthlyByKey[$key][$ym]['costo'] += (float) $row->costo;
                $this->monthlyByKey[$key][$ym]['kqty'] += (float) $row->kqty;
                $this->storeMetaFromRow($key, $row);
            }
        }
    }

    private function rollupTotalsFromMonthly(): void
    {
        foreach ($this->monthlyByKey as $key => $months) {
            $tot = ['descuento' => 0.0, 'valor' => 0.0, 'costo' => 0.0, 'kqty' => 0.0];
            foreach ($months as $data) {
                $tot['descuento'] += $data['descuento'];
                $tot['valor'] += $data['valor'];
                $tot['costo'] += $data['costo'];
                $tot['kqty'] += $data['kqty'];
            }
            $this->totalsByKey[$key] = $tot;
        }
    }

    private function storeMetaFromRow(string $key, object $row): void
    {
        if ($this->agruparPor === 'cliente' && isset($row->id_cliente)) {
            $this->metaByKey[$key]['id_cliente'] = (int) $row->id_cliente;
        }
        if ($this->agruparPor === 'vendedor' && isset($row->id_vendedor)) {
            $this->metaByKey[$key]['id_vendedor'] = (int) $row->id_vendedor;
        }
    }

    private function hydrateMetaLabels(): void
    {
        $clienteIds = [];
        $vendedorIds = [];
        foreach ($this->metaByKey as $meta) {
            if (!empty($meta['id_cliente'])) {
                $clienteIds[] = (int) $meta['id_cliente'];
            }
            if (!empty($meta['id_vendedor'])) {
                $vendedorIds[] = (int) $meta['id_vendedor'];
            }
        }

        if ($clienteIds !== []) {
            foreach (Cliente::query()->whereIn('id', array_unique($clienteIds))->get() as $cliente) {
                $this->clienteNombreById[(int) $cliente->id] = (string) $cliente->nombre_completo;
            }
        }
        if ($vendedorIds !== []) {
            foreach (User::query()->whereIn('id', array_unique($vendedorIds))->get(['id', 'name']) as $user) {
                $this->vendedorNombreById[(int) $user->id] = (string) $user->name;
            }
        }

        foreach ($this->metaByKey as $key => $meta) {
            if (!empty($meta['id_cliente'])) {
                $this->metaByKey[$key]['cliente'] = $this->clienteNombreById[$meta['id_cliente']] ?? ('Cliente #' . $meta['id_cliente']);
            }
            if (!empty($meta['id_vendedor'])) {
                $this->metaByKey[$key]['vendedor'] = $this->vendedorNombreById[$meta['id_vendedor']] ?? ('Vendedor #' . $meta['id_vendedor']);
            }
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildRows(): array
    {
        $keys = array_keys($this->totalsByKey);
        if ($this->agruparPor === 'producto' && $this->todosProductos) {
            foreach (array_keys($this->productosById) as $idProducto) {
                $keys[] = 'p:' . $idProducto;
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            $row = $this->buildRowForKey($key);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function buildRowForKey(string $key): ?array
    {
        if (str_starts_with($key, 'cat:')) {
            return $this->buildRowCategoria($key);
        }

        $idProducto = $this->productIdFromKey($key);
        if ($idProducto === null || !isset($this->productosById[$idProducto])) {
            return null;
        }

        $producto = $this->productosById[$idProducto];
        $monthCells = [];
        $totalVendido = 0.0;
        $monthsConVenta = 0;

        foreach ($this->months as $m) {
            if ($m->gt($this->fin)) {
                $monthCells[] = 0;
                continue;
            }
            $ym = $m->format('Y-m');
            $data = $this->monthlyByKey[$key][$ym] ?? null;
            $cell = $this->monthCellValue($data);
            $monthCells[] = $cell;
            if ($this->mostrarDatos === 'unidades') {
                $qty = (int) round($data['qty'] ?? 0);
                $totalVendido += $qty;
                if ($qty > 0) {
                    $monthsConVenta++;
                }
            } else {
                $val = (float) ($data ? ($data['valor'] - $data['descuento']) : 0);
                $totalVendido += $val;
                if ($val > 0) {
                    $monthsConVenta++;
                }
            }
        }
        $totalVendidoOut = $this->mostrarDatos === 'unidades'
            ? (int) round($totalVendido)
            : round($totalVendido, 2);

        if ($this->agruparPor === 'producto' && !$this->todosProductos && $totalVendidoOut <= 0) {
            $tot = $this->totalsByKey[$key] ?? null;
            if (!$tot || ($tot['kqty'] <= 0 && ($tot['valor'] - $tot['descuento']) <= 0)) {
                return null;
            }
        }

        $stock = $producto->inventarios ? (int) round($producto->inventarios->sum('stock')) : 0;
        $unitCost = $this->unitCostoProducto($producto);
        $precioUnit = (float) ($producto->precio ?? 0);
        $valorInventario = round($precioUnit * $stock, 2);
        $costoInventario = round($unitCost * $stock, 2);
        $utilidadInv = round($valorInventario - $costoInventario, 2);

        $ventaPromedio = $monthsConVenta > 0
            ? ($this->mostrarDatos === 'unidades'
                ? (int) round($totalVendido / $monthsConVenta)
                : round($totalVendido / $monthsConVenta, 2))
            : 0;
        $mesesInv = $ventaPromedio > 0 ? round($stock / (float) $ventaPromedio, 2) : '';

        $nombre = $this->nombreProducto($producto);
        $categoriaNombre = $producto->categoria ? (string) $producto->categoria->nombre : '';
        $proveedorNombre = $this->nombreProveedorProducto($producto, $key);

        $tot = $this->totalsByKey[$key] ?? ['descuento' => 0, 'valor' => 0, 'costo' => 0, 'kqty' => 0];
        [$valorKardex, $costoKardex, $utilidadKardex, $precioVentaPromedio, $costoPorUnidad] = self::metricasDesdeKardex(
            (float) $tot['valor'],
            (float) $tot['descuento'],
            (float) $tot['kqty'],
            (float) $tot['costo'],
            $this->costoRetaceoPorProducto[$idProducto] ?? null
        );

        $row = [
            (string) ($producto->codigo ?? ''),
            $nombre,
        ];
        foreach ($monthCells as $cell) {
            $row[] = $cell;
        }
        $row = array_merge($row, [
            $totalVendidoOut,
            $valorKardex,
            $costoKardex,
            $utilidadKardex,
            $stock,
            $valorInventario,
            $costoInventario,
            $utilidadInv,
            $categoriaNombre,
            $proveedorNombre,
            $ventaPromedio,
            $mesesInv,
            $precioVentaPromedio,
            $costoPorUnidad,
        ]);

        if ($this->agruparPor === 'cliente') {
            $row[] = $this->metaByKey[$key]['cliente'] ?? '';
        }
        if ($this->agruparPor === 'vendedor') {
            $row[] = $this->metaByKey[$key]['vendedor'] ?? '';
        }

        return $row;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function buildRowCategoria(string $key): ?array
    {
        $idCategoria = (int) substr($key, 4);
        $cat = $idCategoria > 0 ? ($this->categoriasById[$idCategoria] ?? null) : null;
        $nombreCategoria = $cat ? (string) $cat->nombre : 'Sin categoría';

        $monthCells = [];
        $totalVendido = 0.0;
        $monthsConVenta = 0;
        foreach ($this->months as $m) {
            if ($m->gt($this->fin)) {
                $monthCells[] = 0;
                continue;
            }
            $ym = $m->format('Y-m');
            $data = $this->monthlyByKey[$key][$ym] ?? null;
            $cell = $this->monthCellValue($data);
            $monthCells[] = $cell;
            if ($this->mostrarDatos === 'unidades') {
                $qty = (int) round($data['qty'] ?? 0);
                $totalVendido += $qty;
                if ($qty > 0) {
                    $monthsConVenta++;
                }
            } else {
                $val = (float) ($data ? ($data['valor'] - $data['descuento']) : 0);
                $totalVendido += $val;
                if ($val > 0) {
                    $monthsConVenta++;
                }
            }
        }
        $totalVendidoOut = $this->mostrarDatos === 'unidades'
            ? (int) round($totalVendido)
            : round($totalVendido, 2);

        if ($totalVendidoOut <= 0) {
            return null;
        }

        $stock = 0;
        $valorInventario = 0.0;
        $costoInventario = 0.0;
        foreach ($this->productosById as $producto) {
            $catProducto = (int) ($producto->id_categoria ?? 0);
            if ($catProducto !== $idCategoria) {
                continue;
            }
            $s = $producto->inventarios ? (int) round($producto->inventarios->sum('stock')) : 0;
            $stock += $s;
            $precioUnit = (float) ($producto->precio ?? 0);
            $unitCost = $this->unitCostoProducto($producto);
            $valorInventario += $precioUnit * $s;
            $costoInventario += $unitCost * $s;
        }
        $valorInventario = round($valorInventario, 2);
        $costoInventario = round($costoInventario, 2);
        $utilidadInv = round($valorInventario - $costoInventario, 2);
        $ventaPromedio = $monthsConVenta > 0
            ? ($this->mostrarDatos === 'unidades'
                ? (int) round($totalVendido / $monthsConVenta)
                : round($totalVendido / $monthsConVenta, 2))
            : 0;
        $mesesInv = $ventaPromedio > 0 ? round($stock / (float) $ventaPromedio, 2) : '';

        $tot = $this->totalsByKey[$key] ?? ['descuento' => 0, 'valor' => 0, 'costo' => 0, 'kqty' => 0];
        [$valorKardex, $costoKardex, $utilidadKardex, $precioVentaPromedio, $costoPorUnidad] = self::metricasDesdeKardex(
            (float) $tot['valor'],
            (float) $tot['descuento'],
            (float) $tot['kqty'],
            (float) $tot['costo'],
            null
        );

        $row = ['', $nombreCategoria];
        foreach ($monthCells as $cell) {
            $row[] = $cell;
        }

        return array_merge($row, [
            $totalVendidoOut,
            $valorKardex,
            $costoKardex,
            $utilidadKardex,
            $stock,
            $valorInventario,
            $costoInventario,
            $utilidadInv,
            $nombreCategoria,
            '',
            $ventaPromedio,
            $mesesInv,
            $precioVentaPromedio,
            $costoPorUnidad,
        ]);
    }

    /**
     * @param array{qty?: float, descuento?: float, valor?: float, costo?: float, kqty?: float}|null $data
     */
    private function monthCellValue(?array $data): float|int
    {
        if (!$data) {
            return 0;
        }
        if ($this->mostrarDatos === 'unidades') {
            return (int) round($data['qty'] ?? 0);
        }

        return round((float) ($data['valor'] ?? 0) - (float) ($data['descuento'] ?? 0), 2);
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>
     */
    private function splitRowsByCliente(array $rows): array
    {
        $headings = $this->headings();
        $clienteCol = array_search('Cliente', $headings, true);
        $byClient = [];
        foreach ($rows as $row) {
            $name = $clienteCol !== false ? (string) ($row[$clienteCol] ?? 'Sin cliente') : 'Sin cliente';
            $byClient[$name][] = $row;
        }
        ksort($byClient, SORT_NATURAL | SORT_FLAG_CASE);

        $sheets = [];
        $i = 0;
        foreach ($byClient as $name => $clientRows) {
            $sheets[] = [
                'title' => $this->sanitizeSheetTitle($name, ++$i),
                'headings' => $headings,
                'rows' => $clientRows,
            ];
        }

        if ($sheets === []) {
            return [[
                'title' => 'Reporte',
                'headings' => $headings,
                'rows' => [],
            ]];
        }

        return $sheets;
    }

    private function sanitizeSheetTitle(string $name, int $index): string
    {
        $title = preg_replace('/[\[\]\*\?\:\/\\\\]/', '', $name) ?? '';
        $title = trim($title);
        if ($title === '') {
            $title = 'Cliente ' . $index;
        }

        return mb_substr($title, 0, 31);
    }

    private function baseDetalleQuery()
    {
        $q = DB::table('detalles_venta')
            ->join('ventas', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->join('productos', 'productos.id', '=', 'detalles_venta.id_producto')
            ->where('ventas.id_empresa', $this->empresa->id)
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->whereDate('ventas.fecha', '>=', $this->inicio->toDateString())
            ->whereDate('ventas.fecha', '<=', $this->fin->toDateString())
            ->whereIn('productos.id', array_keys($this->productosById));

        $this->applySaleFilters($q);

        if ($this->agruparPor === 'proveedor' || $this->filters['id_proveedor']) {
            $q->leftJoin(DB::raw('(SELECT id_producto, MIN(id_proveedor) AS id_proveedor FROM producto_proveedores GROUP BY id_producto) AS pp'), 'pp.id_producto', '=', 'productos.id');
        }

        return $q;
    }

    private function baseKardexQuery()
    {
        $q = DB::table('kardexs')
            ->join('ventas', 'ventas.id', '=', 'kardexs.referencia')
            ->join('productos', 'productos.id', '=', 'kardexs.id_producto')
            ->where('ventas.id_empresa', $this->empresa->id)
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->whereDate('kardexs.fecha', '>=', $this->inicio->toDateString())
            ->whereDate('kardexs.fecha', '<=', $this->fin->toDateString())
            ->where('kardexs.salida_cantidad', '>', 0)
            ->where(function ($query) {
                $query->where('kardexs.detalle', 'Venta')
                    ->orWhere('kardexs.detalle', 'Venta a consigna')
                    ->orWhere('kardexs.detalle', 'like', 'Venta %');
            })
            ->where('kardexs.detalle', 'not like', 'Venta Anulada%')
            ->whereIn('productos.id', array_keys($this->productosById));

        $this->applySaleFilters($q);

        if ($this->agruparPor === 'proveedor' || $this->filters['id_proveedor']) {
            $q->leftJoin(DB::raw('(SELECT id_producto, MIN(id_proveedor) AS id_proveedor FROM producto_proveedores GROUP BY id_producto) AS pp'), 'pp.id_producto', '=', 'productos.id');
        }

        return $q;
    }

    private function applySaleFilters($q): void
    {
        if ($this->filters['id_cliente']) {
            $q->where('ventas.id_cliente', $this->filters['id_cliente']);
        }
        if ($this->filters['id_vendedor']) {
            $q->where('ventas.id_vendedor', $this->filters['id_vendedor']);
        }
    }

    /**
     * @return array{select: array<int, \Illuminate\Contracts\Database\Query\Expression|string>, groupBy: array<int, \Illuminate\Contracts\Database\Query\Expression|string>}
     */
    private function detalleGroupColumns(): array
    {
        return match ($this->agruparPor) {
            'cliente' => [
                'select' => ['detalles_venta.id_producto', 'ventas.id_cliente'],
                'groupBy' => ['detalles_venta.id_producto', 'ventas.id_cliente'],
            ],
            'vendedor' => [
                'select' => ['detalles_venta.id_producto', DB::raw('COALESCE(ventas.id_vendedor, 0) AS id_vendedor')],
                'groupBy' => ['detalles_venta.id_producto', DB::raw('COALESCE(ventas.id_vendedor, 0)')],
            ],
            'proveedor' => [
                'select' => ['detalles_venta.id_producto', DB::raw('COALESCE(pp.id_proveedor, 0) AS id_proveedor')],
                'groupBy' => ['detalles_venta.id_producto', DB::raw('COALESCE(pp.id_proveedor, 0)')],
            ],
            'categoria' => [
                'select' => [DB::raw('COALESCE(productos.id_categoria, 0) AS id_categoria')],
                'groupBy' => [DB::raw('COALESCE(productos.id_categoria, 0)')],
            ],
            default => [
                'select' => ['detalles_venta.id_producto'],
                'groupBy' => ['detalles_venta.id_producto'],
            ],
        };
    }

    /**
     * @return array{select: array<int, \Illuminate\Contracts\Database\Query\Expression|string>, groupBy: array<int, \Illuminate\Contracts\Database\Query\Expression|string>}
     */
    private function kardexGroupColumns(): array
    {
        return match ($this->agruparPor) {
            'cliente' => [
                'select' => ['kardexs.id_producto', 'ventas.id_cliente'],
                'groupBy' => ['kardexs.id_producto', 'ventas.id_cliente'],
            ],
            'vendedor' => [
                'select' => ['kardexs.id_producto', DB::raw('COALESCE(ventas.id_vendedor, 0) AS id_vendedor')],
                'groupBy' => ['kardexs.id_producto', DB::raw('COALESCE(ventas.id_vendedor, 0)')],
            ],
            'proveedor' => [
                'select' => ['kardexs.id_producto', DB::raw('COALESCE(pp.id_proveedor, 0) AS id_proveedor')],
                'groupBy' => ['kardexs.id_producto', DB::raw('COALESCE(pp.id_proveedor, 0)')],
            ],
            'categoria' => [
                'select' => [DB::raw('COALESCE(productos.id_categoria, 0) AS id_categoria')],
                'groupBy' => [DB::raw('COALESCE(productos.id_categoria, 0)')],
            ],
            default => [
                'select' => ['kardexs.id_producto'],
                'groupBy' => ['kardexs.id_producto'],
            ],
        };
    }

    private function makeKeyFromRow(object $row): ?string
    {
        return match ($this->agruparPor) {
            'cliente' => 'p:' . (int) $row->id_producto . '|c:' . (int) $row->id_cliente,
            'vendedor' => 'p:' . (int) $row->id_producto . '|v:' . (int) $row->id_vendedor,
            'proveedor' => 'p:' . (int) $row->id_producto . '|pr:' . (int) $row->id_proveedor,
            'categoria' => 'cat:' . (int) $row->id_categoria,
            default => 'p:' . (int) $row->id_producto,
        };
    }

    private function productIdFromKey(string $key): ?int
    {
        if (!str_starts_with($key, 'p:')) {
            return null;
        }
        $parts = explode('|', substr($key, 2), 2);

        return (int) $parts[0];
    }

    private function nombreProducto(Producto $producto): string
    {
        $nombre = $producto->nombre ?? '';
        if ($producto->empresa && $producto->empresa->shopify_store_url && $producto->nombre_variante) {
            $nombre = $nombre . ' ' . $producto->nombre_variante;
        }

        return $nombre;
    }

    private function nombreProveedorProducto(Producto $producto, string $key): string
    {
        if ($this->agruparPor === 'proveedor' && preg_match('/\|pr:(\d+)/', $key, $m)) {
            $idProveedor = (int) $m[1];
            foreach ($producto->proveedores as $rel) {
                if ((int) $rel->id_proveedor === $idProveedor) {
                    return (string) $rel->nombre_proveedor;
                }
            }
        }
        $firstRel = $producto->proveedores->first();

        return $firstRel ? (string) $firstRel->nombre_proveedor : '';
    }

    private function unitCostoProducto(Producto $producto): float
    {
        $vi = strtolower((string) ($this->empresa->valor_inventario ?? ''));
        if ($vi === 'promedio' && (float) ($producto->costo_promedio ?? 0) > 0) {
            return (float) $producto->costo_promedio;
        }

        return (float) ($producto->costo ?? 0);
    }
}
