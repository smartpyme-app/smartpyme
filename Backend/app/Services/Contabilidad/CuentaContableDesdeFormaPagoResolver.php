<?php

namespace App\Services\Contabilidad;

use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Exception;

final class CuentaContableDesdeFormaPagoResolver
{
    /**
     * @throws Exception
     */
    public static function resolve(string $nombreFormaPago): Cuenta
    {
        $nombre = trim($nombreFormaPago);
        if ($nombre === '') {
            throw new Exception('La venta no tiene forma de pago asignada', 400);
        }

        $formapago = FormaDePago::with('banco')->where('nombre', $nombre)->first();
        if (!$formapago) {
            throw new Exception('No se encontró la forma de pago: ' . $nombre, 400);
        }

        if (!$formapago->banco || !$formapago->banco->id_cuenta_contable) {
            throw new Exception(
                'La forma de pago no tiene un banco o cuenta contable configurada, para configurarla puede ir al menú de la aplicación, en Finanzas > Métodos de pago',
                400
            );
        }

        $cuenta = Cuenta::find($formapago->banco->id_cuenta_contable);
        if (!$cuenta) {
            throw new Exception(
                'No se encontró la cuenta contable del banco asociado a la forma de pago, para configurarla puede ir al menú de la aplicación, en Finanzas > Bancos, seleccionar el tab de Cuentas y agregar la cuenta contable al banco.',
                400
            );
        }

        return $cuenta;
    }
}
