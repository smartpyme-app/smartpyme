-- Costo promedio
ALTER TABLE productos ADD costo_promedio decimal(10,2) NOT NULL default 0 after costo;
ALTER TABLE empresas ADD valor_inventario varchar(255) NOT NULL default 'ultimo' after vender_sin_stock;

ALTER TABLE ventas ADD condicion varchar(255) NULL default 'Contado' after num_cotizacion;

ALTER TABLE compras ADD tipo_gasto varchar(255) NULL after fecha_pago;
ALTER TABLE compras ADD sector varchar(255) NULL after tipo_gasto;
ALTER TABLE compras ADD clasificacion varchar(255) NULL after sector;
ALTER TABLE compras ADD tipo_operacion varchar(255) NULL after clasificacion;

ALTER TABLE egresos ADD tipo_gasto varchar(255) NULL after fecha_pago;
ALTER TABLE egresos ADD sector varchar(255) NULL after tipo_gasto;
ALTER TABLE egresos ADD clasificacion varchar(255) NULL after sector;
ALTER TABLE egresos ADD tipo_operacion varchar(255) NULL after clasificacion;

ALTER TABLE ventas ADD tipo_renta varchar(255) NULL after fecha_pago;
ALTER TABLE ventas ADD tipo_operacion varchar(255) NULL after tipo_renta;


