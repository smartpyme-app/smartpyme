<?php

namespace App\Imports\Concerns;

trait ParsesComandaExcelColumns
{
    /**
     * @param  mixed  $value
     */
    protected function parseGeneraComanda($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value != 0.0;
        }
        $s = mb_strtolower(trim((string) $value));
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $s);

        return in_array($s, ['1', 'si', 'sí', 'true', 'yes', 'y', 'x', 'verdadero'], true);
    }

    /**
     * @param  mixed  $value
     */
    protected function parseDestinoComanda($value, bool $generaComanda): string
    {
        if (! $generaComanda) {
            return 'cocina';
        }
        $s = mb_strtolower(trim((string) ($value ?? '')));
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $s);
        if (in_array($s, ['barra', 'bar', 'bebidas'], true)) {
            return 'barra';
        }
        if (in_array($s, ['ambos', 'cocina y barra', 'cocina_y_barra', 'cocina/barra'], true)) {
            return 'ambos';
        }

        return 'cocina';
    }
}
