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

    /** Canonical PascalCase para UI/API (BD puede tener legacy en minúsculas). */
    public static function normalizarEstado(?string $estado): ?string
    {
        if ($estado === null || trim($estado) === '') {
            return $estado;
        }

        if (in_array($estado, self::estadosPermitidos(), true)) {
            return $estado;
        }

        $e = mb_strtolower(trim($estado));

        return match ($e) {
            'pendiente', 'enviada', 'borrador', 'aceptada' => self::ESTADO_PENDIENTE,
            'facturada' => self::ESTADO_FACTURADA,
            'anulada', 'vencida', 'cancelada' => self::ESTADO_ANULADA,
            default => ucfirst($e),
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

    /** Ventas Limitado crea/edita solo con ventas.cotizaciones.crear / ventas.cotizaciones.editar. */
    public static function puedeGuardarCotizacion($user, bool $creando): bool
    {
        if (! self::esVentasLimitado($user)) {
            return true;
        }

        return is_object($user)
            && method_exists($user, 'can')
            && $user->can($creando ? 'ventas.cotizaciones.crear' : 'ventas.cotizaciones.editar');
    }

    /** Ventas Limitado imprime solo con ventas.cotizaciones.ver o ventas.cotizaciones.editar. */
    public static function puedeImprimirCotizacion($user): bool
    {
        if (! self::esVentasLimitado($user)) {
            return true;
        }

        return is_object($user)
            && method_exists($user, 'can')
            && ($user->can('ventas.cotizaciones.ver') || $user->can('ventas.cotizaciones.editar'));
    }

    const ESTADO_PRODUCCION_CREADA = 'creada';
    const ESTADO_PRODUCCION_EN_PROCESO = 'en_proceso';
    const ESTADO_PRODUCCION_ACEPTADA = 'aceptada';
    const ESTADO_PRODUCCION_COMPLETADA = 'completada';
    const ESTADO_PRODUCCION_ENTREGADA = 'entregada';
    const ESTADO_PRODUCCION_ANULADA = 'anulada';
}
