-- FEX

ALTER TABLE ventas ADD tipo_item_export varchar(255) NULL AFTER dte_invalidacion;
ALTER TABLE ventas ADD cod_incoterm varchar(255) NULL AFTER tipo_item_export;
ALTER TABLE ventas ADD incoterm varchar(255) NULL AFTER cod_incoterm;
ALTER TABLE ventas ADD recinto_fiscal varchar(255) NULL AFTER incoterm;
ALTER TABLE ventas ADD regimen varchar(255) NULL AFTER recinto_fiscal;

ALTER TABLE ventas ADD seguro decimal(10,2) default 0 AFTER regimen;
ALTER TABLE ventas ADD flete decimal(10,2) default 0 AFTER seguro;

ALTER TABLE clientes ADD tipo_persona varchar(255) default 0 AFTER cod_departamento;
ALTER TABLE clientes ADD tipo_documento varchar(255) default 0 AFTER tipo_persona;

ALTER TABLE devoluciones_venta ADD id_sucursal int NULL AFTER id_empresa;
ALTER TABLE devoluciones_compra ADD id_sucursal int NULL AFTER id_empresa;


CREATE TABLE detalles_compuesto_devolucion_venta (
    id int NOT NULL AUTO_INCREMENT,
    id_producto int  NOT NULL,
    cantidad decimal(10,2) NULL,
    id_detalle int  NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);
