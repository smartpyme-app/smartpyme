<?php

namespace App\Services\Contabilidad\Partidas;

class ReglaCuentaIva
{
    public static function tipoDe(object $doc): string
    {
        if (! empty($doc->nombre_documento)) {
            return (string) $doc->nombre_documento;
        }
        if (isset($doc->documento) && is_object($doc->documento) && ! empty($doc->documento->nombre)) {
            return (string) $doc->documento->nombre;
        }
        if (! empty($doc->tipo_documento)) {
            return (string) $doc->tipo_documento;
        }

        return '';
    }

    public static function esCreditoFiscal(string $tipo): bool
    {
        $n = self::normalizar($tipo);
        if ($n === '') {
            return false;
        }

        return $n === 'ccf' || str_contains($n, 'credito fiscal');
    }

    public static function idCuentaVentas(object $config, string $tipo): ?int
    {
        return self::idSegunTipo(
            $config,
            $tipo,
            $config->id_cuenta_iva_ventas ?? null,
            $config->id_cuenta_iva_ventas_cf ?? null
        );
    }

    public static function idCuentaCompras(object $config, string $tipo): ?int
    {
        return self::idSegunTipo(
            $config,
            $tipo,
            $config->id_cuenta_iva_compras ?? null,
            $config->id_cuenta_iva_compras_cf ?? null
        );
    }

    private static function idSegunTipo(object $config, string $tipo, mixed $general, mixed $cf): ?int
    {
        $idGeneral = self::id($general);
        if (empty($config->separar_cuentas_iva) || self::esCreditoFiscal($tipo)) {
            return $idGeneral;
        }

        return self::id($cf) ?? $idGeneral;
    }

    private static function id(mixed $value): ?int
    {
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }

    private static function normalizar(string $tipo): string
    {
        $n = mb_strtolower(trim($tipo));
        $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return $n;
    }
}
