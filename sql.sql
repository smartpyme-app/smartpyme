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
