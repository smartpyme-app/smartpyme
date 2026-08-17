<?php

namespace App\Support\Restaurante;

final class PresentacionPos
{
    public static function nombreMostrar(?string $nombreComercial, ?string $nombreProducto): string
    {
        $prod = trim((string) $nombreProducto);
        $com = trim((string) $nombreComercial);
        if ($com === '') {
            return $prod === '' ? 'Producto' : $prod;
        }

        return $com.' ('.($prod !== '' ? $prod : 'Producto').')';
    }

    public static function cantidadBase(float $cantidad, ?float $factor): float
    {
        $f = ($factor !== null && $factor > 0) ? $factor : 1.0;

        return round($cantidad * $f, 4);
    }
}
