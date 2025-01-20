
ALTER TABLE cotizacion_ventas 
ADD COLUMN terminos_de_venta TEXT 
AFTER observaciones;

DROP TABLE IF EXISTS historial_orden_produccion;
DROP TABLE IF EXISTS archivos_orden_produccion;
DROP TABLE IF EXISTS detalles_orden_produccion;
DROP TABLE IF EXISTS ordenes_produccion;
CREATE TABLE ordenes_produccion (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    fecha DATE NOT NULL,
    fecha_entrega DATE,
    estado ENUM('pendiente', 'aceptada', 'en_proceso', 'completada','entregada','anulada') DEFAULT 'pendiente',
    id_cotizacion_venta BIGINT ,
    id_cliente BIGINT NOT NULL,
    id_usuario BIGINT NOT NULL,
    id_asesor BIGINT NOT NULL,
    id_bodega  BIGINT NOT NULL,
    id_empresa BIGINT NOT NULL,
    id_vendedor BIGINT NOT NULL,
    observaciones TEXT,
    terminos_condiciones TEXT,
    subtotal DECIMAL(10,2) DEFAULT 0,
    total_costo DECIMAL(10,2) DEFAULT 0,
    descuento DECIMAL(10,2) DEFAULT 0,
    no_sujeta DECIMAL(10,2) DEFAULT 0,
    excenta DECIMAL(10,2) DEFAULT 0,
    cuenta_a_terceros DECIMAL(10,2) DEFAULT 0,
    gravada DECIMAL(10,2) DEFAULT 0,
    iva DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX idx_fecha_cliente (fecha)
);

CREATE TABLE detalles_orden_produccion (
    id  bigint unsigned NOT NULL AUTO_INCREMENT,
    id_orden_produccion bigint unsigned NOT NULL,
    cantidad INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    total_costo DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0,
    id_producto BIGINT NOT NULL,
    descripcion TEXT,
    id_cotizacion_venta BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `detalles_orden_produccion_id_orden_produccion_foreign` (`id_orden_produccion`),
    CONSTRAINT `detalles_orden_produccion_id_orden_produccion_foreign` FOREIGN KEY (`id_orden_produccion`) REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE
);


CREATE TABLE historial_orden_produccion (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    id_orden_produccion bigint unsigned NOT NULL,
    estado_anterior VARCHAR(50),
    estado_nuevo VARCHAR(50) NOT NULL,
    id_usuario BIGINT NOT NULL,
    comentarios TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY `historial_orden_produccion_id_orden_produccion_foreign` (`id_orden_produccion`),
    CONSTRAINT `historial_orden_produccion_id_orden_produccion_foreign` FOREIGN KEY (`id_orden_produccion`) REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE
);


ALTER TABLE product_custom_fields
ADD COLUMN orden_produccion_detalle_id BIGINT AFTER cotizacion_venta_detalle_id;

ALTER TABLE notificaciones 
ADD COLUMN id_orden_produccion INT NULL;