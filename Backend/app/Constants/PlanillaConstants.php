<?php

namespace App\Constants;

class PlanillaConstants
{
    // ESTADOS GENERALES
    const ESTADO_ACTIVO = 1;
    const ESTADO_INACTIVO = 0;

    // Tipos de Contrato
    const TIPO_CONTRATO_PERMANENTE = 1;
    const TIPO_CONTRATO_TEMPORAL = 2;
    const TIPO_CONTRATO_POR_OBRA = 3;
    const TIPO_CONTRATO_SERVICIOS_PROFESIONALES = 4;

    // Tipos de Jornada
    const TIPO_JORNADA_TIEMPO_COMPLETO = 1;
    const TIPO_JORNADA_MEDIO_TIEMPO = 2;

    // Estados de Empleado
    const ESTADO_EMPLEADO_INACTIVO = 0;
    const ESTADO_EMPLEADO_ACTIVO = 1;
    const ESTADO_EMPLEADO_VACACIONES = 2;
    const ESTADO_EMPLEADO_INCAPACIDAD = 3;
    const ESTADO_EMPLEADO_SUSPENDIDO = 4;

    // Tipos de Documentos

    const TIPO_DOCUMENTO_RENUNCIA = 1;
    const TIPO_DOCUMENTO_DESPIDO = 2;
    const TIPO_DOCUMENTO_TERMINACION = 3;
    const TIPO_DOCUMENTO_ALTA = 4;
    const TIPO_DOCUMENTO_DUI = 5;
    const TIPO_DOCUMENTO_NIT = 6;
    const TIPO_DOCUMENTO_ISSS = 7;
    const TIPO_DOCUMENTO_AFP = 8;
    const TIPO_DOCUMENTO_TITULO = 9;
    const TIPO_DOCUMENTO_CERTIFICACIONES = 10;
    const TIPO_DOCUMENTO_OTRO = 11;

    const MOTIVO_CAMBIO_CONTRATO_REINGRESO = 'Reingreso';

    // Tipos de Baja
    const TIPO_BAJA_RENUNCIA = 1;
    const TIPO_BAJA_DESPIDO = 2;
    const TIPO_BAJA_TERMINACION_CONTRATO = 3;
    const TIPO_BAJA_FALLECIMIENTO = 4;
    const TIPO_BAJA_JUBILACION = 5;

    //Motivos de cambios
    const MOTIVO_CAMBIO_CONTRATO_INICIAL = 'Contrato inicial';
    const MOTIVO_CAMBIO_CONTRATO_ACTUALIZACION = 'Actualización de contrato';

    //estado de planillas y detalle de planillas
    const PLANILLA_INACTIVA = 0;
    const PLANILLA_ACTIVA = 1;
    const PLANILLA_BORRADOR = 2;
    const PLANILLA_PENDIENTE = 3;
    const PLANILLA_APROBADA = 4;
    const PLANILLA_PAGADA = 5;
    const PLANILLA_ANULADA = 6;


    //descuentos
    const DESCUENTO_ISSS_EMPLEADO = 0.03;
    const DESCUENTO_ISSS_PATRONO = 0.075;
    const DESCUENTO_AFP_EMPLEADO = 0.0725;
    const DESCUENTO_AFP_PATRONO = 0.0875;
    const PORCENTAJE_HORAS_EXTRA = 1.25;
    const HORAS_DIA = 8;
    const DIAS_LABORADOS = 30;


    const RENTA_MINIMA = 550.00;
    const RENTA_MAXIMA_PRIMER_TRAMO = 895.24;
    const RENTA_MAXIMA_SEGUNDO_TRAMO = 2038.10;
    const PORCENTAJE_PRIMER_TRAMO = 0.1;
    const PORCENTAJE_SEGUNDO_TRAMO = 0.2;
    const PORCENTAJE_TERCER_TRAMO = 0.3;
    const IMPUESTO_PRIMER_TRAMO = 0.00;    // Tramo I - Sin retención
    const IMPUESTO_SEGUNDO_TRAMO = 17.67;  // Tramo II
    const IMPUESTO_TERCER_TRAMO = 60.00;   // Tramo III
    const IMPUESTO_CUARTO_TRAMO = 288.57;


    const RENTA_MENSUAL_TRAMO_1_DESDE = 0.01;
    const RENTA_MENSUAL_TRAMO_1_HASTA = 550.00;
    const RENTA_MENSUAL_TRAMO_1_PORCENTAJE = 0.00; // Sin retención
    const RENTA_MENSUAL_TRAMO_1_SOBRE_EXCESO = 0.00;
    const RENTA_MENSUAL_TRAMO_1_CUOTA_FIJA = 0.00;

