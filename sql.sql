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

ALTER TABLE ajustes
CHANGE created_at created_at DATETIME NULL DEFAULT NULL;

ALTER TABLE traslados
ADD id_usuario INT NULL after id_sucursal;

ALTER TABLE kardexs
CHANGE valor_unitario costo_unitario DECIMAL(10,2) NULL;

ALTER TABLE clientes
CHANGE celular telefono VARCHAR(100) NULL;

ALTER TABLE kardexs
ADD precio_unitario DECIMAL(10,2) NULL;

ALTER TABLE productos_imagenes 
CHANGE ruta_imagen img VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE productos_imagenes 
CHANGE producto_id id_producto INT(11) NULL DEFAULT NULL;


ALTER TABLE compras
CHANGE total_compra total DECIMAL(10,2) NOT NULL DEFAULT 0;

ALTER TABLE compras
CHANGE num_referencia referencia VARCHAR(255) NULL;

ALTER TABLE compras
CHANGE documento tipo_documento VARCHAR(255) NULL;

ALTER TABLE compras
CHANGE vencimiento fecha_pago date NULL;

ALTER TABLE compras
CHANGE id_user id_usuario INT(11) NOT NULL;


ALTER TABLE ventas
CHANGE total_venta total DECIMAL(10,2) NOT NULL DEFAULT 0;

ALTER TABLE ventas
CHANGE vencimiento fecha_pago date NULL;

ALTER TABLE ventas
CHANGE id_user id_usuario INT(11) NOT NULL;


CREATE TABLE gastos_categorias (
    id int NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE egresos
ADD id_categoria INT(11) NOT NULL after tipo;

ALTER TABLE egresos 
CHANGE monto total DECIMAL(10,2) NOT NULL;

ALTER TABLE users 
CHANGE last_login ultimo_login DATETIME NULL DEFAULT NULL;

RENAME TABLE recordatorios TO notificaciones;

ALTER TABLE egresos 
CHANGE factura referencia VARCHAR(255) NULL;


INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Alquiler', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Gastos varios', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Insumos', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Impuestos', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Mantenimiento', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Marketing', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Materia Prima', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Pago comisión', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Planilla', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Préstamos', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Publicidad', '13');
INSERT INTO gastos_categorias (nombre, id_empresa) VALUES ('Servicios', '13');
