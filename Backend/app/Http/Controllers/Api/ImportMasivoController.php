<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Importación masiva por lotes vía URL (evita timeout del servidor).
 * Llamar repetidamente hasta que done=true. Únicos cambios: id_cliente e id_venta (viejo→nuevo).
 *
 * Uso: GET /api/import-masivo?step=clientes&offset=0
 *      (recargar con el next_url que devuelve la respuesta hasta terminar)
 */
class ImportMasivoController extends Controller
{
    protected string $datosPath = 'datos';
    protected string $progressFile = 'import_masivo_progress.json';
    protected int $batchClientes = 100;
    protected int $batchVentas = 50;
    protected int $batchDetalles = 150;

    public function __invoke(Request $request): JsonResponse
    {
        set_time_limit(120);
        ini_set('memory_limit', '2048M');

        $step = $request->query('step', 'clientes');
        $offset = (int) $request->query('offset', 0);

        $clientesPath = base_path($this->datosPath . DIRECTORY_SEPARATOR . 'clientes.php');
        $ventasPath = base_path($this->datosPath . DIRECTORY_SEPARATOR . 'ventas.php');
        $detallesPath = base_path($this->datosPath . DIRECTORY_SEPARATOR . 'detalles_venta.php');

        if (!is_readable($clientesPath) || !is_readable($ventasPath) || !is_readable($detallesPath)) {
            return response()->json([
                'error' => 'No se encontraron los archivos de datos.',
                'rutas' => [$clientesPath, $ventasPath, $detallesPath],
            ], 404);
        }

        try {
            if ($step === 'clientes') {
                return $this->procesarClientes($clientesPath, $offset);
            }
            if ($step === 'ventas') {
                return $this->procesarVentas($ventasPath, $offset);
            }
            if ($step === 'detalles') {
                return $this->procesarDetalles($detallesPath, $offset);
            }
            return response()->json(['error' => 'step inválido. Use: clientes, ventas o detalles'], 400);
        } catch (\Throwable $e) {
            Log::error('ImportMasivo', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function getProgress(): array
    {
        $path = $this->progressFile;
        if (!Storage::exists($path)) {
            return ['mapCliente' => [], 'mapVenta' => []];
        }
        $data = json_decode(Storage::get($path), true);
        return [
            'mapCliente' => $data['mapCliente'] ?? [],
            'mapVenta' => $data['mapVenta'] ?? [],
        ];
    }

    protected function saveProgress(array $mapCliente, array $mapVenta): void
    {
        Storage::put($this->progressFile, json_encode(compact('mapCliente', 'mapVenta')));
    }

    protected function procesarClientes(string $path, int $offset): JsonResponse
    {
        $clientes = $this->loadPhpArray($path, 'clientes');
        $total = count($clientes);
        $batch = array_slice($clientes, $offset, $this->batchClientes);
        $progress = $this->getProgress();
        $mapCliente = $progress['mapCliente'];

        DB::beginTransaction();
        try {
            foreach ($batch as $row) {
                $idViejo = $row['id'] ?? null;
                $idEmpresa = (string) ($row['id_empresa'] ?? '');
                if ($idViejo === null || $idViejo === '' || $idEmpresa === '') continue;

                $key = $idEmpresa . '|' . $idViejo;
                $existente = $this->buscarClienteExistente($row, $idEmpresa);
                if ($existente) {
                    $mapCliente[$key] = $existente->id;
                    continue;
                }
                $data = $this->soloFillable($row, (new Cliente())->getFillable());
                unset($data['id']);
                $cliente = Cliente::withoutGlobalScopes()->create($data);
                $mapCliente[$key] = $cliente->id;
            }
            $this->saveProgress($mapCliente, $progress['mapVenta']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOffset = $offset + count($batch);
        $done = $newOffset >= $total;
        $nextStep = $done ? 'ventas' : 'clientes';
        $nextOffset = $done ? 0 : $newOffset;

        return response()->json([
            'step' => 'clientes',
            'offset' => $newOffset,
            'total' => $total,
            'processed' => count($batch),
            'done' => $done,
            'next_url' => url('/api/import-masivo?step=' . $nextStep . '&offset=' . $nextOffset),
            'message' => $done ? 'Clientes completados. Siguiente: ventas.' : "Clientes {$newOffset}/{$total}",
        ]);
    }

    protected function procesarVentas(string $path, int $offset): JsonResponse
    {
        $progress = $this->getProgress();
        $mapCliente = $progress['mapCliente'];
        if (empty($mapCliente) && $offset === 0) {
            return response()->json(['error' => 'Primero complete clientes (step=clientes&offset=0)'], 400);
        }

        $ventas = $this->loadPhpArray($path, 'ventas');
        $total = count($ventas);
        $batch = array_slice($ventas, $offset, $this->batchVentas);
        $mapVenta = $progress['mapVenta'];

        DB::beginTransaction();
        try {
            foreach ($batch as $row) {
                $idViejo = $row['id'] ?? null;
                if ($idViejo === null || $idViejo === '') continue;

                $data = $this->soloFillable($row, (new Venta())->getFillable());
                unset($data['id']);
                $idClienteViejo = $row['id_cliente'] ?? null;
                $idEmpresa = (string) ($row['id_empresa'] ?? '');
                $data['id_cliente'] = null;
                if ($idClienteViejo !== null && $idClienteViejo !== '' && $idEmpresa !== '') {
                    $clave = $idEmpresa . '|' . $idClienteViejo;
                    $data['id_cliente'] = $mapCliente[$clave] ?? $mapCliente[$idEmpresa . '|' . (int) $idClienteViejo] ?? null;
                }
                $venta = Venta::withoutGlobalScopes()->create($data);
                $mapVenta[$idViejo] = $venta->id;
            }
            $this->saveProgress($mapCliente, $mapVenta);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOffset = $offset + count($batch);
        $done = $newOffset >= $total;
        $nextStep = $done ? 'detalles' : 'ventas';
        $nextOffset = $done ? 0 : $newOffset;

        return response()->json([
            'step' => 'ventas',
            'offset' => $newOffset,
            'total' => $total,
            'processed' => count($batch),
            'done' => $done,
            'next_url' => url('/api/import-masivo?step=' . $nextStep . '&offset=' . $nextOffset),
            'message' => $done ? 'Ventas completadas. Siguiente: detalles.' : "Ventas {$newOffset}/{$total}",
        ]);
    }

    protected function procesarDetalles(string $path, int $offset): JsonResponse
    {
        $progress = $this->getProgress();
        $mapVenta = $progress['mapVenta'];
        if (empty($mapVenta) && $offset === 0) {
            return response()->json(['error' => 'Primero complete ventas (step=ventas&offset=0)'], 400);
        }

        $detalles = $this->loadPhpArray($path, 'detalles_venta');
        $total = count($detalles);
        $batch = array_slice($detalles, $offset, $this->batchDetalles);
        $fillable = (new Detalle())->getFillable();
        $created = 0;

        $idVentasUnicas = array_unique(array_filter(array_map(function ($r) use ($mapVenta) {
            $idv = $r['id_venta'] ?? null;
            if ($idv === null || $idv === '') return null;
            return $mapVenta[$idv] ?? $mapVenta[(int) $idv] ?? null;
        }, $batch)));
        $ventas = Venta::withoutGlobalScopes()->whereIn('id', $idVentasUnicas)->get()->keyBy('id');

        DB::beginTransaction();
        try {
            foreach ($batch as $row) {
                $idVentaVieja = $row['id_venta'] ?? null;
                if ($idVentaVieja === null || $idVentaVieja === '') continue;

                $idVentaNueva = $mapVenta[$idVentaVieja] ?? $mapVenta[(int) $idVentaVieja] ?? null;
                if ($idVentaNueva === null) continue;

                $venta = $ventas->get($idVentaNueva);
                if (!$venta) continue;

                $data = $this->soloFillable($row, $fillable);
                unset($data['id']);
                $data['id_venta'] = $idVentaNueva;
                Detalle::create($data);
                $created++;

                if ($venta->cotizacion == 0) {
                    $this->actualizarInventarioVenta($venta, $data, $row);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOffset = $offset + count($batch);
        $done = $newOffset >= $total;

        $response = [
            'step' => 'detalles',
            'offset' => $newOffset,
            'total' => $total,
            'processed' => count($batch),
            'created' => $created,
            'done' => $done,
            'message' => $done ? 'Importación completada.' : "Detalles {$newOffset}/{$total}",
        ];
        if (!$done) {
            $response['next_url'] = url('/api/import-masivo?step=detalles&offset=' . $newOffset);
        } else {
            Storage::delete($this->progressFile);
        }
        return response()->json($response);
    }

    protected function loadPhpArray(string $path, string $variableName): array
    {
        $result = [];
        if ($variableName === 'clientes') {
            (function () use ($path, &$result) { $clientes = []; include $path; $result = is_array($clientes) ? $clientes : []; })();
        } elseif ($variableName === 'ventas') {
            (function () use ($path, &$result) { $ventas = []; include $path; $result = is_array($ventas) ? $ventas : []; })();
        } elseif ($variableName === 'detalles_venta') {
            (function () use ($path, &$result) { $detalles_venta = []; include $path; $result = is_array($detalles_venta) ? $detalles_venta : []; })();
        } else {
            throw new \InvalidArgumentException("Variable no soportada: {$variableName}");
        }
        return $result;
    }

    protected function buscarClienteExistente(array $row, string $idEmpresa): ?Cliente
    {
        $v = fn($x) => ($x === null || $x === '' || $x === 'null') ? '' : trim((string) $x);
        $nit = $v($row['nit'] ?? null);
        $dui = $v($row['dui'] ?? null);
        $correo = $v($row['correo'] ?? null);
        $nombre = $v($row['nombre'] ?? null);
        $apellido = $v($row['apellido'] ?? null);
        $nombreEmpresa = $v($row['nombre_empresa'] ?? null);
        $q = fn() => Cliente::withoutGlobalScopes()->where('id_empresa', $idEmpresa);

        if ($nit !== '') { $f = $q()->where('nit', $nit)->first(); if ($f) return $f; }
        if ($dui !== '') { $f = $q()->where('dui', $dui)->first(); if ($f) return $f; }
        if ($correo !== '') { $f = $q()->where('correo', $correo)->first(); if ($f) return $f; }
        if (($row['tipo'] ?? '') === 'Empresa' && $nombreEmpresa !== '') { $f = $q()->where('nombre_empresa', $nombreEmpresa)->first(); if ($f) return $f; }
        if ($nombre !== '' || $apellido !== '') {
            $qb = $q(); if ($nombre !== '') $qb->where('nombre', $nombre); if ($apellido !== '') $qb->where('apellido', $apellido);
            $f = $qb->first(); if ($f) return $f;
        }
        return null;
    }

    protected function actualizarInventarioVenta(\App\Models\Ventas\Venta $venta, array $det, array $row): void
    {
        $idProducto = $det['id_producto'] ?? $row['id_producto'] ?? null;
        $cantidad = (float) ($det['cantidad'] ?? $row['cantidad'] ?? 0);
        $precio = (float) ($det['precio'] ?? $row['precio'] ?? 0);

        if (!$idProducto || $cantidad <= 0) return;

        $producto = Producto::withoutGlobalScopes()->find($idProducto);
        if (!$producto || $producto->tipo == 'Servicio') return;

        $inventario = Inventario::withoutGlobalScopes()
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock -= $cantidad;
            $inventario->save();
            $inventario->kardex($venta, $cantidad, $precio);
        }
    }

    protected function soloFillable(array $row, array $fillable): array
    {
        $out = [];
        foreach ($fillable as $key) {
            if (array_key_exists($key, $row)) {
                $v = $row[$key];
                $out[$key] = ($v === 'null' || $v === 'NULL') ? null : $v;
            }
        }
        return $out;
    }
}
