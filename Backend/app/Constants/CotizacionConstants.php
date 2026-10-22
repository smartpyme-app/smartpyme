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
        return $user && ($user->tipo ?? null) === 'Ventas Limitado';
    }

    const ESTADO_PRODUCCION_CREADA = 'creada';
    const ESTADO_PRODUCCION_EN_PROCESO = 'en_proceso';
    const ESTADO_PRODUCCION_ACEPTADA = 'aceptada';
    const ESTADO_PRODUCCION_COMPLETADA = 'completada';
    const ESTADO_PRODUCCION_ENTREGADA = 'entregada';
    const ESTADO_PRODUCCION_ANULADA = 'anulada';
}
