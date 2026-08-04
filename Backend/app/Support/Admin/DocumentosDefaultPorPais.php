<?php

namespace App\Support\Admin;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Facades\Schema;

/**
 * Nombres de documentos por país.
 * Fuente: pais_configuracion (modulo=documentos); fallback = plantilla en código.
 * Alineado con Frontend documento-nombre-options.ts.
 */
final class DocumentosDefaultPorPais
{
    public const CR_TIQUETE = 'Tiquete Electrónico';

    public const CR_FACTURA = 'Factura Electrónica';

    /**
     * Plantillas embebidas (también las usa el seeder).
     *
     * @return array{nombres: list<string>, seed: list<string>}
     */
    public static function plantilla(string $pais): array
    {
        $pais = strtoupper($pais);

        if ($pais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            $nombres = [
                self::CR_FACTURA,
                self::CR_TIQUETE,
                'Cotización',
                'Recibo',
                'Orden de compra',
                'Factura Electrónica de Compra',
                'Nota de Crédito Electrónica',
                'Nota de Débito Electrónica',
                'Abono de Venta',
            ];

            return [
                'nombres' => $nombres,
                'seed' => [
                    self::CR_TIQUETE,
                    self::CR_FACTURA,
                    'Cotización',
                    'Orden de compra',
                ],
            ];
        }

        if ($pais === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS) {
            // Alineado con Frontend DOCUMENTO_NOMBRE_OPCIONES_HN
            $nombres = [
                'Factura con RTN',
                'Factura sin RTN',
                'Factura', // legacy
                'Ticket',
                'Boleta de compra',
                'Nota de crédito',
                'Nota de débito',
                'Recibo por honorarios profesionales',
                'Guía de remisión',
                'Comprobante de retención',
                'Cotización',
                'Orden de compra',
                'Recibo',
                'Abono de Venta',
            ];

            return [
                'nombres' => $nombres,
                'seed' => [
                    'Ticket',
                    'Factura sin RTN',
                    'Cotización',
                    'Orden de compra',
                ],
            ];
        }

        // SV (default)
        $nombres = [
            'Factura',
            'Crédito fiscal',
            'Ticket',
            'Cotización',
            'Recibo',
            'Orden de compra',
            'Nota de crédito',
            'Nota de débito',
            'Sujeto excluido',
            'Factura de exportación',
            'Abono de Venta',
            'Factura comercial',
        ];

        return [
            'nombres' => $nombres,
            // literales: plantilla usable sin bootstrap Laravel (tests)
            'seed' => [
                'Ticket',
                'Factura',
                'Crédito fiscal',
                'Cotización',
                'Orden de compra',
            ],
        ];
    }

    /** @return array{nombres: list<string>, seed: list<string>} */
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
                ->modulo(PaisConfiguracion::MODULO_DOCUMENTOS)
                ->first();

            if (! $row || ! is_array($row->configuracion)) {
                return $fallback;
            }

            $cfg = $row->configuracion;
            $nombres = array_values(array_filter($cfg['nombres'] ?? [], 'is_string'));
            $seed = array_values(array_filter($cfg['seed'] ?? [], 'is_string'));

            return [
                'nombres' => $nombres !== [] ? $nombres : $fallback['nombres'],
                'seed' => $seed !== [] ? $seed : ($nombres !== [] ? $nombres : $fallback['seed']),
            ];
        } catch (\Throwable $e) {
            // ponytail: sin tabla / sin DB en tests → plantilla en código
            return $fallback;
        }
    }

    /** @return list<string> opciones de nombre para UI */
    public static function opciones(?string $pais = null): array
    {
        $pais = $pais
            ? strtoupper($pais)
            : FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR;

        return self::configuracion($pais)['nombres'];
    }

    /** @return list<string> nombres a crear al alta de empresa/sucursal */
    public static function nombres(?Empresa $empresa): array
    {
        $pais = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);

        return self::configuracion($pais)['seed'];
    }
}
