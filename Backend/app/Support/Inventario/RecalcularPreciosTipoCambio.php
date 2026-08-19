<?php

namespace App\Support\Inventario;

use InvalidArgumentException;

/**
 * Recálculo de precios de catálogo HN: precio × (TC venta / TC catálogo).
 */
final class RecalcularPreciosTipoCambio
{
    public const KEY_VENTA = 'tipo_cambio_venta';

    public const KEY_CATALOGO = 'tipo_cambio_catalogo';

    public static function factor(float $nuevo, float $catalogo): float
    {
        if ($nuevo <= 0 || $catalogo <= 0) {
            throw new InvalidArgumentException('El tipo de cambio debe ser mayor que cero.');
        }

        return $nuevo / $catalogo;
    }

    public static function escalar(float $precio, float $factor): float
    {
        return round($precio * $factor, 2);
    }

    /**
     * @return list<string>
     */
    public static function tipos(bool $productos, bool $servicios): array
    {
        $tipos = [];
        if ($productos) {
            $tipos[] = 'Producto';
            $tipos[] = 'Compuesto';
        }
        if ($servicios) {
            $tipos[] = 'Servicio';
        }
        if ($tipos === []) {
            throw new InvalidArgumentException('Seleccione productos y/o servicios.');
        }

        return $tipos;
    }

    /**
     * @param  array<string, mixed>  $producto
     * @return array<string, mixed>
     */
    public static function aplicarAProducto(array $producto, float $factor): array
    {
        foreach (['precio', 'precio_sin_iva', 'precio_con_iva'] as $campo) {
            if (! array_key_exists($campo, $producto) || $producto[$campo] === null || $producto[$campo] === '') {
                continue;
            }
            $producto[$campo] = self::escalar((float) $producto[$campo], $factor);
        }

        if (! empty($producto['precios']) && is_array($producto['precios'])) {
            foreach ($producto['precios'] as $i => $fila) {
                if (! is_array($fila) || ! isset($fila['precio'])) {
                    continue;
                }
                $producto['precios'][$i]['precio'] = self::escalar((float) $fila['precio'], $factor);
            }
        }

        return $producto;
    }

    /**
     * @return array{sembrar: bool, catalogo: float, venta: float}
     */
    public static function sembrarCatalogoSiFalta(?float $catalogo, float $venta): array
    {
        if ($venta <= 0) {
            throw new InvalidArgumentException('El tipo de cambio debe ser mayor que cero.');
        }
        if ($catalogo === null || $catalogo <= 0) {
            return ['sembrar' => true, 'catalogo' => $venta, 'venta' => $venta];
        }

        return ['sembrar' => false, 'catalogo' => $catalogo, 'venta' => $venta];
    }

    public static function sugeridoVenta(?float $ventaEmpresa, ?float $sugeridoApi): ?float
    {
        if ($ventaEmpresa !== null && $ventaEmpresa > 0) {
            return $ventaEmpresa;
        }

        return $sugeridoApi;
    }
}
