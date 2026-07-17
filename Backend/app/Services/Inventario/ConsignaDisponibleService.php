<?php

namespace App\Services\Inventario;

use App\Constants\OrigenStockVentaConstants;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Compra;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Services\Inventario\ConversionInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ConsignaDisponibleService
{
    /**
     * Pool virtual de compras en consigna (proveedor) menos ventas que descontaron de ese pool.
     * El inventario físico sigue siendo uno solo; esto solo rastrea el origen contable.
     */
    public static function calcularDisponibleDesdeComponentes(
        float $entradaAbierta,
        float $ventasConsigna,
        float $liquidado,
        float $stockFisico
    ): float {
        $salidaEfectiva = max(0, $ventasConsigna - $liquidado);
        $disponible = max(0, $entradaAbierta - $salidaEfectiva);

        return min($disponible, $stockFisico);
    }

    public function calcularDisponible(int $idProducto, int $idBodega, ?int $excluirVentaId = null): float
    {
        $entrada = $this->sumEntradaComprasConsigna($idProducto, $idBodega);
        $salida = $this->sumSalidaVentasDesdeConsignaCompra($idProducto, $idBodega, $excluirVentaId);
        $liquidado = $this->sumLiquidadoComprasConsigna($idProducto, $idBodega);
        $stockFisico = $this->obtenerStockFisico($idProducto, $idBodega);

        return self::calcularDisponibleDesdeComponentes($entrada, $salida, $liquidado, $stockFisico);
    }

    /**
     * @return array{consigna_disponible: float, stock_fisico: float, stock_normal: float, tiene_consigna_compra: bool}
     */
    public function obtenerResumenStock(int $idProducto, int $idBodega, ?int $excluirVentaId = null): array
    {
        $consignaDisponible = $this->calcularDisponible($idProducto, $idBodega, $excluirVentaId);
        $stockFisico = $this->obtenerStockFisico($idProducto, $idBodega);
        $entrada = $this->sumEntradaComprasConsigna($idProducto, $idBodega);

        return [
            'id_producto' => $idProducto,
            'id_bodega' => $idBodega,
            'consigna_disponible' => round($consignaDisponible, 4),
            'disponible' => round($consignaDisponible, 4),
            'stock_fisico' => round($stockFisico, 4),
            'stock_normal' => round(max(0, $stockFisico - $consignaDisponible), 4),
            'tiene_consigna_compra' => $entrada > 0,
        ];
    }

    public function calcularDisponibleAgregadoProducto(int $idProducto, ?int $excluirVentaId = null): float
    {
        $bodegaIds = Compra::query()
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->whereHas('detalles', function ($query) use ($idProducto) {
                $query->where('id_producto', $idProducto);
            })
            ->pluck('id_bodega')
            ->unique()
            ->filter();

        $total = 0.0;
        foreach ($bodegaIds as $idBodega) {
            $total += $this->calcularDisponible($idProducto, (int) $idBodega, $excluirVentaId);
        }

        return $total;
    }

    public function esVentaConsigna(Request $request): bool
    {
        return $request->input('estado') === 'Consigna' || $request->boolean('consigna');
    }

    public function normalizarRequestVentaConsigna(Request $request): void
    {
        if ($this->esVentaConsigna($request)) {
            $request->merge(['estado' => 'Consigna']);
        }
    }

    public function validarVentaConsigna(Request $request): ?string
    {
        if (!$this->esVentaConsigna($request)) {
            return null;
        }

        if (!$request->id_cliente) {
            return 'El cliente es obligatorio para ventas por consigna.';
        }

        return null;
    }

    public function validarOrigenStockEnFacturacion(Request $request): ?string
    {
        if ($request->cotizacion == 1) {
            return null;
        }

        $idBodega = (int) $request->id_bodega;
        $excluirVentaId = $request->id ? (int) $request->id : null;
        $cantidadesPorProducto = $this->agruparCantidadesConsignaCompra($request->detalles ?? []);

        foreach ($cantidadesPorProducto as $idProducto => $cantidadRequerida) {
            $disponible = $this->calcularDisponible((int) $idProducto, $idBodega, $excluirVentaId);
            $producto = Producto::find($idProducto);
            $nombre = $producto ? $producto->nombre : "producto #{$idProducto}";

            if ($disponible <= 0) {
                return "No hay stock en consigna de compra disponible para \"{$nombre}\" en esta bodega.";
            }

            if ($cantidadRequerida > $disponible + 0.0001) {
                return "Stock en consigna de compra insuficiente para \"{$nombre}\". Disponible: "
                    . round($disponible, 2) . ', solicitado: ' . round($cantidadRequerida, 2) . '.';
            }
        }

        return null;
    }

    /**
     * @param  array<int, float>
     */
    private function agruparCantidadesConsignaCompra(array $detalles): array
    {
        $cantidades = [];

        foreach ($detalles as $det) {
            if (empty($det['id_producto'])) {
                continue;
            }

            if (!OrigenStockVentaConstants::esConsignaCompra($det['origen_stock'] ?? null)) {
                continue;
            }

            $idProducto = (int) $det['id_producto'];
            $cantidadBase = $this->cantidadDetalleEnBase($det);

            if (!isset($cantidades[$idProducto])) {
                $cantidades[$idProducto] = 0;
            }
            $cantidades[$idProducto] += $cantidadBase;
        }

        return $cantidades;
    }

    private function cantidadDetalleEnBase(array $det): float
    {
        $factor = 1.0;
        if (!empty($det['id_presentacion'])) {
            $presentacion = ProductoPresentacion::find($det['id_presentacion']);
            if ($presentacion) {
                $factor = (float) $presentacion->factor_conversion;
            }
        }

        return ConversionInventarioService::calcularCantidadBase(
            (float) ($det['cantidad'] ?? 0),
            $factor
        );
    }

    private function sumLiquidadoComprasConsigna(int $idProducto, int $idBodega): float
    {
        return (float) DetalleCompra::query()
            ->where('id_producto', $idProducto)
            ->whereHas('compra', function ($query) use ($idBodega) {
                $query->where('es_consigna', true)
                    ->where('estado', 'Pagada')
                    ->where('id_bodega', $idBodega)
                    ->where('cotizacion', 0);
            })
            ->sum('cantidad');
    }

    private function sumEntradaComprasConsigna(int $idProducto, int $idBodega): float
    {
        return (float) DetalleCompra::query()
            ->where('id_producto', $idProducto)
            ->whereHas('compra', function ($query) use ($idBodega) {
                $query->where('estado', 'Consigna')
                    ->where('id_bodega', $idBodega)
                    ->where('cotizacion', 0);
            })
            ->sum('cantidad');
    }

    public function cantidadVendidaDesdeConsignaCompra(int $idProducto, int $idBodega, ?int $excluirVentaId = null): float
    {
        return $this->sumSalidaVentasDesdeConsignaCompra($idProducto, $idBodega, $excluirVentaId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarVentasDesdeConsignaCompra(int $idProducto, int $idBodega, ?int $excluirVentaId = null): array
    {
        $query = DetalleVenta::query()
            ->where('id_producto', $idProducto)
            ->where('origen_stock', OrigenStockVentaConstants::CONSIGNA_COMPRA)
            ->whereHas('venta', function ($query) use ($idBodega, $excluirVentaId) {
                $query->where('id_bodega', $idBodega)
                    ->where(function ($q) {
                        $q->where('cotizacion', 0)->orWhereNull('cotizacion');
                    });
                if ($excluirVentaId) {
                    $query->where('id', '!=', $excluirVentaId);
                }
            })
            ->with('venta')
            ->orderByDesc('id')
            ->get();

        $ventas = [];

        foreach ($query as $detalle) {
            $venta = $detalle->venta;
            if (!$venta) {
                continue;
            }

            $factor = 1.0;
            if ($detalle->id_presentacion) {
                $presentacion = \App\Models\Inventario\ProductoPresentacion::find($detalle->id_presentacion);
                if ($presentacion) {
                    $factor = (float) $presentacion->factor_conversion;
                }
            }
            $cantidadBase = ConversionInventarioService::calcularCantidadBase((float) $detalle->cantidad, $factor);
            $ventaId = (int) $venta->id;

            if (!isset($ventas[$ventaId])) {
                $ventas[$ventaId] = [
                    'fecha' => $venta->fecha,
                    'cliente' => $venta->nombre_cliente,
                    'cantidad' => 0,
                    'id' => $ventaId,
                    'nombre_documento' => $venta->nombre_documento,
                    'correlativo' => $venta->correlativo,
                    'uuid' => Crypt::encrypt($ventaId),
                ];
            }

            $ventas[$ventaId]['cantidad'] += $cantidadBase;
        }

        return array_values(array_map(function (array $row) {
            $row['cantidad'] = round($row['cantidad'], 4);

            return $row;
        }, $ventas));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarVentasDesdeConsignaCompraAgregado(int $idProducto, ?int $excluirVentaId = null): array
    {
        $bodegaIds = Compra::query()
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->whereHas('detalles', function ($query) use ($idProducto) {
                $query->where('id_producto', $idProducto);
            })
            ->pluck('id_bodega')
            ->unique()
            ->filter();

        $ventasPorId = [];

        foreach ($bodegaIds as $idBodega) {
            foreach ($this->listarVentasDesdeConsignaCompra($idProducto, (int) $idBodega, $excluirVentaId) as $venta) {
                $ventaId = (int) $venta['id'];
                if (!isset($ventasPorId[$ventaId])) {
                    $ventasPorId[$ventaId] = $venta;
                    continue;
                }
                $ventasPorId[$ventaId]['cantidad'] = round(
                    (float) $ventasPorId[$ventaId]['cantidad'] + (float) $venta['cantidad'],
                    4
                );
            }
        }

        return array_values($ventasPorId);
    }

    private function sumSalidaVentasDesdeConsignaCompra(int $idProducto, int $idBodega, ?int $excluirVentaId = null): float
    {
        $query = DetalleVenta::query()
            ->where('id_producto', $idProducto)
            ->where('origen_stock', OrigenStockVentaConstants::CONSIGNA_COMPRA)
            ->whereHas('venta', function ($query) use ($idBodega, $excluirVentaId) {
                $query->where('id_bodega', $idBodega)
                    ->where(function ($q) {
                        $q->where('cotizacion', 0)->orWhereNull('cotizacion');
                    });
                if ($excluirVentaId) {
                    $query->where('id', '!=', $excluirVentaId);
                }
            });

        $total = 0.0;
        foreach ($query->get() as $detalle) {
            $factor = 1.0;
            if ($detalle->id_presentacion) {
                $presentacion = ProductoPresentacion::find($detalle->id_presentacion);
                if ($presentacion) {
                    $factor = (float) $presentacion->factor_conversion;
                }
            }
            $total += ConversionInventarioService::calcularCantidadBase((float) $detalle->cantidad, $factor);
        }

        return $total;
    }

    private function obtenerStockFisico(int $idProducto, int $idBodega): float
    {
        $inventario = Inventario::query()
            ->where('id_producto', $idProducto)
            ->where('id_bodega', $idBodega)
            ->first();

        return $inventario ? (float) $inventario->stock : 0;
    }
}
