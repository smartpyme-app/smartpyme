ALTER TABLE devoluciones_venta ADD correlativo varchar(255) NULL after fecha;
ALTER TABLE devoluciones_venta ADD id_documento int NULL after correlativo;

ALTER TABLE productos ADD deleted_at timestamp NULL after updated_at;
ALTER TABLE inventario ADD deleted_at timestamp NULL after updated_at;


CREATE TABLE licencias (
    id int NOT NULL AUTO_INCREMENT,
    num_licencias int NULL,
    id_empresa int  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE licencia_empresas (
    id int NOT NULL AUTO_INCREMENT,
    id_licencia int  NOT NULL,
    id_empresa int NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE empresas ADD agrupar_detalles_venta BOOL DEFAULT false after editar_precio_venta;
ALTER TABLE empresas ADD vendedor_inventario BOOL DEFAULT false after agrupar_detalles_venta;
ALTER TABLE empresas ADD venta_consigna BOOL DEFAULT true after vendedor_inventario;

ALTER TABLE eventos CHANGE id_servicio id_servicio INT(11) NULL;

CREATE TABLE detalles_evento (
    id int NOT NULL AUTO_INCREMENT,
    id_producto int  NOT NULL,
    cantidad int NOT NULL,
    id_evento int NOT NULL,
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


ALTER TABLE sucursales ADD tipo_establecimiento varchar(255) NULL AFTER direccion;
ALTER TABLE sucursales ADD cod_estable_mh varchar(255) NULL AFTER tipo_establecimiento;




CREATE TABLE cotizacion_ventas (
  id int unsigned NOT NULL AUTO_INCREMENT,
  estado varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  observaciones text COLLATE utf8mb4_unicode_ci,
  fecha_expiracion date DEFAULT NULL,
  fecha date NOT NULL,
  total decimal(10,2) NOT NULL DEFAULT '0.00',
  correlativo int DEFAULT NULL,
  id_documento int DEFAULT NULL,
  id_cliente int DEFAULT NULL,
  id_proyecto int DEFAULT NULL,
  id_usuario int NOT NULL,
  id_vendedor int DEFAULT NULL,
  id_empresa int NOT NULL,
  id_sucursal int NOT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  cobrar_impuestos BOOL DEFAULT false,
  aplicar_retencion BOOL DEFAULT false,
  PRIMARY KEY (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalles_cotizacion_ventas (
  id int NOT NULL AUTO_INCREMENT,
  cantidad decimal(10,2) NOT NULL,
  precio double NOT NULL,
  total double NOT NULL,
  total_costo decimal(9,2) DEFAULT '0.00',
  descuento double NOT NULL DEFAULT '0',
  no_sujeta decimal(9,2) NOT NULL DEFAULT '0.00',
  exenta decimal(9,2) NOT NULL DEFAULT '0.00',
  cuenta_a_terceros decimal(9,2) NOT NULL DEFAULT '0.00',
  subtotal decimal(9,4) DEFAULT '0.0000',
  gravada decimal(9,4) DEFAULT '0.0000',
  iva decimal(9,4) DEFAULT '0.0000',
  descripcion varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  id_producto int NOT NULL,
  id_cotizacion_venta int NOT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



ALTER TABLE clientes ADD id_cuenta_contable INT NULL after id_usuario;
ALTER TABLE proveedores ADD id_cuenta_contable INT  NULL after id_usuario;


ALTER TABLE categorias ADD id_sucursal INT  NULL after id_empresa;
ALTER TABLE categorias ADD id_cuenta_contable INT  NULL after id_sucursal;
ALTER TABLE categorias ADD id_cuenta_contable_costo INT  NULL after id_cuenta_contable;
ALTER TABLE categoria_sucursal_cuenta ADD id_cuenta_contable_ingresos INT  NULL after id_cuenta_contable_costo;
ALTER TABLE categoria_sucursal_cuenta ADD id_cuenta_contable_devoluciones INT  NULL after id_cuenta_contable_ingresos;


CREATE TABLE categoria_sucursal_cuenta (
    id int NOT NULL AUTO_INCREMENT,
    categoria_id INT NOT NULL,
    sucursal_id INT NOT NULL,
    cuenta_contable_id INT NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);
