-- Costo promedio
ALTER TABLE ajustes ADD costo decimal(10,2) NULL default 0 after ajuste;
ALTER TABLE traslados ADD costo decimal(10,2) NULL default 0 after cantidad;
ALTER TABLE traslado_detalles ADD costo decimal(10,2) NOT NULL after cantidad;


ALTER TABLE contabilidad_configuracion ADD id_cuenta_perdida_ajuste INT NULL after id_cuenta_renta_retenida_compras;
ALTER TABLE contabilidad_configuracion ADD id_cuenta_ganancia_ajuste INT NULL after id_cuenta_perdida_ajuste;


