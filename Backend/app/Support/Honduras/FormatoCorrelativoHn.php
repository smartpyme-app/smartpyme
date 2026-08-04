<?php

namespace App\Support\Honduras;

final class FormatoCorrelativoHn
{
    public static function format(?string $numeroEmision, string|int|null $correlativo): string
    {
        $corr = (string) ($correlativo ?? '');
        $em = trim((string) ($numeroEmision ?? ''));
        if ($em === '') {
            return $corr;
        }
        $nn = str_pad(preg_replace('/\D/', '', $em) ?: '0', 2, '0', STR_PAD_LEFT);
        $digits = preg_replace('/\D/', '', $corr) ?: '0';
        return '001-001-' . $nn . '-' . str_pad($digits, 8, '0', STR_PAD_LEFT);
    }
}
