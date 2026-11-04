<?php

namespace App\Services\Compras;

use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;
use App\Services\Inventario\ConversionInventarioService;
use App\Services\Inventario\LoteAsignacionService;
use Illuminate\Support\Facades\DB;

/**
 * Aplica entrada de inventario + kardex para una compra ya guardada (p. ej. DTE sin actualizar_inventario).
 */
class CompraInventarioAplicacionService
{
    /**
     * @return array{ok: bool, message?: string, aplicados: int, omitidos: int, errores: array<int, string>, lineas: array<int, string>}
     */
    public function aplicar(int $compraId, bool $dryRun = false, bool $force = false): array
    {
        $compra = Compra::withoutGlobalScopes()
            ->with('detalles')
            ->find($compraId);

        if (!$compra) {
            return [
                'ok' => false,
                'message' => "Compra #{$compraId} no encontrada.",
                'aplicados' => 0,
                'omitidos' => 0,
                'errores' => [],
                'lineas' => [],
            ];
        }

        if ((int) ($compra->cotizacion ?? 0) === 1) {
            return [
                'ok' => false,
                'message' => 'Es orden de compra (cotizacion=1); no aplica inventario.',
                'aplicados' => 0,
                'omitidos' => 0,
                'errores' => [],
                'lineas' => [],
            ];
        }

        if (($compra->estado ?? '') === 'Anulada') {
            return [
                'ok' => false,
                'message' => 'Compra anulada; no aplica inventario.',
                'aplicados' => 0,
                'omitidos' => 0,
                'errores' => [],
                'lineas' => [],
            ];
        }

        if (!$compra->id_bodega) {
            return [
                'ok' => false,
                'message' => 'La compra no tiene bodega (id_bodega).',
                'aplicados' => 0,
                'omitidos' => 0,
                'errores' => [],
                'lineas' => [],
            ];
        }

        if ($compra->detalles->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'La compra no tiene detalles.',
                'aplicados' => 0,
                'omitidos' => 0,
                'errores' => [],
                'lineas' => [],
            ];
        }

        $aplicados = 0;
        $omitidos = 0;
        $errores = [];
        $lineas = [];

        $runner = function () use ($compra, $dryRun, $force, &$aplicados, &$omitidos, &$errores, &$lineas) {
            foreach ($compra->detalles as $detalle) {
                $resultado = $this->aplicarDetalle($compra, $detalle, $dryRun, $force);
                $lineas[] = $resultado['linea'];
                if ($resultado['status'] === 'applied') {
                    $aplicados++;
                } elseif ($resultado['status'] === 'skipped') {
                    $omitidos++;
                } else {
                    $errores[$detalle->id] = $resultado['message'];
                }
            }
        };

        if ($dryRun) {
            $runner();
        } else {
            DB::transaction($runner);
        }

