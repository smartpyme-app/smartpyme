ALTER TABLE producto_precios
ADD COLUMN clasificacion VARCHAR(100) NULL AFTER precio;

ALTER TABLE clientes
ADD COLUMN clasificacion VARCHAR(100) NULL AFTER nombre;