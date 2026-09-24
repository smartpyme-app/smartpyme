<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;

class ShopifyVentaConsolidacionService
{
    private ShopifyTransformer $transformer;
    private ImpuestosService $impuestosService;

    public function __construct(ShopifyTransformer $transformer, ImpuestosService $impuestosService)
    {
        $this->transformer = $transformer;
        $this->impuestosService = $impuestosService;
    }

    public function consolidar(Venta $venta, array $shopifyOrder, User $usuario): array
    {
        if (!empty($venta->sello_mh)) {
            return [
                'status' => 'ignored',
                'venta_id' => $venta->id,
                'mensaje' => 'Venta ya emitida en SmartPyme',
            ];
        }

        DB::transaction(function () use ($venta, $shopifyOrder, $usuario) {
            $lineas = $this->lineasVigentes($shopifyOrder);
            $this->quitarLineasAusentes($venta, $lineas);
            $this->aplicarLineas($venta, $lineas, $shopifyOrder, $usuario);
            $this->aplicarEnvios($venta, $shopifyOrder, $usuario);
            $this->aplicarCabecera($venta, $shopifyOrder, $usuario);
        });

        return [
            'status' => 'ok',
            'venta_id' => $venta->id,
            'mensaje' => 'Venta consolidada con Shopify',
        ];
    }

