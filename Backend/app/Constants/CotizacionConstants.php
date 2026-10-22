<?php

namespace App\Constants;

class CotizacionConstants
{
    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_ACEPTADA = 'aceptada';
    const ESTADO_PERDIDA = 'perdida';
    const ESTADO_ANULADA = 'anulada';
    const ESTADO_FACTURADA = 'Facturada';
    const ESTADO_ENVIADA = 'enviada';

    /** Ventas Limitado: pendiente factura, facturada imprime, el resto no actúa. */
    public static function accionVentasLimitado(?string $estado): ?string
    {
        return match (mb_strtolower(trim((string) $estado))) {
            'pendiente' => 'facturar',
            'facturada' => 'imprimir',
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

    /** Vendedor Ventas (no ventas limitado): solo sus cotizaciones. Ventas limitado ve las de la empresa. */
    public static function soloListadoCotizacionesPropias($user): bool
    {
        if (! $user || self::esVentasLimitado($user)) {
            return false;
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
