-- Costo promedio
ALTER TABLE ajustes ADD costo decimal(10,2) NULL default 0 after ajuste;
ALTER TABLE traslados ADD costo decimal(10,2) NULL default 0 after cantidad;
ALTER TABLE traslado_detalles ADD costo decimal(10,2) NOT NULL after cantidad;


