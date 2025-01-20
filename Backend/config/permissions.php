<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Permisos de Sistema y Organización
    |--------------------------------------------------------------------------
    */
    'PERMISSION_ORGANIZACION' => [
        'ver' => 'organizacion.ver',
        'crear' => 'organizacion.crear',
        'editar' => 'organizacion.editar',
        'eliminar' => 'organizacion.eliminar',
        'exportar' => 'organizacion.exportar',

        'empresas' => [
            'ver' => 'organizacion.empresas.ver',
            'crear' => 'organizacion.empresas.crear',
            'editar' => 'organizacion.empresas.editar',
            'eliminar' => 'organizacion.empresas.eliminar',
            'exportar' => 'organizacion.empresas.exportar'
        ],

        'usuarios' => [
            'ver' => 'organizacion.usuarios.ver',
            'crear' => 'organizacion.usuarios.crear',
            'editar' => 'organizacion.usuarios.editar',
            'eliminar' => 'organizacion.usuarios.eliminar',
            'exportar' => 'organizacion.usuarios.exportar'
        ],

        'licencias' => [
            'ver' => 'organizacion.licencias.ver',
            'crear' => 'organizacion.licencias.crear',
            'editar' => 'organizacion.licencias.editar',
            'eliminar' => 'organizacion.licencias.eliminar',
            'exportar' => 'organizacion.licencias.exportar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Administración
    |--------------------------------------------------------------------------
    */
    'PERMISSION_ADMINISTRACION' => [
        'ver' => 'administracion.ver',
        'crear' => 'administracion.crear',
        'editar' => 'administracion.editar',
        'eliminar' => 'administracion.eliminar',
        'exportar' => 'administracion.exportar',

        'dashboards' => [
            'ver' => 'administracion.dashboards.ver',
            'crear' => 'administracion.dashboards.crear',
            'editar' => 'administracion.dashboards.editar',
            'eliminar' => 'administracion.dashboards.eliminar',
            'exportar' => 'administracion.dashboards.exportar'
        ],

        'facturaciones' => [
            'ver' => 'administracion.facturaciones.ver',
            'crear' => 'administracion.facturaciones.crear',
            'editar' => 'administracion.facturaciones.editar',
            'eliminar' => 'administracion.facturaciones.eliminar',
            'exportar' => 'administracion.facturaciones.exportar'
        ],
        'sucursales' => [
            'ver' => 'administracion.sucursales.ver',
            'crear' => 'administracion.sucursales.crear',
            'editar' => 'administracion.sucursales.editar',
            'eliminar' => 'administracion.sucursales.eliminar',
            'exportar' => 'administracion.sucursales.exportar'
        ],
        'roles' => [
            'ver' => 'administracion.roles.ver',
            'crear' => 'administracion.roles.crear',
            'editar' => 'administracion.roles.editar',
            'eliminar' => 'administracion.roles.eliminar',
            'exportar' => 'administracion.roles.exportar'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Inteligencia de Negocios
    |--------------------------------------------------------------------------
    */
    'PERMISSION_INTELIGENCIA_NEGOCIOS' => [
        'ver' => 'inteligencia_negocios.ver',
        'crear' => 'inteligencia_negocios.crear',
        'editar' => 'inteligencia_negocios.editar',
        'eliminar' => 'inteligencia_negocios.eliminar',
        'exportar' => 'inteligencia_negocios.exportar'
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Productos
    |--------------------------------------------------------------------------
    */
    'PERMISSION_PRODUCTOS' => [
        'ver' => 'productos.ver',
        'crear' => 'productos.crear',
        'editar' => 'productos.editar',
        'eliminar' => 'productos.eliminar',
        'exportar' => 'productos.exportar',

        'inventario' => [
            'ver' => 'productos.inventario.ver',
            'crear' => 'productos.inventario.crear',
            'editar' => 'productos.inventario.editar',
            'eliminar' => 'productos.inventario.eliminar',
            'exportar' => 'productos.inventario.exportar'
        ],

        'bodegas' => [
            'ver' => 'productos.bodegas.ver',
            'crear' => 'productos.bodegas.crear',
            'editar' => 'productos.bodegas.editar',
            'eliminar' => 'productos.bodegas.eliminar',
            'exportar' => 'productos.bodegas.exportar'
        ],

        'categorias' => [
            'ver' => 'productos.categorias.ver',
            'crear' => 'productos.categorias.crear',
            'editar' => 'productos.categorias.editar',
            'eliminar' => 'productos.categorias.eliminar',
            'exportar' => 'productos.categorias.exportar'
        ],

        'campos_personalizados' => [
            'ver' => 'productos.campos_personalizados.ver',
            'crear' => 'productos.campos_personalizados.crear',
            'editar' => 'productos.campos_personalizados.editar',
            'eliminar' => 'productos.campos_personalizados.eliminar',
            'exportar' => 'productos.campos_personalizados.exportar'
        ],

        'paquetes' => [
            'ver' => 'productos.paquetes.ver',
            'crear' => 'productos.paquetes.crear',
            'editar' => 'productos.paquetes.editar',
            'eliminar' => 'productos.paquetes.eliminar',
            'exportar' => 'productos.paquetes.exportar'
        ],
        'ajustes' => [
            'ver' => 'productos.ajustes.ver',
            'crear' => 'productos.ajustes.crear',
            'editar' => 'productos.ajustes.editar',
            'eliminar' => 'productos.ajustes.eliminar',
            'exportar' => 'productos.ajustes.exportar'
        ],
        'materias_primas' => [
            'ver' => 'productos.materias_primas.ver',
            'crear' => 'productos.materias_primas.crear',
            'editar' => 'productos.materias_primas.editar',
            'eliminar' => 'productos.materias_primas.eliminar',
            'exportar' => 'productos.materias_primas.exportar'
        ],
        'compuestos' => [
            'ver' => 'productos.compuestos.ver',
            'crear' => 'productos.compuestos.crear',
            'editar' => 'productos.compuestos.editar',
            'eliminar' => 'productos.compuestos.eliminar',
            'exportar' => 'productos.compuestos.exportar'
        ],
        'combos' => [
            'ver' => 'productos.combos.ver',
            'crear' => 'productos.combos.crear',
            'editar' => 'productos.combos.editar',
            'eliminar' => 'productos.combos.eliminar',
            'exportar' => 'productos.combos.exportar'
        ],
        'promociones' => [
            'ver' => 'productos.promociones.ver',
            'crear' => 'productos.promociones.crear',
            'editar' => 'productos.promociones.editar',
            'eliminar' => 'productos.promociones.eliminar',
            'exportar' => 'productos.promociones.exportar'
        ],
        'traslados' => [
            'ver' => 'productos.traslados.ver',
            'crear' => 'productos.traslados.crear',
            'editar' => 'productos.traslados.editar',
            'eliminar' => 'productos.traslados.eliminar',
            'exportar' => 'productos.traslados.exportar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Servicios
    |--------------------------------------------------------------------------
    */
    'PERMISSION_SERVICIOS' => [
        'ver' => 'servicios.ver',
        'crear' => 'servicios.crear',
        'editar' => 'servicios.editar',
        'eliminar' => 'servicios.eliminar',
        'exportar' => 'servicios.exportar'
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Ventas
    |--------------------------------------------------------------------------
    */
    'PERMISSION_VENTAS' => [
        'ver' => 'ventas.ver',
        'crear' => 'ventas.crear',
        'editar' => 'ventas.editar',
        'eliminar' => 'ventas.eliminar',
        'exportar' => 'ventas.exportar',

        'registros' => [
            'ver' => 'ventas.registros.ver',
            'crear' => 'ventas.registros.crear',
            'editar' => 'ventas.registros.editar',
            'eliminar' => 'ventas.registros.eliminar',
            'exportar' => 'ventas.registros.exportar'
        ],

        'cotizaciones' => [
            'ver' => 'ventas.cotizaciones.ver',
            'crear' => 'ventas.cotizaciones.crear',
            'editar' => 'ventas.cotizaciones.editar',
            'eliminar' => 'ventas.cotizaciones.eliminar',
            'exportar' => 'ventas.cotizaciones.exportar'
        ],

        'clientes' => [
            'ver' => 'ventas.clientes.ver',
            'crear' => 'ventas.clientes.crear',
            'editar' => 'ventas.clientes.editar',
            'eliminar' => 'ventas.clientes.eliminar',
            'exportar' => 'ventas.clientes.exportar'
        ],

        'canales_venta' => [
            'ver' => 'ventas.canales_venta.ver',
            'crear' => 'ventas.canales_venta.crear',
            'editar' => 'ventas.canales_venta.editar',
            'eliminar' => 'ventas.canales_venta.eliminar',
            'exportar' => 'ventas.canales_venta.exportar'
        ],

        'formas_pago' => [
            'ver' => 'ventas.formas_pago.ver',
            'crear' => 'ventas.formas_pago.crear',
            'editar' => 'ventas.formas_pago.editar',
            'eliminar' => 'ventas.formas_pago.eliminar',
            'exportar' => 'ventas.formas_pago.exportar'
        ],

        'proyectos' => [
            'ver' => 'ventas.proyectos.ver',
            'crear' => 'ventas.proyectos.crear',
            'editar' => 'ventas.proyectos.editar',
            'eliminar' => 'ventas.proyectos.eliminar',
            'exportar' => 'ventas.proyectos.exportar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Compras
    |--------------------------------------------------------------------------
    */
    'PERMISSION_COMPRAS' => [
        'ver' => 'compras.ver',
        'crear' => 'compras.crear',
        'editar' => 'compras.editar',
        'eliminar' => 'compras.eliminar',
        'exportar' => 'compras.exportar',

        'registros' => [
            'ver' => 'compras.registros.ver',
            'crear' => 'compras.registros.crear',
            'editar' => 'compras.registros.editar',
            'eliminar' => 'compras.registros.eliminar',
            'exportar' => 'compras.registros.exportar'
        ],

        'ordenes_compra' => [
            'ver' => 'compras.ordenes_compra.ver',
            'crear' => 'compras.ordenes_compra.crear',
            'editar' => 'compras.ordenes_compra.editar',
            'eliminar' => 'compras.ordenes_compra.eliminar',
            'exportar' => 'compras.ordenes_compra.exportar'
        ],

        'proveedores' => [
            'ver' => 'compras.proveedores.ver',
            'crear' => 'compras.proveedores.crear',
            'editar' => 'compras.proveedores.editar',
            'eliminar' => 'compras.proveedores.eliminar',
            'exportar' => 'compras.proveedores.exportar'
        ],

        'retaceo' => [
            'ver' => 'compras.retaceo.ver',
            'crear' => 'compras.retaceo.crear',
            'editar' => 'compras.retaceo.editar',
            'eliminar' => 'compras.retaceo.eliminar',
            'exportar' => 'compras.retaceo.exportar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Gastos
    |--------------------------------------------------------------------------
    */
    'PERMISSION_GASTOS' => [
        'ver' => 'gastos.ver',
        'crear' => 'gastos.crear',
        'editar' => 'gastos.editar',
        'eliminar' => 'gastos.eliminar',
        'exportar' => 'gastos.exportar',

        'registros' => [
            'ver' => 'gastos.registros.ver',
            'crear' => 'gastos.registros.crear',
            'editar' => 'gastos.registros.editar',
            'eliminar' => 'gastos.registros.eliminar',
            'exportar' => 'gastos.registros.exportar'
        ],

        'categorias' => [
            'ver' => 'gastos.categorias.ver',
            'crear' => 'gastos.categorias.crear',
            'editar' => 'gastos.categorias.editar',
            'eliminar' => 'gastos.categorias.eliminar',
            'exportar' => 'gastos.categorias.exportar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Citas
    |--------------------------------------------------------------------------
    */
    'PERMISSION_CITAS' => [
        'ver' => 'citas.ver',
        'crear' => 'citas.crear',
        'editar' => 'citas.editar',
        'eliminar' => 'citas.eliminar',
        'exportar' => 'citas.exportar'
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Finanzas
    |--------------------------------------------------------------------------
    */
    'PERMISSION_FINANZAS' => [
        'ver' => 'finanzas.ver',
        'crear' => 'finanzas.crear',
        'editar' => 'finanzas.editar',
        'eliminar' => 'finanzas.eliminar',
        'exportar' => 'finanzas.exportar',

        'bancos' => [
            'ver' => 'finanzas.bancos.ver',
            'crear' => 'finanzas.bancos.crear',
            'editar' => 'finanzas.bancos.editar',
            'eliminar' => 'finanzas.bancos.eliminar',
            'exportar' => 'finanzas.bancos.exportar'
        ],

        'reporteria' => [
            'ver' => 'finanzas.reporteria.ver',
            'crear' => 'finanzas.reporteria.crear',
            'editar' => 'finanzas.reporteria.editar',
            'eliminar' => 'finanzas.reporteria.eliminar',
            'exportar' => 'finanzas.reporteria.exportar'
        ],

        'libro_iva' => [
            'ver' => 'finanzas.libro_iva.ver',
            'crear' => 'finanzas.libro_iva.crear',
            'editar' => 'finanzas.libro_iva.editar',
            'eliminar' => 'finanzas.libro_iva.eliminar',
            'exportar' => 'finanzas.libro_iva.exportar'
        ],

        'presupuestos' => [
            'ver' => 'finanzas.presupuestos.ver',
            'crear' => 'finanzas.presupuestos.crear',
            'editar' => 'finanzas.presupuestos.editar',
            'eliminar' => 'finanzas.presupuestos.eliminar',
            'exportar' => 'finanzas.presupuestos.exportar'
        ],

        'documentos' => [
            'ver' => 'finanzas.documentos.ver',
            'crear' => 'finanzas.documentos.crear',
            'editar' => 'finanzas.documentos.editar',
            'eliminar' => 'finanzas.documentos.eliminar',
            'exportar' => 'finanzas.documentos.exportar'
        ],

        'impuestos' => [
            'ver' => 'finanzas.impuestos.ver',
            'crear' => 'finanzas.impuestos.crear',
            'editar' => 'finanzas.impuestos.editar',
            'eliminar' => 'finanzas.impuestos.eliminar',
            'exportar' => 'finanzas.impuestos.exportar'
        ],

        'cierre_caja' => [
            'ver' => 'finanzas.cierre_caja.ver',
            'crear' => 'finanzas.cierre_caja.crear',
            'editar' => 'finanzas.cierre_caja.editar',
            'eliminar' => 'finanzas.cierre_caja.eliminar',
            'exportar' => 'finanzas.cierre_caja.exportar'
        ],
        'cheques' => [
            'ver' => 'finanzas.cheques.ver',
            'crear' => 'finanzas.cheques.crear',
            'editar' => 'finanzas.cheques.editar',
            'eliminar' => 'finanzas.cheques.eliminar',
            'exportar' => 'finanzas.cheques.exportar'
        ],
        'conciliaciones' => [
            'ver' => 'finanzas.conciliaciones.ver',
            'crear' => 'finanzas.conciliaciones.crear',
            'editar' => 'finanzas.conciliaciones.editar',
            'eliminar' => 'finanzas.conciliaciones.eliminar',
            'exportar' => 'finanzas.conciliaciones.exportar'
        ],
        'cuentas' => [
            'ver' => 'finanzas.ver',
            'crear' => 'finanzas.cuentas.crear',
            'editar' => 'finanzas.cuentas.editar',
            'eliminar' => 'finanzas.cuentas.eliminar',
            'exportar' => 'finanzas.cuentas.exportar'
        ],
        'transacciones' => [
            'ver' => 'finanzas.transacciones.ver',
            'crear' => 'finanzas.transacciones.crear',
            'editar' => 'finanzas.transacciones.editar',
            'eliminar' => 'finanzas.transacciones.eliminar',
            'exportar' => 'finanzas.transacciones.exportar'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Contabilidad
    |--------------------------------------------------------------------------
    */
    'PERMISSION_CONTABILIDAD' => [
        'ver' => 'contabilidad.ver',
        'crear' => 'contabilidad.crear',
        'editar' => 'contabilidad.editar',
        'eliminar' => 'contabilidad.eliminar',
        'exportar' => 'contabilidad.exportar',

        'partidas' => [
            'ver' => 'contabilidad.partidas.ver',
            'crear' => 'contabilidad.partidas.crear',
            'editar' => 'contabilidad.partidas.editar',
            'eliminar' => 'contabilidad.partidas.eliminar',
            'exportar' => 'contabilidad.partidas.exportar'
        ],

        'catalogo_cuentas' => [
            'ver' => 'contabilidad.catalogo_cuentas.ver',
            'crear' => 'contabilidad.catalogo_cuentas.crear',
            'editar' => 'contabilidad.catalogo_cuentas.editar',
            'eliminar' => 'contabilidad.catalogo_cuentas.eliminar',
            'exportar' => 'contabilidad.catalogo_cuentas.exportar'
        ],

        'configuracion' => [
            'ver' => 'contabilidad.configuracion.ver',
            'crear' => 'contabilidad.configuracion.crear',
            'editar' => 'contabilidad.configuracion.editar',
            'eliminar' => 'contabilidad.configuracion.eliminar',
            'exportar' => 'contabilidad.configuracion.exportar'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Permisos de Ayuda
    |--------------------------------------------------------------------------
    */
    'PERMISSION_AYUDA' => [
        'ver' => 'ayuda.ver',
        'crear' => 'ayuda.crear',
        'editar' => 'ayuda.editar',
        'eliminar' => 'ayuda.eliminar',
        'exportar' => 'ayuda.exportar'
    ]
];