<?php

namespace App\Support\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Services\ImpuestosService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActualizacionMasivaProductos
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>|null  $producto
     * @param  array<int, array{stock?: float, stock_minimo?: float, stock_maximo?: float}>  $inventarios
     * @param  array<int, int>  $bodegasActivas
     * @param  array<int, string>  $codigosPorId
     * @return array{ok: bool, error?: string, producto?: array<string, mixed>, inventarios?: array<int, array<string, float>>, registrar_kardex?: bool}
     */
    public function evaluarFila(
        array $row,
        ?array $producto,
        array $inventarios,
        array $bodegasActivas,
        array $codigosPorId,
        float $porcentajeIva
    ): array {
        $id = $this->idFila($row);
        if ($id === null) {
            return ['ok' => false, 'error' => 'El ID de creación es obligatorio.'];
        }
        if ($producto === null || (int) ($producto['id'] ?? 0) !== $id) {
            return ['ok' => false, 'error' => 'El ID de creación no existe.'];
        }
        if (($producto['tipo'] ?? '') === 'Servicio') {
            return ['ok' => false, 'error' => 'El ID no corresponde a un producto de inventario.'];
        }

        $patch = [];
        $invPatch = [];
        $kardex = false;
        $bodegas = array_fill_keys(array_map('intval', $bodegasActivas), true);

        if (array_key_exists('codigo', $row) && $this->presente($row['codigo'])) {
            $codigo = trim((string) $row['codigo']);
            $clave = mb_strtolower($codigo);
            foreach ($codigosPorId as $otroId => $otroCodigo) {
                if ((int) $otroId !== $id && mb_strtolower(trim((string) $otroCodigo)) === $clave) {
                    return ['ok' => false, 'error' => 'El código ya pertenece a otro producto.'];
                }
            }
            if ($codigo !== (string) ($producto['codigo'] ?? '')) {
                $patch['codigo'] = $codigo;
            }
        }

        $costo = $this->numero($row['costo'] ?? null, 'El costo');
        if (isset($costo['error'])) {
            return ['ok' => false, 'error' => $costo['error']];
        }
        if (isset($costo['value'])) {
            if (!$this->igual($costo['value'], $producto['costo'] ?? null)) {
                $patch['costo'] = $costo['value'];
                $kardex = true;
            }
            if (!$this->igual($costo['value'], $producto['costo_promedio'] ?? null)) {
                $patch['costo_promedio'] = $costo['value'];
            }
        }

        $precio = $this->numero($row['precio_sin_iva'] ?? null, 'El precio sin IVA');
        if (isset($precio['error'])) {
            return ['ok' => false, 'error' => $precio['error']];
        }
        if (isset($precio['value'])) {
            $sin = $precio['value'];
            $con = $porcentajeIva > 0 ? round($sin * (1 + ($porcentajeIva / 100)), 2) : $sin;
            if (!$this->igual($sin, $producto['precio'] ?? null)) {
                $patch['precio'] = $sin;
                $kardex = true;
            }
            if (!$this->igual($sin, $producto['precio_sin_iva'] ?? null)) {
                $patch['precio_sin_iva'] = $sin;
            }
            if (!$this->igual($con, $producto['precio_con_iva'] ?? null)) {
                $patch['precio_con_iva'] = $con;
            }
        }

        foreach (['entra_a_menu' => 'mostrar_en_restaurante', 'mostrar_en_restaurante' => 'mostrar_en_restaurante', 'genera_comanda' => 'genera_comanda'] as $columna => $campo) {
            if (!array_key_exists($columna, $row)) {
                continue;
            }
            $bandera = $this->bandera($row[$columna], $columna === 'genera_comanda' ? 'genera comanda' : 'entra a menú');
            if (isset($bandera['error'])) {
                return ['ok' => false, 'error' => $bandera['error']];
            }
            if (!isset($bandera['value'])) {
                continue;
            }
            $actual = (bool) ($producto[$campo] ?? false);
            if ($bandera['value'] !== $actual) {
                $patch[$campo] = $bandera['value'];
            }
        }

        foreach ($row as $key => $value) {
            $columna = $this->parseColumnaBodega((string) $key);
            if ($columna === null) {
                continue;
            }
            $numero = $this->numero($value, $columna['campo'] === 'stock_minimo' ? 'El stock mínimo' : 'El stock máximo');
            if (isset($numero['error'])) {
                return ['ok' => false, 'error' => $numero['error']];
            }
            if (!isset($numero['value'])) {
                continue;
            }
            $idBodega = $columna['id_bodega'];
            if (!isset($bodegas[$idBodega])) {
                return ['ok' => false, 'error' => 'La bodega '.$idBodega.' no es una bodega activa de la empresa.'];
            }
            if (!isset($inventarios[$idBodega])) {
                return ['ok' => false, 'error' => 'El producto no tiene inventario en la bodega '.$idBodega.'.'];
            }
            $actual = $inventarios[$idBodega][$columna['campo']] ?? null;
            if (!$this->igual($numero['value'], $actual)) {
                $invPatch[$idBodega][$columna['campo']] = $numero['value'];
            }
        }

        $out = [
            'ok' => true,
            'producto' => $patch,
            'inventarios' => $invPatch,
            'registrar_kardex' => $kardex,
            'lote' => null,
        ];

        return $out;
    }

    /**
     * Valida y arma cambios de lote cuando la empresa tiene lotes activos y el producto usa inventario por lotes.
     *
     * @param  array<int, int>  $bodegasActivas
     * @return array{ok: bool, error?: string, lote?: array{id?: int, id_bodega: int, crear: bool, patch: array<string, mixed>, sincronizar_inventario: bool}}
     */
    public function evaluarDatosLoteEnFila(
        array $row,
        ?Producto $modelo,
        bool $lotesActivo,
        array $bodegasActivas,
        int $idEmpresa
    ): array {
        if (!$lotesActivo || !$modelo || !$modelo->inventario_por_lotes) {
            return ['ok' => true];
        }
        if (!$this->filaTieneDatosLote($row)) {
            return ['ok' => true];
        }

        $idLote = $this->enteroOpcional($row['id_lote'] ?? null);
        $idBodegaFila = $this->enteroOpcional($row['id_bodega'] ?? null);
        $numeroLote = $this->textoNumeroLote($row);

        $lote = null;
        if ($idLote !== null) {
            $lote = Lote::withoutGlobalScopes()
                ->where('id', $idLote)
                ->where('id_empresa', $idEmpresa)
                ->whereNull('deleted_at')
                ->first();
            if (!$lote || (int) $lote->id_producto !== (int) $modelo->id) {
                return ['ok' => false, 'error' => 'El lote indicado no existe o no pertenece al producto.'];
            }
            $idBodega = (int) $lote->id_bodega;
        } else {
            if ($idBodegaFila === null || $numeroLote === null) {
                return ['ok' => false, 'error' => 'Para el lote indique id_lote o id_bodega y lote.'];
            }
            $idBodega = $idBodegaFila;
            if (!in_array($idBodega, array_map('intval', $bodegasActivas), true)) {
                return ['ok' => false, 'error' => 'La bodega '.$idBodega.' no es una bodega activa de la empresa.'];
            }
            $lote = Lote::withoutGlobalScopes()
                ->where('id_producto', $modelo->id)
                ->where('id_bodega', $idBodega)
                ->where('id_empresa', $idEmpresa)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(numero_lote) = ?', [mb_strtolower($numeroLote)])
                ->first();
        }

        $patch = [];
        $sincronizar = false;

        if ($numeroLote !== null && (!$lote || (string) $lote->numero_lote !== $numeroLote)) {
            $patch['numero_lote'] = $numeroLote;
        }

        foreach (['fecha_fabricacion' => 'La fecha de fabricación', 'fecha_vencimiento' => 'La fecha de vencimiento'] as $col => $etiqueta) {
            if (!array_key_exists($col, $row)) {
                continue;
            }
            $fecha = $this->parseFecha($row[$col], $etiqueta);
            if (isset($fecha['error'])) {
                return ['ok' => false, 'error' => $fecha['error']];
            }
            if (!isset($fecha['value'])) {
                continue;
            }
            $actual = $lote && $lote->{$col} ? $lote->{$col}->format('Y-m-d') : null;
            if ($fecha['value'] !== $actual) {
                $patch[$col] = $fecha['value'];
            }
        }

        $stockLote = $this->numero($row['stock_lote'] ?? null, 'El stock del lote');
        if (isset($stockLote['error'])) {
            return ['ok' => false, 'error' => $stockLote['error']];
        }
        if (isset($stockLote['value'])) {
            $actualStock = $lote ? (float) $lote->stock : null;
            if (!$this->igual($stockLote['value'], $actualStock)) {
                $patch['stock'] = $stockLote['value'];
                $sincronizar = true;
            }
        }

        if ($lote === null) {
            if (!isset($patch['stock'])) {
                return ['ok' => false, 'error' => 'Para crear un lote nuevo indique stock_lote.'];
            }
            if (!Inventario::query()->where('id_producto', $modelo->id)->where('id_bodega', $idBodega)->exists()) {
                return ['ok' => false, 'error' => 'El producto no tiene inventario en la bodega '.$idBodega.'.'];
            }

            return [
                'ok' => true,
                'lote' => [
                    'id_bodega' => $idBodega,
                    'crear' => true,
                    'patch' => array_merge($patch, [
                        'numero_lote' => $numeroLote,
                        'id_producto' => (int) $modelo->id,
                        'id_empresa' => $idEmpresa,
                        'stock_inicial' => $patch['stock'],
                    ]),
                    'sincronizar_inventario' => true,
                ],
            ];
        }

        if ($patch === []) {
            return ['ok' => true];
        }

        return [
            'ok' => true,
            'lote' => [
                'id' => (int) $lote->id,
                'id_bodega' => $idBodega,
                'crear' => false,
                'patch' => $patch,
                'sincronizar_inventario' => $sincronizar,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $bodegasActivas
     */
    public function claveFilaUnica(array $row, bool $lotesActivo, bool $inventarioPorLotes): ?string
    {
        $id = $this->idFila($row);
        if ($id === null) {
            return null;
        }
        if ($lotesActivo && $inventarioPorLotes && $this->filaTieneDatosLote($row)) {
            $idLote = $this->enteroOpcional($row['id_lote'] ?? null);
            if ($idLote !== null) {
                return $id.':l:'.$idLote;
            }
            $idBodega = $this->enteroOpcional($row['id_bodega'] ?? null);
            $numero = $this->textoNumeroLote($row);
            if ($idBodega !== null && $numero !== null) {
                return $id.':b:'.$idBodega.':'.mb_strtolower($numero);
            }
        }

        return (string) $id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{actualizados: int, errores: array<int, array{fila: int, mensaje: string}>}
     */
    public function procesar(array $filas, int $idEmpresa, int $idUsuario): array
    {
        $empresa = Empresa::find($idEmpresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;

        $ids = [];
        foreach ($filas as $fila) {
            $id = $this->idFila($fila);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        $productos = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $bodegas = Bodega::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->orderBy('id_sucursal')
            ->orderBy('id')
            ->get();
        $bodegaIds = $bodegas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $inventarios = Inventario::query()
            ->whereIn('id_producto', $ids)
            ->whereIn('id_bodega', $bodegaIds ?: [0])
            ->get()
            ->groupBy('id_producto');

        $codigosPorId = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->pluck('codigo', 'id')
            ->all();

        $ivaEmpresa = (float) app(ImpuestosService::class)->obtenerPorcentajeImpuesto($idEmpresa);
        $actualizados = 0;
        $errores = [];
        $vistos = [];

        foreach ($filas as $indice => $fila) {
            if ($this->filaVacia($fila)) {
                continue;
            }
            $numeroFila = $indice + 2;
            $id = $this->idFila($fila);
            $modelo = $id !== null ? $productos->get($id) : null;
            $claveUnica = $this->claveFilaUnica(
                $fila,
                $lotesActivo,
                $modelo ? (bool) $modelo->inventario_por_lotes : false
            );
            if ($claveUnica !== null && isset($vistos[$claveUnica])) {
                $errores[] = ['fila' => $numeroFila, 'mensaje' => 'Fila duplicada en el archivo (mismo producto o lote).'];
                continue;
            }
            if ($claveUnica !== null) {
                $vistos[$claveUnica] = true;
            }
            $invModelo = $modelo ? $inventarios->get($modelo->id, collect()) : collect();
            $inv = [];
            foreach ($invModelo as $item) {
                $inv[(int) $item->id_bodega] = [
                    'stock' => (float) $item->stock,
                    'stock_minimo' => (float) $item->stock_minimo,
                    'stock_maximo' => (float) $item->stock_maximo,
                ];
            }

            $pct = $modelo && (float) ($modelo->porcentaje_impuesto ?? 0) > 0
                ? (float) $modelo->porcentaje_impuesto
                : $ivaEmpresa;

            $resultado = $this->evaluarFila(
                $fila,
                $modelo ? [
                    'id' => (int) $modelo->id,
                    'tipo' => $modelo->tipo,
                    'codigo' => $modelo->codigo,
                    'costo' => $modelo->costo,
                    'costo_promedio' => $modelo->costo_promedio,
                    'precio' => $modelo->precio,
                    'precio_sin_iva' => $modelo->precio_sin_iva,
                    'precio_con_iva' => $modelo->precio_con_iva,
                    'mostrar_en_restaurante' => (bool) $modelo->mostrar_en_restaurante,
                    'genera_comanda' => (bool) $modelo->genera_comanda,
                ] : null,
                $inv,
                $bodegaIds,
                $codigosPorId,
                $pct
            );

            if (!$resultado['ok']) {
                $errores[] = ['fila' => $numeroFila, 'mensaje' => $resultado['error']];
                continue;
            }

            $loteEval = $this->evaluarDatosLoteEnFila($fila, $modelo, $lotesActivo, $bodegaIds, $idEmpresa);
            if (!$loteEval['ok']) {
                $errores[] = ['fila' => $numeroFila, 'mensaje' => $loteEval['error']];
                continue;
            }
            $resultado['lote'] = $loteEval['lote'] ?? null;

            if ($resultado['producto'] === [] && $resultado['inventarios'] === [] && empty($resultado['lote'])) {
                $actualizados++;
                continue;
            }

            try {
                DB::transaction(function () use ($modelo, $resultado, $idUsuario, $idEmpresa) {
                    if ($resultado['producto'] !== []) {
                        $modelo->fill($resultado['producto']);
                        $modelo->save();
                    }
                    foreach ($resultado['inventarios'] as $idBodega => $valores) {
                        $inventario = Inventario::query()
                            ->where('id_producto', $modelo->id)
                            ->where('id_bodega', $idBodega)
                            ->first();
                        if (!$inventario) {
                            throw new \RuntimeException('El producto no tiene inventario en la bodega '.$idBodega.'.');
                        }
                        if (array_key_exists('stock_minimo', $valores)) {
                            $inventario->stock_minimo = $valores['stock_minimo'];
                        }
                        if (array_key_exists('stock_maximo', $valores)) {
                            $inventario->stock_maximo = $valores['stock_maximo'];
                        }
                        $inventario->save();
                    }
                    if ($resultado['registrar_kardex']) {
                        $modelo->refresh();
                        foreach (Inventario::where('id_producto', $modelo->id)->get() as $inventario) {
                            if ((float) $inventario->stock > 0) {
                                $inventario->kardex($modelo, 0, $modelo->precio, $modelo->costo, null, [
                                    'id_usuario' => $idUsuario,
                                ]);
                            }
                        }
                    }
                    if (!empty($resultado['lote'])) {
                        $this->aplicarCambioLote($resultado['lote'], $modelo, $idEmpresa);
                    }
                });
                if (isset($resultado['producto']['codigo'])) {
                    $codigosPorId[$modelo->id] = $resultado['producto']['codigo'];
                }
                $actualizados++;
            } catch (\Throwable $e) {
                Log::error('Actualización masiva de productos: '.$e->getMessage(), ['fila' => $numeroFila]);
                $errores[] = ['fila' => $numeroFila, 'mensaje' => 'No se pudo actualizar el producto.'];
            }
        }

        return ['actualizados' => $actualizados, 'errores' => $errores];
    }

    /**
     * @param  array<int, mixed>  $encabezados
     */
    public static function tipoPlantilla(array $encabezados): string
    {
        $claves = [];
        foreach ($encabezados as $encabezado) {
            $clave = strtolower(trim((string) $encabezado));
            if ($clave !== '') {
                $claves[$clave] = true;
            }
        }

        $tieneIdCreacion = isset($claves['id_creacion']) || isset($claves['id_de_creacion']);
        if ($tieneIdCreacion) {
            return 'actualizar';
        }
        if (isset($claves['nombre'])) {
            return 'nuevos';
        }

        return '';
    }

    public static function encabezadoStock(string $campo, int $idBodega, string $nombre, string $sucursal): string
    {
        $prefijo = $campo === 'stock_maximo' ? 'stock_maximo' : 'stock_minimo';

        return $prefijo.'_'.$idBodega.' - '.self::sanear($nombre).' ('.self::sanear($sucursal).')';
    }

    /**
     * @return array{campo: string, id_bodega: int}|null
     */
    public function parseColumnaBodega(string $key): ?array
    {
        if (!preg_match('/^stock_(minimo|maximo)_(\d+)/', $key, $m)) {
            return null;
        }

        return [
            'campo' => $m[1] === 'minimo' ? 'stock_minimo' : 'stock_maximo',
            'id_bodega' => (int) $m[2],
        ];
    }

    private function idFila(array $row): ?int
    {
        foreach (['id_creacion', 'id_de_creacion', 'id'] as $clave) {
            if (!array_key_exists($clave, $row) || !$this->presente($row[$clave])) {
                continue;
            }
            $raw = $row[$clave];
            if (!is_numeric($raw) || (float) $raw != (int) $raw) {
                return null;
            }

            return (int) $raw;
        }

        return null;
    }

    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($this->presente($valor)) {
                return false;
            }
        }

        return true;
    }

    private function presente($valor): bool
    {
        return $valor !== null && trim((string) $valor) !== '';
    }

    /**
     * @return array{value?: float, error?: string}
     */
    private function numero($valor, string $etiqueta): array
    {
        if (!$this->presente($valor)) {
            return [];
        }
        if (!is_numeric($valor)) {
            return ['error' => $etiqueta.' no es un número válido.'];
        }
        $numero = round((float) $valor, 4);
        if ($numero < 0) {
            return ['error' => $etiqueta.' no puede ser negativo.'];
        }

        return ['value' => $numero];
    }

    /**
     * @return array{value?: bool, error?: string}
     */
    private function bandera($valor, string $etiqueta): array
    {
        if (!$this->presente($valor)) {
            return [];
        }
        if (is_bool($valor)) {
            return ['value' => $valor];
        }
        if (is_numeric($valor)) {
            return ['value' => (float) $valor != 0.0];
        }
        $texto = mb_strtolower(trim((string) $valor));
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $texto);
        if (in_array($texto, ['1', 'si', 'true', 'yes', 'y', 'x', 'verdadero'], true)) {
            return ['value' => true];
        }
        if (in_array($texto, ['0', 'no', 'false', 'n', 'falso'], true)) {
            return ['value' => false];
        }

        return ['error' => 'El valor de '.$etiqueta.' no es válido. Use Si o No.'];
    }

    private function igual($a, $b): bool
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return false;
        }

        return abs((float) $a - (float) $b) < 0.0001;
    }

    private static function sanear(string $texto): string
    {
        $texto = str_replace(["\r", "\n"], ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', trim($texto));

        return $texto !== '' ? $texto : '-';
    }

  /**
     * @param  array{id?: int, id_bodega: int, crear: bool, patch: array<string, mixed>, sincronizar_inventario: bool}  $operacion
     */
    private function aplicarCambioLote(array $operacion, Producto $modelo, int $idEmpresa): void
    {
        if (!empty($operacion['crear'])) {
            Lote::create($operacion['patch']);
        } else {
            $lote = Lote::withoutGlobalScopes()->find($operacion['id'] ?? 0);
            if (!$lote) {
                throw new \RuntimeException('Lote no encontrado.');
            }
            $lote->fill($operacion['patch']);
            $lote->save();
        }

        if (!empty($operacion['sincronizar_inventario'])) {
            $this->sincronizarInventarioDesdeLotes($modelo, (int) $operacion['id_bodega'], $idEmpresa);
        }
    }

    private function sincronizarInventarioDesdeLotes(Producto $modelo, int $idBodega, int $idEmpresa): void
    {
        $sum = (float) Lote::withoutGlobalScopes()
            ->where('id_producto', $modelo->id)
            ->where('id_bodega', $idBodega)
            ->where('id_empresa', $idEmpresa)
            ->whereNull('deleted_at')
            ->sum('stock');

        $inventario = Inventario::query()
            ->where('id_producto', $modelo->id)
            ->where('id_bodega', $idBodega)
            ->first();
        if ($inventario) {
            $inventario->stock = round($sum, 4);
            $inventario->save();
        }
    }

    private function filaTieneDatosLote(array $row): bool
    {
        foreach (['id_lote', 'id_bodega', 'lote', 'numero_lote', 'fecha_fabricacion', 'fecha_vencimiento', 'stock_lote'] as $clave) {
            if (array_key_exists($clave, $row) && $this->presente($row[$clave])) {
                return true;
            }
        }

        return false;
    }

    private function textoNumeroLote(array $row): ?string
    {
        foreach (['lote', 'numero_lote'] as $clave) {
            if (array_key_exists($clave, $row) && $this->presente($row[$clave])) {
                return trim((string) $row[$clave]);
            }
        }

        return null;
    }

    private function enteroOpcional($valor): ?int
    {
        if (!$this->presente($valor)) {
            return null;
        }
        if (!is_numeric($valor) || (float) $valor != (int) $valor) {
            return null;
        }

        return (int) $valor;
    }

    /**
     * @return array{value?: string, error?: string}
     */
    private function parseFecha($valor, string $etiqueta): array
    {
        if (!$this->presente($valor)) {
            return [];
        }
        if (is_numeric($valor)) {
            try {
                $serial = (float) $valor;
                $base = Carbon::create(1899, 12, 30);

                return ['value' => $base->addDays((int) floor($serial))->format('Y-m-d')];
            } catch (\Throwable) {
                return ['error' => $etiqueta.' no es una fecha válida.'];
            }
        }
        try {
            return ['value' => Carbon::parse((string) $valor)->format('Y-m-d')];
        } catch (\Throwable) {
            return ['error' => $etiqueta.' no es una fecha válida.'];
        }
    }
}
