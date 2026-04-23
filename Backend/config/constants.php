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

    /** Días que suma la acción admin «Conceder acceso temporal» (sin mover fecha_proximo_pago). */
    'DIAS_ACCESO_TEMPORAL_ADMIN' => 2,

    /** Días hasta la próxima fecha de pago al registrar «Pago recibido» (transferencia/efectivo). */
    'DIAS_PAGO_RECIBIDO_PROXIMO_CICLO' => 30,

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
        'jennifer.d@smartpyme.sv',
        'karla.b@smartpyme.sv',
        'alejandro.a@smartpyme.sv',
    ],

    /** Reporte mensual de flujo de caja (Excel): entradas esperadas por quincena. */
    'MAIL_REPORTE_FLUJO_CAJA_MENSUAL' => "alejandro.a@smartpyme.sv",

    // Roles principales del JIRA
    // 'ROL_SUPER_ADMIN'          => 'super_admin',          // Del SP-117
    // 'ROL_ADMIN'                => 'admin',                // Del SP-117
    // 'ROL_CONTADOR_SUPERIOR'    => 'usuario_contador',       // Del SP-118
    // 'ROL_CONTADOR_AUXILIAR'    => 'auxiliar_contable',    // Del SP-118
    // 'ROL_USUARIO_SUPERVISOR'   => 'usuario_supervisor',       // Del SP-119
    // 'ROL_GERENTE_OPERACIONES' => 'gerente_operaciones',   // Del SP-119
    // 'ROL_GERENTE_COMPRAS'     => 'gerente_compras',      // Del SP-119
    // 'ROL_USUARIO'             => 'usuario',               // Del SP-120
    // 'ROL_SUPERVISOR_LIMITADO' => 'supervisor_limitado',

    'ROL_SUPER_ADMIN'          => 'super_admin',
    'ROL_ADMIN'                => 'admin',
    'ROL_USUARIO'              => 'usuario',
    'ROL_CONTADOR_SUPERIOR'    => 'contador_superior',
    'ROL_CONTADOR_AUXILIAR'    => 'contador_auxiliar',
    'ROL_GERENTE_COMPRAS'      => 'gerente_compras',
    'ROL_GERENTE_VENTAS'       => 'gerente_ventas',
    'ROL_GERENTE_OPERACIONES'  => 'gerente_operaciones',
    'ROL_USUARIO_VENTAS'       => 'usuario_ventas',
    'ROL_USUARIO_CITAS'        => 'usuario_citas',
    'ROL_USUARIO_CONSULTAS'    => 'usuario_consultas',
    'ROL_SUPERVISOR_LIMITADO'  => 'supervisor_limitado',
    // Los siguientes roles están comentados en el seeder y no se usan actualmente:
    // 'ROL_USUARIO_CAJERO'      => 'usuario_cajero',
    // 'ROL_USUARIO_VENDEDOR'    => 'usuario_vendedor',
    // 'ROL_USUARIO_SUPERVISOR'  => 'usuario_supervisor',
    // 'ROL_USUARIO_COCINERO'    => 'usuario_cocinero',

    // Roles adicionales según los nuevos requerimientos
    'ROL_USUARIO_VENTAS'      => 'usuario_ventas',
    'ROL_USUARIO_CITAS'       => 'usuario_citas',
    'ROL_USUARIO_CONSULTAS'   => 'usuario_consultas',
    'ROL_USUARIO_CAJERO'      => 'usuario_cajero',
    'ROL_USUARIO_VENDEDOR'    => 'usuario_vendedor',
    'ROL_USUARIO_COCINERO'    => 'usuario_cocinero',

    'FRECUENCIA_PAGO_MENSUAL' => 'Mensual',
    'FRECUENCIA_PAGO_TRIMESTRAL' => 'Trimestral',
    'FRECUENCIA_PAGO_ANUAL' => 'Anual',
];
