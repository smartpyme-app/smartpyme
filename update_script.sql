SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS detalles_cotizacion_ventas;
DROP TABLE IF EXISTS cotizacion_ventas;

SET FOREIGN_KEY_CHECKS = 1;


CREATE TABLE `cotizacion_ventas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `forma_pago` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `descripcion_personalizada` tinyint(1) DEFAULT '0',
  `descripcion_impresion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `detalle_banco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date NOT NULL,
  `total_costo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sub_total` double(10,2) NOT NULL DEFAULT '0.00',
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT '0.00',
  `exenta` decimal(9,2) NOT NULL DEFAULT '0.00',
  `gravada` decimal(9,4) DEFAULT '0.0000',
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT '0.00',
  `iva` double(10,2) NOT NULL DEFAULT '0.00',
  `iva_retenido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `iva_percibido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `descuento` double(10,2) NOT NULL DEFAULT '0.00',
  `correlativo` int DEFAULT NULL,
  `id_documento` int DEFAULT NULL,
  `id_cliente` int DEFAULT NULL,
  `id_proyecto` int DEFAULT NULL,
  `id_bodega` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_vendedor` int DEFAULT NULL,
  `id_empresa` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cobrar_impuestos` tinyint(1) DEFAULT '0',
  `aplicar_retencion` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_cotizacion_cliente` (`id_cliente`),
  KEY `idx_cotizacion_usuario` (`id_usuario`),
  KEY `idx_cotizacion_empresa` (`id_empresa`),
  KEY `idx_cotizacion_sucursal` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `detalles_cotizacion_ventas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(10,2) NOT NULL,
  `costo` decimal(10,2) NOT NULL,
  `precio` double NOT NULL,
  `total` double NOT NULL,
  `total_costo` decimal(10,2) NOT NULL,
  `descuento` double NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL,
  `exenta` decimal(9,2) NOT NULL,
  `cuenta_a_terceros` decimal(9,2) NOT NULL,
  `subtotal` decimal(9,2) NOT NULL,
  `gravada` decimal(9,4) NOT NULL,
  `iva` decimal(9,4) NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_producto` int NOT NULL,
  `id_cotizacion_venta` int NOT NULL,
  `id_vendedor` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalle_cotizacion` (`id_cotizacion_venta`),
  KEY `idx_detalle_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;