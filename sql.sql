ALTER TABLE productos
ADD tipo varchar(255) DEFAULT 'Producto' after etiquetas;

ALTER TABLE productos
CHANGE enable enable Boolean NOT NULL DEFAULT true;

ALTER TABLE users
ADD avatar varchar(255) DEFAULT 'usuarios/default.jpg' after tipo;

ALTER TABLE categorias
CHANGE enable enable Boolean NOT NULL DEFAULT false;

ALTER TABLE categorias
CHANGE descripcion descripcion VARCHAR(255) NULL;

ALTER TABLE ajustes
CHANGE estado estado VARCHAR(100) NOT NULL DEFAULT 'Confirmado';


ALTER TABLE kardexs
CHANGE valor_unitario costo_unitario DECIMAL(10,2) NULL;

ALTER TABLE kardexs
ADD precio_unitario DECIMAL(10,2) NULL;

ALTER TABLE productos_imagenes 
CHANGE ruta_imagen img VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE productos_imagenes 
CHANGE producto_id id_producto INT(11) NULL DEFAULT NULL;
