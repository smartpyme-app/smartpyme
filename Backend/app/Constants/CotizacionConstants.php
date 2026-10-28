<?php

namespace App\Constants;

class CotizacionConstants
{
    const ESTADO_PENDIENTE = 'Pendiente';
    const ESTADO_FACTURADA = 'Facturada';
    const ESTADO_ANULADA = 'Anulada';

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_PENDIENTE,
            self::ESTADO_FACTURADA,
            self::ESTADO_ANULADA,
        ];
    }

    /** Ventas Limitado: pendiente factura, facturada imprime, el resto no actúa. */
    public static function accionVentasLimitado(?string $estado): ?string
    {
        return match (trim((string) $estado)) {
            self::ESTADO_PENDIENTE => 'facturar',
            self::ESTADO_FACTURADA => 'imprimir',
            default => null,
        };
    }

    public static function esVentasLimitado($user): bool
    {
        if (! $user) {
            return false;
        }
        if (($user->tipo ?? null) === 'Ventas Limitado') {
            return true;
        }
        if (is_object($user) && method_exists($user, 'hasRole')) {
            return $user->hasRole(config('constants.ROL_VENTAS_LIMITADO', 'ventas_limitado'));
        }

        return false;
    }

    /** Ventas y Ventas limitado: solo sus cotizaciones en listado/export. */
    public static function soloListadoCotizacionesPropias($user): bool
    {
        if (! $user) {
            return false;
        }
        if (self::esVentasLimitado($user)) {
            return true;
        }

        return ($user->tipo ?? null) === 'Ventas';
    }

    /** Crear sí, si el rol tiene ventas.cotizaciones.crear. Editar sigue bloqueado en el llamador. */
    public static function puedeCrearCotizacion($user): bool
    {
        if (! self::esVentasLimitado($user)) {
            return true;
        }

        return is_object($user)
            && method_exists($user, 'can')
            && $user->can('ventas.cotizaciones.crear');
    }

    const ESTADO_PRODUCCION_CREADA = 'creada';
    const ESTADO_PRODUCCION_EN_PROCESO = 'en_proceso';
    const ESTADO_PRODUCCION_ACEPTADA = 'aceptada';
    const ESTADO_PRODUCCION_COMPLETADA = 'completada';
    const ESTADO_PRODUCCION_ENTREGADA = 'entregada';
    const ESTADO_PRODUCCION_ANULADA = 'anulada';
}