    private function lineasVigentes(array $shopifyOrder): array
    {
        $lineas = [];
        foreach ($shopifyOrder['line_items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cantidad = isset($item['current_quantity']) && is_numeric($item['current_quantity'])
                ? (float) $item['current_quantity']
                : (float) ($item['quantity'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }
            $item['current_quantity'] = $cantidad;
            $lineas[] = $item;
        }

        return $lineas;
    }

    private function quitarLineasAusentes(Venta $venta, array $lineas): void
    {
        $variantes = [];
        foreach ($lineas as $item) {
            if (!empty($item['variant_id'])) {
                $variantes[] = (string) $item['variant_id'];
            }
        }

        $detalles = $venta->detalles()->with('producto.categoria')->get();
        foreach ($detalles as $detalle) {
            $producto = $detalle->producto;
            if ($this->esEnvio($producto)) {
                continue;
            }
            $variante = $producto ? (string) $producto->shopify_variant_id : '';
            if ($variante !== '' && in_array($variante, $variantes, true)) {
                continue;
            }
            $this->moverStock($venta, $producto, -1 * (float) $detalle->cantidad, (float) $detalle->precio);
            $detalle->delete();
        }
    }

    private function aplicarLineas(Venta $venta, array $lineas, array $shopifyOrder, User $usuario): void
    {
        $taxesIncluded = (bool) ($shopifyOrder['taxes_included'] ?? false);

        foreach ($lineas as $item) {
            $producto = $this->resolverProducto($item, $venta, $usuario);
            if (!$producto) {
                continue;
            }

            $cantidad = (float) $item['current_quantity'];
            $item['quantity'] = $cantidad;
            $datos = $this->transformer->transformarDetallesVenta($item, $venta->id, $venta->id_empresa, $taxesIncluded);
            $datos['id_producto'] = $producto->id;
            $datos['cantidad'] = $cantidad;
            unset($datos['subtotal']);

            $detalle = $venta->detalles()
                ->where('id_producto', $producto->id)
                ->first();

            if ($detalle) {
                $anterior = (float) $detalle->cantidad;
                $detalle->update($datos);
                $this->moverStock($venta, $producto, $cantidad - $anterior, (float) ($datos['precio'] ?? 0));
                continue;
            }

            $venta->detalles()->create($datos);
            $this->moverStock($venta, $producto, $cantidad, (float) ($datos['precio'] ?? 0));
        }
    }

    private function aplicarEnvios(Venta $venta, array $shopifyOrder, User $usuario): void
    {
        $activos = [];
        foreach ($shopifyOrder['shipping_lines'] ?? [] as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $titulo = $linea['title'] ?? '';
            $precio = (float) ($linea['discounted_price'] ?? $linea['price'] ?? 0);
            if ($titulo === '' || $precio <= 0 || !empty($linea['is_removed'])) {
                continue;
            }
            $activos[$titulo] = $linea;
        }

        $existentes = $venta->detalles()->with('producto.categoria')->get()->filter(function ($detalle) {
            return $this->esEnvio($detalle->producto);
        });

        foreach ($existentes as $detalle) {
            if (!isset($activos[$detalle->descripcion])) {
                $detalle->delete();
            }
        }

        foreach ($activos as $titulo => $linea) {
            $detalle = $venta->detalles()->where('descripcion', $titulo)->whereHas('producto', function ($query) {
                $query->where('tipo', 'Servicio');
            })->first();

            $montos = $this->montosEnvio($linea, $venta->id_empresa);
            if ($detalle) {
                $detalle->update($montos);
                continue;
            }

            $producto = $this->servicioEnvio($titulo, $montos['precio'], $venta->id_empresa, $usuario->id);
            $venta->detalles()->create(array_merge($montos, [
                'id_producto' => $producto->id,
                'descripcion' => $titulo,
                'cantidad' => 1,
                'id_vendedor' => $usuario->id,
            ]));
        }
    }

    private function aplicarCabecera(Venta $venta, array $shopifyOrder, User $usuario): void
    {
        $datos = array_merge($shopifyOrder, [
            'id_empresa' => $venta->id_empresa,
            'id_bodega' => $venta->id_bodega,
            'id_usuario' => $usuario->id,
            'id_sucursal' => $usuario->id_sucursal,
            'id_canal' => $venta->id_canal ?? null,
        ]);

        $cabecera = $this->transformer->transformarVenta(
            $datos,
            $venta->id_cliente,
            $venta->id_documento ?? null,
            $venta->correlativo
        );

        $venta->update([
            'total' => $cabecera['total'],
            'sub_total' => $cabecera['sub_total'],
            'gravada' => $cabecera['gravada'],
            'exenta' => $cabecera['exenta'],
            'iva' => $cabecera['iva'],
            'descuento' => $cabecera['descuento'],
            'monto_pago' => $cabecera['monto_pago'],
            'estado' => $cabecera['estado'],
        ]);
    }

    private function resolverProducto(array $item, Venta $venta, User $usuario): ?Producto
    {
        $producto = null;
        if (!empty($item['variant_id'])) {
            $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                ->where('id_empresa', $venta->id_empresa)
                ->orderBy('id', 'desc')
                ->first();
        }
        if ($producto) {
            return $producto;
        }

        $datos = $this->transformer->transformarProducto(
            $item,
            $venta->id_empresa,
            $usuario->id,
            $usuario->id_sucursal
        );

        return Producto::create($datos);
    }

    private function servicioEnvio(string $titulo, float $precio, int $empresaId, int $usuarioId): Producto
    {
        $existente = Producto::where('nombre', $titulo)
            ->where('id_empresa', $empresaId)
            ->where('tipo', 'Servicio')
            ->whereHas('categoria', function ($query) {
                $query->where('nombre', 'envios');
            })
            ->first();

        if ($existente) {
            return $existente;
        }

        $categoria = Categoria::firstOrCreate(
            ['nombre' => 'envios', 'id_empresa' => $empresaId],
            ['descripcion' => 'Categoría para servicios de envío desde Shopify', 'enable' => 1]
        );

        return Producto::create([
            'nombre' => $titulo,
            'descripcion' => 'Servicio de envío desde Shopify: ' . $titulo,
            'codigo' => 'ENVIO-' . strtoupper(substr(md5($titulo), 0, 8)),
            'tipo' => 'Servicio',
            'precio' => $precio,
            'costo' => 0,
            'id_categoria' => $categoria->id,
            'id_empresa' => $empresaId,
            'id_usuario' => $usuarioId,
            'enable' => 1,
        ]);
    }

    private function montosEnvio(array $linea, int $empresaId): array
    {
        $precio = (float) ($linea['discounted_price'] ?? $linea['price'] ?? 0);
        $tieneIva = !empty($linea['tax_lines']);
        if ($tieneIva) {
            $base = $this->impuestosService->calcularPrecioSinImpuesto($precio, $empresaId);
            $iva = round($precio - $base, 2);

            return [
                'precio' => $base,
                'precio_sin_iva' => $base,
                'precio_con_iva' => $precio,
                'descuento' => 0,
                'total' => $base,
                'gravada' => $base,
                'exenta' => 0,
                'no_sujeta' => 0,
                'iva' => $iva,
                'costo' => 0,
            ];
        }

        return [
            'precio' => $precio,
            'precio_sin_iva' => $precio,
            'precio_con_iva' => $precio,
            'descuento' => 0,
            'total' => $precio,
            'gravada' => 0,
            'exenta' => $precio,
            'no_sujeta' => 0,
            'iva' => 0,
            'costo' => 0,
        ];
    }

    private function moverStock(Venta $venta, ?Producto $producto, float $delta, float $precio): void
    {
        if (!$producto || abs($delta) < 0.00001 || $producto->tipo === 'Servicio') {
            return;
        }

        $inventario = Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $venta->id_bodega)
            ->first();
        if (!$inventario) {
            return;
        }

        if ($delta > 0) {
            $inventario->decrement('stock', $delta);
        } else {
            $inventario->increment('stock', abs($delta));
        }
        $inventario->refresh();
        $inventario->kardex($venta, $delta, $precio, $producto->costo, null, ['origen' => 'shopify']);
    }

    private function esEnvio(?Producto $producto): bool
    {
        if (!$producto || $producto->tipo !== 'Servicio') {
            return false;
        }
        if (!$producto->relationLoaded('categoria')) {
            $producto->load('categoria');
        }

        return ($producto->categoria->nombre ?? null) === 'envios';
    }
}
