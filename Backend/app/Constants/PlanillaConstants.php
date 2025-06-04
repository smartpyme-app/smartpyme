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
    const DESCUENTO_AFP_PATRONO = 0.0773;
    const PORCENTAJE_HORAS_EXTRA = 1.25;
    const HORAS_DIA = 8;
    const DIAS_LABORADOS = 30;


    const RENTA_MINIMA = 472.00;
    const RENTA_MAXIMA_PRIMER_TRAMO = 895.24;
    const RENTA_MAXIMA_SEGUNDO_TRAMO = 2038.10;
    const PORCENTAJE_PRIMER_TRAMO = 0.1;
    const PORCENTAJE_SEGUNDO_TRAMO = 0.2;
    const PORCENTAJE_TERCER_TRAMO = 0.3;
    const IMPUESTO_PRIMER_TRAMO = 17.67;
    const IMPUESTO_SEGUNDO_TRAMO = 60.00;
    const IMPUESTO_TERCER_TRAMO = 288.57;







    // Arrays para usar en forms y selects
    public static function getTiposContrato()
    {
        return [
            self::TIPO_CONTRATO_PERMANENTE => 'Permanente',
            self::TIPO_CONTRATO_TEMPORAL => 'Temporal',
            self::TIPO_CONTRATO_POR_OBRA => 'Por obra'
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
}