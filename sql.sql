-- Costo promedio
ALTER TABLE productos ADD costo_promedio decimal(10,2) NOT NULL default 0 after costo;
ALTER TABLE empresas ADD valor_inventario varchar(255) NOT NULL default 'ultimo' after vender_sin_stock;

ALTER TABLE ventas ADD condicion varchar(255) NULL default 'Contado' after num_cotizacion;


