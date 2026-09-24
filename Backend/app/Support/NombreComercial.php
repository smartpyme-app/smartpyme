<?php

namespace App\Support;

class NombreComercial
{
    public static function anexar(?string $legal, ?string $comercial): string
    {
        $legalRaw = (string) $legal;
        $comercial = trim((string) $comercial);
        if ($comercial === '' || strcasecmp($comercial, trim($legalRaw)) === 0) {
            return $legalRaw;
        }

        $base = trim($legalRaw);
        if ($base === '') {
            return $comercial;
        }

        return $base.' ('.$comercial.')';
    }

    /**
     * Nombre del documento: razón social y, si hay, el comercial entre paréntesis.
     * $origen puede ser el cliente, el proveedor o el registro (venta, compra, gasto).
     */
    public static function mostrar(?string $legal, mixed $origen = null): string
    {
        return self::anexar($legal, self::comercialDe($origen));
    }

    public static function comercialDe(mixed $origen): string
    {
        if (! is_object($origen)) {
            return '';
        }

        $parte = null;
        if (method_exists($origen, 'cliente')) {
            $parte = $origen->cliente;
        }
        if (! is_object($parte) && method_exists($origen, 'proveedor')) {
            $parte = $origen->proveedor;
        }
        if (! is_object($parte) && isset($origen->nombre_comercial)) {
            $parte = $origen;
        }
        if (! is_object($parte)) {
            return '';
        }

        return trim((string) ($parte->nombre_comercial ?? ''));
    }
}
