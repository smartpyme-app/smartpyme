<?php

namespace App\Support\Admin;

/**
 * Config de moneda / tipo de cambio por país.
 * Fuente: pais_configuracion (modulo=moneda); plantilla también la usa el seeder.
 *
 * ponytail: rate_del_dia es cache de un solo día (no historial); el TC usado vive en el documento.
 */
final class MonedaDefaultPorPais
{
    /**
     * @return array{
     *   moneda_funcional: string,
     *   monedas_documento: list<string>,
     *   fuente: 'api'|'manual',
     *   api: array{provider: string}|null,
     *   rate_del_dia: null,
     *   rate_manual: float|null,
     *   permitir_editar: bool
     * }
     */
    public static function plantilla(string $pais): array
    {
        $pais = strtoupper($pais);

        if ($pais === 'CR') {
            return [
                'moneda_funcional' => 'CRC',
                'monedas_documento' => ['CRC', 'USD'],
                'fuente' => 'api',
                'api' => ['provider' => 'bccr'],
                'rate_del_dia' => null,
                'rate_manual' => null,
                'permitir_editar' => false,
            ];
        }

        if ($pais === 'HN') {
            return [
                'moneda_funcional' => 'HNL',
                'monedas_documento' => ['HNL', 'USD'],
                // BCH sugiere TC de referencia; permitir_editar deja ajustar a BAC u otra banca.
                'fuente' => 'api',
                'api' => ['provider' => 'bch'],
                'rate_del_dia' => null,
                'rate_manual' => null,
                'permitir_editar' => true,
            ];
        }

        return [
            'moneda_funcional' => 'USD',
            'monedas_documento' => ['USD'],
            'fuente' => 'manual',
            'api' => null,
            'rate_del_dia' => null,
            'rate_manual' => null,
            'permitir_editar' => false,
        ];
    }
}
