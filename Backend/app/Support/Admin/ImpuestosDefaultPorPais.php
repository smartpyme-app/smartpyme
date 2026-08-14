<?php

namespace App\Support\Admin;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Facades\Schema;

/**
 * Defaults fiscales por país (moneda, IVA, percepción, retención).
 * Fuente: pais_configuracion (modulo=impuestos); fallback = plantilla en código.
 */
final class ImpuestosDefaultPorPais
{
    /**
     * @return array{moneda: string, iva: float, percepcion: float, retencion_iva: float}
     */
    public static function plantilla(string $pais): array
    {
        $pais = strtoupper($pais);

        $map = [
            'SV' => ['moneda' => 'USD', 'iva' => 13.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'CR' => ['moneda' => 'CRC', 'iva' => 13.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'HN' => ['moneda' => 'HNL', 'iva' => 15.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'GT' => ['moneda' => 'GTQ', 'iva' => 12.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'NI' => ['moneda' => 'NIO', 'iva' => 15.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'PA' => ['moneda' => 'PAB', 'iva' => 7.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'BZ' => ['moneda' => 'BZD', 'iva' => 12.5, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
            'MX' => ['moneda' => 'MXN', 'iva' => 16.0, 'percepcion' => 1.0, 'retencion_iva' => 1.0],
        ];

        return $map[$pais] ?? $map['SV'];
    }

    /**
     * @return array{moneda: string, iva: float, percepcion: float, retencion_iva: float}
     */
    public static function configuracion(string $pais): array
    {
        $pais = strtoupper($pais);
        $fallback = self::plantilla($pais);

        try {
            if (! Schema::hasTable('pais_configuracion')) {
                return $fallback;
            }

            $row = PaisConfiguracion::query()
                ->pais($pais)
                ->modulo(PaisConfiguracion::MODULO_IMPUESTOS)
                ->first();

            if (! $row || ! is_array($row->configuracion)) {
                return $fallback;
            }

            $cfg = $row->configuracion;

            return [
                'moneda' => is_string($cfg['moneda'] ?? null) && $cfg['moneda'] !== ''
                    ? $cfg['moneda']
                    : $fallback['moneda'],
                'iva' => isset($cfg['iva']) ? (float) $cfg['iva'] : $fallback['iva'],
                'percepcion' => isset($cfg['percepcion']) ? (float) $cfg['percepcion'] : $fallback['percepcion'],
                'retencion_iva' => isset($cfg['retencion_iva']) ? (float) $cfg['retencion_iva'] : $fallback['retencion_iva'],
            ];
        } catch (\Throwable $e) {
            // ponytail: tests / sin DB → plantilla
            return $fallback;
        }
    }

    public static function paraEmpresa(?Empresa $empresa): array
    {
        $pais = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);

        return self::configuracion($pais);
    }

    /** IVA % default del país (si la empresa no tiene iva). */
    public static function ivaFallback(?Empresa $empresa): float
    {
        if ($empresa && $empresa->iva !== null && $empresa->iva !== '') {
            return (float) $empresa->iva;
        }

        return self::paraEmpresa($empresa)['iva'];
    }

    /** Fracción de percepción (1 → 0.01). */
    public static function fraccionPercepcion(?Empresa $empresa = null): float
    {
        return self::paraEmpresa($empresa)['percepcion'] / 100.0;
    }

    /** Fracción de retención IVA (1 → 0.01). */
    public static function fraccionRetencionIva(?Empresa $empresa = null): float
    {
        return self::paraEmpresa($empresa)['retencion_iva'] / 100.0;
    }
}
