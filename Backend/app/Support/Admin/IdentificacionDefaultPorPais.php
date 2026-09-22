<?php

namespace App\Support\Admin;

use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de tipos de identificación por país.
 * Fuente: pais_configuracion (modulo=identificacion); fallback = plantilla en código.
 *
 * @phpstan-type TipoIdentificacion array{codigo: string, label: string, uso: list<string>}
 * @phpstan-type ConfigIdentificacion array{default_persona: string, default_empresa: string, default_extranjero: string, tipos: list<TipoIdentificacion>}
 */
final class IdentificacionDefaultPorPais
{
    /**
     * @return ConfigIdentificacion
     */
    public static function plantilla(string $pais): array
    {
        $pais = strtoupper($pais);

        if ($pais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return [
                'default_persona' => '01',
                'default_empresa' => '02',
                'default_extranjero' => '05',
                'tipos' => [
                    ['codigo' => '01', 'label' => 'Cédula física', 'uso' => ['emisor', 'receptor']],
                    ['codigo' => '02', 'label' => 'Cédula jurídica', 'uso' => ['emisor', 'receptor']],
                    ['codigo' => '03', 'label' => 'DIMEX', 'uso' => ['emisor', 'receptor']],
                    ['codigo' => '04', 'label' => 'NITE', 'uso' => ['emisor', 'receptor']],
                    ['codigo' => '05', 'label' => 'Extranjero no domiciliado', 'uso' => ['emisor', 'receptor']],
                    ['codigo' => '06', 'label' => 'No contribuyente', 'uso' => ['receptor']],
                ],
            ];
        }

        if ($pais === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS) {
            return [
                'default_persona' => 'dni',
                'default_empresa' => 'rtn',
                'default_extranjero' => 'pasaporte',
                'tipos' => [
                    ['codigo' => 'dni', 'label' => 'DNI', 'uso' => ['receptor']],
                    ['codigo' => 'rtn', 'label' => 'RTN', 'uso' => ['receptor']],
                    ['codigo' => 'pasaporte', 'label' => 'Pasaporte', 'uso' => ['receptor']],
                ],
            ];
        }

        return [
            'default_persona' => '13',
            'default_empresa' => '36',
            'default_extranjero' => '03',
            'tipos' => [
                ['codigo' => '13', 'label' => 'DUI', 'uso' => ['receptor']],
                ['codigo' => '36', 'label' => 'NIT', 'uso' => ['receptor']],
                ['codigo' => '03', 'label' => 'Pasaporte', 'uso' => ['receptor']],
                ['codigo' => '02', 'label' => 'Carnet de residente', 'uso' => ['receptor']],
                ['codigo' => '37', 'label' => 'Otro', 'uso' => ['receptor']],
            ],
        ];
    }

    /**
     * @return ConfigIdentificacion
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
                ->modulo(PaisConfiguracion::MODULO_IDENTIFICACION)
                ->first();

            if (! $row || ! is_array($row->configuracion)) {
                return $fallback;
            }

            return self::normalizar($row->configuracion, $fallback);
        } catch (\Throwable $e) {
            // ponytail: sin tabla / sin DB en tests → plantilla en código
            return $fallback;
        }
    }

    public static function defaultParaTipo(string $pais, string $tipoCliente): string
    {
        $cfg = self::configuracion($pais);
        $tipo = strtolower(trim($tipoCliente));

        return match ($tipo) {
            'empresa' => $cfg['default_empresa'],
            'extranjero' => $cfg['default_extranjero'],
            default => $cfg['default_persona'],
        };
    }

    /**
     * @return list<TipoIdentificacion>
     */
    public static function tiposReceptor(string $pais): array
    {
        return self::tiposPorUso($pais, 'receptor');
    }

    /**
     * @return list<TipoIdentificacion>
     */
    public static function tiposEmisor(string $pais): array
    {
        return self::tiposPorUso($pais, 'emisor');
    }

    /**
     * @return list<TipoIdentificacion>
     */
    private static function tiposPorUso(string $pais, string $uso): array
    {
        return array_values(array_filter(
            self::configuracion($pais)['tipos'],
            static fn (array $t) => in_array($uso, $t['uso'] ?? [], true)
        ));
    }

    /**
     * @param array<string, mixed> $cfg
     * @param ConfigIdentificacion $fallback
     * @return ConfigIdentificacion
     */
    private static function normalizar(array $cfg, array $fallback): array
    {
        $tipos = [];
        foreach ($cfg['tipos'] ?? [] as $t) {
            if (! is_array($t) || ! is_string($t['codigo'] ?? null) || $t['codigo'] === '') {
                continue;
            }
            $uso = array_values(array_filter($t['uso'] ?? ['receptor'], 'is_string'));
            $tipos[] = [
                'codigo' => (string) $t['codigo'],
                'label' => is_string($t['label'] ?? null) ? $t['label'] : (string) $t['codigo'],
                'uso' => $uso !== [] ? $uso : ['receptor'],
            ];
        }

        return [
            'default_persona' => is_string($cfg['default_persona'] ?? null) ? $cfg['default_persona'] : $fallback['default_persona'],
            'default_empresa' => is_string($cfg['default_empresa'] ?? null) ? $cfg['default_empresa'] : $fallback['default_empresa'],
            'default_extranjero' => is_string($cfg['default_extranjero'] ?? null) ? $cfg['default_extranjero'] : $fallback['default_extranjero'],
            'tipos' => $tipos !== [] ? $tipos : $fallback['tipos'],
        ];
    }
}
