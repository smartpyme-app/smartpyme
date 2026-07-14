<?php

namespace App\Helpers;

use App\Constants\PlanillaConstants;

class IsssHelper
{
    /**
     * Tope mensual de salario cotizable ISSS (USD).
     */
    public const TOPE_MENSUAL = 1000.00;

    /**
     * Obtiene el tope de cotización ISSS aplicable al período de pago.
     * El tope legal es mensual ($1,000); cada planilla aplica el tope al ingreso del período.
     */
    public static function obtenerTopePorPeriodo(string $tipoPlanilla = 'mensual'): float
    {
        return self::TOPE_MENSUAL;
    }

    /**
     * Calcula la retención ISSS del empleado (3%) sobre la base cotizable del período.
     * La base debe ser el salario devengado más ingresos sujetos a retención del período.
     */
    public static function calcularRetencionEmpleado(float $baseCotizable, string $tipoPlanilla = 'mensual'): float
    {
        if ($baseCotizable <= 0) {
            return 0.00;
        }

        $base = min($baseCotizable, self::obtenerTopePorPeriodo($tipoPlanilla));

        return round($base * PlanillaConstants::DESCUENTO_ISSS_EMPLEADO, 2);
    }

    /**
     * Calcula el aporte ISSS patronal (7.5%) sobre la base cotizable del período.
     */
    public static function calcularAportePatronal(float $baseCotizable, string $tipoPlanilla = 'mensual'): float
    {
        if ($baseCotizable <= 0) {
            return 0.00;
        }

        $base = min($baseCotizable, self::obtenerTopePorPeriodo($tipoPlanilla));

        return round($base * PlanillaConstants::DESCUENTO_ISSS_PATRONO, 2);
    }

    /**
     * Calcula ISSS empleado y patronal en una sola llamada.
     *
     * @return array{isss_empleado: float, isss_patronal: float}
     */
    public static function calcularIsss(float $baseCotizable, string $tipoPlanilla = 'mensual'): array
    {
        return [
            'isss_empleado' => self::calcularRetencionEmpleado($baseCotizable, $tipoPlanilla),
            'isss_patronal' => self::calcularAportePatronal($baseCotizable, $tipoPlanilla),
        ];
    }
}
