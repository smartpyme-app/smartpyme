<?php

return [
    'PLAN_EMPRENDEDOR' => 'Emprendedor',
    'PLAN_ESTANDAR' => 'Estándar',
    'PLAN_AVANZADO' => 'Avanzado',
    'PLAN_PRO' => 'Pro',
    'URL_N1CO_EMPRENDEDOR' => 'https://pay.n1co.shop/pl/WEwwXTOpy',
    'URL_N1CO_ESTANDAR' => 'https://pay.n1co.shop/pl/yX99lF1Dl',
    'URL_N1CO_AVANZADO' => 'https://pay.n1co.shop/pl/vbj8Rh0y1',
    'URL_N1CO_PRO' => 'https://pay.n1co.shop/pl/vbj8Rh0y1',
    'TIPO_PAGO_EFECTIVO' => 'Efectivo',
    'TIPO_PAGO_TRANSFERENCIA' => 'Transferencia',
    'TIPO_PAGO_TARJETA' => 'Tarjeta de crédito/débito',

    'METODO_PAGO_N1CO' => 'n1co',
    'METODO_PAGO_TRANSFERENCIA' => 'Transferencia',

    'TIPO_PAGO_AUTOMATICO' => 'Automatico',
    'TIPO_PAGO_MANUAL' => 'Manual',

    // Días tras el vencimiento con acceso (1..N); suspensión desde el día N+1 si siguen saldos pendientes (p. ej. N=3).
    'DIAS_PRORROGA_SUSCRIPCION' => 3,
    
    'ESTADO_SUSCRIPCION_ACTIVO' => 'Activo',
    'ESTADO_SUSCRIPCION_INACTIVO' => 'Inactivo',
    'ESTADO_SUSCRIPCION_CANCELADO' => 'Cancelado',
    'ESTADO_SUSCRIPCION_PENDIENTE' => 'Pendiente',
    'ESTADO_SUSCRIPCION_RENOVADO' => 'Renovado',
    'ESTADO_SUSCRIPCION_EN_PRUEBA' => 'En prueba',
    'ESTADO_SUSCRIPCION_SUSPENDIDO' => 'Suspendido',

    'ESTADO_ORDEN_PAGO_PENDIENTE' => 'Pendiente',
    'ESTADO_ORDEN_PAGO_RECHAZADO' => 'Rechazada',
    'ESTADO_ORDEN_PAGO_COMPLETADO' => 'Completado',
    'ESTADO_ORDEN_PAGO_FALLIDO' => 'Fallido',

    'ESTADO_ORDEN_AUTENTICACION_PENDIENTE' => 'autenticacion_pendiente',
    'ESTADO_ORDEN_AUTENTICACION_EXITOSA' => 'autenticacion_exitosa',
    'ESTADO_ORDEN_AUTENTICACION_RECHAZADA' => 'autenticacion_rechazada',
    'ESTADO_ORDEN_AUTENTICACION_CANCELADA' => 'autenticacion_cancelada',
    'ESTADO_ORDEN_AUTENTICACION_FALLIDA' => 'autenticacion_fallida',

    'TIPO_DOCUMENTO_TICKET' => 'Ticket',
    'TIPO_DOCUMENTO_FACTURA' => 'Factura',
    'TIPO_DOCUMENTO_CREDITO_FISCAL' => 'Crédito fiscal',
    'TIPO_DOCUMENTO_COTIZACION' => 'Cotización',
    'TIPO_DOCUMENTO_ORDEN_COMPRA' => 'Orden de compra',

    'TIPO_USUARIO_ADMINISTRADOR' => 'Administrador',
    'TIPO_USUARIO_VENDEDOR' => 'Vendedor',
    'TIPO_USUARIO_ALMACEN' => 'Almacén',

    'MAIL_CC_ADDRESS_1' => 'jennifer.d@smartpyme.sv',
    'MAIL_CC_ADDRESS_2' => 'contact@smartpyme.sv',

    /** Destinatarios de reportes internos de suscripciones (equipo SmartPyme). */
    'MAIL_EQUIPO_REPORTES_SUSCRIPCION' => [
        'jose.e@smartpyme.sv',
        // 'jennifer.d@smartpyme.sv',
        // 'karla.b@smartpyme.sv',
        // 'alejandro.a@smartpyme.sv',
    ],

    /** Reporte mensual de flujo de caja (Excel): entradas esperadas por quincena. */
    'MAIL_REPORTE_FLUJO_CAJA_MENSUAL' => "alejandro.a@smartpyme.sv",
    // 'alejandro.a@smartpyme.sv',

    'FRECUENCIA_PAGO_MENSUAL' => 'Mensual',
    'FRECUENCIA_PAGO_TRIMESTRAL' => 'Trimestral',
    'FRECUENCIA_PAGO_ANUAL' => 'Anual',
];
