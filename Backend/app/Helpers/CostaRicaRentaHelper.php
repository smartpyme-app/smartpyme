<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class CostaRicaRentaHelper
{
    /**
     * Tramos mensuales vigentes Decreto 45333-H (Enero 2026)
     */
    public const TRAMOS_RENTA_MENSUAL = [
        [
            'desde' => 0.0,
            'hasta' => 941000.0,
            'porcentaje' => 0.0,
            'cuota_fija' => 0.0,
            'sobre_exceso' => 0.0,
        ],
        [
            'desde' => 941000.0,
            'hasta' => 1381000.0,
            'porcentaje' => 0.10,
            'cuota_fija' => 0.0,
            'sobre_exceso' => 941000.0,
        ],
        [
            'desde' => 1381000.0,
            'hasta' => 2423000.0,
            'porcentaje' => 0.15,
            'cuota_fija' => 44000.0,
            'sobre_exceso' => 1381000.0,
        ],
        [
            'desde' => 2423000.0,
            'hasta' => 4845000.0,
            'porcentaje' => 0.20,
            'cuota_fija' => 200300.0,
            'sobre_exceso' => 2423000.0,
        ],
        [
            'desde' => 4845000.0,
            'hasta' => null,
            'porcentaje' => 0.25,
            'cuota_fija' => 684700.0,
            'sobre_exceso' => 4845000.0,
        ],
    ];

    /**
     * Créditos fiscales mensuales (Decreto 45333-H)
     */
    public const CREDITO_HIJO_MENSUAL = 1710.0;
    public const CREDITO_CONYUGE_MENSUAL = 2590.0;

    /**
     * Calcular retención de Impuesto sobre la Renta en Costa Rica
     *
     * @param float $salarioBruto Salario bruto mensual/periódico (Renta en CR aplica sobre salario bruto directo)
     * @param string $tipoPlanilla Frecuencia de la planilla ('mensual', 'quincenal', 'semanal')
     * @param int $cantidadHijos Cantidad de hijos dependientes
     * @param bool $tieneConyuge Indica si tiene cónyuge dependiente
     * @return float Monto de retención final de renta
     */
    public static function calcularRetencionRenta(
        float $salarioBruto,
        string $tipoPlanilla = 'mensual',
        int $cantidadHijos = 0,
        bool $tieneConyuge = false
    ): float {
        if ($salarioBruto <= 0) {
            return 0.00;
        }

        // Determinar divisor de frecuencia (mensual = 1, quincenal = 2, semanal = 4.33)
        $divisorFrecuencia = self::obtenerDivisorFrecuencia($tipoPlanilla);

        // Convertir salario periódico a salario mensual equivalente para consultar tabla
        $salarioMensual = $salarioBruto * $divisorFrecuencia;

        // Calcular impuesto bruto en base mensual
        $impuestoBrutoMensual = self::calcularImpuestoMensual($salarioMensual);

        if ($impuestoBrutoMensual <= 0) {
            return 0.00;
        }

        // Calcular créditos familiares mensuales
        $creditoHijos = max(0, $cantidadHijos) * self::CREDITO_HIJO_MENSUAL;
        $creditoConyuge = $tieneConyuge ? self::CREDITO_CONYUGE_MENSUAL : 0.0;
        $totalCreditosMensuales = $creditoHijos + $creditoConyuge;

        // Aplicar créditos sobre el impuesto resultante (no sobre la base)
        $impuestoNetoMensual = max(0.0, $impuestoBrutoMensual - $totalCreditosMensuales);

        // Ajustar el impuesto a la frecuencia del período
        $impuestoPeriodo = $impuestoNetoMensual / $divisorFrecuencia;

        Log::info('🇨🇷 Cálculo de Renta Costa Rica', [
            'salario_bruto_periodo' => $salarioBruto,
            'tipo_planilla' => $tipoPlanilla,
            'salario_mensual_eq' => $salarioMensual,
            'impuesto_bruto_mensual' => $impuestoBrutoMensual,
            'total_creditos_mensuales' => $totalCreditosMensuales,
            'impuesto_neto_mensual' => $impuestoNetoMensual,
            'impuesto_periodo_final' => round($impuestoPeriodo, 2),
        ]);

        return round($impuestoPeriodo, 2);
    }

    /**
     * Calcular impuesto bruto mensual según tramos del Decreto 45333-H
     */
    public static function calcularImpuestoMensual(float $salarioMensual): float
    {
        $salarioMensual = round($salarioMensual, 2);

        foreach (self::TRAMOS_RENTA_MENSUAL as $tramo) {
            $desde = $tramo['desde'];
            $hasta = $tramo['hasta'];

            if ($salarioMensual > $desde && ($hasta === null || $salarioMensual <= $hasta)) {
                $exceso = $salarioMensual - $tramo['sobre_exceso'];
                $impuesto = $tramo['cuota_fija'] + ($exceso * $tramo['porcentaje']);
                return round($impuesto, 2);
            }
        }

        return 0.0;
    }

    /**
     * Obtener el divisor de frecuencia para ajustar la tabla a la frecuencia de pago
     */
    private static function obtenerDivisorFrecuencia(string $tipoPlanilla): float
    {
        return match ($tipoPlanilla) {
            'quincenal' => 2.0,
            'semanal' => 4.33,
            default => 1.0,
        };
    }
}
