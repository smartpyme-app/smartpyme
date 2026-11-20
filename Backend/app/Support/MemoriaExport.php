<?php

namespace App\Support;

/**
 * PhpSpreadsheet arma el .xlsx completo en memoria. Para descargas grandes se amplía el
 * memory_limit sólo en esa petición, sin tocar el límite global (128 MB) del resto del sistema.
 */
final class MemoriaExport
{
    public static function ampliar(string $limite = '512M'): void
    {
        if (self::aBytes((string) ini_get('memory_limit')) < self::aBytes($limite)) {
            ini_set('memory_limit', $limite);
        }
    }

    private static function aBytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '-1') {
            return PHP_INT_MAX;
        }
        $numero = (int) $valor;
        switch (strtolower(substr($valor, -1))) {
            case 'g':
                return $numero * 1024 * 1024 * 1024;
            case 'm':
                return $numero * 1024 * 1024;
            case 'k':
                return $numero * 1024;
        }

        return $numero;
    }
}