        return [
            'ok' => $errores === [],
            'aplicados' => $aplicados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'lineas' => $lineas,
        ];
    }

    /**
     * @return array{status: 'applied'|'skipped'|'error', linea: string, message?: string}
     */
    protected function aplicarDetalle(Compra $compra, Detalle $detalle, bool $dryRun, bool $force): array
    {
        $producto = Producto::withoutGlobalScopes()->find($detalle->id_producto);
        if (!$producto) {
            return [
                'status' => 'error',
                'linea' => "Detalle #{$detalle->id}: producto {$detalle->id_producto} no encontrado.",
                'message' => 'Producto no encontrado.',
            ];
        }

        if (($producto->tipo ?? '') === 'Servicio') {
            return [
                'status' => 'skipped',
                'linea' => "Detalle #{$detalle->id} ({$producto->nombre}): servicio, omitido.",
            ];
        }

        $factor = 1.0;
        if (!empty($detalle->id_presentacion)) {
            $pres = ProductoPresentacion::find($detalle->id_presentacion);
            if ($pres) {
                $factor = (float) $pres->factor_conversion;
            }
        }

        $cantidadBase = ConversionInventarioService::calcularCantidadBase(
            (float) $detalle->cantidad,
            $factor
        );

        if ($cantidadBase <= 0) {
            return [
                'status' => 'skipped',
                'linea' => "Detalle #{$detalle->id} ({$producto->nombre}): cantidad base 0, omitido.",
            ];
        }

        if (!$force && $this->tieneEntradaKardexCompra($compra, (int) $producto->id)) {
            return [
                'status' => 'skipped',
                'linea' => "Detalle #{$detalle->id} ({$producto->nombre}): ya hay kardex de entrada ref. compra {$compra->id}, omitido.",
            ];
        }

        $costoUnitarioBase = ConversionInventarioService::calcularCostoUnitarioNetoBase(
            (float) $detalle->cantidad,
            (float) $detalle->costo,
            (float) ($detalle->descuento ?? 0),
            $factor
        );

        $etiqueta = "Detalle #{$detalle->id} {$producto->nombre}: +{$cantidadBase} bodega {$compra->id_bodega}";

        if ($dryRun) {
            return [
                'status' => 'applied',
                'linea' => "[dry-run] {$etiqueta}",
            ];
        }

        $producto->load('inventarios');
        $stockAnterior = ($producto->inventarios->sum('stock') ?? 0);
        $stockTotal = $stockAnterior + $cantidadBase;
        if ($stockTotal > 0) {
            $costoPromedio = (($stockAnterior * (float) $producto->costo) + ($cantidadBase * $costoUnitarioBase)) / $stockTotal;
        } else {
            $costoPromedio = $costoUnitarioBase;
        }
        $producto->costo_anterior = $producto->costo;
        $producto->costo = $costoUnitarioBase;
        $producto->costo_promedio = $costoPromedio;
        $producto->save();

        $empresa = Empresa::find($compra->id_empresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;

        if ($producto->inventario_por_lotes && $lotesActivo) {
            $errorLote = $this->aplicarConLotes($compra, $detalle, $producto, $cantidadBase, $lotesActivo);
            if ($errorLote !== null) {
                return [
                    'status' => 'error',
                    'linea' => "Detalle #{$detalle->id} ({$producto->nombre}): {$errorLote}",
                    'message' => $errorLote,
                ];
            }

            return [
                'status' => 'applied',
                'linea' => $etiqueta . ' (lotes)',
            ];
        }

        $inventario = Inventario::withoutGlobalScopes()->firstOrCreate(
            [
                'id_producto' => $producto->id,
                'id_bodega' => $compra->id_bodega,
            ],
            ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
        );
        $inventario->stock += $cantidadBase;
        $inventario->save();
        $inventario->kardex($compra, $cantidadBase, null, null, $compra->fecha);

        return [
            'status' => 'applied',
            'linea' => $etiqueta,
        ];
    }

    protected function aplicarConLotes(
        Compra $compra,
        Detalle $detalle,
        Producto $producto,
        float $cantidadBase,
        bool $lotesActivo
    ): ?string {
        $asignacionManual = null;
        if ($detalle->lote_id) {
            $asignacionManual = [['lote_id' => (int) $detalle->lote_id, 'cantidad' => $cantidadBase]];
        }

        try {
            $asignaciones = LoteAsignacionService::resolverAsignacionesEntrada(
                $producto,
                (int) $compra->id_bodega,
                $cantidadBase,
                $lotesActivo,
                $detalle->lote_id ? (int) $detalle->lote_id : null,
                $asignacionManual
            );
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        if ($asignaciones === []) {
            return 'requiere lote; asigne lote en la compra antes de aplicar inventario.';
        }

        $inventario = Inventario::withoutGlobalScopes()->firstOrCreate(
            [
                'id_producto' => $producto->id,
                'id_bodega' => $compra->id_bodega,
            ],
            ['stock' => 0, 'stock_minimo' => 0, 'stock_maximo' => 0]
        );

        LoteAsignacionService::aplicarEntradaCompra($asignaciones, $detalle, $compra, $inventario);

        return null;
    }

    public function tieneEntradaKardexCompra(Compra $compra, int $idProducto): bool
    {
        return Kardex::query()
            ->where('referencia', $compra->id)
            ->where('id_producto', $idProducto)
            ->where('id_inventario', $compra->id_bodega)
            ->whereIn('detalle', ['Compra', 'Compra a consigna'])
            ->where('entrada_cantidad', '>', 0)
            ->exists();
    }
}
