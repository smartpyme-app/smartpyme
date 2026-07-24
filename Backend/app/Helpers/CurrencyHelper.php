<?php

namespace App\Helpers;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Auth;

class CurrencyHelper
{
    /** Misma tabla que Frontend currency-format.helper.ts */
    private const FALLBACK_SYMBOLS = [
        'USD' => '$',
        'HNL' => 'L',
        'GTQ' => 'Q',
        'CRC' => '₡',
        'NIO' => 'C$',
        'PAB' => 'B/.',
        'BZD' => 'BZ$',
        'MXN' => '$',
        'EUR' => '€',
    ];

    public static function symbol(?Empresa $empresa = null): string
    {
        $empresa = $empresa ?? self::resolveEmpresa();

        if ($empresa) {
            $empresa->loadMissing('currency');
            if ($empresa->currency && $empresa->currency->currency_symbol) {
                return $empresa->currency->currency_symbol;
            }

            $code = strtoupper((string) ($empresa->moneda ?? ''));
            if ($code !== '' && isset(self::FALLBACK_SYMBOLS[$code])) {
                return self::FALLBACK_SYMBOLS[$code];
            }
        }

        return '$';
    }

    public static function excelFormat(?Empresa $empresa = null): string
    {
        $symbol = self::symbol($empresa);

        return '"' . str_replace('"', '""', $symbol) . '"#,##0.00';
    }

    private static function resolveEmpresa(): ?Empresa
    {
        $user = Auth::user();

        if (!$user || !$user->id_empresa) {
            return null;
        }

        return Empresa::with('currency')->find($user->id_empresa);
    }
}
