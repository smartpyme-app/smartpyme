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

        'empresas' => [
            'ver' => 'organizacion.empresas.ver',
            'crear' => 'organizacion.empresas.crear',
            'editar' => 'organizacion.empresas.editar',
            'eliminar' => 'organizacion.empresas.eliminar'
        ],

        'usuarios' => [
            'ver' => 'organizacion.usuarios.ver',
            'crear' => 'organizacion.usuarios.crear',
            'editar' => 'organizacion.usuarios.editar',
            'eliminar' => 'organizacion.usuarios.eliminar'
        ],

        'licencias' => [
            'ver' => 'organizacion.licencias.ver',
            'crear' => 'organizacion.licencias.crear',
            'editar' => 'organizacion.licencias.editar',
            'eliminar' => 'organizacion.licencias.eliminar'
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

        'dashboards' => [
            'ver' => 'administracion.dashboards.ver',
            'crear' => 'administracion.dashboards.crear',
            'editar' => 'administracion.dashboards.editar',
            'eliminar' => 'administracion.dashboards.eliminar'
        ],

        'facturaciones' => [
            'ver' => 'administracion.facturaciones.ver',
            'crear' => 'administracion.facturaciones.crear',
            'editar' => 'administracion.facturaciones.editar',
            'eliminar' => 'administracion.facturaciones.eliminar'
        ],
        'sucursales' => [
            'ver' => 'administracion.sucursales.ver',
            'crear' => 'administracion.sucursales.crear',
            'editar' => 'administracion.sucursales.editar',
            'eliminar' => 'administracion.sucursales.eliminar'
        ],
        'roles' => [
            'ver' => 'administracion.roles.ver',
            'crear' => 'administracion.roles.crear',
            'editar' => 'administracion.roles.editar',
            'eliminar' => 'administracion.roles.eliminar'
        ],
        'modules' => [
            'ver' => 'administracion.modules.ver',
            'crear' => 'administracion.modules.crear',
            'editar' => 'administracion.modules.editar',
            'eliminar' => 'administracion.modules.eliminar'
        ],
        'empresas' => [
            'ver' => 'administracion.empresas.ver',
            'crear' => 'administracion.empresas.crear',
            'editar' => 'administracion.empresas.editar',
            'eliminar' => 'administracion.empresas.eliminar'
        ],
        'licencias' => [
            'ver' => 'administracion.licencias.ver',
            'crear' => 'administracion.licencias.crear',
            'editar' => 'administracion.licencias.editar',
            'eliminar' => 'administracion.licencias.eliminar'
        ],
        'usuarios' => [
            'ver' => 'administracion.usuarios.ver',
            'crear' => 'administracion.usuarios.crear',
            'editar' => 'administracion.usuarios.editar',
            'eliminar' => 'administracion.usuarios.eliminar'
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
        'eliminar' => 'inteligencia_negocios.eliminar'
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

        'inventario' => [
            'ver' => 'productos.inventario.ver',
            'crear' => 'productos.inventario.crear',
            'editar' => 'productos.inventario.editar',
            'eliminar' => 'productos.inventario.eliminar'
        ],

        'bodegas' => [
            'ver' => 'productos.bodegas.ver',
            'crear' => 'productos.bodegas.crear',
            'editar' => 'productos.bodegas.editar',
            'eliminar' => 'productos.bodegas.eliminar'
        ],

        'categorias' => [
            'ver' => 'productos.categorias.ver',
            'crear' => 'productos.categorias.crear',
            'editar' => 'productos.categorias.editar',
            'eliminar' => 'productos.categorias.eliminar'
        ],

        'campos_personalizados' => [
            'ver' => 'productos.campos_personalizados.ver',
            'crear' => 'productos.campos_personalizados.crear',
            'editar' => 'productos.campos_personalizados.editar',
            'eliminar' => 'productos.campos_personalizados.eliminar'
        ],

        'paquetes' => [
            'ver' => 'productos.paquetes.ver',
            'crear' => 'productos.paquetes.crear',
            'editar' => 'productos.paquetes.editar',
            'eliminar' => 'productos.paquetes.eliminar'
        ],
        'ajustes' => [
            'ver' => 'productos.ajustes.ver',
            'crear' => 'productos.ajustes.crear',
            'editar' => 'productos.ajustes.editar',
            'eliminar' => 'productos.ajustes.eliminar'
        ],
        'materias_primas' => [
            'ver' => 'productos.materias_primas.ver',
            'crear' => 'productos.materias_primas.crear',
            'editar' => 'productos.materias_primas.editar',
            'eliminar' => 'productos.materias_primas.eliminar'
        ],
        'compuestos' => [
            'ver' => 'productos.compuestos.ver',
            'crear' => 'productos.compuestos.crear',
            'editar' => 'productos.compuestos.editar',
            'eliminar' => 'productos.compuestos.eliminar'
        ],
        'combos' => [
            'ver' => 'productos.combos.ver',
            'crear' => 'productos.combos.crear',
            'editar' => 'productos.combos.editar',
            'eliminar' => 'productos.combos.eliminar'
        ],
        'promociones' => [
            'ver' => 'productos.promociones.ver',
            'crear' => 'productos.promociones.crear',
            'editar' => 'productos.promociones.editar',
            'eliminar' => 'productos.promociones.eliminar'
        ],
        'traslados' => [
            'ver' => 'productos.traslados.ver',
            'crear' => 'productos.traslados.crear',
            'editar' => 'productos.traslados.editar',
            'eliminar' => 'productos.traslados.eliminar'
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
        'eliminar' => 'servicios.eliminar'
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

        'registros' => [
            'ver' => 'ventas.registros.ver',
            'crear' => 'ventas.registros.crear',
            'editar' => 'ventas.registros.editar',
            'eliminar' => 'ventas.registros.eliminar'
        ],

        'cotizaciones' => [
            'ver' => 'ventas.cotizaciones.ver',
            'crear' => 'ventas.cotizaciones.crear',
            'editar' => 'ventas.cotizaciones.editar',
            'eliminar' => 'ventas.cotizaciones.eliminar'
        ],

        'clientes' => [
            'ver' => 'ventas.clientes.ver',
            'crear' => 'ventas.clientes.crear',
            'editar' => 'ventas.clientes.editar',
            'eliminar' => 'ventas.clientes.eliminar'
        ],

        'canales_venta' => [
            'ver' => 'ventas.canales_venta.ver',
            'crear' => 'ventas.canales_venta.crear',
            'editar' => 'ventas.canales_venta.editar',
            'eliminar' => 'ventas.canales_venta.eliminar'
        ],

        'formas_pago' => [
            'ver' => 'ventas.formas_pago.ver',
            'crear' => 'ventas.formas_pago.crear',
            'editar' => 'ventas.formas_pago.editar',
            'eliminar' => 'ventas.formas_pago.eliminar'
        ],

        'proyectos' => [
            'ver' => 'ventas.proyectos.ver',
            'crear' => 'ventas.proyectos.crear',
            'editar' => 'ventas.proyectos.editar',
            'eliminar' => 'ventas.proyectos.eliminar'
        ],
        'abonos' => [
            'ver' => 'ventas.abonos.ver',
            'crear' => 'ventas.abonos.crear',
            'editar' => 'ventas.abonos.editar',
            'eliminar' => 'ventas.abonos.eliminar'
        ],
        'devoluciones' => [
            'ver' => 'ventas.devoluciones.ver',
            'crear' => 'ventas.devoluciones.crear',
            'editar' => 'ventas.devoluciones.editar',
            'eliminar' => 'ventas.devoluciones.eliminar'
        ],
        'ordenes_produccion' => [
            'ver' => 'ventas.ordenes_produccion.ver',
            'crear' => 'ventas.ordenes_produccion.crear',
            'editar' => 'ventas.ordenes_produccion.editar',
            'eliminar' => 'ventas.ordenes_produccion.eliminar'
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

        'registros' => [
            'ver' => 'compras.registros.ver',
            'crear' => 'compras.registros.crear',
            'editar' => 'compras.registros.editar',
            'eliminar' => 'compras.registros.eliminar'
        ],

        'ordenes_compra' => [
            'ver' => 'compras.ordenes_compra.ver',
            'crear' => 'compras.ordenes_compra.crear',
            'editar' => 'compras.ordenes_compra.editar',
            'eliminar' => 'compras.ordenes_compra.eliminar'
        ],

        'proveedores' => [
            'ver' => 'compras.proveedores.ver',
            'crear' => 'compras.proveedores.crear',
            'editar' => 'compras.proveedores.editar',
            'eliminar' => 'compras.proveedores.eliminar'
        ],

        'retaceo' => [
            'ver' => 'compras.retaceo.ver',
            'crear' => 'compras.retaceo.crear',
            'editar' => 'compras.retaceo.editar',
            'eliminar' => 'compras.retaceo.eliminar'
        ],
        'devoluciones' => [
            'ver' => 'compras.devoluciones.ver',
            'crear' => 'compras.devoluciones.crear',
            'editar' => 'compras.devoluciones.editar',
            'eliminar' => 'compras.devoluciones.eliminar'
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

        'registros' => [
            'ver' => 'gastos.registros.ver',
            'crear' => 'gastos.registros.crear',
            'editar' => 'gastos.registros.editar',
            'eliminar' => 'gastos.registros.eliminar'
        ],

        'categorias' => [
            'ver' => 'gastos.categorias.ver',
            'crear' => 'gastos.categorias.crear',
            'editar' => 'gastos.categorias.editar',
            'eliminar' => 'gastos.categorias.eliminar'
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
        'eliminar' => 'citas.eliminar'
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

        'bancos' => [
            'ver' => 'finanzas.bancos.ver',
            'crear' => 'finanzas.bancos.crear',
            'editar' => 'finanzas.bancos.editar',
            'eliminar' => 'finanzas.bancos.eliminar'
        ],

        'reporteria' => [
            'ver' => 'finanzas.reporteria.ver',
            'crear' => 'finanzas.reporteria.crear',
            'editar' => 'finanzas.reporteria.editar',
            'eliminar' => 'finanzas.reporteria.eliminar'
        ],

        'libro_iva' => [
            'ver' => 'finanzas.libro_iva.ver',
            'crear' => 'finanzas.libro_iva.crear',
            'editar' => 'finanzas.libro_iva.editar',
            'eliminar' => 'finanzas.libro_iva.eliminar'
        ],

        'presupuestos' => [
            'ver' => 'finanzas.presupuestos.ver',
            'crear' => 'finanzas.presupuestos.crear',
            'editar' => 'finanzas.presupuestos.editar',
            'eliminar' => 'finanzas.presupuestos.eliminar'
        ],

        'documentos' => [
            'ver' => 'finanzas.documentos.ver',
            'crear' => 'finanzas.documentos.crear',
            'editar' => 'finanzas.documentos.editar',
            'eliminar' => 'finanzas.documentos.eliminar'
        ],

        'impuestos' => [
            'ver' => 'finanzas.impuestos.ver',
            'crear' => 'finanzas.impuestos.crear',
            'editar' => 'finanzas.impuestos.editar',
            'eliminar' => 'finanzas.impuestos.eliminar'
        ],

        'cierre_caja' => [
            'ver' => 'finanzas.cierre_caja.ver',
            'crear' => 'finanzas.cierre_caja.crear',
            'editar' => 'finanzas.cierre_caja.editar',
            'eliminar' => 'finanzas.cierre_caja.eliminar'
        ],
        'cheques' => [
            'ver' => 'finanzas.cheques.ver',
            'crear' => 'finanzas.cheques.crear',
            'editar' => 'finanzas.cheques.editar',
            'eliminar' => 'finanzas.cheques.eliminar'
        ],
        'conciliaciones' => [
            'ver' => 'finanzas.conciliaciones.ver',
            'crear' => 'finanzas.conciliaciones.crear',
            'editar' => 'finanzas.conciliaciones.editar',
            'eliminar' => 'finanzas.conciliaciones.eliminar'
        ],
        'cuentas' => [
            'ver' => 'finanzas.cuentas.ver',
            'crear' => 'finanzas.cuentas.crear',
            'editar' => 'finanzas.cuentas.editar',
            'eliminar' => 'finanzas.cuentas.eliminar'
        ],
        'transacciones' => [
            'ver' => 'finanzas.transacciones.ver',
            'crear' => 'finanzas.transacciones.crear',
            'editar' => 'finanzas.transacciones.editar',
            'eliminar' => 'finanzas.transacciones.eliminar'
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

        'partidas' => [
            'ver' => 'contabilidad.partidas.ver',
            'crear' => 'contabilidad.partidas.crear',
            'editar' => 'contabilidad.partidas.editar',
            'eliminar' => 'contabilidad.partidas.eliminar'
        ],

        'catalogo_cuentas' => [
            'ver' => 'contabilidad.catalogo_cuentas.ver',
            'crear' => 'contabilidad.catalogo_cuentas.crear',
            'editar' => 'contabilidad.catalogo_cuentas.editar',
            'eliminar' => 'contabilidad.catalogo_cuentas.eliminar'
        ],

        'configuracion' => [
            'ver' => 'contabilidad.configuracion.ver',
            'crear' => 'contabilidad.configuracion.crear',
            'editar' => 'contabilidad.configuracion.editar',
            'eliminar' => 'contabilidad.configuracion.eliminar'
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
        'eliminar' => 'ayuda.eliminar'
    ]
];