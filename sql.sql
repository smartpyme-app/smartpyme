ALTER TABLE transacciones CHANGE monto total DOUBLE(10,2) NOT NULL;
ALTER TABLE transacciones ADD fecha DATE NULL AFTER id, ADD correlativo INT NULL AFTER fecha, ADD estado VARCHAR(255) NULL AFTER correlativo, ADD metodo_pago VARCHAR(255) NULL AFTER estado, ADD tipo_documento VARCHAR(255) NULL AFTER metodo_pago;
ALTER TABLE transacciones ADD referencia VARCHAR(255) NULL AFTER tipo_documento, ADD nota VARCHAR(255) NULL AFTER referencia;
ALTER TABLE transacciones ADD id_empresa INT NULL AFTER total, ADD id_usuario INT NULL AFTER id_empresa;

ALTER TABLE users CHANGE enable enable TINYINT(1) NOT NULL DEFAULT true;
ALTER TABLE users CHANGE celular telefono VARCHAR(255) DEFAULT NULL;
ALTER TABLE users CHANGE last_login ultimo_login DATETIME NULL DEFAULT NULL;
ALTER TABLE users ADD avatar varchar(255) DEFAULT 'usuarios/default.jpg' after tipo;

ALTER TABLE empresas CHANGE email correo VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE empresas CHANGE activo activo TINYINT(1) NOT NULL DEFAULT true;
ALTER TABLE empresas CHANGE descripcion descripcion VARCHAR(255) NULL;
ALTER TABLE empresas CHANGE logo logo varchar(255) NOT NULL DEFAULT 'empresas/default.jpg';
ALTER TABLE empresas ADD municipio VARCHAR(255) NULL after giro;
ALTER TABLE empresas ADD departamento VARCHAR(255) NULL after municipio;
ALTER TABLE empresas ADD pais VARCHAR(255) NULL after departamento;
ALTER TABLE empresas ADD tipo_plan VARCHAR(255) NULL AFTER plan;
ALTER TABLE empresas ADD total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tipo_plan;
ALTER TABLE empresas ADD industria VARCHAR(255) NULL AFTER giro;
ALTER TABLE empresas ADD fecha_cancelacion DATE NULL AFTER tipo_plan;
ALTER TABLE empresas ADD referido VARCHAR(255) NULL AFTER fecha_cancelacion;
ALTER TABLE empresas ADD campania VARCHAR(255) NULL AFTER referido;
ALTER TABLE empresas ADD tipo_contribuyente VARCHAR(255) NULL AFTER campania;

ALTER TABLE clientes CHANGE email correo VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE clientes CHANGE tipo tipo_contribuyente VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE clientes CHANGE comentarios nota VARCHAR(1500) NULL DEFAULT NULL;
ALTER TABLE clientes CHANGE celular telefono VARCHAR(255) NULL;
ALTER TABLE clientes CHANGE enable enable TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE clientes ADD tipo varchar(250) DEFAULT 'Persona' after tipo_contribuyente;
ALTER TABLE clientes ADD id_usuario INT NOT NULL after nota;
ALTER TABLE clientes ADD etiquetas varchar(500) NULL after id_usuario;
ALTER TABLE clientes ADD fecha_cumpleanos date NULL after etiquetas;
ALTER TABLE clientes ADD empresa_telefono varchar(250) NULL after fecha_cumpleanos;
ALTER TABLE clientes ADD empresa_direccion varchar(250) NULL after empresa_telefono;
ALTER TABLE clientes CHANGE nombre nombre VARCHAR(255) NULL;
ALTER TABLE clientes CHANGE apellido apellido VARCHAR(255) NULL;

ALTER TABLE proveedores CHANGE tipo tipo_contribuyente VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE proveedores CHANGE comentarios nota VARCHAR(1500) NULL DEFAULT NULL;
ALTER TABLE proveedores CHANGE enable enable TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE proveedores CHANGE apellido apellido VARCHAR(255) NULL;
ALTER TABLE proveedores ADD nombre_empresa VARCHAR(255) NULL after apellido;
ALTER TABLE proveedores ADD id_usuario INT NOT NULL after nota;
ALTER TABLE proveedores ADD tipo varchar(250) DEFAULT 'Persona' after tipo_contribuyente;
ALTER TABLE proveedores ADD etiquetas varchar(500) NULL after tipo;
ALTER TABLE proveedores CHANGE nombre nombre VARCHAR(255) NULL;

