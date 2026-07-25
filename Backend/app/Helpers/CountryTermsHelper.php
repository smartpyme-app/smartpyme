<?php

namespace App\Helpers;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Términos de UI/documentos genéricos por país (espejo de Frontend country.tax.*).
 * No usar en DTE, FE-CR ni formatos_empresas.
 */
class CountryTermsHelper
{
    private const SUPPORTED = ['SV', 'HN', 'CR', 'GT'];

    private const FALLBACK = 'SV';

    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    public static function code(?Empresa $empresa = null): string
    {
        $empresa = $empresa ?? self::resolveEmpresa();
        $code = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);

        return in_array($code, self::SUPPORTED, true) ? $code : self::FALLBACK;
    }

    /**
     * @param  array<string, string|int|float>  $replace  Placeholders Laravel (:rate)
     */
    public static function tax(string $key, ?Empresa $empresa = null, array $replace = []): string
    {
        return self::get('tax.'.$key, $empresa, $replace);
    }

    /**
     * @param  array<string, string|int|float>  $replace
     */
    public static function get(string $dotKey, ?Empresa $empresa = null, array $replace = []): string
    {
        $terms = self::terms(self::code($empresa));
        $value = data_get($terms, $dotKey);

        if (! is_string($value) || $value === '') {
            $value = data_get(self::terms(self::FALLBACK), $dotKey, $dotKey);
        }

        if (! is_string($value)) {
            return $dotKey;
        }

        foreach ($replace as $k => $v) {
            $value = str_replace(':'.$k, (string) $v, $value);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function terms(string $code): array
    {
        if (isset(self::$cache[$code])) {
            return self::$cache[$code];
        }

        $path = resource_path('lang/country/'.$code.'.php');
        if (! is_file($path)) {
            $path = resource_path('lang/country/'.self::FALLBACK.'.php');
        }

        /** @var array<string, mixed> $loaded */
        $loaded = is_file($path) ? require $path : [];
        self::$cache[$code] = $loaded;

        return self::$cache[$code];
    }

    private static function resolveEmpresa(): ?Empresa
    {
        $user = Auth::user();
        if (! $user || ! $user->id_empresa) {
            return null;
        }

        return Empresa::find($user->id_empresa);
    }
}