    const RENTA_MENSUAL_TRAMO_2_DESDE = 550.01;
    const RENTA_MENSUAL_TRAMO_2_HASTA = 895.24;
    const RENTA_MENSUAL_TRAMO_2_PORCENTAJE = 0.10; // 10%
    const RENTA_MENSUAL_TRAMO_2_SOBRE_EXCESO = 550.00;
    const RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA = 17.67;

    const RENTA_MENSUAL_TRAMO_3_DESDE = 895.25;
    const RENTA_MENSUAL_TRAMO_3_HASTA = 2038.10;
    const RENTA_MENSUAL_TRAMO_3_PORCENTAJE = 0.20; // 20%
    const RENTA_MENSUAL_TRAMO_3_SOBRE_EXCESO = 895.24;
    const RENTA_MENSUAL_TRAMO_3_CUOTA_FIJA = 60.00;

    const RENTA_MENSUAL_TRAMO_4_DESDE = 2038.11;
    const RENTA_MENSUAL_TRAMO_4_HASTA = 999999.99; // En adelante
    const RENTA_MENSUAL_TRAMO_4_PORCENTAJE = 0.30; // 30%
    const RENTA_MENSUAL_TRAMO_4_SOBRE_EXCESO = 2038.10;
    const RENTA_MENSUAL_TRAMO_4_CUOTA_FIJA = 288.57;

    // === REMUNERACIONES QUINCENALES ===
    const RENTA_QUINCENAL_TRAMO_1_DESDE = 0.01;
    const RENTA_QUINCENAL_TRAMO_1_HASTA = 275.00;
    const RENTA_QUINCENAL_TRAMO_1_PORCENTAJE = 0.00; // Sin retención
    const RENTA_QUINCENAL_TRAMO_1_SOBRE_EXCESO = 0.00;
    const RENTA_QUINCENAL_TRAMO_1_CUOTA_FIJA = 0.00;

    const RENTA_QUINCENAL_TRAMO_2_DESDE = 275.01;
    const RENTA_QUINCENAL_TRAMO_2_HASTA = 447.62;
    const RENTA_QUINCENAL_TRAMO_2_PORCENTAJE = 0.10; // 10%
    const RENTA_QUINCENAL_TRAMO_2_SOBRE_EXCESO = 275.00;
    const RENTA_QUINCENAL_TRAMO_2_CUOTA_FIJA = 8.83;

    const RENTA_QUINCENAL_TRAMO_3_DESDE = 447.63;
    const RENTA_QUINCENAL_TRAMO_3_HASTA = 1019.05;
    const RENTA_QUINCENAL_TRAMO_3_PORCENTAJE = 0.20; // 20%
    const RENTA_QUINCENAL_TRAMO_3_SOBRE_EXCESO = 447.62;
    const RENTA_QUINCENAL_TRAMO_3_CUOTA_FIJA = 30.00;

    const RENTA_QUINCENAL_TRAMO_4_DESDE = 1019.06;
    const RENTA_QUINCENAL_TRAMO_4_HASTA = 999999.99; // En adelante
    const RENTA_QUINCENAL_TRAMO_4_PORCENTAJE = 0.30; // 30%
    const RENTA_QUINCENAL_TRAMO_4_SOBRE_EXCESO = 1019.05;
    const RENTA_QUINCENAL_TRAMO_4_CUOTA_FIJA = 144.28;

    // === REMUNERACIONES SEMANALES ===
    const RENTA_SEMANAL_TRAMO_1_DESDE = 0.01;
    const RENTA_SEMANAL_TRAMO_1_HASTA = 137.50;
    const RENTA_SEMANAL_TRAMO_1_PORCENTAJE = 0.00; // Sin retención
    const RENTA_SEMANAL_TRAMO_1_SOBRE_EXCESO = 0.00;
    const RENTA_SEMANAL_TRAMO_1_CUOTA_FIJA = 0.00;

    const RENTA_SEMANAL_TRAMO_2_DESDE = 137.51;
    const RENTA_SEMANAL_TRAMO_2_HASTA = 223.81;
    const RENTA_SEMANAL_TRAMO_2_PORCENTAJE = 0.10; // 10%
    const RENTA_SEMANAL_TRAMO_2_SOBRE_EXCESO = 137.50;
    const RENTA_SEMANAL_TRAMO_2_CUOTA_FIJA = 4.42;

