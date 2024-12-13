
ALTER TABLE cotizacion_ventas
ADD forma_pago varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
ADD descripcion_personalizada tinyint(1) DEFAULT 0,
ADD descripcion_impresion varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
ADD id_bodega int NOT NULL,
ADD sub_total double(10,2) NOT NULL DEFAULT 0.00,
ADD gravada decimal(9,4) DEFAULT 0.0000,
ADD iva double(10,2) NOT NULL DEFAULT 0.00,
ADD descuento double(10,2) NOT NULL DEFAULT 0.00,
ADD no_sujeta decimal(9,2) NOT NULL DEFAULT 0.00,
ADD exenta decimal(9,2) NOT NULL DEFAULT 0.00,
ADD cuenta_a_terceros decimal(9,2) NOT NULL DEFAULT 0.00,
ADD iva_retenido decimal(10,2) NOT NULL DEFAULT 0.00,
ADD iva_percibido decimal(10,2) NOT NULL DEFAULT 0.00;


ALTER TABLE detalles_cotizacion_ventas
ADD id_vendedor int DEFAULT NULL,
ADD costo decimal(10,2) NOT NULL DEFAULT 0.00,