ALTER TABLE productos CHANGE enable enable Boolean NOT NULL DEFAULT true;
ALTER TABLE productos CHANGE precio precio DECIMAL(11,4) NOT NULL;
ALTER TABLE productos ADD tipo varchar(255) DEFAULT 'Producto' after etiquetas;
ALTER TABLE producto_precios CHANGE precio precio DECIMAL(11,4) NOT NULL;

ALTER TABLE productos_imagenes CHANGE ruta_imagen img VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE productos_imagenes CHANGE producto_id id_producto INT(11) NULL DEFAULT NULL;

ALTER TABLE categorias CHANGE enable enable Boolean NOT NULL DEFAULT false;
ALTER TABLE categorias CHANGE descripcion descripcion VARCHAR(255) NULL;


ALTER TABLE traslados ADD id_usuario INT NULL after id_sucursal;
ALTER TABLE traslados CHANGE cantidad cantidad DECIMAL(10,2) NOT NULL;

ALTER TABLE kardexs CHANGE valor_unitario costo_unitario DECIMAL(10,2) NULL;
ALTER TABLE kardexs ADD precio_unitario DECIMAL(10,2) NULL;




ALTER TABLE compras CHANGE total_compra total DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE compras CHANGE num_referencia referencia VARCHAR(255) NULL;
ALTER TABLE compras CHANGE documento tipo_documento VARCHAR(255) NULL;
ALTER TABLE compras CHANGE vencimiento fecha_pago date NULL;
ALTER TABLE compras CHANGE id_user id_usuario INT(11) NOT NULL;
ALTER TABLE compras CHANGE percepcion percepcion DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE compras CHANGE id_proveedor id_proveedor INT(11) NULL;
ALTER TABLE compras CHANGE descuento descuento DOUBLE(10,2) NOT NULL DEFAULT 0;
ALTER TABLE compras ADD recurrente Boolean DEFAULT 0 AFTER observaciones;
ALTER TABLE compras ADD fecha_expiracion date NULL AFTER recurrente;

ALTER TABLE ventas CHANGE total_venta total DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE ventas CHANGE vencimiento fecha_pago date NULL;
ALTER TABLE ventas CHANGE id_user id_usuario INT(11) NOT NULL;
ALTER TABLE ventas CHANGE id_cliente id_cliente INT(50) NULL;
ALTER TABLE ventas CHANGE total_costo total_costo DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE ventas ADD recurrente Boolean DEFAULT 0 AFTER observaciones;
ALTER TABLE ventas ADD fecha_expiracion date NULL AFTER recurrente;

ALTER TABLE detalles_venta CHANGE cantidad cantidad DECIMAL(10,2) NOT NULL;
ALTER TABLE detalles_venta ADD total_costo Boolean DEFAULT 0 AFTER total;


ALTER TABLE devoluciones_venta CHANGE enable enable TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE devoluciones_venta CHANGE id_cliente id_cliente INT(11) NULL;
ALTER TABLE devoluciones_venta ADD id_usuario INT(11) NOT NULL after id_cliente;
ALTER TABLE devoluciones_venta ADD iva DECIMAL(10,2) NOT NULL DEFAULT 0 after sub_total;
ALTER TABLE devoluciones_venta ADD descuento DECIMAL(10,2) NOT NULL DEFAULT 0 after iva;
ALTER TABLE detalles_devolucion_venta ADD costo DECIMAL(10,2) NOT NULL DEFAULT 0 after precio;
ALTER TABLE detalles_devolucion_venta ADD descuento DECIMAL(10,2) NOT NULL DEFAULT 0 after costo;
ALTER TABLE detalles_devolucion_venta CHANGE sub_total total DOUBLE(10,2) NOT NULL;

ALTER TABLE devoluciones_compra CHANGE enable enable TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE devoluciones_compra CHANGE id_proveedor id_proveedor INT(11) NULL;
ALTER TABLE devoluciones_compra ADD id_usuario INT(11) NOT NULL after id_proveedor;
ALTER TABLE devoluciones_compra ADD iva DECIMAL(10,2) NOT NULL DEFAULT 0 after sub_total;
ALTER TABLE devoluciones_compra ADD descuento DECIMAL(10,2) NOT NULL DEFAULT 0 after iva;
ALTER TABLE detalles_devolucion_compra ADD descuento DECIMAL(10,2) NOT NULL DEFAULT 0 after costo;
ALTER TABLE detalles_devolucion_compra CHANGE sub_total total DOUBLE(10,2) NOT NULL;