    const RENTA_SEMANAL_TRAMO_3_DESDE = 223.82;
    const RENTA_SEMANAL_TRAMO_3_HASTA = 509.52;
    const RENTA_SEMANAL_TRAMO_3_PORCENTAJE = 0.20; // 20%
    const RENTA_SEMANAL_TRAMO_3_SOBRE_EXCESO = 223.81;
    const RENTA_SEMANAL_TRAMO_3_CUOTA_FIJA = 15.00;

    const RENTA_SEMANAL_TRAMO_4_DESDE = 509.53;
    const RENTA_SEMANAL_TRAMO_4_HASTA = 999999.99; // En adelante
    const RENTA_SEMANAL_TRAMO_4_PORCENTAJE = 0.30; // 30%
    const RENTA_SEMANAL_TRAMO_4_SOBRE_EXCESO = 509.52;
    const RENTA_SEMANAL_TRAMO_4_CUOTA_FIJA = 72.14;

    const RENTA_RECALCULO_JUNIO_TRAMO_1_DESDE = 0.01;
    const RENTA_RECALCULO_JUNIO_TRAMO_1_HASTA = 3300.00;
    const RENTA_RECALCULO_JUNIO_TRAMO_1_PORCENTAJE = 0.00; // Sin retención
    const RENTA_RECALCULO_JUNIO_TRAMO_1_CUOTA_FIJA = 0.00;

    const RENTA_RECALCULO_JUNIO_TRAMO_2_DESDE = 3300.01;
    const RENTA_RECALCULO_JUNIO_TRAMO_2_HASTA = 5371.44;
    const RENTA_RECALCULO_JUNIO_TRAMO_2_PORCENTAJE = 0.10; // 10%
    const RENTA_RECALCULO_JUNIO_TRAMO_2_SOBRE_EXCESO = 3300.00;
    const RENTA_RECALCULO_JUNIO_TRAMO_2_CUOTA_FIJA = 106.20;

    const RENTA_RECALCULO_JUNIO_TRAMO_3_DESDE = 5371.45;
    const RENTA_RECALCULO_JUNIO_TRAMO_3_HASTA = 12228.60;
    const RENTA_RECALCULO_JUNIO_TRAMO_3_PORCENTAJE = 0.20; // 20%
    const RENTA_RECALCULO_JUNIO_TRAMO_3_SOBRE_EXCESO = 5371.44;
    const RENTA_RECALCULO_JUNIO_TRAMO_3_CUOTA_FIJA = 360.00;

    const RENTA_RECALCULO_JUNIO_TRAMO_4_DESDE = 12228.61;
    const RENTA_RECALCULO_JUNIO_TRAMO_4_HASTA = 999999.99; // En adelante
    const RENTA_RECALCULO_JUNIO_TRAMO_4_PORCENTAJE = 0.30; // 30%
    const RENTA_RECALCULO_JUNIO_TRAMO_4_SOBRE_EXCESO = 12228.60;
    const RENTA_RECALCULO_JUNIO_TRAMO_4_CUOTA_FIJA = 1731.42;

    // Para mes de diciembre (Segundo recálculo)
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_1_DESDE = 0.01;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_1_HASTA = 6600.00;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_1_PORCENTAJE = 0.00; // Sin retención
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_1_CUOTA_FIJA = 0.00;

    const RENTA_RECALCULO_DICIEMBRE_TRAMO_2_DESDE = 6600.01;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_2_HASTA = 10742.86;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_2_PORCENTAJE = 0.10; // 10%
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_2_SOBRE_EXCESO = 6600.00;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_2_CUOTA_FIJA = 212.12;

    const RENTA_RECALCULO_DICIEMBRE_TRAMO_3_DESDE = 10742.87;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_3_HASTA = 24457.14;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_3_PORCENTAJE = 0.20; // 20%
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_3_SOBRE_EXCESO = 10742.86;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_3_CUOTA_FIJA = 720.00;

    const RENTA_RECALCULO_DICIEMBRE_TRAMO_4_DESDE = 24457.15;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_4_HASTA = 999999.99; // En adelante
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_4_PORCENTAJE = 0.30; // 30%
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_4_SOBRE_EXCESO = 24457.14;
    const RENTA_RECALCULO_DICIEMBRE_TRAMO_4_CUOTA_FIJA = 3462.86;

    // Deducción para empleados asalariados con ingresos anuales hasta $9,100.00
    const DEDUCCION_EMPLEADOS_ASALARIADOS = 1600.00;

