<?php

namespace App\Services\Bancos;

use App\Models\Admin\FormaDePago;
use App\Models\Bancos\Cuenta;
use Exception;
use Illuminate\Support\Facades\Auth;

class CuentaBancariaResolver
{
    /**
     * @throws Exception
     */
    public static function resolve(object $registro): Cuenta
    {
        $empresaId = Auth::user()->id_empresa;
        $detalle = trim((string) ($registro->detalle_banco ?? ''));

        if ($detalle !== '') {
            $cuenta = Cuenta::where('id_empresa', $empresaId)
                ->where('nombre_banco', $detalle)
                ->first();

            if ($cuenta) {
                return $cuenta;
            }

            throw new Exception(
                'No se encontró la cuenta bancaria "' . $detalle . '" configurada para su empresa',
                400
            );
        }

        $formaPago = FormaDePago::with('banco')
            ->where('nombre', $registro->forma_pago ?? '')
            ->first();

        if ($formaPago?->banco) {
            return $formaPago->banco;
        }

        throw new Exception(
            'La forma de pago "' . ($registro->forma_pago ?? '') . '" no tiene un banco configurado. Seleccione el banco de la transacción.',
            400
        );
    }
}