CREATE TABLE producto_composiciones (
    id int NOT NULL AUTO_INCREMENT,
    id_producto int NOT NULL,
    id_compuesto int NOT NULL,
    cantidad DECIMAL(9,2) NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

CREATE TABLE gastos_categorias (
    id int NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    id_empresa int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);

ALTER TABLE egresos ADD id_categoria INT(11) NULL after tipo;
ALTER TABLE egresos ADD id_usuario INT(11) NOT NULL after id_empresa;
ALTER TABLE egresos CHANGE vencimiento fecha_pago date NULL;
ALTER TABLE egresos ADD sub_total DECIMAL(10,2) NOT NULL DEFAULT 0 after iva;
ALTER TABLE egresos ADD tipo_documento VARCHAR(255) after referencia;
ALTER TABLE egresos CHANGE monto total DECIMAL(10,2) NOT NULL;
ALTER TABLE egresos CHANGE factura referencia VARCHAR(255) NULL;


RENAME TABLE recordatorios TO notificaciones;

ALTER TABLE notificaciones CHANGE intro titulo VARCHAR(5000) NULL DEFAULT NULL;
ALTER TABLE notificaciones CHANGE texto descripcion VARCHAR(5000) NULL DEFAULT NULL;



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


ALTER TABLE inventario CHANGE stock stock DECIMAL(10,2) NOT NULL;

ALTER TABLE ajustes CHANGE stock_actual stock_actual DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE ajustes CHANGE stock_real stock_real DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE ajustes CHANGE ajuste ajuste DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE ajustes CHANGE estado estado VARCHAR(255) NOT NULL DEFAULT 'Confirmado';
ALTER TABLE ajustes CHANGE created_at created_at DATETIME NULL DEFAULT NULL;
ALTER TABLE ajustes CHANGE updated_at updated_at TIMESTAMP NULL DEFAULT NULL;


ALTER TABLE detalles_compra CHANGE cantidad cantidad DECIMAL(10,2) NOT NULL;
ALTER TABLE detalles_compra CHANGE sub_total total DECIMAL(10,2) NOT NULL;


ALTER TABLE presupuestos CHANGE gastos egresos DECIMAL(10,2) NOT NULL;
ALTER TABLE presupuestos CHANGE enable enable TINYINT(1) NOT NULL DEFAULT true;
ALTER TABLE presupuestos ADD combustible DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER servicios;
ALTER TABLE presupuestos ADD prestamos DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER combustible;
ALTER TABLE presupuestos ADD materia_prima DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER prestamos;
ALTER TABLE presupuestos ADD compras DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER ingresos;
ALTER TABLE presupuestos ADD costo_de_venta DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER materia_prima;
ALTER TABLE presupuestos ADD insumos DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER costo_de_venta;
ALTER TABLE presupuestos ADD impuestos DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER insumos;
ALTER TABLE presupuestos ADD gastos_administrativos DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER impuestos;

ALTER TABLE eventos CHANGE fecha_start inicio DATETIME NOT NULL;
ALTER TABLE eventos CHANGE fecha_end fin DATETIME NULL DEFAULT NULL;


RENAME TABLE recibos TO abonos_ventas;

ALTER TABLE abonos_ventas CHANGE monto total DOUBLE(10,2) NOT NULL;
ALTER TABLE abonos_ventas ADD id_sucursal INT NULL AFTER id_empresa;
ALTER TABLE abonos_ventas ADD id_usuario INT NULL AFTER id_sucursal;
ALTER TABLE abonos_ventas ADD detalle_banco varchar(255) NULL AFTER id_usuario;

CREATE TABLE abonos_compras (
    id int NOT NULL AUTO_INCREMENT,
    concepto VARCHAR(500) NOT NULL,
    fecha date NOT NULL,
    nombre_de VARCHAR(500) NOT NULL,
    total double(10,2) NOT NULL,
    forma_pago  varchar(150) NOT NULL,
    estado  varchar(150) NOT NULL,
    detalle_banco  varchar(150) NULL,
    id_empresa int NOT NULL,
    id_sucursal int NOT NULL,
    id_usuario int NOT NULL,
    id_compra int NOT NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id)
);
