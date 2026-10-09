<?php

namespace App\Support\Ventas;

final class ComparadorNombreProducto
{
    public static function normalize(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_strtolower($text, 'UTF-8');
    }

    public static function coincideExacto(?string $descripcionDetalle, ?string $nombreProducto): bool
    {
        $a = self::normalize($descripcionDetalle);
        $b = self::normalize($nombreProducto);

        return $a !== '' && $a === $b;
    }
}
