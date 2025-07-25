<?php

namespace App\Services\Planilla;

use App\Constants\PlanillaConstants;

use function Symfony\Component\VarDumper\Dumper\esc;

class PlanillaTemplatesService
{
    /**
     * Obtener configuración por código de país
     */
    public static function getConfiguracionPorPais($codPais)
    {
        switch ($codPais) {
            case 'SV':
                return self::getConfiguracionSalvador();
            case 'GT':
                return self::getConfiguracionGuatemala();
            case 'HN':
                return self::getConfiguracionHonduras();
            case 'NI':
                return self::getConfiguracionNicaragua();
            case 'CR':
                return self::getConfiguracionCostaRica();
            case 'PA':
                return self::getConfiguracionPanama();
            default:
                return self::getConfiguracionSalvador();
        }
    }

    /**
     * El Salvador
     */
    public static function getConfiguracionSalvador()
    {
        return [
            "conceptos" => [
                "isss_empleado" => [
                    "nombre" => "ISSS Empleado",
                    "codigo" => "ISSS_EMP",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_ISSS_EMPLEADO * 100,
                    "tope_maximo" => 1000,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "isss_patronal" => [
                    "nombre" => "ISSS Patronal",
                    "codigo" => "ISSS_PAT",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_ISSS_PATRONO * 100,
                    "tope_maximo" => 1000,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "afp_empleado" => [
                    "nombre" => "AFP Empleado",
                    "codigo" => "AFP_EMP",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_AFP_EMPLEADO * 100,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 3
                ],
                "afp_patronal" => [
                    "nombre" => "AFP Patronal",
                    "codigo" => "AFP_PAT",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_AFP_PATRONO * 100,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 4
                ],
                "renta" => [
                    "nombre" => "Retención Renta",
                    "codigo" => "RENTA",
                    "tipo" => "sistema_existente",
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 5
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "USD",
                "dias_mes" => 30,
                "horas_dia" => 8,
                "recargo_horas_extra" => 25,
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 365.00
            ]
        ];
    }

    /**
     * Guatemala - CORREGIDO
     */
    public static function getConfiguracionGuatemala()
    {
        return [
            "conceptos" => [
                "igss_empleado" => [
                    "nombre" => "IGSS Empleado",
                    "codigo" => "IGSS_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 4.83, // ✅ CORRECTO
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "igss_patronal" => [
                    "nombre" => "IGSS Patronal",
                    "codigo" => "IGSS_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 10.67, // ✅ CORRECTO
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "irtra_patronal" => [
                    "nombre" => "IRTRA Patronal",
                    "codigo" => "IRTRA_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 1.0,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 3
                ],
                "intecap_patronal" => [
                    "nombre" => "INTECAP Patronal",
                    "codigo" => "INTECAP_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 1.0,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 4
                ],
                "isr_guatemala" => [
                    "nombre" => "ISR Guatemala",
                    "codigo" => "ISR_GT",
                    "tipo" => "tabla_progresiva",
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 5
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "GTQ",
                "dias_mes" => 30,
                "horas_dia" => 8,
                "recargo_horas_extra" => 50,
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 2992.38 // Actualizar según 2024
            ]
        ];
    }

    /**
     * Honduras - CORREGIDO según decretos 47-2024 y 48-2024
     */
    public static function getConfiguracionHonduras()
    {
        return [
            "conceptos" => [
                "ihss_salud_empleado" => [
                    "nombre" => "IHSS Salud Empleado",
                    "codigo" => "IHSS_SALUD_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 2.5, // ✅ CORRECTO 2024
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "ihss_salud_patronal" => [
                    "nombre" => "IHSS Salud Patronal",
                    "codigo" => "IHSS_SALUD_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 5.0, // ✅ CORRECTO 2024
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "ihss_ivm_empleado" => [
                    "nombre" => "IHSS IVM Empleado",
                    "codigo" => "IHSS_IVM_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 1.75, // ✅ CORRECTO según nueva ley
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 3
                ],
                "ihss_ivm_patronal" => [
                    "nombre" => "IHSS IVM Patronal", 
                    "codigo" => "IHSS_IVM_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 3.75, // ✅ CORRECTO según nueva ley
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 4
                ],
                "rap_empleado" => [
                    "nombre" => "RAP Empleado",
                    "codigo" => "RAP_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 1.5, // ✅ CORRECTO
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 5
                ],
                "rap_patronal" => [
                    "nombre" => "RAP Patronal",
                    "codigo" => "RAP_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 1.5, // ✅ CORRECTO
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 6
                ],
                "fondo_reserva_laboral" => [
                    "nombre" => "Fondo Reserva Laboral",
                    "codigo" => "FRL_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 4.0, // ✅ NUEVO - Solo patronal
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 7
                ],
                "isr_honduras" => [
                    "nombre" => "ISR Honduras",
                    "codigo" => "ISR_HN",
                    "tipo" => "tabla_progresiva",
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 8
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "HNL",
                "dias_mes" => 30,
                "horas_dia" => 8,
                "recargo_horas_extra" => 25,
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 13156.53 // ✅ ACTUALIZADO 2024
            ]
        ];
    }

    /**
     * Nicaragua
     */
    public static function getConfiguracionNicaragua()
    {
        return [
            "conceptos" => [
                "inss_empleado" => [
                    "nombre" => "INSS Empleado",
                    "codigo" => "INSS_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 6.25,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "inss_patronal" => [
                    "nombre" => "INSS Patronal",
                    "codigo" => "INSS_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 19.0,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "ir_nicaragua" => [
                    "nombre" => "IR Nicaragua",
                    "codigo" => "IR_NI",
                    "tipo" => "tabla_progresiva",
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 3
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "NIO",
                "dias_mes" => 30,
                "horas_dia" => 8,
                "recargo_horas_extra" => 100,
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 6518.48
            ]
        ];
    }

    /**
     * Costa Rica
     */
    public static function getConfiguracionCostaRica()
    {
        return [
            "conceptos" => [
                "ccss_empleado" => [
                    "nombre" => "CCSS Empleado",
                    "codigo" => "CCSS_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 10.5,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "ccss_patronal" => [
                    "nombre" => "CCSS Patronal",
                    "codigo" => "CCSS_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 26.0,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "renta_costarica" => [
                    "nombre" => "Renta Costa Rica",
                    "codigo" => "RENTA_CR",
                    "tipo" => "tabla_progresiva",
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 3
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "CRC",
                "dias_mes" => 30,
                "horas_dia" => 8,
                "recargo_horas_extra" => 50,
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 317916.21
            ]
        ];
    }

    /**
     * Panamá
     */
    public static function getConfiguracionPanama()
    {
        return [
            "conceptos" => [
                "css_empleado" => [
                    "nombre" => "CSS Empleado",
                    "codigo" => "CSS_EMP",
                    "tipo" => "porcentaje",
                    "valor" => 9.75,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "css_patronal" => [
                    "nombre" => "CSS Patronal",
                    "codigo" => "CSS_PAT",
                    "tipo" => "porcentaje",
                    "valor" => 12.25,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "isr_panama" => [
                    "nombre" => "ISR Panamá",
                    "codigo" => "ISR_PA",
                    "tipo" => "tabla_progresiva",
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 3
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "USD",
                "dias_mes" => 30,
                "horas_dia" => 8,
                "recargo_horas_extra" => 25,
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 700.00
            ]
        ];
    }
}