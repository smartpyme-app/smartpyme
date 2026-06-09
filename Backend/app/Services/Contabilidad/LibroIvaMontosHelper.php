<?php

namespace App\Services\Contabilidad;

/**
 * Clasificación de montos exentos y gravados para libros de IVA (ventas y compras).
 */
class LibroIvaMontosHelper
{
    public static function ventasExentas(object $documento): float
    {
        $exenta = (float) ($documento->exenta ?? 0);
        if ($exenta > 0) {
            return round($exenta, 2);
        }

        if ((float) ($documento->iva ?? 0) <= 0) {
            return round(
                max(0, (float) ($documento->sub_total ?? 0) - (float) ($documento->no_sujeta ?? 0)),
                2
            );
        }

        return 0.0;
    }

    public static function ventasGravadas(object $documento): float
    {
        $gravada = (float) ($documento->gravada ?? 0);
        if ($gravada > 0) {
            return round($gravada, 2);
        }

        if ((float) ($documento->iva ?? 0) > 0) {
            return round(
                max(
                    0,
                    (float) ($documento->sub_total ?? 0)
                        - (float) ($documento->exenta ?? 0)
                        - (float) ($documento->no_sujeta ?? 0)
                ),
                2
            );
        }

        return 0.0;
    }

    public static function comprasExentas(object $documento): float
    {
        if (isset($documento->exenta)) {
            $exenta = (float) $documento->exenta;
            if ($exenta > 0) {
                return round($exenta, 2);
            }
        }

        $desdeDetalles = self::sumDetalleEgresosPorTipo($documento, 'exenta');
        if ($desdeDetalles > 0) {
            return $desdeDetalles;
        }

        if ((float) ($documento->iva ?? 0) <= 0) {
            return round(
                max(0, (float) ($documento->sub_total ?? 0) - (float) ($documento->no_sujeta ?? 0)),
                2
            );
        }

        return 0.0;
    }

    public static function comprasGravadas(object $documento): float
    {
        $desdeDetalles = self::sumDetalleEgresosPorTipo($documento, 'gravada');
        if ($desdeDetalles > 0) {
            return $desdeDetalles;
        }

        if ((float) ($documento->iva ?? 0) > 0) {
            return round(
                max(
                    0,
                    (float) ($documento->sub_total ?? 0)
                        - self::comprasExentas($documento)
                        - (float) ($documento->no_sujeta ?? 0)
                ),
                2
            );
        }

        return 0.0;
    }

    public static function comprasNoSujetas(object $documento): float
    {
        $noSujeta = (float) ($documento->no_sujeta ?? 0);
        if ($noSujeta > 0) {
            return round($noSujeta, 2);
        }

        return self::sumDetalleEgresosPorTipo($documento, 'no_sujeta');
    }

    public static function esImportacion(object $documento): bool
    {
        return ($documento->tipo_documento ?? '') === 'Importación';
    }

    /**
     * @return array{
     *     compras_exentas: float,
     *     no_sujeta: float,
     *     importaciones_exentas: float,
     *     compras_gravadas: float,
     *     importaciones_gravadas: float,
     *     credito_fiscal: float,
     *     total: float
     * }
     */
    public static function columnasCompra(object $documento, float $multiplier = 1.0): array
    {
        $exenta = self::comprasExentas($documento) * $multiplier;
        $gravada = self::comprasGravadas($documento) * $multiplier;
        $noSujeta = self::comprasNoSujetas($documento) * $multiplier;

        $columnas = [
            'compras_exentas' => 0.0,
            'no_sujeta' => round($noSujeta, 2),
            'importaciones_exentas' => 0.0,
            'compras_gravadas' => 0.0,
            'importaciones_gravadas' => 0.0,
            'credito_fiscal' => round((float) ($documento->iva ?? 0) * $multiplier, 2),
            'total' => round((float) ($documento->total ?? 0) * $multiplier, 2),
        ];

        if (self::esImportacion($documento)) {
            $columnas['importaciones_exentas'] = round($exenta, 2);
            $columnas['importaciones_gravadas'] = round($gravada, 2);
        } else {
            $columnas['compras_exentas'] = round($exenta, 2);
            $columnas['compras_gravadas'] = round($gravada, 2);
        }

        return $columnas;
    }

    private static function sumDetalleEgresosPorTipo(object $documento, string $tipoGravado): float
    {
        if (!method_exists($documento, 'detalles')) {
            return 0.0;
        }

        $detalles = $documento->relationLoaded('detalles')
            ? $documento->detalles
            : $documento->detalles()->get();

        if ($detalles->isEmpty()) {
            return 0.0;
        }

        return round(
            (float) $detalles->where('tipo_gravado', $tipoGravado)->sum('sub_total'),
            2
        );
    }
}
