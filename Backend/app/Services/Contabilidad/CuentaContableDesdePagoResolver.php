<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Services\Bancos\CuentaBancariaResolver;
use Exception;

final class CuentaContableDesdePagoResolver
{
    /**
     * Catálogo contable según el banco elegido en el registro (detalle_banco) o el banco por defecto de la forma de pago.
     *
     * @throws Exception
     */
    public static function resolve(object $registro): Cuenta
    {
        $cuentaBancaria = CuentaBancariaResolver::resolve($registro);

        if (!$cuentaBancaria->id_cuenta_contable) {
            $nombre = trim((string) ($cuentaBancaria->nombre_banco ?? $registro->detalle_banco ?? $registro->forma_pago ?? ''));
            throw new Exception(
                'El banco "' . $nombre . '" no tiene cuenta contable configurada. Configúrela en Finanzas > Bancos.',
                400
            );
        }

        $cuenta = Cuenta::find($cuentaBancaria->id_cuenta_contable);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta contable del banco seleccionado.', 400);
        }

        return $cuenta;
    }
}
