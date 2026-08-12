<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class CostaRicaCargasSocialesHelper
{
    /**
     * Bases Mínimas Contributivas (BMC) vigentes mensuales
     */
    public const BMC_SEM_MENSUAL = 333328.0; // Seguro de Enfermedad y Maternidad
    public const BMC_IVM_MENSUAL = 311990.0; // Invalidez, Vejez y Muerte

    /**
     * Tasas de cargas sociales del trabajador
     */
    public const TASA_SEM_EMPLEADO = 0.0550; // 5.50%
    public const TASA_IVM_EMPLEADO = 0.0433; // 4.33%
    public const TASA_BP_EMPLEADO = 0.0100;  // 1.00%

    /**
     * Tasas de cargas sociales patronales (Estándar = 26.83%)
     */
    public const TASA_SEM_PATRONAL = 0.0925; // 9.25%
    public const TASA_IVM_PATRONAL = 0.0558; // 5.58%
    public const TASA_BP_PATRONAL = 0.0025;  // 0.25%
    public const TASA_FODESAF_PATRONAL = 0.0500; // 5.00%
    public const TASA_IMAS_PATRONAL = 0.0050; // 0.50%
    public const TASA_INA_PATRONAL = 0.0150;  // 1.50%
    public const TASA_FCL_PATRONAL = 0.0150;  // 1.50%
    public const TASA_ROP_PATRONAL = 0.0325;  // 3.25%

    /**
     * Tasa INS Riesgos de Trabajo por defecto (1.5%)
     */
    public const TASA_INS_PATRONAL_DEFECTO = 0.0150;

    /**
     * Calcular todas las cargas sociales de Costa Rica para un empleado y patrono
     *
     * @param float $salarioBruto Salario bruto del período
     * @param string $tipoPlanilla Frecuencia ('mensual', 'quincenal', 'semanal')
     * @param float|null $tasaInsPorcentaje Porcentaje de INS configurado para la empresa (e.g. 1.5 para 1.5%)
     * @param bool $esPatronoPequeno Indica si aplica tarifa reducida para patronos pequeños (<5 colaboradores)
     * @return array Detalle de cálculos trabajador, patronal e INS
     */
    public static function calcularCargasSociales(
        float $salarioBruto,
        string $tipoPlanilla = 'mensual',
        ?float $tasaInsPorcentaje = null,
        bool $esPatronoPequeno = false
    ): array {
        if ($salarioBruto <= 0) {
            return self::estructuraVacia();
        }

        $divisor = match ($tipoPlanilla) {
            'quincenal' => 2.0,
            'semanal' => 4.33,
            default => 1.0,
        };

        // Salario mensual equivalente para evaluar BMC
        $salarioMensual = $salarioBruto * $divisor;

        // Base para SEM e IVM en el período aplicando pisos salariales (BMC)
        $baseSemMensual = max($salarioMensual, self::BMC_SEM_MENSUAL);
        $baseIvmMensual = max($salarioMensual, self::BMC_IVM_MENSUAL);

        $baseSemPeriodo = $baseSemMensual / $divisor;
        $baseIvmPeriodo = $baseIvmMensual / $divisor;

        // --- CÁLCULO TRABAJADOR ---
        $semEmpleado = round($baseSemPeriodo * self::TASA_SEM_EMPLEADO, 2);
        $ivmEmpleado = round($baseIvmPeriodo * self::TASA_IVM_EMPLEADO, 2);
        $bpEmpleado = round($salarioBruto * self::TASA_BP_EMPLEADO, 2);
        $totalEmpleado = round($semEmpleado + $ivmEmpleado + $bpEmpleado, 2);

        // --- CÁLCULO PATRONAL ---
        $semPatronal = round($baseSemPeriodo * self::TASA_SEM_PATRONAL, 2);
        $ivmPatronal = round($baseIvmPeriodo * self::TASA_IVM_PATRONAL, 2);
        $bpPatronal = round($salarioBruto * self::TASA_BP_PATRONAL, 2);

        // Ajuste FODESAF para patrono pequeño si aplica (~3.5% a 4% vs 5.0%)
        $tasaFodesaf = $esPatronoPequeno ? 0.0350 : self::TASA_FODESAF_PATRONAL;
        $fodesafPatronal = round($salarioBruto * $tasaFodesaf, 2);

        $imasPatronal = round($salarioBruto * self::TASA_IMAS_PATRONAL, 2);
        $inaPatronal = round($salarioBruto * self::TASA_INA_PATRONAL, 2);
        $fclPatronal = round($salarioBruto * self::TASA_FCL_PATRONAL, 2);
        $ropPatronal = round($salarioBruto * self::TASA_ROP_PATRONAL, 2);

        $totalPatronalCCSS = round(
            $semPatronal + $ivmPatronal + $bpPatronal +
            $fodesafPatronal + $imasPatronal + $inaPatronal +
            $fclPatronal + $ropPatronal,
            2
        );

        // --- CÁLCULO INS (Riesgos del trabajo - Patronal) ---
        $tasaIns = ($tasaInsPorcentaje !== null && $tasaInsPorcentaje >= 0)
            ? ($tasaInsPorcentaje / 100.0)
            : self::TASA_INS_PATRONAL_DEFECTO;

        $insPatronal = round($salarioBruto * $tasaIns, 2);

        return [
            'ccss_empleado' => $totalEmpleado,
            'ccss_patronal' => $totalPatronalCCSS,
            'ins_patronal' => $insPatronal,
            'desglose_empleado' => [
                'sem' => $semEmpleado,
                'ivm' => $ivmEmpleado,
                'banco_popular' => $bpEmpleado,
            ],
            'desglose_patronal' => [
                'sem' => $semPatronal,
                'ivm' => $ivmPatronal,
                'banco_popular' => $bpPatronal,
                'fodesaf' => $fodesafPatronal,
                'imas' => $imasPatronal,
                'ina' => $inaPatronal,
                'fcl' => $fclPatronal,
                'rop' => $ropPatronal,
            ],
            'bases_aplicadas' => [
                'base_sem_periodo' => round($baseSemPeriodo, 2),
                'base_ivm_periodo' => round($baseIvmPeriodo, 2),
                'aplico_bmc_sem' => $salarioMensual < self::BMC_SEM_MENSUAL,
                'aplico_bmc_ivm' => $salarioMensual < self::BMC_IVM_MENSUAL,
            ]
        ];
    }

    private static function estructuraVacia(): array
    {
        return [
            'ccss_empleado' => 0.0,
            'ccss_patronal' => 0.0,
            'ins_patronal' => 0.0,
            'desglose_empleado' => ['sem' => 0.0, 'ivm' => 0.0, 'banco_popular' => 0.0],
            'desglose_patronal' => [
                'sem' => 0.0, 'ivm' => 0.0, 'banco_popular' => 0.0,
                'fodesaf' => 0.0, 'imas' => 0.0, 'ina' => 0.0, 'fcl' => 0.0, 'rop' => 0.0
            ],
            'bases_aplicadas' => [
                'base_sem_periodo' => 0.0,
                'base_ivm_periodo' => 0.0,
                'aplico_bmc_sem' => false,
                'aplico_bmc_ivm' => false,
            ]
        ];
    }
}
