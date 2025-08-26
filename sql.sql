ALTER TABLE empresas ADD tipo_renta_servicios VARCHAR(255) NULL AFTER logo;
ALTER TABLE empresas ADD tipo_renta_productos VARCHAR(255) NULL AFTER tipo_renta_servicios;
ALTER TABLE empresas ADD tipo_sector VARCHAR(255) NULL AFTER tipo_renta_productos;


-- Tabla principal de entradas de inventario
CREATE TABLE `inventario_entradas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha de la entrada',
  `bodega_id` bigint(20) unsigned NOT NULL COMMENT 'ID de la bodega',
  `concepto` varchar(255) NOT NULL COMMENT 'Concepto o motivo de la entrada',
  `tipo` varchar(255) NOT NULL DEFAULT 'Otro' COMMENT 'Tipo de entrada',
  `estado` varchar(255) NOT NULL DEFAULT 'Pendiente' COMMENT 'Estado de la entrada',
  `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'ID del usuario que creó la entrada',
  `corte_id` bigint(20) unsigned NULL COMMENT 'ID del corte de caja (opcional)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal de entradas de inventario';

-- Tabla de detalles de entradas de inventario
CREATE TABLE `inventario_entrada_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entrada_id` bigint(20) unsigned NOT NULL COMMENT 'ID de la entrada padre',
  `producto_id` bigint(20) unsigned NOT NULL COMMENT 'ID del producto',
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Cantidad ingresada',
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo unitario del producto',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total del detalle (cantidad * costo)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles de productos en entradas de inventario';

-- Tabla principal de salidas de inventario
CREATE TABLE `inventario_salidas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha de la salida',
  `bodega_id` bigint(20) unsigned NOT NULL COMMENT 'ID de la bodega',
  `concepto` varchar(255) NOT NULL COMMENT 'Concepto o motivo de la salida',
  `tipo` varchar(255) NOT NULL DEFAULT 'Otro' COMMENT 'Tipo de salida',
  `estado` varchar(255) NOT NULL DEFAULT 'Pendiente' COMMENT 'Estado de la salida',
  `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'ID del usuario que creó la salida',
  `corte_id` bigint(20) unsigned NULL COMMENT 'ID del corte de caja (opcional)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal de salidas de inventario';

-- Tabla de detalles de salidas de inventario
CREATE TABLE `inventario_salida_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salida_id` bigint(20) unsigned NOT NULL COMMENT 'ID de la salida padre',
  `producto_id` bigint(20) unsigned NOT NULL COMMENT 'ID del producto',
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Cantidad salida',
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo unitario del producto',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total del detalle (cantidad * costo)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles de productos en salidas de inventario';

