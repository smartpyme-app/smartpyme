<?php

namespace App\Imports;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Bodega;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrasladosImport implements ToModel, WithHeadingRow, WithStartRow
{
    use Importable;

    protected $concepto;
    protected $trasladados = 0;
    protected $errores = [];
    protected $dryRun = false;
    protected $filasVistaPrevia = [];
    protected $filaContador = 0;
    /** Bodegas del formulario cuando el Excel no trae #ID_BODEGA_* (plantilla solo con columnas visibles). */
    protected $defaultIdBodegaOrigen;
    protected $defaultIdBodegaDestino;

    public function __construct($concepto, bool $dryRun = false, $defaultIdBodegaOrigen = null, $defaultIdBodegaDestino = null)
    {
        $this->concepto = $concepto;
        $this->dryRun = $dryRun;
        $this->defaultIdBodegaOrigen = ($defaultIdBodegaOrigen !== null && $defaultIdBodegaOrigen !== '') ? (string) $defaultIdBodegaOrigen : null;
        $this->defaultIdBodegaDestino = ($defaultIdBodegaDestino !== null && $defaultIdBodegaDestino !== '') ? (string) $defaultIdBodegaDestino : null;
    }

    /**
     * Obtiene valor por clave exacta o por encabezado normalizado (slug), p. ej. "Código" → codigo, "Cantidad a Trasladar" → cantidad_a_trasladar.
     */
    protected function valorColumna(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row) && $row[$alias] !== null && $row[$alias] !== '') {
                return $this->normalizarValorCelda($row[$alias]);
            }
        }

        $aliasSlugs = [];
        foreach ($aliases as $a) {
            $aliasSlugs[Str::slug(str_replace('#', '', (string) $a), '_')] = true;
        }

        foreach ($row as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $keySlug = Str::slug(str_replace(['#', '.'], '', (string) $key), '_');
            if (isset($aliasSlugs[$keySlug])) {
                return $this->normalizarValorCelda($value);
            }
        }

        return null;
    }

    protected function normalizarValorCelda($value): string
    {
        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    /**
     * Añade claves slug sin «#» para compatibilidad con Excels antiguos y encabezados con #ID_*.
     */
    protected function normalizarClavesFila(array $row): array
    {
        $out = $row;
        foreach ($row as $key => $value) {
            $clean = Str::slug(str_replace(['#', '.'], '', (string) $key), '_');
            if ($clean !== '' && !array_key_exists($clean, $out)) {
                $out[$clean] = $value;
            }
        }

        return $out;
    }

    /**
     * Resuelve id_bodega a partir del nombre mostrado en plantilla (p. ej. "Sucursal 2", "SUCURSAL 1").
     */
    protected function resolverIdBodegaPorNombre(?string $nombre): ?string
    {
        if ($nombre === null || trim($nombre) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($nombre), 'UTF-8');
        $bodegas = Bodega::query()
            ->where('activo', '1')
            ->orderBy('id')
            ->get(['id', 'nombre']);

        foreach ($bodegas as $b) {
            $nom = mb_strtolower(trim((string) $b->nombre), 'UTF-8');
            if ($nom === $needle) {
                return (string) $b->id;
            }
        }

        foreach ($bodegas as $b) {
            $nom = mb_strtolower(trim((string) $b->nombre), 'UTF-8');
            if ($needle !== '' && (mb_strpos($nom, $needle, 0, 'UTF-8') !== false || mb_strpos($needle, $nom, 0, 'UTF-8') !== false)) {
                return (string) $b->id;
            }
        }

        return null;
    }

    public function startRow(): int
    {
        return 2;
    }

    /**
     * Valida la fila sin escribir en BD. Retorna error (mensaje) o null si es trasladable.
     *
     * @return array{error: ?string, id_producto: mixed, nombre_producto: ?string, cantidad: ?float, stock_origen: ?float, id_bodega_origen: mixed, id_bodega_destino: mixed, inventario_origen: ?Inventario, producto: ?Producto}
     */
    protected function validarFilaTraslado(array $row): array
    {
        $row = $this->normalizarClavesFila($row);

        $out = [
            'error' => null,
            'id_producto' => null,
            'nombre_producto' => null,
            'cantidad' => null,
            'stock_origen' => null,
            'id_bodega_origen' => null,
            'id_bodega_destino' => null,
            'inventario_origen' => null,
            'producto' => null,
        ];

        $cantidadStr = $this->valorColumna($row, ['cantidad_a_trasladar', 'cantidad', 'cantidad_a_traslado']);
        $idProductoStr = $this->valorColumna($row, ['id_producto', 'idproducto', 'id_product', '#id_producto']);
        $codigoStr = $this->valorColumna($row, ['codigo', 'código', 'codigo_producto', 'sku', 'code']);

        if (($idProductoStr === null || $idProductoStr === '')
            && ($codigoStr === null || $codigoStr === '')
            && ($cantidadStr === null || $cantidadStr === '' || floatval($cantidadStr) <= 0)) {
            $out['error'] = 'Fila vacía o sin datos.';
            return $out;
        }

        if ($cantidadStr === null || $cantidadStr === '' || floatval($cantidadStr) <= 0) {
            $out['error'] = 'Falta la cantidad a trasladar o no es válida.';
            return $out;
        }

        $cantidadTraslado = floatval($cantidadStr);
        $out['cantidad'] = $cantidadTraslado;

        $producto = null;
        $idProducto = null;

        if ($idProductoStr !== null && $idProductoStr !== '') {
            if (!is_numeric($idProductoStr)) {
                $out['error'] = 'El ID de producto no es numérico.';
                return $out;
            }
            $idProducto = (int) $idProductoStr;
            $producto = Producto::with(['composiciones', 'categoria'])->find($idProducto);
            if (!$producto) {
                $out['error'] = "No se encontró el producto con ID: {$idProducto}.";
                return $out;
            }
        } elseif ($codigoStr !== null && $codigoStr !== '') {
            $producto = Producto::with(['composiciones', 'categoria'])
                ->where(function ($q) use ($codigoStr) {
                    $q->where('codigo', $codigoStr)
                        ->orWhereRaw('TRIM(codigo) = ?', [trim($codigoStr)]);
                })
                ->first();
            if (!$producto) {
                $out['error'] = 'No se encontró producto con código "' . $codigoStr . '".';
                return $out;
            }
            $idProducto = (int) $producto->id;
        } else {
            $out['error'] = 'Falta el ID de producto (columna #ID_PRODUCTO) o el código del producto.';
            return $out;
        }

        $out['id_producto'] = $idProducto;
        $out['nombre_producto'] = $producto->nombre;
        // Datos de UI para vista previa aunque falle stock u otra validación posterior
        $out['img'] = $producto->img;
        $nombreCatDb = $producto->nombre_categoria;
        $nombreCatExcel = $this->valorColumna($row, ['categoria', 'categoría', 'categoria_producto', 'category']);
        $out['nombre_categoria'] = ($nombreCatDb !== null && $nombreCatDb !== '') ? $nombreCatDb : $nombreCatExcel;

        $idBodegaOrigenStr = $this->valorColumna($row, ['id_bodega_origen', 'idbodega_origen']);
        $idBodegaDestinoStr = $this->valorColumna($row, ['id_bodega_destino', 'idbodega_destino']);
        $nombreBodegaOrigen = $this->valorColumna($row, [
            'bodega_origen', 'bodega_de_origen', 'sucursal_origen', 'origen', 'nombre_bodega_origen',
        ]);
        $nombreBodegaDestino = $this->valorColumna($row, [
            'bodega_destino', 'bodega_de_destino', 'sucursal_destino', 'destino', 'nombre_bodega_destino',
        ]);

        $idBodegaOrigen = null;
        if ($idBodegaOrigenStr !== null && $idBodegaOrigenStr !== '' && is_numeric($idBodegaOrigenStr)) {
            $idBodegaOrigen = (string) $idBodegaOrigenStr;
        } elseif ($nombreBodegaOrigen !== null && $nombreBodegaOrigen !== '') {
            $idBodegaOrigen = $this->resolverIdBodegaPorNombre($nombreBodegaOrigen);
            if ($idBodegaOrigen === null) {
                $out['error'] = 'No se encontró una bodega de origen que coincida con "' . $nombreBodegaOrigen . '". Revise el nombre o use la plantilla con #ID_BODEGA_ORIGEN.';
                return $out;
            }
        } else {
            $idBodegaOrigen = $this->defaultIdBodegaOrigen;
        }

        $idBodegaDestino = null;
        if ($idBodegaDestinoStr !== null && $idBodegaDestinoStr !== '' && is_numeric($idBodegaDestinoStr)) {
            $idBodegaDestino = (string) $idBodegaDestinoStr;
        } elseif ($nombreBodegaDestino !== null && $nombreBodegaDestino !== '') {
            $idBodegaDestino = $this->resolverIdBodegaPorNombre($nombreBodegaDestino);
            if ($idBodegaDestino === null) {
                $out['error'] = 'No se encontró una bodega de destino que coincida con "' . $nombreBodegaDestino . '". Revise el nombre o use la plantilla con #ID_BODEGA_DESTINO.';
                return $out;
            }
        } else {
            $idBodegaDestino = $this->defaultIdBodegaDestino;
        }

        $out['id_bodega_origen'] = $idBodegaOrigen;
        $out['id_bodega_destino'] = $idBodegaDestino;

        if ($idBodegaOrigen === null || $idBodegaOrigen === '' || $idBodegaDestino === null || $idBodegaDestino === '') {
            $out['error'] = 'Faltan bodegas de origen y/o destino. Incluya las columnas de nombre en el Excel, selecciónelas en el sistema o use #ID_BODEGA_ORIGEN / #ID_BODEGA_DESTINO.';
            return $out;
        }

        if ((string) $idBodegaOrigen === (string) $idBodegaDestino) {
            $out['error'] = 'La bodega de origen y de destino no pueden ser la misma (origen y destino en el archivo o en el sistema).';
            return $out;
        }

        $inventarioDestinoPrev = Inventario::where('id_producto', $idProducto)
            ->where('id_bodega', $idBodegaDestino)
            ->first();
        $out['stock_destino'] = $inventarioDestinoPrev ? (float) $inventarioDestinoPrev->stock : 0.0;
        $out['id_inventario_destino'] = $inventarioDestinoPrev ? $inventarioDestinoPrev->id : null;

        $inventarioOrigen = Inventario::where('id_producto', $idProducto)
            ->where('id_bodega', $idBodegaOrigen)
            ->first();

        if (!$inventarioOrigen) {
            $out['stock_origen'] = 0.0;
            $out['error'] = "No se encontró inventario en bodega origen para el producto \"{$producto->nombre}\" (ID {$idProducto}).";
            return $out;
        }

        $out['id_inventario_origen'] = $inventarioOrigen->id;
        $out['stock_origen'] = (float) $inventarioOrigen->stock;

        if ($inventarioOrigen->stock < $cantidadTraslado) {
            $out['error'] = 'Stock insuficiente en origen (disponible: ' . $inventarioOrigen->stock . ', solicitado: ' . $cantidadTraslado . ').';
            return $out;
        }
        $out['inventario_origen'] = $inventarioOrigen;
        $out['producto'] = $producto;

        foreach ($producto->composiciones as $composicion) {
            $productoCompuesto = Producto::where('id', $composicion->id_compuesto)->first();

            if (!$productoCompuesto) {
                $out['error'] = "No se encontró el producto compuesto ID {$composicion->id_compuesto}.";
                return $out;
            }

            $cantidadCompuesto = $cantidadTraslado * $composicion->cantidad;

            $inventarioCompuestoOrigen = Inventario::where('id_producto', $composicion->id_compuesto)
                ->where('id_bodega', $idBodegaOrigen)
                ->first();

            if (!$inventarioCompuestoOrigen) {
                $out['error'] = "No hay inventario del componente \"{$productoCompuesto->nombre}\" en bodega origen.";
                return $out;
            }

            if ($inventarioCompuestoOrigen->stock < $cantidadCompuesto) {
                $out['error'] = "Stock insuficiente del componente \"{$productoCompuesto->nombre}\" en origen (necesario: {$cantidadCompuesto}).";
                return $out;
            }
        }

        return $out;
    }

    public function model(array $row)
    {
        Log::info('Fila procesada para traslado:', $row);

        $this->filaContador++;

        try {
            $v = $this->validarFilaTraslado($row);

            if ($this->dryRun) {
                $this->filasVistaPrevia[] = [
                    'fila' => $this->filaContador,
                    'id_producto' => $v['id_producto'],
                    'producto' => $v['nombre_producto'],
                    'cantidad' => $v['cantidad'],
                    'stock_origen' => $v['stock_origen'],
                    'stock_destino' => $v['stock_destino'] ?? null,
                    'id_bodega_origen' => $v['id_bodega_origen'] ?? null,
                    'id_bodega_destino' => $v['id_bodega_destino'] ?? null,
                    'id_inventario_origen' => $v['id_inventario_origen'] ?? null,
                    'id_inventario_destino' => $v['id_inventario_destino'] ?? null,
                    'img' => $v['img'] ?? null,
                    'nombre_categoria' => $v['nombre_categoria'] ?? null,
                    'ok' => $v['error'] === null,
                    'error' => $v['error'],
                ];
                return null;
            }

            if ($v['error'] !== null) {
                $this->errores[] = "Fila {$this->filaContador}: " . $v['error'];
                return null;
            }

            $idProducto = $v['id_producto'];
            $idBodegaOrigen = $v['id_bodega_origen'];
            $idBodegaDestino = $v['id_bodega_destino'];
            $cantidadTraslado = $v['cantidad'];
            $inventarioOrigen = $v['inventario_origen'];
            $producto = $v['producto'];

            DB::beginTransaction();

            try {
                $traslado = new Traslado();
                $traslado->id_producto = $idProducto;
                $traslado->id_bodega_de = $idBodegaOrigen;
                $traslado->id_bodega = $idBodegaDestino;
                $traslado->concepto = $this->concepto;
                $traslado->cantidad = $cantidadTraslado;
                $traslado->id_usuario = Auth::id();
                $traslado->id_empresa = Auth::user()->id_empresa;
                $traslado->estado = 'Confirmado';
                $traslado->save();

                $inventarioOrigen->stock -= $cantidadTraslado;
                $inventarioOrigen->save();
                $inventarioOrigen->kardex($traslado, $cantidadTraslado * -1);

                $inventarioDestino = Inventario::firstOrCreate(
                    [
                        'id_producto' => $idProducto,
                        'id_bodega' => $idBodegaDestino,
                    ],
                    ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                );
                $inventarioDestino->stock += $cantidadTraslado;
                $inventarioDestino->save();
                $inventarioDestino->kardex($traslado, $cantidadTraslado);

                foreach ($producto->composiciones as $composicion) {
                    $productoCompuesto = Producto::where('id', $composicion->id_compuesto)->first();

                    if (!$productoCompuesto) {
                        throw new \Exception("No se encontró el producto compuesto ID {$composicion->id_compuesto}");
                    }

                    $cantidadCompuesto = $cantidadTraslado * $composicion->cantidad;

                    $inventarioCompuestoOrigen = Inventario::where('id_producto', $composicion->id_compuesto)
                        ->where('id_bodega', $idBodegaOrigen)
                        ->first();

                    $inventarioCompuestoDestino = Inventario::where('id_producto', $composicion->id_compuesto)
                        ->where('id_bodega', $idBodegaDestino)
                        ->first();

                    if (!$inventarioCompuestoOrigen) {
                        throw new \Exception("No se encontró inventario para el componente {$productoCompuesto->nombre} en bodega origen");
                    }

                    if ($inventarioCompuestoOrigen->stock < $cantidadCompuesto) {
                        throw new \Exception("Stock insuficiente para el componente {$productoCompuesto->nombre} en bodega origen");
                    }

                    $inventarioCompuestoOrigen->stock -= $cantidadCompuesto;
                    $inventarioCompuestoOrigen->save();
                    $inventarioCompuestoOrigen->kardex($traslado, $cantidadCompuesto * -1);

                    $inventarioCompuestoDestino = Inventario::firstOrCreate(
                        [
                            'id_producto' => $composicion->id_compuesto,
                            'id_bodega' => $idBodegaDestino,
                        ],
                        ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
                    );
                    $inventarioCompuestoDestino->stock += $cantidadCompuesto;
                    $inventarioCompuestoDestino->save();
                    $inventarioCompuestoDestino->kardex($traslado, $cantidadCompuesto);
                }

                DB::commit();
                $this->trasladados++;

                Log::info("Producto {$producto->nombre} trasladado. Cantidad: {$cantidadTraslado}");
            } catch (\Exception $e) {
                DB::rollback();
                Log::error("Error en traslado: " . $e->getMessage());
                $this->errores[] = "Fila {$this->filaContador}: Error en traslado de producto ID {$idProducto}: " . $e->getMessage();
            }
        } catch (\Exception $e) {
            Log::error("Error procesando fila: " . $e->getMessage(), $row);
            $this->errores[] = "Fila {$this->filaContador}: " . $e->getMessage();
        }

        return null;
    }

    public function getTrasladados(): int
    {
        return $this->trasladados;
    }

    public function getErrores(): array
    {
        return $this->errores;
    }

    /**
     * @return array<int, array{fila: int, id_producto: mixed, producto: ?string, cantidad: ?float, stock_origen: ?float, ok: bool, error: ?string}>
     */
    public function getFilasVistaPrevia(): array
    {
        return $this->filasVistaPrevia;
    }
}
