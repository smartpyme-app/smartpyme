<?php

namespace App\Support;

final class ContabilidadReporteTipos
{
    public const LIBRO_DIARIO = 'libro-diario';
    public const LIBRO_DIARIO_MAYOR = 'libro-diario-mayor';
    public const LIBRO_AUXILIAR = 'libro-auxiliar';
    public const MOVIMIENTO_CUENTA = 'movimiento-cuenta';
    public const BALANCE_COMPROBACION = 'balance-comprobacion';
    public const BALANCE_GENERAL = 'balance-general';
    public const ESTADO_RESULTADOS = 'estado-resultados';
    public const FLUJO_EFECTIVO = 'flujo-efectivo';
    public const CAMBIOS_PATRIMONIO = 'cambios-patrimonio';
    public const NOTAS_EEFF = 'notas-estados-financieros';
    public const ESTADOS_FINANCIEROS_COMPLETOS = 'estados-financieros-completos';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::LIBRO_DIARIO,
            self::LIBRO_DIARIO_MAYOR,
            self::LIBRO_AUXILIAR,
            self::MOVIMIENTO_CUENTA,
            self::BALANCE_COMPROBACION,
            self::BALANCE_GENERAL,
            self::ESTADO_RESULTADOS,
            self::FLUJO_EFECTIVO,
            self::CAMBIOS_PATRIMONIO,
            self::NOTAS_EEFF,
            self::ESTADOS_FINANCIEROS_COMPLETOS,
        ];
    }

    /** Solo PDF (sin Excel). */
    public static function soloPdf(): array
    {
        return [
            self::MOVIMIENTO_CUENTA,
            self::ESTADOS_FINANCIEROS_COMPLETOS,
        ];
    }
}
