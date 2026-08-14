<?php

/**
 * Self-check correlativo HN estilo Accesorios/SANTRÉ.
 * php Backend/scripts/check_ticket_santre_correlativo.php
 */

function prefijoDesdeRango(string $rango): string
{
    if (preg_match('/(\d{3}-\d{3}-\d{2}-)/', $rango, $m)) {
        return $m[1];
    }

    return '';
}

function numFacturaDisplay(string $correlativo, ?string $prefijo, ?string $prefSucursal = null, ?string $rango = null): string
{
    $corr = str_pad($correlativo, 8, '0', STR_PAD_LEFT);
    $pref = '';
    if ($prefSucursal !== null && trim($prefSucursal) !== '') {
        $pref = trim($prefSucursal);
    } elseif ($prefijo !== null && trim($prefijo) !== '') {
        $pref = trim($prefijo);
    } elseif ($rango !== null && $rango !== '') {
        $pref = prefijoDesdeRango($rango);
    }

    return $pref !== '' ? rtrim($pref, '-').'-'.$corr : $corr;
}

function assertEq(string $label, $got, $expected): void
{
    if ($got !== $expected) {
        fwrite(STDERR, "FAIL $label: got ".json_encode($got).' expected '.json_encode($expected)."\n");
        exit(1);
    }
    echo "OK $label\n";
}

assertEq('pad-only', numFacturaDisplay('1', null), '00000001');
assertEq('prefijo-doc', numFacturaDisplay('439', '001-001-01-'), '001-001-01-00000439');
assertEq('prefijo-sin-guion', numFacturaDisplay('439', '001-001-01'), '001-001-01-00000439');
assertEq('prefijo-sucursal', numFacturaDisplay('1', null, '001-001-01-'), '001-001-01-00000001');
assertEq(
    'desde-rango',
    numFacturaDisplay('1', null, null, '001-001-01-00000001 A 001-001-01-00003000'),
    '001-001-01-00000001'
);
assertEq('rango-vacio-sin-pref', numFacturaDisplay('1', null, null, ''), '00000001');

echo "All checks passed.\n";
exit(0);