    // === CONSTANTES DE AGUINALDO ===
    // Decreto 900: Primeros $1,500 de aguinaldo están exentos de renta
    const AGUINALDO_EXENTO_DECRETO_2023 = 1500.00;

    // Estados de aguinaldo
    const AGUINALDO_BORRADOR = 1;
    const AGUINALDO_PAGADO = 2;

    // Arrays para usar en forms y selects
    public static function getTiposContrato()
    {
        return [
            self::TIPO_CONTRATO_PERMANENTE => 'Permanente',
            self::TIPO_CONTRATO_TEMPORAL => 'Temporal',
            self::TIPO_CONTRATO_POR_OBRA => 'Por obra',
            self::TIPO_CONTRATO_SERVICIOS_PROFESIONALES => 'Servicios profesionales'
        ];
    }

    public static function getTiposJornada()
    {
        return [
            self::TIPO_JORNADA_TIEMPO_COMPLETO => 'Tiempo completo',
            self::TIPO_JORNADA_MEDIO_TIEMPO => 'Medio tiempo'
        ];
    }

    public static function getEstadosEmpleado()
    {
        return [
            self::ESTADO_EMPLEADO_ACTIVO => 'Activo',
            self::ESTADO_EMPLEADO_INACTIVO => 'Inactivo',
            self::ESTADO_EMPLEADO_VACACIONES => 'En vacaciones',
            self::ESTADO_EMPLEADO_INCAPACIDAD => 'Incapacitado',
            self::ESTADO_EMPLEADO_SUSPENDIDO => 'Suspendido'
        ];
    }

    public static function getTiposDocumento()
    {
        return [
            self::TIPO_DOCUMENTO_RENUNCIA => 'Carta de Renuncia',
            self::TIPO_DOCUMENTO_DESPIDO => 'Carta de Despido',
            self::TIPO_DOCUMENTO_TERMINACION => 'Finalización de Contrato'
        ];
    }

    public static function getTiposBaja()
    {
        return [
            self::TIPO_BAJA_RENUNCIA => 'Renuncia',
            self::TIPO_BAJA_DESPIDO => 'Despido',
            self::TIPO_BAJA_TERMINACION_CONTRATO => 'Terminación de contrato',
            self::TIPO_BAJA_FALLECIMIENTO => 'Fallecimiento',
            self::TIPO_BAJA_JUBILACION => 'Jubilación'
        ];
    }

    public static function getEstadosEmpleadoMap()
    {
        return [
            self::ESTADO_EMPLEADO_ACTIVO => 'Activo',
            self::ESTADO_EMPLEADO_INACTIVO => 'Inactivo',
            self::ESTADO_EMPLEADO_VACACIONES => 'En Vacaciones',
            self::ESTADO_EMPLEADO_INCAPACIDAD => 'Incapacitado',
            self::ESTADO_EMPLEADO_SUSPENDIDO => 'Suspendido'
        ];
    }

