<?php

namespace App\Services\Ventas;

use App\Exceptions\FacturacionException;
use App\Models\User;
use Illuminate\Http\Request;

final class VentaDescuentoAutorizacion
{
    public const PERMISO_APLICAR = 'ventas.descuentos.aplicar';

    public const PERMISO_AUTORIZAR = 'ventas.descuentos.autorizar';

    public const MSG_GENERICO = 'No se pudo autorizar el descuento.';

    public const MSG_SIN_CODIGO = 'El supervisor no tiene código de autorización configurado.';

    public static function tieneDescuentoLinea(array $detalles): bool
    {
        foreach ($detalles as $det) {
            $det = (array) $det;
            foreach (['descuento', 'descuento_porcentaje', 'descuento_monto'] as $campo) {
                if ((float) ($det[$campo] ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function resolverIdAutorizador(User $cajero, Request $request): ?int
    {
        if ((int) $request->input('cotizacion') === 1) {
            return null;
        }
        if ($cajero->can(self::PERMISO_APLICAR)) {
            return null;
        }
        if (! self::tieneDescuentoLinea($request->input('detalles') ?? [])) {
            return null;
        }

        $email = (string) $request->input('descuento_autorizacion.usuario', '');
        $codigo = (string) $request->input('descuento_autorizacion.codigo', '');
        if ($email === '' || $codigo === '') {
            throw new FacturacionException(self::MSG_GENERICO, 403);
        }

        $supervisor = User::query()
            ->where('email', $email)
            ->where('id_empresa', $cajero->id_empresa)
            ->first();
        if (! $supervisor || ! $supervisor->can(self::PERMISO_AUTORIZAR)) {
            throw new FacturacionException(self::MSG_GENERICO, 403);
        }
        if ($supervisor->codigo_autorizacion === null || $supervisor->codigo_autorizacion === '') {
            throw new FacturacionException(self::MSG_SIN_CODIGO, 403);
        }
        if (! hash_equals((string) $supervisor->codigo_autorizacion, $codigo)) {
            throw new FacturacionException(self::MSG_GENERICO, 403);
        }

        return (int) $supervisor->id;
    }
}
