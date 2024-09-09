-- Bancos
CREATE TABLE cuentas_bancarias (
    id int NOT NULL AUTO_INCREMENT,
    numero varchar(255) NOT NULL,
    nombre_banco varchar(255) NOT NULL,
    tipo varchar(255) NOT NULL,
    saldo decimal(10,2) NOT NULL,
    correlativo_cheques int NULL,
    id_cuenta_contable int NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cuentas_bancarias_cheques (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    id_cuenta int NOT NULL,
    correlativo int NOT NULL,
    anombrede varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    referencia varchar(255) NULL,
    id_referencia int NULL,
    total decimal(10,2) NOT NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cuentas_bancarias_transacciones (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    id_cuenta int NOT NULL,
    concepto varchar(255) NOT NULL,
    tipo varchar(255) NOT NULL,
    tipo_operacion varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    total decimal(10,2) NOT NULL,
    referencia varchar(255) NULL,
    id_referencia int NULL,
    url_referencia varchar(255) NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE cuentas_bancarias_conciliaciones (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    desde date NOT NULL,
    hasta date NOT NULL,
    id_cuenta int NOT NULL,
    gastos decimal(10,2) NULL DEFAULT 0,
    impuestos decimal(10,2) NULL DEFAULT 0,
    otras_entradas decimal(10,2) NULL DEFAULT 0,
    saldo_anterior decimal(10,2) NOT NULL,
    saldo_actual decimal(10,2) NOT NULL,
    nota varchar(255) NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

-- Catalogo

CREATE TABLE contabilidad_configuracion (
    id int NOT NULL AUTO_INCREMENT,
    id_cuenta_ventas int NOT NULL,
    id_cuenta_devoluciones_ventas int NOT NULL,

    id_cuenta_inventario int NOT NULL,
    id_cuenta_ajustes_inventario int NOT NULL,

    id_cuenta_cxc int NOT NULL,
    id_cuenta_devoluciones_clientes int NOT NULL,
    id_cuenta_cxp int NOT NULL,
    id_cuenta_devoluciones_proveedores int NOT NULL,

    id_cuenta_iva_ventas int NOT NULL,
    id_cuenta_iva_compras int NOT NULL,

    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);


CREATE TABLE catalogo_cuentas (
    id int NOT NULL AUTO_INCREMENT,
    codigo int NOT NULL,
    nombre varchar(255) NOT NULL,
    naturaleza varchar(255) NOT NULL,
    id_cuenta_padre int NULL,
    rubro varchar(255) NOT NULL,
    nivel int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);


CREATE TABLE partidas (
    id int NOT NULL AUTO_INCREMENT,
    fecha date NOT NULL,
    tipo varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    estado varchar(255) NOT NULL,
    referencia varchar(50) NOT NULL,
    id_referencia int NOT NULL,
    id_usuario int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE partida_detalles (
    id int NOT NULL AUTO_INCREMENT,
    id_cuenta int NOT NULL,
    codigo varchar(255) NOT NULL,
    nombre_cuenta varchar(255) NOT NULL,
    concepto varchar(255) NOT NULL,
    debe decimal(10,2) NULL DEFAULT 0,
    haber decimal(10,2) NULL DEFAULT 0,
    saldo decimal(10,2) NULL DEFAULT 0,
    id_partida int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE retenciones (
    id int NOT NULL AUTO_INCREMENT,
    nombre varchar(255) NOT NULL,
    porcentaje decimal(10,2) NOT NULL,
    id_cuenta_contable_ventas int NOT NULL,
    id_cuenta_contable_compras int NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);




ALTER TABLE empresas ADD agrupar_detalles_venta BOOL DEFAULT false after editar_precio_venta;
ALTER TABLE empresas ADD vendedor_inventario BOOL DEFAULT false after agrupar_detalles_venta;
ALTER TABLE empresas ADD venta_consigna BOOL DEFAULT true after vendedor_inventario;

ALTER TABLE impuestos ADD id_cuenta_contable_ventas INT NULL after porcentaje;
ALTER TABLE impuestos ADD id_cuenta_contable_compras INT NULL after id_cuenta_contable_ventas;


-- Bodegas


CREATE TABLE sucursal_bodegas AS SELECT * FROM sucursales;
ALTER TABLE sucursal_bodegas ADD PRIMARY KEY(id);

ALTER TABLE `sucursal_bodegas`
  DROP `telefono`,
  DROP `correo`,
  DROP `municipio`,
  DROP `departamento`,
  DROP `direccion`;

ALTER TABLE sucursal_bodegas ADD id_sucursal INT NULL after activo;
UPDATE sucursal_bodegas SET id_sucursal=id;


ALTER TABLE ajustes CHANGE id_sucursal id_bodega INT(11) NULL DEFAULT NULL;
ALTER TABLE traslados CHANGE id_sucursal_de id_bodega_de INT(11) NULL DEFAULT NULL;
ALTER TABLE traslados CHANGE id_sucursal id_bodega INT(11) NULL DEFAULT NULL;

ALTER TABLE inventario CHANGE id_sucursal id_bodega INT(11) NULL DEFAULT NULL;

ALTER TABLE compras ADD id_bodega INT NOT NULL after total;
ALTER TABLE ventas ADD id_bodega INT NOT NULL after id_proyecto;

ALTER TABLE partidas ADD referencia varchar(50) NOT NULL after estado;
ALTER TABLE partidas ADD id_referencia INT NOT NULL after referencia;


--Traslados
ALTER TABLE traslados ADD fecha date NULL after id;
ALTER TABLE traslados CHANGE id_producto id_producto INT(11) NULL;
ALTER TABLE traslados CHANGE concepto concepto VARCHAR(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;
ALTER TABLE traslados CHANGE cantidad cantidad DECIMAL(10,2) NULL;
CREATE TABLE traslado_detalles (
    id int NOT NULL AUTO_INCREMENT,
    id_producto INT NOT NULL,
    cantidad decimal(10,2) NOT NULL,
    id_traslado int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);




CREATE TABLE producto_composicion_opciones (
    id int NOT NULL AUTO_INCREMENT,
    id_composicion int  NOT NULL,
    id_producto int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE venta_metodos_pago (
    id int NOT NULL AUTO_INCREMENT,
    id_venta int  NOT NULL,
    nombre varchar(255) NOT NULL,
    total decimal(9,2) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE empresas ADD vendedor_detalle_venta BOOL DEFAULT false after agrupar_detalles_venta;
ALTER TABLE empresas ADD facturacion_electronica BOOL DEFAULT false after vendedor_detalle_venta;
ALTER TABLE empresas ADD fe_ambiente varchar(10) DEFAULT '00' after facturacion_electronica;
ALTER TABLE empresas ADD cotizacion_compras_terminos text NULL after fe_ambiente;
ALTER TABLE empresas ADD enviar_dte BOOL DEFAULT false after fe_ambiente;

ALTER TABLE ventas ADD id_vendedor INT(11) NULL after id_usuario;
ALTER TABLE detalles_venta ADD id_vendedor INT(11) NULL after id_venta;

ALTER TABLE egresos ADD iva_percibido decimal(9,2) NULL after iva;

ALTER TABLE clientes ADD pais varchar(255) after direccion;

ALTER TABLE proyectos ADD id_cliente INT NULL after enable;



ALTER TABLE ventas ADD tipo_dte varchar(255) NULL AFTER id;
ALTER TABLE ventas ADD numero_control varchar(255) NULL AFTER id;
ALTER TABLE ventas ADD codigo_generacion varchar(255) NULL AFTER id;
ALTER TABLE ventas ADD sello_mh varchar(255) NULL AFTER codigo_generacion;

ALTER TABLE devoluciones_venta ADD tipo_dte varchar(255) NULL AFTER id;
ALTER TABLE devoluciones_venta ADD numero_control varchar(255) NULL AFTER id;
ALTER TABLE devoluciones_venta ADD codigo_generacion varchar(255) NULL AFTER id;
ALTER TABLE devoluciones_venta ADD sello_mh varchar(255) NULL AFTER codigo_generacion;
ALTER TABLE devoluciones_venta ADD dte LONGTEXT NULL AFTER id_usuario;
ALTER TABLE devoluciones_venta ADD dte_invalidacion LONGTEXT NULL AFTER dte;

ALTER TABLE egresos ADD tipo_dte varchar(255) NULL AFTER id;
ALTER TABLE egresos ADD numero_control varchar(255) NULL AFTER id;
ALTER TABLE egresos ADD codigo_generacion varchar(255) NULL AFTER id;
ALTER TABLE egresos ADD sello_mh varchar(255) NULL AFTER codigo_generacion;
ALTER TABLE egresos ADD renta_retenida DECIMAL(10,2) NULL DEFAULT '0' AFTER iva;
ALTER TABLE egresos ADD dte LONGTEXT NULL AFTER id_usuario;
ALTER TABLE egresos ADD dte_invalidacion LONGTEXT NULL AFTER dte;

ALTER TABLE proveedores ADD cod_municipio varchar(10) NULL AFTER municipio;
ALTER TABLE proveedores ADD cod_departamento varchar(10) NULL AFTER departamento;
ALTER TABLE proveedores ADD cod_giro varchar(10) NULL AFTER giro;
ALTER TABLE proveedores ADD pais varchar(255) NULL AFTER municipio;

ALTER TABLE detalles_devolucion_compra ADD no_sujeta varchar(255) NULL AFTER descuento;
ALTER TABLE detalles_devolucion_compra ADD exenta varchar(255) NULL AFTER no_sujeta;
ALTER TABLE detalles_devolucion_compra ADD gravada varchar(255) NULL AFTER exenta;


ALTER TABLE productos ADD talla varchar(255) NULL AFTER medida;
ALTER TABLE productos ADD color varchar(255) NULL AFTER talla;
ALTER TABLE productos ADD material varchar(255) NULL AFTER color;
ALTER TABLE productos ADD dimensiones decimal(9,2) NULL AFTER material;


ALTER TABLE categorias  ADD subcategoria BOOL DEFAULT false after id_empresa;
ALTER TABLE categorias  ADD id_cate_padre INT NULL after subcategoria;
CREATE TABLE detalles_compuesto_venta (
    id int NOT NULL AUTO_INCREMENT,
    id_producto int  NOT NULL,
    cantidad varchar(255) NOT NULL,
    id_detalle int  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);


CREATE TABLE paises (
    id int NOT NULL AUTO_INCREMENT,
    cod varchar(255) NOT NULL,
    nombre varchar(255)  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE proveedores ADD cod_pais varchar(255) NULL AFTER pais;
ALTER TABLE clientes ADD cod_pais varchar(255) NULL AFTER pais;

ALTER TABLE productos  ADD cod_proveed_prod varchar(255) NULL after id_categoria;
ALTER TABLE productos  ADD id_subcategoria INT(11) NULL after id_categoria;