    /**
     * Obtiene los tramos de renta según el tipo de planilla
     */
    public static function getTramosRenta($tipoPlanilla = 'mensual')
    {
        switch ($tipoPlanilla) {
            case 'quincenal':
                return [
                    [
                        'desde' => self::RENTA_QUINCENAL_TRAMO_1_DESDE,
                        'hasta' => self::RENTA_QUINCENAL_TRAMO_1_HASTA,
                        'porcentaje' => self::RENTA_QUINCENAL_TRAMO_1_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_QUINCENAL_TRAMO_1_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_QUINCENAL_TRAMO_1_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_QUINCENAL_TRAMO_2_DESDE,
                        'hasta' => self::RENTA_QUINCENAL_TRAMO_2_HASTA,
                        'porcentaje' => self::RENTA_QUINCENAL_TRAMO_2_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_QUINCENAL_TRAMO_2_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_QUINCENAL_TRAMO_2_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_QUINCENAL_TRAMO_3_DESDE,
                        'hasta' => self::RENTA_QUINCENAL_TRAMO_3_HASTA,
                        'porcentaje' => self::RENTA_QUINCENAL_TRAMO_3_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_QUINCENAL_TRAMO_3_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_QUINCENAL_TRAMO_3_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_QUINCENAL_TRAMO_4_DESDE,
                        'hasta' => self::RENTA_QUINCENAL_TRAMO_4_HASTA,
                        'porcentaje' => self::RENTA_QUINCENAL_TRAMO_4_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_QUINCENAL_TRAMO_4_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_QUINCENAL_TRAMO_4_CUOTA_FIJA
                    ]
                ];

            case 'semanal':
                return [
                    [
                        'desde' => self::RENTA_SEMANAL_TRAMO_1_DESDE,
                        'hasta' => self::RENTA_SEMANAL_TRAMO_1_HASTA,
                        'porcentaje' => self::RENTA_SEMANAL_TRAMO_1_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_SEMANAL_TRAMO_1_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_SEMANAL_TRAMO_1_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_SEMANAL_TRAMO_2_DESDE,
                        'hasta' => self::RENTA_SEMANAL_TRAMO_2_HASTA,
                        'porcentaje' => self::RENTA_SEMANAL_TRAMO_2_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_SEMANAL_TRAMO_2_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_SEMANAL_TRAMO_2_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_SEMANAL_TRAMO_3_DESDE,
                        'hasta' => self::RENTA_SEMANAL_TRAMO_3_HASTA,
                        'porcentaje' => self::RENTA_SEMANAL_TRAMO_3_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_SEMANAL_TRAMO_3_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_SEMANAL_TRAMO_3_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_SEMANAL_TRAMO_4_DESDE,
                        'hasta' => self::RENTA_SEMANAL_TRAMO_4_HASTA,
                        'porcentaje' => self::RENTA_SEMANAL_TRAMO_4_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_SEMANAL_TRAMO_4_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_SEMANAL_TRAMO_4_CUOTA_FIJA
                    ]
                ];

            default: // mensual
                return [
                    [
                        'desde' => self::RENTA_MENSUAL_TRAMO_1_DESDE,
                        'hasta' => self::RENTA_MENSUAL_TRAMO_1_HASTA,
                        'porcentaje' => self::RENTA_MENSUAL_TRAMO_1_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_MENSUAL_TRAMO_1_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_MENSUAL_TRAMO_1_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_MENSUAL_TRAMO_2_DESDE,
                        'hasta' => self::RENTA_MENSUAL_TRAMO_2_HASTA,
                        'porcentaje' => self::RENTA_MENSUAL_TRAMO_2_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_MENSUAL_TRAMO_2_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_MENSUAL_TRAMO_3_DESDE,
                        'hasta' => self::RENTA_MENSUAL_TRAMO_3_HASTA,
                        'porcentaje' => self::RENTA_MENSUAL_TRAMO_3_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_MENSUAL_TRAMO_3_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_MENSUAL_TRAMO_3_CUOTA_FIJA
                    ],
                    [
                        'desde' => self::RENTA_MENSUAL_TRAMO_4_DESDE,
                        'hasta' => self::RENTA_MENSUAL_TRAMO_4_HASTA,
                        'porcentaje' => self::RENTA_MENSUAL_TRAMO_4_PORCENTAJE,
                        'sobre_exceso' => self::RENTA_MENSUAL_TRAMO_4_SOBRE_EXCESO,
                        'cuota_fija' => self::RENTA_MENSUAL_TRAMO_4_CUOTA_FIJA
                    ]
                ];
        }
    }

    public static function esContratoServiciosProfesionales($tipoContrato)
    {
        return $tipoContrato == self::TIPO_CONTRATO_SERVICIOS_PROFESIONALES;
    }

    /**
     * Verifica si el tipo de contrato NO tiene prestaciones laborales (ISSS, AFP)
     * Aplica para: Por obra y Servicios Profesionales
     * Estos contratos solo tienen retención de renta del 10% fijo
     *
     * @param int $tipoContrato
     * @return bool
     */
    public static function esContratoSinPrestaciones($tipoContrato)
    {
        return $tipoContrato == self::TIPO_CONTRATO_POR_OBRA ||
               $tipoContrato == self::TIPO_CONTRATO_SERVICIOS_PROFESIONALES;
    }

    /**
     * Verifica si el tipo de contrato tiene derecho a aguinaldo
     * Según la legislación salvadoreña, tienen derecho:
     * - Contratos Permanentes
     * - Contratos Temporales
     * 
     * NO tienen derecho:
     * - Contratos Por Obra
     * - Contratos de Servicios Profesionales
     *
     * @param int $tipoContrato
     * @return bool
     */
    public static function contratoTieneDerechoAguinaldo($tipoContrato)
    {
        return $tipoContrato == self::TIPO_CONTRATO_PERMANENTE ||
               $tipoContrato == self::TIPO_CONTRATO_TEMPORAL;
    }
}