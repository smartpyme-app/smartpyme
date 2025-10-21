ALTER TABLE producto_precios
ADD COLUMN clasificacion VARCHAR(100) NULL AFTER precio;

ALTER TABLE clientes
ADD COLUMN clasificacion VARCHAR(100) NULL AFTER nombre;



ALTER TABLE devoluciones_venta
ADD COLUMN tipo VARCHAR(100) NULL AFTER tipo_dte;

ALTER TABLE devoluciones_compra
ADD COLUMN tipo VARCHAR(100) NULL AFTER tipo_documento;