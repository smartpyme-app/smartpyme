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
