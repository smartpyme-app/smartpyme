<?php

/**
 * Self-check correlativo HN estilo Accesorios/SANTRÉ.
 * php Backend/scripts/check_ticket_santre_correlativo.php
 */

function numFacturaDisplay(string $correlativo, ?string $prefijo, ?string $prefSucursal = null): string
{
    $corr = str_pad($correlativo, 8, '0', STR_PAD_LEFT);
    if ($prefSucursal !== null && trim($prefSucursal) !== '') {
        return rtrim(trim($prefSucursal), '-').'-'.$corr;
    }
    $pref = trim((string) $prefijo);
    if ($pref !== '') {
        return rtrim($pref, '-').'-'.$corr;
    }

    return $corr;
}

function assertEq(string $label, $got, $expected): void
{
    if ($got !== $expected) {
        fwrite(STDERR, "FAIL $label: got ".json_encode($got).' expected '.json_encode($expected)."\n");
        exit(1);
    }
    echo "OK $label\n";
}

assertEq('pad-only', numFacturaDisplay('439', null), '00000439');
assertEq('prefijo-doc', numFacturaDisplay('439', '001-001-01-'), '001-001-01-00000439');
assertEq('prefijo-sin-guion', numFacturaDisplay('439', '001-001-01'), '001-001-01-00000439');
assertEq('prefijo-sucursal', numFacturaDisplay('439', 'X', '001-001-01-'), '001-001-01-00000439');

echo "All checks passed.\n";
exit(0);
