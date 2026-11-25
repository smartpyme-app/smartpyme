/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `abonos_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `abonos_compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(500) NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `correlativo` varchar(255) DEFAULT NULL,
  `id_documento` int(10) unsigned DEFAULT NULL,
  `nombre_de` varchar(500) NOT NULL,
  `total` double(10,2) NOT NULL,
  `forma_pago` varchar(150) NOT NULL,
  `estado` varchar(150) NOT NULL,
  `detalle_banco` varchar(150) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_abc_empresa_fecha` (`id_empresa`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `abonos_gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `abonos_gastos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `correlativo` varchar(191) DEFAULT NULL,
  `id_documento` int(10) unsigned DEFAULT NULL,
  `concepto` varchar(191) NOT NULL,
  `referencia` varchar(191) DEFAULT NULL,
  `estado` varchar(191) NOT NULL,
  `nombre_de` varchar(191) NOT NULL,
  `forma_pago` varchar(191) NOT NULL,
  `detalle_banco` varchar(191) DEFAULT NULL,
  `mora` decimal(9,2) NOT NULL DEFAULT 0.00,
  `comision` decimal(9,2) NOT NULL DEFAULT 0.00,
  `total` decimal(9,2) NOT NULL,
  `nota` text DEFAULT NULL,
  `id_caja` int(11) DEFAULT NULL,
  `id_corte` int(11) DEFAULT NULL,
  `id_gasto` int(11) NOT NULL,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `abonos_gastos_id_gasto_foreign` (`id_gasto`),
  KEY `abonos_gastos_id_usuario_foreign` (`id_usuario`),
  KEY `abonos_gastos_id_sucursal_foreign` (`id_sucursal`),
  KEY `abonos_gastos_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `abonos_gastos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `abonos_gastos_id_gasto_foreign` FOREIGN KEY (`id_gasto`) REFERENCES `egresos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `abonos_gastos_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `abonos_gastos_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `abonos_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `abonos_ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(500) NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `correlativo` varchar(255) DEFAULT NULL,
  `id_documento` int(10) unsigned DEFAULT NULL,
  `nombre_de` varchar(500) DEFAULT NULL,
  `total` double(10,2) NOT NULL,
  `forma_pago` varchar(150) DEFAULT NULL,
  `estado` varchar(150) DEFAULT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `detalle_banco` varchar(255) DEFAULT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_abonos_fecha_estado` (`fecha`,`estado`),
  KEY `idx_abonos_fecha` (`fecha`),
  KEY `idx_abonos_id_venta_estado` (`id_venta`,`estado`),
  KEY `idx_abv_empresa_fecha` (`id_empresa`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_accesos_id_usuario` (`id_usuario`),
  KEY `idx_accesos_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `actividades_economicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `actividades_economicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aguinaldo_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aguinaldo_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_aguinaldo` bigint(20) unsigned NOT NULL,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `monto_aguinaldo_bruto` decimal(10,2) NOT NULL,
  `monto_exento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_gravado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `retencion_renta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aguinaldo_neto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aguinaldo_detalles_id_aguinaldo_index` (`id_aguinaldo`),
  KEY `aguinaldo_detalles_id_empleado_index` (`id_empleado`),
  CONSTRAINT `aguinaldo_detalles_id_aguinaldo_foreign` FOREIGN KEY (`id_aguinaldo`) REFERENCES `aguinaldos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `aguinaldo_detalles_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aguinaldos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aguinaldos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `anio` year(4) NOT NULL,
  `fecha_calculo` date DEFAULT NULL,
  `total_aguinaldos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_retenciones` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` int(11) NOT NULL DEFAULT 1,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `aguinaldos_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `aguinaldos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ajustes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ajustes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(250) NOT NULL,
  `stock_actual` decimal(10,2) DEFAULT NULL,
  `stock_real` decimal(10,2) DEFAULT NULL,
  `ajuste` decimal(10,2) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  `estado` varchar(255) NOT NULL DEFAULT 'Confirmado',
  `id_producto` int(11) NOT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_bodega` int(11) DEFAULT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ajustes_id_presentacion_foreign` (`id_presentacion`),
  KEY `idx_aj_empresa_created` (`id_empresa`,`created_at`),
  KEY `idx_aj_producto_bodega` (`id_producto`,`id_bodega`),
  CONSTRAINT `ajustes_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aliados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `aliados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aliados_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `analisis_ventas_mensual_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `analisis_ventas_mensual_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) NOT NULL,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_usuario` bigint(20) unsigned DEFAULT NULL,
  `params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`params`)),
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `file_path` varchar(191) DEFAULT NULL,
  `file_name` varchar(191) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `analisis_ventas_mensual_queue_status_created_at_index` (`status`,`created_at`),
  KEY `analisis_ventas_mensual_queue_id_empresa_index` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `areas_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `areas_empresa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `id_departamento` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `areas_empresa_id_departamento_index` (`id_departamento`),
  CONSTRAINT `areas_empresa_id_departamento_foreign` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos_empresa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(191) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `id_empresa` bigint(20) unsigned DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `event` varchar(191) NOT NULL,
  `auditable_type` varchar(191) NOT NULL,
  `auditable_id` bigint(20) unsigned NOT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `url` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(1023) DEFAULT NULL,
  `tags` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_user_type_index` (`user_id`,`user_type`),
  KEY `audits_empresa_module_created_idx` (`id_empresa`,`module`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `authorization_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `authorization_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `display_name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditions`)),
  `expiration_hours` int(11) NOT NULL DEFAULT 24,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `authorizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `authorizations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) NOT NULL,
  `authorization_type_id` bigint(20) unsigned NOT NULL,
  `authorizeable_type` varchar(191) NOT NULL,
  `authorizeable_id` bigint(20) unsigned NOT NULL,
  `id_empresa` int(10) unsigned DEFAULT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `authorized_by` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
  `description` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `notes` text DEFAULT NULL,
  `operation_type` varchar(191) DEFAULT NULL,
  `operation_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`operation_data`)),
  `operation_hash` varchar(191) DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `authorized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorizations_code_unique` (`code`),
  KEY `authorizations_authorization_type_id_foreign` (`authorization_type_id`),
  KEY `authorizations_authorizeable_type_authorizeable_id_index` (`authorizeable_type`,`authorizeable_id`),
  KEY `authorizations_id_empresa_foreign` (`id_empresa`),
  KEY `authorizations_requested_by_foreign` (`requested_by`),
  KEY `authorizations_authorized_by_foreign` (`authorized_by`),
  KEY `authorizations_operation_hash_index` (`operation_hash`),
  CONSTRAINT `authorizations_authorization_type_id_foreign` FOREIGN KEY (`authorization_type_id`) REFERENCES `authorization_types` (`id`),
  CONSTRAINT `authorizations_authorized_by_foreign` FOREIGN KEY (`authorized_by`) REFERENCES `users` (`id`),
  CONSTRAINT `authorizations_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `authorizations_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bancos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bancos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `orden` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bono_evaluaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bono_evaluaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `origen` varchar(16) NOT NULL,
  `id_usuario` bigint(20) unsigned DEFAULT NULL,
  `resumen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`resumen`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bono_generados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bono_generados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_vendedor` bigint(20) unsigned NOT NULL,
  `id_regla` bigint(20) unsigned NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `monto_ventas_base` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `monto` decimal(14,4) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `origen` varchar(32) NOT NULL DEFAULT 'evaluacion',
  `aprobado_por` bigint(20) unsigned DEFAULT NULL,
  `aprobado_at` timestamp NULL DEFAULT NULL,
  `pagado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bono_generados_unique_eval` (`id_empresa`,`id_vendedor`,`id_regla`,`periodo_inicio`,`periodo_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bono_reglas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bono_reglas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `nombre` varchar(191) NOT NULL,
  `tipo` varchar(32) NOT NULL,
  `ventana` varchar(32) NOT NULL DEFAULT 'mensual',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `alcance` varchar(32) NOT NULL DEFAULT 'global',
  `reemplaza_global` tinyint(1) NOT NULL DEFAULT 0,
  `id_vendedores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`id_vendedores`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `boxful_parcels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `boxful_parcels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `boxful_shipment_id` bigint(20) unsigned NOT NULL,
  `contenido` text NOT NULL,
  `alto` decimal(8,2) NOT NULL,
  `ancho` decimal(8,2) NOT NULL,
  `largo` decimal(8,2) NOT NULL,
  `peso` decimal(8,2) NOT NULL,
  `valor_declarado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `es_fragil` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `boxful_parcels_boxful_shipment_id_foreign` (`boxful_shipment_id`),
  CONSTRAINT `boxful_parcels_boxful_shipment_id_foreign` FOREIGN KEY (`boxful_shipment_id`) REFERENCES `boxful_shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `boxful_shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `boxful_shipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `paquete_id` int(11) NOT NULL,
  `direccion_origen_id` bigint(20) unsigned DEFAULT NULL,
  `fecha_recoleccion` datetime DEFAULT NULL,
  `cod` tinyint(1) NOT NULL DEFAULT 0,
  `cod_monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `boxful_shipment_id` varchar(191) DEFAULT NULL,
  `shipment_number` varchar(191) DEFAULT NULL,
  `boxful_courier_id` varchar(191) DEFAULT NULL,
  `boxful_courier_name` varchar(191) DEFAULT NULL,
  `boxful_label_url` text DEFAULT NULL,
  `boxful_tracking_url` text DEFAULT NULL,
  `boxful_status` tinyint(4) DEFAULT NULL,
  `boxful_status_description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `boxful_shipments_boxful_shipment_id_unique` (`boxful_shipment_id`),
  KEY `boxful_shipments_paquete_id_foreign` (`paquete_id`),
  KEY `boxful_shipments_direccion_origen_id_foreign` (`direccion_origen_id`),
  CONSTRAINT `boxful_shipments_direccion_origen_id_foreign` FOREIGN KEY (`direccion_origen_id`) REFERENCES `direcciones_origen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `boxful_shipments_paquete_id_foreign` FOREIGN KEY (`paquete_id`) REFERENCES `paquetes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caja_cortes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `caja_cortes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `saldo_inicial` decimal(9,2) NOT NULL DEFAULT 0.00,
  `saldo_final` decimal(9,2) NOT NULL DEFAULT 0.00,
  `apertura` datetime NOT NULL,
  `cierre` datetime DEFAULT NULL,
  `fecha` date NOT NULL,
  `caja_id` int(10) unsigned NOT NULL,
  `supervisor_id` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cajas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `tipo` varchar(191) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `sucursal_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campanias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campanias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campanias_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `canales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `canales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cobra_propina` varchar(50) DEFAULT NULL,
  `envios` varchar(100) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL,
  `predeterminado` tinyint(1) NOT NULL DEFAULT 0,
  `id_empresa` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cardex`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cardex` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(150) NOT NULL,
  `fecha` date NOT NULL,
  `stock` int(11) DEFAULT NULL,
  `costo` double(10,2) DEFAULT NULL,
  `precio` double(10,2) DEFAULT NULL,
  `entradas` int(11) DEFAULT NULL,
  `salidas` int(11) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cargos_de_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cargos_de_empresa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(191) DEFAULT NULL,
  `salario_base` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `estado` int(11) NOT NULL DEFAULT 1,
  `id_departamento` bigint(20) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `catalogo_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogo_cuentas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `naturaleza` varchar(255) NOT NULL,
  `id_cuenta_padre` int(11) DEFAULT NULL,
  `rubro` varchar(255) NOT NULL,
  `nivel` int(11) NOT NULL,
  `cargo` decimal(9,2) DEFAULT 0.00,
  `abono` decimal(9,2) DEFAULT 0.00,
  `saldo` decimal(9,2) DEFAULT 0.00,
  `saldo_inicial` decimal(10,2) DEFAULT NULL,
  `acepta_datos` tinyint(1) NOT NULL DEFAULT 0,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_cuenta_padre_backup` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_catalogo_empresa` (`id_empresa`),
  KEY `idx_catalogo_codigo` (`codigo`(20)),
  KEY `idx_catalogo_jerarquia` (`id_cuenta_padre`,`nivel`),
  KEY `idx_catalogo_rubro_empresa` (`rubro`(30),`id_empresa`),
  KEY `idx_catalogo_acepta_datos` (`acepta_datos`,`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categoria_sucursal_cuenta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categoria_sucursal_cuenta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_cuenta_contable_inventario` int(11) NOT NULL,
  `id_cuenta_contable_costo` int(11) NOT NULL,
  `id_cuenta_contable_ingresos` int(11) DEFAULT NULL,
  `id_cuenta_contable_devoluciones` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cat_suc_cat_suc` (`id_categoria`,`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `img` varchar(191) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 0,
  `mostrar_en_restaurante` tinyint(1) NOT NULL DEFAULT 1,
  `id_empresa` int(11) NOT NULL,
  `subcategoria` tinyint(1) DEFAULT 0,
  `id_cate_padre` int(11) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_categorias_nombre_empresa` (`nombre`,`id_empresa`),
  KEY `idx_categorias_lookup` (`id`,`nombre`),
  KEY `idx_categorias_nombre` (`nombre`),
  KEY `idx_cat_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_actividad_reciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_actividad_reciente` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `tipo_actividad` varchar(20) NOT NULL,
  `id_referencia` bigint(20) unsigned DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `puntos` int(11) DEFAULT NULL,
  `icono` varchar(10) NOT NULL DEFAULT '?',
  `estado` varchar(20) NOT NULL DEFAULT 'completado',
  `fecha_actividad` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_actividad_reciente_id_cliente_fecha_actividad_index` (`id_cliente`,`fecha_actividad`),
  KEY `cliente_actividad_reciente_id_cliente_index` (`id_cliente`),
  KEY `cliente_actividad_reciente_tipo_actividad_index` (`tipo_actividad`),
  KEY `idx_actividad_cliente_fecha_desc` (`id_cliente`,`fecha_actividad` DESC),
  KEY `idx_actividad_cliente_fecha` (`id_cliente`,`fecha_actividad`),
  CONSTRAINT `cliente_actividad_reciente_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_categorias_preferidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_categorias_preferidas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_categoria` bigint(20) unsigned DEFAULT NULL,
  `nombre_categoria` varchar(100) NOT NULL,
  `cantidad_productos` int(11) NOT NULL DEFAULT 0,
  `total_compras` int(11) NOT NULL DEFAULT 0,
  `total_gastado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `porcentaje_gasto` decimal(5,2) NOT NULL DEFAULT 0.00,
  `ranking` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_categorias_preferidas_id_cliente_ranking_index` (`id_cliente`,`ranking`),
  KEY `cliente_categorias_preferidas_id_cliente_index` (`id_cliente`),
  KEY `idx_cliente_categorias_cliente_ranking` (`id_cliente`,`ranking`),
  KEY `idx_cliente_categorias_cliente` (`id_cliente`),
  CONSTRAINT `cliente_categorias_preferidas_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_fidelizacion_snapshot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_fidelizacion_snapshot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_empresa` int(10) unsigned DEFAULT NULL,
  `puntos_disponibles` int(11) NOT NULL DEFAULT 0,
  `puntos_totales_ganados` int(11) NOT NULL DEFAULT 0,
  `puntos_totales_canjeados` int(11) NOT NULL DEFAULT 0,
  `valor_puntos_canjeados` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transacciones_ultimos_30_dias` int(11) NOT NULL DEFAULT 0,
  `puntos_ganados_ultimos_30_dias` int(11) NOT NULL DEFAULT 0,
  `puntos_canjeados_ultimos_30_dias` int(11) NOT NULL DEFAULT 0,
  `fecha_ultima_ganancia` date DEFAULT NULL,
  `fecha_ultimo_canje` date DEFAULT NULL,
  `tasa_redencion` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fecha_snapshot` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_fidelizacion_snapshot_id_cliente_index` (`id_cliente`),
  KEY `cliente_fidelizacion_snapshot_puntos_disponibles_index` (`puntos_disponibles`),
  KEY `idx_fidelizacion_cliente` (`id_cliente`),
  KEY `idx_fidelizacion_fecha_snapshot` (`fecha_snapshot`),
  KEY `cliente_fidelizacion_snapshot_id_cliente_id_empresa_index` (`id_cliente`,`id_empresa`),
  CONSTRAINT `cliente_fidelizacion_snapshot_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_metricas_rfm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_metricas_rfm` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `fecha_ultima_compra` date DEFAULT NULL,
  `dias_ultima_compra` int(11) DEFAULT NULL,
  `total_compras` int(11) NOT NULL DEFAULT 0,
  `compras_ultimos_12_meses` int(11) NOT NULL DEFAULT 0,
  `compras_ultimos_6_meses` int(11) NOT NULL DEFAULT 0,
  `compras_ultimos_3_meses` int(11) NOT NULL DEFAULT 0,
  `total_gastado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gasto_ultimos_12_meses` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gasto_ultimos_6_meses` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gasto_ultimos_3_meses` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ticket_promedio` decimal(15,2) NOT NULL DEFAULT 0.00,
  `recency_score` tinyint(4) NOT NULL DEFAULT 0,
  `frequency_score` tinyint(4) NOT NULL DEFAULT 0,
  `monetary_score` tinyint(4) NOT NULL DEFAULT 0,
  `health_score` tinyint(4) NOT NULL DEFAULT 0,
  `segmento_rfm` varchar(20) DEFAULT NULL,
  `clasificacion_abc` varchar(50) DEFAULT NULL,
  `tendencia_consumo` varchar(30) DEFAULT NULL,
  `porcentaje_tendencia` decimal(8,2) DEFAULT NULL,
  `gasto_anio_anterior` decimal(15,2) NOT NULL DEFAULT 0.00,
  `fecha_calculo` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_metricas_rfm_health_score_index` (`health_score`),
  KEY `cliente_metricas_rfm_segmento_rfm_index` (`segmento_rfm`),
  KEY `cliente_metricas_rfm_fecha_ultima_compra_index` (`fecha_ultima_compra`),
  KEY `idx_metricas_rfm_cliente` (`id_cliente`),
  KEY `idx_metricas_rfm_segmento` (`segmento_rfm`),
  KEY `idx_metricas_rfm_fecha_calculo` (`fecha_calculo`),
  KEY `cliente_metricas_rfm_clasificacion_abc_index` (`clasificacion_abc`),
  KEY `cliente_metricas_rfm_tendencia_consumo_index` (`tendencia_consumo`),
  CONSTRAINT `cliente_metricas_rfm_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_notas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `tipo` varchar(191) NOT NULL,
  `titulo` varchar(191) NOT NULL,
  `contenido` text NOT NULL,
  `responsable` varchar(191) NOT NULL,
  `prioridad` varchar(191) NOT NULL DEFAULT 'medium',
  `estado` varchar(191) NOT NULL DEFAULT 'activo',
  `requiere_seguimiento` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_seguimiento` date DEFAULT NULL,
  `resolucion` text DEFAULT NULL,
  `fecha_interaccion` date NOT NULL,
  `hora_interaccion` time NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_notas_id_cliente_tipo_index` (`id_cliente`,`tipo`),
  KEY `cliente_notas_id_cliente_fecha_interaccion_index` (`id_cliente`,`fecha_interaccion`),
  KEY `cliente_notas_responsable_fecha_interaccion_index` (`responsable`,`fecha_interaccion`),
  KEY `cliente_notas_estado_requiere_seguimiento_index` (`estado`,`requiere_seguimiento`),
  KEY `cliente_notas_tipo_estado_index` (`tipo`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_notas_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_notas_categorias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_nota` bigint(20) unsigned NOT NULL,
  `categoria` varchar(191) NOT NULL,
  `subcategoria` varchar(191) DEFAULT NULL,
  `score_relevancia` decimal(3,2) NOT NULL DEFAULT 1.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_notas_categorias_categoria_subcategoria_index` (`categoria`,`subcategoria`),
  KEY `cliente_notas_categorias_id_nota_categoria_index` (`id_nota`,`categoria`),
  CONSTRAINT `cliente_notas_categorias_id_nota_foreign` FOREIGN KEY (`id_nota`) REFERENCES `cliente_notas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_productos_top`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_productos_top` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_producto` int(10) unsigned NOT NULL,
  `total_cantidad` int(11) NOT NULL DEFAULT 0,
  `total_monto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_compras` int(11) NOT NULL DEFAULT 0,
  `ultima_compra` date DEFAULT NULL,
  `dias_ultima_compra` int(11) DEFAULT NULL,
  `precio_promedio` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ranking` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cliente_productos_top_id_cliente_id_producto_unique` (`id_cliente`,`id_producto`),
  KEY `cliente_productos_top_id_cliente_ranking_index` (`id_cliente`,`ranking`),
  KEY `cliente_productos_top_id_cliente_index` (`id_cliente`),
  KEY `cliente_productos_top_id_producto_index` (`id_producto`),
  KEY `idx_productos_top_cliente_ranking` (`id_cliente`,`ranking`),
  CONSTRAINT `cliente_productos_top_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cliente_productos_top_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_ventas_mensuales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_ventas_mensuales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `año` year(4) NOT NULL,
  `mes` tinyint(4) NOT NULL,
  `cantidad_ventas` int(11) NOT NULL DEFAULT 0,
  `total_ventas` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ticket_promedio` decimal(15,2) NOT NULL DEFAULT 0.00,
  `productos_unicos` int(11) NOT NULL DEFAULT 0,
  `items_totales` int(11) NOT NULL DEFAULT 0,
  `es_mes_alto` tinyint(1) NOT NULL DEFAULT 0,
  `variacion_mes_anterior` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cliente_ventas_mensuales_id_cliente_año_mes_unique` (`id_cliente`,`año`,`mes`),
  KEY `cliente_ventas_mensuales_id_cliente_index` (`id_cliente`),
  KEY `cliente_ventas_mensuales_año_mes_index` (`año`,`mes`),
  KEY `cliente_ventas_mensuales_id_cliente_año_mes_index` (`id_cliente`,`año`,`mes`),
  KEY `idx_ventas_mensuales_cliente_fecha` (`id_cliente`,`año`,`mes`),
  CONSTRAINT `cliente_ventas_mensuales_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cliente_visitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_visitas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `tipo_visita` varchar(191) NOT NULL,
  `titulo` varchar(191) NOT NULL,
  `descripcion` text NOT NULL,
  `responsable` varchar(191) NOT NULL,
  `prioridad` varchar(191) NOT NULL DEFAULT 'medium',
  `productos_mencionados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`productos_mencionados`)),
  `servicios_mencionados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`servicios_mencionados`)),
  `valor_potencial` decimal(15,2) DEFAULT NULL,
  `estado` varchar(191) NOT NULL DEFAULT 'programada',
  `fecha_visita` date NOT NULL,
  `hora_visita` time NOT NULL,
  `duracion_minutos` int(11) DEFAULT NULL,
  `resultados` text DEFAULT NULL,
  `proximos_pasos` text DEFAULT NULL,
  `fecha_seguimiento` date DEFAULT NULL,
  `requiere_seguimiento` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_visitas_id_cliente_fecha_visita_index` (`id_cliente`,`fecha_visita`),
  KEY `cliente_visitas_responsable_fecha_visita_index` (`responsable`,`fecha_visita`),
  KEY `cliente_visitas_estado_fecha_visita_index` (`estado`,`fecha_visita`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_cliente` varchar(60) DEFAULT NULL,
  `tiempo_pago` int(11) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `clasificacion` varchar(100) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `codigo` varchar(250) DEFAULT NULL,
  `nombre_empresa` varchar(255) DEFAULT NULL,
  `nombre_comercial` varchar(255) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `shopify_customer_id` varchar(191) DEFAULT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `cod_departamento` varchar(10) DEFAULT NULL,
  `tipo_persona` varchar(255) DEFAULT '0',
  `tipo_documento` varchar(255) DEFAULT NULL,
  `tipo_factura_preferida` varchar(80) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `cod_municipio` varchar(10) DEFAULT NULL,
  `ncr` varchar(100) DEFAULT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `dui` varchar(255) DEFAULT NULL,
  `tipo_contribuyente` varchar(255) DEFAULT NULL,
  `tipo_fiscal` varchar(40) DEFAULT NULL,
  `distrito` varchar(255) DEFAULT NULL,
  `cod_distrito` varchar(100) DEFAULT NULL,
  `tipo` varchar(250) DEFAULT 'Persona',
  `giro` varchar(400) DEFAULT NULL,
  `cod_giro` varchar(10) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `direccion` varchar(500) DEFAULT NULL,
  `pais` varchar(255) DEFAULT NULL,
  `cod_pais` varchar(255) DEFAULT NULL,
  `nota` varchar(1500) DEFAULT NULL,
  `red_social` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_cuenta_contable` int(11) DEFAULT NULL,
  `etiquetas` varchar(500) DEFAULT NULL,
  `fecha_cumpleanos` date DEFAULT NULL,
  `empresa_telefono` varchar(250) DEFAULT NULL,
  `empresa_correo` varchar(255) DEFAULT NULL,
  `empresa_direccion` varchar(250) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `nivel` int(11) NOT NULL DEFAULT 1 COMMENT 'DEFAULT 1 (STANDARD)',
  `id_tipo_cliente` bigint(20) unsigned DEFAULT NULL,
  `id_vendedor` int(10) unsigned DEFAULT NULL,
  `habilita_credito` tinyint(1) NOT NULL DEFAULT 0,
  `dias_credito` smallint(5) unsigned DEFAULT NULL,
  `limite_credito` decimal(12,2) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clientes_codigo_cliente_index` (`codigo_cliente`),
  KEY `clientes_shopify_customer_id_index` (`shopify_customer_id`),
  KEY `clientes_id_tipo_cliente_foreign` (`id_tipo_cliente`),
  KEY `clientes_id_empresa_enable_index` (`id_empresa`,`enable`),
  KEY `clientes_correo_index` (`correo`),
  KEY `clientes_telefono_index` (`telefono`),
  KEY `idx_cli_empresa_enable_nombre` (`id_empresa`,`enable`,`nombre`),
  FULLTEXT KEY `ft_clientes_buscador` (`nombre`,`apellido`,`nombre_empresa`,`nit`,`ncr`),
  CONSTRAINT `clientes_id_tipo_cliente_foreign` FOREIGN KEY (`id_tipo_cliente`) REFERENCES `tipos_cliente_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_consultas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_consultas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_expediente` bigint(20) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_usuario_profesional` bigint(20) unsigned NOT NULL,
  `id_usuario_registro` bigint(20) unsigned DEFAULT NULL,
  `id_evento` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `motivo` varchar(255) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'borrador',
  `anamnesis` text DEFAULT NULL,
  `antecedentes` text DEFAULT NULL,
  `examen_fisico` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `indicaciones` text DEFAULT NULL,
  `signos_vitales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`signos_vitales`)),
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `addendum` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_consultas_id_expediente_foreign` (`id_expediente`),
  KEY `clinica_consultas_id_paciente_foreign` (`id_paciente`),
  KEY `clinica_consultas_id_empresa_id_paciente_fecha_index` (`id_empresa`,`id_paciente`,`fecha`),
  KEY `clinica_consultas_id_sucursal_foreign` (`id_sucursal`),
  CONSTRAINT `clinica_consultas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_consultas_id_expediente_foreign` FOREIGN KEY (`id_expediente`) REFERENCES `clinica_expedientes` (`id`),
  CONSTRAINT `clinica_consultas_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`),
  CONSTRAINT `clinica_consultas_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_diagnostico_catalogo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_diagnostico_catalogo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `codigo` varchar(32) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_diag_cat_emp_cod_uq` (`id_empresa`,`codigo`),
  CONSTRAINT `clinica_diagnostico_catalogo_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_diagnosticos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_diagnosticos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_expediente` bigint(20) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_consulta` bigint(20) unsigned DEFAULT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_usuario_profesional` bigint(20) unsigned NOT NULL,
  `id_usuario_registro` bigint(20) unsigned DEFAULT NULL,
  `id_diagnostico_anterior` bigint(20) unsigned DEFAULT NULL,
  `codigo` varchar(32) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `rol` varchar(16) NOT NULL DEFAULT 'secundario',
  `fecha` date NOT NULL,
  `estado` varchar(16) NOT NULL DEFAULT 'activo',
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_diagnosticos_id_expediente_foreign` (`id_expediente`),
  KEY `clinica_diagnosticos_id_paciente_foreign` (`id_paciente`),
  KEY `clinica_diag_pac_fecha_idx` (`id_empresa`,`id_paciente`,`fecha`),
  KEY `clinica_diag_cons_rol_idx` (`id_consulta`,`rol`,`estado`),
  CONSTRAINT `clinica_diagnosticos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_diagnosticos_id_expediente_foreign` FOREIGN KEY (`id_expediente`) REFERENCES `clinica_expedientes` (`id`),
  CONSTRAINT `clinica_diagnosticos_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_especies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_especies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_especies_id_empresa_nombre_unique` (`id_empresa`,`nombre`),
  CONSTRAINT `clinica_especies_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_expediente_secuencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_expediente_secuencias` (
  `id_empresa` int(10) unsigned NOT NULL,
  `ultimo` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_empresa`),
  CONSTRAINT `clinica_expediente_secuencias_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_expedientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_expedientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_sucursal_apertura` int(11) DEFAULT NULL,
  `numero` int(10) unsigned NOT NULL,
  `fecha_apertura` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'abierto',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_expedientes_id_empresa_numero_unique` (`id_empresa`,`numero`),
  UNIQUE KEY `clinica_expedientes_id_paciente_unique` (`id_paciente`),
  KEY `clinica_expedientes_id_sucursal_apertura_foreign` (`id_sucursal_apertura`),
  CONSTRAINT `clinica_expedientes_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_expedientes_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`),
  CONSTRAINT `clinica_expedientes_id_sucursal_apertura_foreign` FOREIGN KEY (`id_sucursal_apertura`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_historial_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_historial_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_expediente` bigint(20) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `fecha_evento` date NOT NULL,
  `hora_evento` time DEFAULT NULL,
  `id_usuario_profesional` bigint(20) unsigned DEFAULT NULL,
  `origen_tipo` varchar(40) NOT NULL,
  `origen_id` bigint(20) unsigned NOT NULL,
  `resumen` varchar(255) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_hist_evt_origen_uq` (`origen_tipo`,`origen_id`),
  KEY `clinica_hist_evt_exp_fecha_idx` (`id_expediente`,`fecha_evento`,`hora_evento`),
  KEY `clinica_historial_eventos_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_historial_eventos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_historial_eventos_id_expediente_foreign` FOREIGN KEY (`id_expediente`) REFERENCES `clinica_expedientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_paciente_responsables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_paciente_responsables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_responsable` bigint(20) unsigned DEFAULT NULL,
  `rol` varchar(30) NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `es_el_paciente` tinyint(1) NOT NULL DEFAULT 0,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_paciente_responsables_id_responsable_foreign` (`id_responsable`),
  KEY `clinica_paciente_responsables_id_paciente_vigente_hasta_index` (`id_paciente`,`vigente_hasta`),
  KEY `clinica_paciente_responsables_id_empresa_id_cliente_index` (`id_empresa`,`id_cliente`),
  KEY `clinica_paciente_responsables_id_cliente_foreign` (`id_cliente`),
  CONSTRAINT `clinica_paciente_responsables_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinica_paciente_responsables_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_paciente_responsables_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`),
  CONSTRAINT `clinica_paciente_responsables_id_responsable_foreign` FOREIGN KEY (`id_responsable`) REFERENCES `clinica_responsables` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_pacientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_pacientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `id_usuario` bigint(20) unsigned DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `alta_cerrada` tinyint(1) NOT NULL DEFAULT 0,
  `nombres` varchar(120) DEFAULT NULL,
  `apellidos` varchar(120) DEFAULT NULL,
  `nombre` varchar(120) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` varchar(20) DEFAULT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `informacion_relevante` text DEFAULT NULL,
  `id_especie` bigint(20) unsigned DEFAULT NULL,
  `id_raza` bigint(20) unsigned DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `peso` decimal(8,2) DEFAULT NULL,
  `microchip` varchar(50) DEFAULT NULL,
  `esterilizado` tinyint(1) DEFAULT NULL,
  `identificadores` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_pacientes_id_empresa_documento_unique` (`id_empresa`,`documento`),
  UNIQUE KEY `clinica_pacientes_id_empresa_microchip_unique` (`id_empresa`,`microchip`),
  KEY `clinica_pacientes_id_especie_foreign` (`id_especie`),
  KEY `clinica_pacientes_id_raza_foreign` (`id_raza`),
  KEY `clinica_pacientes_id_empresa_activo_tipo_index` (`id_empresa`,`activo`,`tipo`),
  KEY `clinica_pacientes_id_sucursal_foreign` (`id_sucursal`),
  CONSTRAINT `clinica_pacientes_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_pacientes_id_especie_foreign` FOREIGN KEY (`id_especie`) REFERENCES `clinica_especies` (`id`),
  CONSTRAINT `clinica_pacientes_id_raza_foreign` FOREIGN KEY (`id_raza`) REFERENCES `clinica_razas` (`id`),
  CONSTRAINT `clinica_pacientes_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_producto_perfiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_producto_perfiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_producto` int(10) unsigned NOT NULL,
  `via_habitual` varchar(80) DEFAULT NULL,
  `unidad_dosis` varchar(80) DEFAULT NULL,
  `controla_lote` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_prod_perfil_emp_prod_uq` (`id_empresa`,`id_producto`),
  KEY `clinica_producto_perfiles_id_producto_foreign` (`id_producto`),
  CONSTRAINT `clinica_producto_perfiles_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_producto_perfiles_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_profesional_sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_profesional_sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_profesional` bigint(20) unsigned NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_profesional_sucursales_id_profesional_id_sucursal_unique` (`id_profesional`,`id_sucursal`),
  KEY `clinica_profesional_sucursales_id_sucursal_foreign` (`id_sucursal`),
  CONSTRAINT `clinica_profesional_sucursales_id_profesional_foreign` FOREIGN KEY (`id_profesional`) REFERENCES `clinica_profesionales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinica_profesional_sucursales_id_sucursal_foreign` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_profesionales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_profesionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `especialidad` varchar(80) DEFAULT NULL,
  `colegiatura` varchar(80) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_profesionales_id_usuario_unique` (`id_usuario`),
  KEY `clinica_profesionales_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_profesionales_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_profesionales_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_razas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_razas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_especie` bigint(20) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinica_razas_id_especie_nombre_unique` (`id_especie`,`nombre`),
  KEY `clinica_razas_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_razas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_razas_id_especie_foreign` FOREIGN KEY (`id_especie`) REFERENCES `clinica_especies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_receta_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_receta_lineas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_receta` bigint(20) unsigned NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 1,
  `id_producto` int(10) unsigned DEFAULT NULL,
  `nombre_medicamento` varchar(255) NOT NULL,
  `dosis` varchar(120) NOT NULL,
  `frecuencia` varchar(120) DEFAULT NULL,
  `duracion` varchar(120) DEFAULT NULL,
  `via` varchar(80) DEFAULT NULL,
  `indicaciones` text DEFAULT NULL,
  `peso_referencia` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_receta_lineas_id_receta_foreign` (`id_receta`),
  KEY `clinica_receta_lineas_id_empresa_foreign` (`id_empresa`),
  KEY `clinica_receta_lineas_id_producto_foreign` (`id_producto`),
  CONSTRAINT `clinica_receta_lineas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_receta_lineas_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`),
  CONSTRAINT `clinica_receta_lineas_id_receta_foreign` FOREIGN KEY (`id_receta`) REFERENCES `clinica_recetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_recetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_recetas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_expediente` bigint(20) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_consulta` bigint(20) unsigned DEFAULT NULL,
  `id_tratamiento` bigint(20) unsigned DEFAULT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_usuario_profesional` bigint(20) unsigned NOT NULL,
  `id_usuario_registro` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'borrador',
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_recetas_id_expediente_foreign` (`id_expediente`),
  KEY `clinica_recetas_id_paciente_foreign` (`id_paciente`),
  KEY `clinica_rec_pac_fecha_idx` (`id_empresa`,`id_paciente`,`fecha`),
  CONSTRAINT `clinica_recetas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_recetas_id_expediente_foreign` FOREIGN KEY (`id_expediente`) REFERENCES `clinica_expedientes` (`id`),
  CONSTRAINT `clinica_recetas_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_responsables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_responsables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_responsables_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_responsables_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_tratamiento_avances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_tratamiento_avances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_tratamiento` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `nota` text DEFAULT NULL,
  `incumplimiento` tinyint(1) NOT NULL DEFAULT 0,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_tratamiento_avances_id_tratamiento_foreign` (`id_tratamiento`),
  KEY `clinica_tratamiento_avances_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_tratamiento_avances_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_tratamiento_avances_id_tratamiento_foreign` FOREIGN KEY (`id_tratamiento`) REFERENCES `clinica_tratamientos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_tratamiento_terapias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_tratamiento_terapias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_tratamiento` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(80) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `notas_resultado` text DEFAULT NULL,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_tratamiento_terapias_id_tratamiento_foreign` (`id_tratamiento`),
  KEY `clinica_tratamiento_terapias_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_tratamiento_terapias_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_tratamiento_terapias_id_tratamiento_foreign` FOREIGN KEY (`id_tratamiento`) REFERENCES `clinica_tratamientos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_tratamientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_tratamientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_expediente` bigint(20) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_consulta` bigint(20) unsigned DEFAULT NULL,
  `id_usuario_profesional` bigint(20) unsigned NOT NULL,
  `id_usuario_registro` bigint(20) unsigned DEFAULT NULL,
  `nombre` varchar(160) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `frecuencia` varchar(120) DEFAULT NULL,
  `duracion` varchar(120) DEFAULT NULL,
  `indicaciones` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'indicado',
  `motivo_suspension` varchar(255) DEFAULT NULL,
  `motivo_cierre` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_tratamientos_id_expediente_foreign` (`id_expediente`),
  KEY `clinica_tratamientos_id_paciente_foreign` (`id_paciente`),
  KEY `clinica_trat_pac_fecha_idx` (`id_empresa`,`id_paciente`,`fecha_inicio`),
  CONSTRAINT `clinica_tratamientos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_tratamientos_id_expediente_foreign` FOREIGN KEY (`id_expediente`) REFERENCES `clinica_expedientes` (`id`),
  CONSTRAINT `clinica_tratamientos_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_vacuna_aplicaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_vacuna_aplicaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_expediente` bigint(20) unsigned NOT NULL,
  `id_paciente` bigint(20) unsigned NOT NULL,
  `id_catalogo` bigint(20) unsigned DEFAULT NULL,
  `nombre_vacuna` varchar(160) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'vacuna',
  `fecha_aplicacion` date NOT NULL,
  `dosis` varchar(120) DEFAULT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_usuario_profesional` bigint(20) unsigned NOT NULL,
  `id_usuario_registro` bigint(20) unsigned DEFAULT NULL,
  `id_producto` int(10) unsigned DEFAULT NULL,
  `id_lote` bigint(20) unsigned DEFAULT NULL,
  `lote_numero` varchar(80) DEFAULT NULL,
  `lote_vencimiento` date DEFAULT NULL,
  `fabricante` varchar(120) DEFAULT NULL,
  `proxima_fecha` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activa',
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_vacuna_aplicaciones_id_expediente_foreign` (`id_expediente`),
  KEY `clinica_vacuna_aplicaciones_id_paciente_foreign` (`id_paciente`),
  KEY `clinica_vac_apl_idx` (`id_empresa`,`id_paciente`,`fecha_aplicacion`),
  KEY `clinica_vacuna_aplicaciones_id_catalogo_foreign` (`id_catalogo`),
  CONSTRAINT `clinica_vacuna_aplicaciones_id_catalogo_foreign` FOREIGN KEY (`id_catalogo`) REFERENCES `clinica_vacuna_catalogo` (`id`),
  CONSTRAINT `clinica_vacuna_aplicaciones_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`),
  CONSTRAINT `clinica_vacuna_aplicaciones_id_expediente_foreign` FOREIGN KEY (`id_expediente`) REFERENCES `clinica_expedientes` (`id`),
  CONSTRAINT `clinica_vacuna_aplicaciones_id_paciente_foreign` FOREIGN KEY (`id_paciente`) REFERENCES `clinica_pacientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinica_vacuna_catalogo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinica_vacuna_catalogo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'vacuna',
  `tipo_paciente` varchar(20) DEFAULT NULL,
  `id_especie` bigint(20) unsigned DEFAULT NULL,
  `intervalo_dias` smallint(5) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinica_vacuna_catalogo_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `clinica_vacuna_catalogo_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `codigos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(250) NOT NULL,
  `codigo` varchar(250) DEFAULT NULL,
  `porcentaje` decimal(10,2) NOT NULL,
  `estado` varchar(250) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `codigos_dashboards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `codigos_dashboards` (
  `id_empresa` int(3) NOT NULL DEFAULT 0,
  `codigo_embed` varchar(296) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comanda_detalle_restaurante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comanda_detalle_restaurante` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `comanda_id` bigint(20) unsigned NOT NULL,
  `orden_detalle_id` bigint(20) unsigned DEFAULT NULL,
  `pedido_detalle_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comanda_orden_detalle_unique` (`comanda_id`,`orden_detalle_id`),
  KEY `comanda_detalle_restaurante_orden_detalle_id_foreign` (`orden_detalle_id`),
  KEY `comanda_detalle_restaurante_pedido_detalle_id_foreign` (`pedido_detalle_id`),
  CONSTRAINT `comanda_detalle_restaurante_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas_restaurante` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comanda_detalle_restaurante_orden_detalle_id_foreign` FOREIGN KEY (`orden_detalle_id`) REFERENCES `orden_detalle_restaurante` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comanda_detalle_restaurante_pedido_detalle_id_foreign` FOREIGN KEY (`pedido_detalle_id`) REFERENCES `restaurante_pedido_detalles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comanda_estado_tiempos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comanda_estado_tiempos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `comanda_id` bigint(20) unsigned NOT NULL,
  `estado_desde` varchar(20) NOT NULL,
  `estado_hasta` varchar(20) NOT NULL,
  `inicio_at` timestamp NOT NULL,
  `fin_at` timestamp NOT NULL,
  `segundos` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comanda_estado_tiempos_comanda_id_foreign` (`comanda_id`),
  KEY `comanda_estado_tiempos_id_empresa_comanda_id_index` (`id_empresa`,`comanda_id`),
  CONSTRAINT `comanda_estado_tiempos_comanda_id_foreign` FOREIGN KEY (`comanda_id`) REFERENCES `comandas_restaurante` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comandas_restaurante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comandas_restaurante` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `sesion_id` bigint(20) unsigned DEFAULT NULL,
  `pedido_id` bigint(20) unsigned DEFAULT NULL,
  `numero_comanda` varchar(30) NOT NULL COMMENT 'Número correlativo de comanda',
  `estado` enum('pendiente','preparando','listo','servido') NOT NULL DEFAULT 'pendiente',
  `destino` varchar(20) NOT NULL DEFAULT 'cocina' COMMENT 'cocina, barra, eliminacion',
  `pantalla_id` bigint(20) unsigned DEFAULT NULL,
  `eliminacion_item_enviado` tinyint(1) DEFAULT NULL COMMENT 'Solo destino=eliminacion: ítem ya estaba en cocina/barra',
  `motivo_eliminacion_codigo` varchar(50) DEFAULT NULL,
  `motivo_eliminacion_detalle` text DEFAULT NULL,
  `enviado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `preparando_at` timestamp NULL DEFAULT NULL,
  `listo_at` timestamp NULL DEFAULT NULL,
  `servido_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comandas_restaurante_sesion_id_estado_index` (`sesion_id`,`estado`),
  KEY `comandas_restaurante_pedido_id_foreign` (`pedido_id`),
  KEY `comandas_restaurante_id_empresa_estado_created_at_index` (`id_empresa`,`estado`,`created_at`),
  KEY `comandas_restaurante_pantalla_id_foreign` (`pantalla_id`),
  CONSTRAINT `comandas_restaurante_pantalla_id_foreign` FOREIGN KEY (`pantalla_id`) REFERENCES `restaurante_pantallas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `comandas_restaurante_pedido_id_foreign` FOREIGN KEY (`pedido_id`) REFERENCES `restaurante_pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comandas_restaurante_sesion_id_foreign` FOREIGN KEY (`sesion_id`) REFERENCES `restaurante_sesiones_mesa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comision_categoria_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comision_categoria_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_regla` bigint(20) unsigned DEFAULT NULL,
  `id_categoria` bigint(20) unsigned NOT NULL,
  `porcentaje` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comision_categoria_config_id_regla_id_categoria_unique` (`id_regla`,`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comision_liquidaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comision_liquidaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_periodo` bigint(20) unsigned NOT NULL,
  `id_vendedor` bigint(20) unsigned NOT NULL,
  `total_comision` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `salario_base` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `ajuste_salario_minimo` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `salario_minimo_aplicado` decimal(14,4) DEFAULT NULL,
  `total_a_pagar` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `pagado_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comision_liquidaciones_id_empresa_id_periodo_id_vendedor_unique` (`id_empresa`,`id_periodo`,`id_vendedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comision_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comision_movimientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_vendedor` bigint(20) unsigned NOT NULL,
  `id_periodo` bigint(20) unsigned DEFAULT NULL,
  `id_regla` bigint(20) unsigned DEFAULT NULL,
  `origen` varchar(32) NOT NULL,
  `id_venta` bigint(20) unsigned DEFAULT NULL,
  `id_detalle_venta` bigint(20) unsigned DEFAULT NULL,
  `id_abono` bigint(20) unsigned DEFAULT NULL,
  `id_gift_card_redencion` bigint(20) unsigned DEFAULT NULL,
  `id_categoria` bigint(20) unsigned DEFAULT NULL,
  `id_subcategoria` bigint(20) unsigned DEFAULT NULL,
  `monto_base` decimal(14,4) NOT NULL,
  `porcentaje_aplicado` decimal(8,4) NOT NULL,
  `monto_comision` decimal(14,4) NOT NULL,
  `id_movimiento_origen` bigint(20) unsigned DEFAULT NULL,
  `fecha_evento` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_regla_ifnull` bigint(20) unsigned GENERATED ALWAYS AS (ifnull(`id_regla`,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comision_mov_unique_ajuste` (`id_empresa`,`origen`,`id_movimiento_origen`),
  UNIQUE KEY `comision_mov_unique_redencion_ifnull` (`id_empresa`,`id_gift_card_redencion`,`id_regla_ifnull`),
  KEY `comision_mov_idx_detalle` (`id_empresa`,`origen`,`id_detalle_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comision_periodos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comision_periodos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'abierto',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comision_periodos_id_empresa_fecha_inicio_fecha_fin_unique` (`id_empresa`,`fecha_inicio`,`fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comision_reglas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comision_reglas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `nombre` varchar(191) NOT NULL,
  `tipo_calculo` varchar(32) NOT NULL,
  `alcance` varchar(32) NOT NULL DEFAULT 'global',
  `id_vendedores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`id_vendedores`)),
  `momento_devengo` varchar(32) NOT NULL DEFAULT 'al_pagar',
  `reemplaza_global` tinyint(1) NOT NULL DEFAULT 0,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comision_reglas_id_empresa_activo_index` (`id_empresa`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comision_subcategoria_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comision_subcategoria_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_regla` bigint(20) unsigned DEFAULT NULL,
  `id_subcategoria` bigint(20) unsigned NOT NULL,
  `porcentaje` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comision_subcategoria_config_id_regla_id_subcategoria_unique` (`id_regla`,`id_subcategoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `compra_impuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compra_impuestos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `id_impuesto` int(10) unsigned NOT NULL,
  `id_compra` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `compra_impuestos_id_compra_id_impuesto_unique` (`id_compra`,`id_impuesto`),
  KEY `compra_impuestos_id_compra_index` (`id_compra`),
  KEY `compra_impuestos_id_impuesto_index` (`id_impuesto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_generacion` varchar(255) DEFAULT NULL,
  `es_retaceo` tinyint(1) NOT NULL DEFAULT 0,
  `sello_mh` varchar(255) DEFAULT NULL,
  `numero_control` varchar(255) DEFAULT NULL,
  `tipo_dte` varchar(255) DEFAULT NULL,
  `estado` varchar(255) NOT NULL,
  `es_consigna` tinyint(1) NOT NULL DEFAULT 0,
  `forma_pago` varchar(255) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `recurrente` tinyint(1) DEFAULT 0,
  `cotizacion` tinyint(1) DEFAULT 0,
  `fecha_expiracion` date DEFAULT NULL,
  `num_orden_compra` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `fecha_pago` date DEFAULT NULL,
  `tipo_gasto` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `sector` varchar(255) DEFAULT NULL,
  `clasificacion` varchar(255) DEFAULT NULL,
  `tipo_operacion` varchar(255) DEFAULT NULL,
  `sub_total` double(10,2) NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `porcentaje_impuesto` decimal(5,2) DEFAULT NULL,
  `iva` double(10,2) NOT NULL,
  `renta_retenida` decimal(10,2) DEFAULT 0.00,
  `iva_retenido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `percepcion` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `otros_cargos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_bodega` int(11) NOT NULL,
  `tipo_documento` varchar(255) DEFAULT NULL,
  `num_identificacion` varchar(80) DEFAULT NULL,
  `num_serie` varchar(255) DEFAULT NULL,
  `detalle_banco` varchar(255) DEFAULT NULL,
  `referencia_pago` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `descuento` double(10,2) NOT NULL DEFAULT 0.00,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_authorization` bigint(20) unsigned DEFAULT NULL,
  `id_proyecto` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `dte` longtext DEFAULT NULL,
  `dte_s3_key` varchar(512) DEFAULT NULL,
  `dte_migrated_at` timestamp NULL DEFAULT NULL,
  `dte_invalidacion` longtext DEFAULT NULL,
  `dte_invalidacion_s3_key` varchar(512) DEFAULT NULL,
  `dte_invalidacion_migrated_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tipo_clasificacion` varchar(255) DEFAULT NULL,
  `tipo_sector` varchar(255) DEFAULT NULL,
  `tipo_costo_gasto` varchar(255) DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `exchange_rate` decimal(18,5) NOT NULL DEFAULT 1.00000,
  `exchange_rate_date` date DEFAULT NULL,
  `equivalent_total` decimal(18,5) DEFAULT NULL,
  `equivalent_iva` decimal(18,5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `compras_id_authorization_index` (`id_authorization`),
  KEY `idx_compras_empresa_sucursal_fecha` (`id_empresa`,`id_sucursal`,`fecha`,`estado`),
  KEY `idx_compras_empresa_fecha` (`id_empresa`,`fecha`,`estado`),
  KEY `idx_compras_empresa_sucursal_created` (`id_empresa`,`id_sucursal`,`created_at`),
  KEY `idx_comp_proveedor` (`id_proveedor`),
  KEY `idx_compras_empresa_num_identificacion` (`id_empresa`,`num_identificacion`),
  CONSTRAINT `compras_id_authorization_foreign` FOREIGN KEY (`id_authorization`) REFERENCES `authorizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consigna_custodias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consigna_custodias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_cliente` bigint(20) unsigned NOT NULL,
  `id_producto` bigint(20) unsigned NOT NULL,
  `id_bodega` bigint(20) unsigned NOT NULL,
  `cantidad` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consigna_custodias_unique` (`id_empresa`,`id_cliente`,`id_producto`,`id_bodega`),
  KEY `consigna_custodias_id_empresa_index` (`id_empresa`),
  KEY `consigna_custodias_id_cliente_index` (`id_cliente`),
  KEY `consigna_custodias_id_producto_index` (`id_producto`),
  KEY `consigna_custodias_id_bodega_index` (`id_bodega`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consigna_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consigna_movimientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `fecha` datetime NOT NULL,
  `tipo` varchar(64) NOT NULL,
  `cantidad` decimal(16,4) NOT NULL,
  `id_producto` bigint(20) unsigned NOT NULL,
  `id_bodega` bigint(20) unsigned NOT NULL,
  `lote_id` bigint(20) unsigned DEFAULT NULL,
  `id_proveedor` bigint(20) unsigned DEFAULT NULL,
  `id_cliente` bigint(20) unsigned DEFAULT NULL,
  `costo_unitario` decimal(16,4) DEFAULT NULL,
  `id_compra` bigint(20) unsigned DEFAULT NULL,
  `id_detalle_compra` bigint(20) unsigned DEFAULT NULL,
  `id_venta` bigint(20) unsigned DEFAULT NULL,
  `id_detalle_venta` bigint(20) unsigned DEFAULT NULL,
  `id_traslado` bigint(20) unsigned DEFAULT NULL,
  `nota` varchar(500) DEFAULT NULL,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `id_usuario` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consigna_movimientos_idempotency_unique` (`id_empresa`,`idempotency_key`),
  KEY `consigna_movimientos_id_empresa_fecha_index` (`id_empresa`,`fecha`),
  KEY `consigna_movimientos_id_empresa_id_producto_id_bodega_index` (`id_empresa`,`id_producto`,`id_bodega`),
  KEY `consigna_movimientos_id_empresa_index` (`id_empresa`),
  KEY `consigna_movimientos_id_producto_index` (`id_producto`),
  KEY `consigna_movimientos_id_bodega_index` (`id_bodega`),
  KEY `consigna_movimientos_id_proveedor_index` (`id_proveedor`),
  KEY `consigna_movimientos_id_cliente_index` (`id_cliente`),
  KEY `consigna_movimientos_lote_id_index` (`lote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consumo_puntos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consumo_puntos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_canje_tx` bigint(20) unsigned NOT NULL,
  `id_ganancia_tx` bigint(20) unsigned NOT NULL,
  `puntos_consumidos` int(11) NOT NULL COMMENT '> 0',
  `descripcion` text DEFAULT NULL COMMENT 'opcional - para auditoría',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consumo_puntos_id_ganancia_tx_foreign` (`id_ganancia_tx`),
  KEY `consumo_puntos_id_cliente_created_at_index` (`id_cliente`,`created_at`),
  KEY `consumo_puntos_id_canje_tx_index` (`id_canje_tx`),
  CONSTRAINT `consumo_puntos_id_canje_tx_foreign` FOREIGN KEY (`id_canje_tx`) REFERENCES `transacciones_puntos` (`id`),
  CONSTRAINT `consumo_puntos_id_ganancia_tx_foreign` FOREIGN KEY (`id_ganancia_tx`) REFERENCES `transacciones_puntos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contabilidad_configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contabilidad_configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cuenta_ventas` int(11) NOT NULL,
  `id_cuenta_costo_venta` int(11) DEFAULT NULL,
  `generar_partidas` varchar(100) DEFAULT 'Manual',
  `id_cuenta_renta_retenida_compras` int(11) DEFAULT NULL,
  `id_cuenta_perdida_ajuste` int(11) DEFAULT NULL,
  `id_cuenta_ganancia_ajuste` int(11) DEFAULT NULL,
  `id_cuenta_iva_retenido_compras` int(11) DEFAULT NULL,
  `id_cuenta_renta_retenida_ventas` int(11) DEFAULT NULL,
  `id_cuenta_iva_retenido_ventas` int(11) DEFAULT NULL,
  `id_cuenta_devoluciones_ventas` int(11) DEFAULT NULL,
  `id_cuenta_inventario` int(11) DEFAULT NULL,
  `id_cuenta_ajustes_inventario` int(11) DEFAULT NULL,
  `id_cuenta_cxc` int(11) DEFAULT NULL,
  `id_cuenta_devoluciones_clientes` int(11) DEFAULT NULL,
  `id_cuenta_cxp` int(11) DEFAULT NULL,
  `id_cuenta_devoluciones_proveedores` int(11) DEFAULT NULL,
  `id_cuenta_propina_ventas` int(11) DEFAULT NULL,
  `id_cuenta_cuenta_a_terceros` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_iva_ventas` int(11) DEFAULT NULL,
  `id_cuenta_iva_compras` int(11) DEFAULT NULL,
  `id_cuenta_pedidos_transito` int(11) DEFAULT NULL,
  `id_cuenta_inventario_transitorio` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_resultados_prefijos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`estado_resultados_prefijos`)),
  `id_cuenta_prestamos_corto` int(10) unsigned DEFAULT NULL,
  `id_cuenta_prestamos_largo` int(10) unsigned DEFAULT NULL,
  `id_cuenta_gastos_financieros` int(10) unsigned DEFAULT NULL,
  `separar_cuentas_iva` tinyint(1) NOT NULL DEFAULT 0,
  `abonos_en_cartera` tinyint(1) NOT NULL DEFAULT 0,
  `id_cuenta_iva_ventas_cf` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_iva_compras_cf` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_gasto_salarios` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_gasto_cargas_patronales` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_gasto_aguinaldo` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_gasto_vacaciones` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_pasivo_cargas_sociales` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_pasivo_ins` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_pasivo_retencion_renta` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_pasivo_salarios_por_pagar` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_pasivo_provision_aguinaldo` bigint(20) unsigned DEFAULT NULL,
  `id_cuenta_pasivo_provision_vacaciones` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contactos_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactos_cliente` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL COMMENT 'Contacto adicional del cliente Padre',
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `sexo` enum('Hombre','Mujer','Otro') DEFAULT NULL,
  `red_social` varchar(100) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contactos_emergencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactos_emergencia` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `relacion` varchar(50) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` text DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contactos_emergencia_id_empleado_foreign` (`id_empleado`),
  CONSTRAINT `contactos_emergencia_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contador_empresa_accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contador_empresa_accesos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario_contador` bigint(20) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permisos`)),
  `invitado_por_user_id` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contador_empresa_accesos_unique` (`id_usuario_contador`,`id_empresa`),
  KEY `contador_empresa_accesos_id_empresa_foreign` (`id_empresa`),
  KEY `contador_empresa_accesos_invitado_por_user_id_foreign` (`invitado_por_user_id`),
  CONSTRAINT `contador_empresa_accesos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contador_empresa_accesos_id_usuario_contador_foreign` FOREIGN KEY (`id_usuario_contador`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contador_empresa_accesos_invitado_por_user_id_foreign` FOREIGN KEY (`invitado_por_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contador_empresa_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contador_empresa_documentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `slug` varchar(64) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `ruta` varchar(512) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `mime` varchar(128) DEFAULT NULL,
  `tamano_bytes` int(10) unsigned DEFAULT NULL,
  `vence_en` date DEFAULT NULL,
  `id_usuario_carga` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contador_empresa_documentos_id_empresa_idx` (`id_empresa`),
  KEY `contador_empresa_documentos_id_usuario_carga_foreign` (`id_usuario_carga`),
  CONSTRAINT `contador_empresa_documentos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contador_empresa_documentos_id_usuario_carga_foreign` FOREIGN KEY (`id_usuario_carga`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contador_empresa_invitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contador_empresa_invitaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(64) NOT NULL,
  `email_admin` varchar(255) NOT NULL,
  `id_empresa_cliente` int(10) unsigned NOT NULL,
  `id_empresa_despacho` int(10) unsigned NOT NULL,
  `id_usuario_invito` bigint(20) unsigned NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `expira_en` timestamp NOT NULL,
  `respondida_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contador_empresa_invitaciones_token_hash_unique` (`token_hash`),
  KEY `contador_empresa_invitaciones_id_empresa_despacho_estado_index` (`id_empresa_despacho`,`estado`),
  KEY `contador_empresa_invitaciones_id_empresa_cliente_estado_index` (`id_empresa_cliente`,`estado`),
  KEY `contador_empresa_invitaciones_id_usuario_invito_foreign` (`id_usuario_invito`),
  CONSTRAINT `contador_empresa_invitaciones_id_empresa_cliente_foreign` FOREIGN KEY (`id_empresa_cliente`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contador_empresa_invitaciones_id_empresa_despacho_foreign` FOREIGN KEY (`id_empresa_despacho`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contador_empresa_invitaciones_id_usuario_invito_foreign` FOREIGN KEY (`id_usuario_invito`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contador_obligaciones_fiscales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contador_obligaciones_fiscales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `codigo` varchar(16) NOT NULL,
  `mes` tinyint(3) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `presentado_en` timestamp NOT NULL,
  `id_usuario_presento` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contador_obligaciones_fiscales_unique` (`id_empresa`,`codigo`,`mes`,`anio`),
  KEY `contador_obligaciones_fiscales_id_usuario_presento_foreign` (`id_usuario_presento`),
  CONSTRAINT `contador_obligaciones_fiscales_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contador_obligaciones_fiscales_id_usuario_presento_foreign` FOREIGN KEY (`id_usuario_presento`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `id_user` bigint(20) unsigned DEFAULT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_id_user_foreign` (`id_user`),
  CONSTRAINT `conversations_id_user_foreign` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `costos_ia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `costos_ia` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint(20) unsigned DEFAULT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `modelo` varchar(191) DEFAULT NULL,
  `tokens_entrada` int(11) NOT NULL DEFAULT 0,
  `tokens_salida` int(11) NOT NULL DEFAULT 0,
  `costo_estimado` decimal(10,6) NOT NULL DEFAULT 0.000000,
  `consulta` text DEFAULT NULL,
  `respuesta` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `costos_ia_id_usuario_foreign` (`id_usuario`),
  KEY `costos_ia_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `costos_ia_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `costos_ia_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cotizacion_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizacion_ventas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `estado` varchar(255) NOT NULL,
  `forma_pago` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `terminos_de_venta` text DEFAULT NULL,
  `descripcion_personalizada` tinyint(1) DEFAULT 0,
  `descripcion_impresion` varchar(500) DEFAULT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `detalle_banco` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `total_costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sub_total` double(10,2) NOT NULL DEFAULT 0.00,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `gravada` decimal(12,4) DEFAULT NULL,
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT 0.00,
  `iva` double(10,2) NOT NULL DEFAULT 0.00,
  `iva_retenido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva_percibido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` double(10,2) NOT NULL DEFAULT 0.00,
  `correlativo` int(11) DEFAULT NULL,
  `id_documento` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_proyecto` int(11) DEFAULT NULL,
  `id_bodega` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `referencia_shopify` varchar(191) DEFAULT NULL,
  `num_orden` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cobrar_impuestos` tinyint(1) DEFAULT 0,
  `aplicar_retencion` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cotizacion_cliente` (`id_cliente`),
  KEY `idx_cotizacion_usuario` (`id_usuario`),
  KEY `idx_cotizacion_empresa` (`id_empresa`),
  KEY `idx_cotizacion_sucursal` (`id_sucursal`),
  KEY `cotizacion_ventas_referencia_shopify_empresa` (`referencia_shopify`,`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credito_contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credito_contratos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `n_cuotas` smallint(5) unsigned NOT NULL,
  `fecha_inicio` date NOT NULL,
  `periodicidad` varchar(20) NOT NULL DEFAULT 'mensual',
  `tasa_interes` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `tasa_mora` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `concepto` varchar(191) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `id_documento` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credito_contratos_id_empresa_id_cliente_index` (`id_empresa`,`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credito_cuotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credito_cuotas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_contrato` bigint(20) unsigned NOT NULL,
  `numero` smallint(5) unsigned NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'programada',
  `id_venta` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credito_cuotas_id_contrato_numero_unique` (`id_contrato`,`numero`),
  CONSTRAINT `credito_cuotas_id_contrato_foreign` FOREIGN KEY (`id_contrato`) REFERENCES `credito_contratos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cuentas_bancarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_bancarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre_banco` varchar(255) NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `saldo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `correlativo_cheques` int(11) DEFAULT NULL,
  `id_cuenta_contable` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cuentas_bancarias_cheques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_bancarias_cheques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_referencia` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `id_cuenta` int(11) NOT NULL,
  `correlativo` int(11) NOT NULL,
  `anombrede` varchar(255) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `estado` varchar(255) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cuentas_bancarias_conciliaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_bancarias_conciliaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `desde` date NOT NULL,
  `hasta` date NOT NULL,
  `id_cuenta` int(11) NOT NULL,
  `gastos` decimal(10,2) DEFAULT 0.00,
  `impuestos` decimal(10,2) DEFAULT 0.00,
  `otras_entradas` decimal(10,2) DEFAULT 0.00,
  `saldo_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `saldo_actual` decimal(10,2) NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cuentas_bancarias_transacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_bancarias_transacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referencia` varchar(255) DEFAULT NULL,
  `tipo_operacion` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `id_cuenta` int(11) NOT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `url_referencia` varchar(255) DEFAULT NULL,
  `concepto` varchar(255) NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `estado` varchar(255) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `currency_code` varchar(3) NOT NULL,
  `currency_name` varchar(50) NOT NULL,
  `currency_symbol` varchar(50) NOT NULL,
  `country_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currencies_country_code_index` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_field_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `custom_field_id` bigint(20) unsigned NOT NULL,
  `value` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `custom_field_values_custom_field_id_foreign` (`custom_field_id`),
  CONSTRAINT `custom_field_values_custom_field_id_foreign` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `empresa_id` int(10) unsigned NOT NULL,
  `field_type` varchar(50) NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `custom_fields_empresa_id_foreign` (`empresa_id`),
  CONSTRAINT `custom_fields_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dashboards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(250) NOT NULL,
  `plataforma` varchar(250) DEFAULT NULL,
  `tipo` varchar(250) DEFAULT NULL,
  `codigo_embed` text NOT NULL,
  `codigo_embed_movil` varchar(900) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dashboards_bkp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dashboards_bkp` (
  `id` int(11) NOT NULL DEFAULT 0,
  `titulo` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `plataforma` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `tipo` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `codigo_embed` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `codigo_embed_movil` varchar(900) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departamentos_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departamentos_empresa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(191) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `estado` int(11) NOT NULL DEFAULT 1,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_compra_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_compra_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_detalle_compra` int(11) NOT NULL,
  `lote_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detalle_compra_lotes_lote_id_foreign` (`lote_id`),
  KEY `detalle_compra_lotes_id_detalle_compra_lote_id_index` (`id_detalle_compra`,`lote_id`),
  CONSTRAINT `detalle_compra_lotes_id_detalle_compra_foreign` FOREIGN KEY (`id_detalle_compra`) REFERENCES `detalles_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_compra_lotes_lote_id_foreign` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_egresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_egresos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_egreso` bigint(20) unsigned NOT NULL,
  `numero_item` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `concepto` text NOT NULL,
  `tipo` varchar(191) DEFAULT NULL,
  `tipo_gravado` varchar(20) NOT NULL DEFAULT 'gravada' COMMENT 'gravada, exenta, no_sujeta',
  `id_categoria` int(10) unsigned DEFAULT NULL,
  `cantidad` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `precio_unitario` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `sub_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(12,2) NOT NULL DEFAULT 0.00,
  `renta_retenida` decimal(12,2) NOT NULL DEFAULT 0.00,
  `iva_percibido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `area_empresa` varchar(191) DEFAULT NULL,
  `id_proyecto` int(10) unsigned DEFAULT NULL,
  `aplica_iva` tinyint(1) NOT NULL DEFAULT 0,
  `aplica_renta` tinyint(1) NOT NULL DEFAULT 0,
  `aplica_percepcion` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `iva_retenido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `aplica_retencion_iva` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_de_egreso` (`id_egreso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_salida_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_salida_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_detalle_salida` int(10) unsigned NOT NULL,
  `lote_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalle_venta_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_venta_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_detalle_venta` int(11) NOT NULL,
  `lote_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detalle_venta_lotes_lote_id_foreign` (`lote_id`),
  KEY `detalle_venta_lotes_id_detalle_venta_lote_id_index` (`id_detalle_venta`,`lote_id`),
  CONSTRAINT `detalle_venta_lotes_id_detalle_venta_foreign` FOREIGN KEY (`id_detalle_venta`) REFERENCES `detalles_venta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_venta_lotes_lote_id_foreign` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(10,2) NOT NULL,
  `costo` double NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `porcentaje_impuesto` decimal(5,2) DEFAULT NULL,
  `iva` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `id_producto` int(11) NOT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `id_compra` int(11) NOT NULL,
  `id_activo` int(10) unsigned DEFAULT NULL,
  `es_activo_fijo` tinyint(1) NOT NULL DEFAULT 0,
  `pendiente_capitalizacion` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detalles_compra_id_presentacion_foreign` (`id_presentacion`),
  KEY `idx_dc_compra` (`id_compra`),
  KEY `idx_dc_producto` (`id_producto`),
  CONSTRAINT `detalles_compra_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_compuesto_devolucion_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_compuesto_devolucion_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `id_detalle` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_compuesto_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_compuesto_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `cantidad` varchar(255) NOT NULL,
  `id_detalle` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dcv_detalle` (`id_detalle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_cotizacion_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_cotizacion_ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(10,2) NOT NULL,
  `costo` decimal(10,2) NOT NULL,
  `precio` double NOT NULL,
  `precio_sin_iva` decimal(16,6) DEFAULT NULL,
  `precio_con_iva` decimal(16,6) DEFAULT NULL,
  `total` double NOT NULL,
  `total_costo` decimal(10,2) NOT NULL,
  `descuento` double NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL,
  `exenta` decimal(9,2) NOT NULL,
  `cuenta_a_terceros` decimal(9,2) NOT NULL,
  `subtotal` decimal(9,2) NOT NULL,
  `gravada` decimal(12,4) NOT NULL,
  `iva` decimal(9,4) NOT NULL,
  `tipo_gravado` varchar(20) NOT NULL DEFAULT 'gravada',
  `descripcion` varchar(255) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_cotizacion_venta` int(11) NOT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalle_cotizacion` (`id_cotizacion_venta`),
  KEY `idx_detalle_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_devolucion_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_devolucion_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `total` double(10,2) NOT NULL,
  `costo` double(10,2) NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `no_sujeta` varchar(255) DEFAULT NULL,
  `exenta` varchar(255) DEFAULT NULL,
  `gravada` varchar(255) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `id_devolucion_compra` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  `iva` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_devolucion_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_devolucion_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `total` double(10,4) NOT NULL,
  `precio` double(10,4) NOT NULL,
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT 0.00,
  `id_producto` int(11) NOT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `id_devolucion_venta` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_evento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_orden_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_orden_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_compra` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `cantidad_procesada` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo` double NOT NULL,
  `descuento` double NOT NULL DEFAULT 0,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_orden_produccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_orden_produccion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` bigint(20) unsigned NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `total_costo` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `id_producto` bigint(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_cotizacion_venta` bigint(20) DEFAULT NULL,
  `cantidad_producida` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `detalles_orden_produccion_id_orden_produccion_foreign` (`id_orden_produccion`),
  CONSTRAINT `detalles_orden_produccion_id_orden_produccion_foreign` FOREIGN KEY (`id_orden_produccion`) REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_promocion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_promocion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cantidad` int(11) NOT NULL,
  `subtotal` double(10,2) NOT NULL,
  `precio` double(10,2) NOT NULL,
  `descuento` double DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `id_promocion` int(11) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detalles_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalles_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cantidad` decimal(10,2) NOT NULL,
  `costo` decimal(16,6) NOT NULL DEFAULT 0.000000,
  `precio` decimal(16,8) NOT NULL,
  `precio_sin_iva` decimal(16,8) DEFAULT NULL,
  `precio_con_iva` decimal(16,6) DEFAULT NULL,
  `total` decimal(9,2) NOT NULL,
  `total_costo` decimal(9,2) DEFAULT 0.00,
  `descuento` decimal(9,2) NOT NULL DEFAULT 0.00,
  `sub_total` decimal(12,4) DEFAULT NULL COMMENT 'cantidad * precio antes de descuento',
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `gravada` decimal(10,2) DEFAULT 0.00,
  `iva` decimal(9,4) DEFAULT 0.0000,
  `porcentaje_impuesto` decimal(5,2) DEFAULT NULL,
  `fe_cr_exoneracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fe_cr_exoneracion`)),
  `tipo_gravado` varchar(20) DEFAULT 'gravada' COMMENT 'gravada, exenta, no_sujeta',
  `descripcion` text DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `origen_stock` varchar(32) DEFAULT NULL,
  `id_venta` int(11) NOT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detalles_venta_id_vendedor` (`id_vendedor`),
  KEY `idx_detalles_venta_producto` (`id_venta`,`id_producto`),
  KEY `idx_detalles_producto_cantidad` (`id_producto`,`cantidad` DESC),
  KEY `detalles_venta_id_presentacion_foreign` (`id_presentacion`),
  KEY `idx_detalles_id_venta_vendedor` (`id_venta`,`id_vendedor`),
  CONSTRAINT `detalles_venta_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `devoluciones_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `devoluciones_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `total` double(10,2) NOT NULL,
  `sub_total` double(10,2) NOT NULL,
  `iva` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva_percibido` decimal(10,2) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `tipo_documento` varchar(255) DEFAULT NULL,
  `tipo_dte` varchar(2) DEFAULT NULL,
  `codigo_generacion` varchar(36) DEFAULT NULL,
  `sello_mh` varchar(191) DEFAULT NULL,
  `dte` longtext DEFAULT NULL,
  `dte_invalidacion` longtext DEFAULT NULL,
  `tipo` varchar(100) DEFAULT 'devolucion',
  `iva_retenido` decimal(10,2) DEFAULT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(500) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `id_bodega` int(11) DEFAULT NULL,
  `id_compra` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devoluciones_compra_id_compra` (`id_compra`),
  KEY `idx_devoluciones_compra_emp_enable_fecha` (`id_empresa`,`enable`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `devoluciones_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `devoluciones_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_generacion` varchar(255) DEFAULT NULL,
  `sello_mh` varchar(255) DEFAULT NULL,
  `numero_control` varchar(255) DEFAULT NULL,
  `tipo_dte` varchar(255) DEFAULT NULL,
  `tipo` varchar(100) DEFAULT 'devolucion',
  `prueba_masiva` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si la nota fue generada como parte de pruebas masivas',
  `fecha` date NOT NULL,
  `correlativo` varchar(255) DEFAULT NULL,
  `id_documento` int(11) DEFAULT NULL,
  `total` double(10,2) NOT NULL,
  `sub_total` double(10,2) NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(10,2) NOT NULL DEFAULT 0.00,
  `iva_retenido` decimal(9,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(250) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `id_bodega` int(11) DEFAULT NULL,
  `id_venta` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `dte` longtext DEFAULT NULL,
  `dte_invalidacion` longtext DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `exchange_rate` decimal(18,5) NOT NULL DEFAULT 1.00000,
  `exchange_rate_date` date DEFAULT NULL,
  `equivalent_total` decimal(18,5) DEFAULT NULL,
  `equivalent_iva` decimal(18,5) DEFAULT NULL,
  `motivo_fe_cr` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devoluciones_id_venta_enable` (`id_venta`,`enable`),
  KEY `idx_devoluciones_venta_emp_enable_fecha` (`id_empresa`,`enable`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direcciones_origen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `direcciones_origen` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `alias` varchar(191) NOT NULL,
  `nombre_contacto` varchar(191) DEFAULT NULL,
  `direccion` text NOT NULL,
  `referencia` text NOT NULL,
  `telefono` varchar(191) NOT NULL,
  `codigo_area` varchar(191) NOT NULL DEFAULT '503',
  `latitud` decimal(10,7) NOT NULL,
  `longitud` decimal(11,7) NOT NULL,
  `boxful_state_id` varchar(191) NOT NULL,
  `boxful_city_id` varchar(191) NOT NULL,
  `boxful_address_id` varchar(191) DEFAULT NULL,
  `es_predeterminada` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `direcciones_origen_boxful_address_id_unique` (`boxful_address_id`),
  KEY `direcciones_origen_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `direcciones_origen_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `distritos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `distritos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(100) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `cod_municipio` varchar(100) NOT NULL,
  `cod_departamento` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `division_cuenta_restaurante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `division_cuenta_restaurante` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sesion_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('equitativa','por_items') NOT NULL DEFAULT 'equitativa',
  `num_pagadores` smallint(5) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `division_cuenta_restaurante_sesion_id_index` (`sesion_id`),
  CONSTRAINT `division_cuenta_restaurante_sesion_id_foreign` FOREIGN KEY (`sesion_id`) REFERENCES `restaurante_sesiones_mesa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `correlativo` int(11) NOT NULL,
  `numero_emision` char(2) DEFAULT NULL,
  `rangos` varchar(255) DEFAULT NULL,
  `numero_autorizacion` varchar(255) DEFAULT NULL,
  `resolucion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `predeterminado` tinyint(1) DEFAULT 0,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentos_empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_empleado` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `tipo_documento` int(11) NOT NULL,
  `nombre_archivo` varchar(191) NOT NULL,
  `ruta_archivo` varchar(191) NOT NULL,
  `fecha_documento` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documentos_empleado_id_empleado_foreign` (`id_empleado`),
  KEY `documentos_empleado_tipo_documento_index` (`tipo_documento`),
  CONSTRAINT `documentos_empleado_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dte_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dte_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `pais` char(2) NOT NULL DEFAULT 'SV',
  `user_email_account_id` bigint(20) unsigned NOT NULL,
  `dte_uuid` varchar(191) NOT NULL COMMENT 'UUID from MH JSON - id field',
  `dte_type` varchar(5) NOT NULL COMMENT '01=FC, 03=CCF, 04=NR, 05=NC, 06=ND, 11=FEX',
  `dte_number` varchar(191) DEFAULT NULL,
  `emission_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `issuer_nit` varchar(191) DEFAULT NULL,
  `issuer_name` varchar(191) DEFAULT NULL,
  `receiver_nit` varchar(191) DEFAULT NULL,
  `formato_origen` varchar(10) NOT NULL DEFAULT 'json',
  `json_path` varchar(191) DEFAULT NULL COMMENT 'Path in storage',
  `xml_path` varchar(191) DEFAULT NULL,
  `acuse_xml_path` varchar(191) DEFAULT NULL,
  `acuse_estado` varchar(191) DEFAULT NULL,
  `pdf_path` varchar(191) DEFAULT NULL,
  `validation_status` enum('pending','valid','invalid') NOT NULL DEFAULT 'pending',
  `validation_errors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_errors`)),
  `processing_status` enum('pending','processed','failed','pendiente_clasificacion','anulado') NOT NULL DEFAULT 'pending',
  `processing_errors` text DEFAULT NULL,
  `destino` varchar(20) DEFAULT NULL COMMENT 'compra|gasto - override from dte_tipo_mapeo, user can change',
  `id_proyecto` int(10) unsigned DEFAULT NULL,
  `id_categoria` bigint(20) unsigned DEFAULT NULL,
  `tipo_gasto` varchar(191) DEFAULT NULL,
  `tipo_costo_gasto` varchar(191) DEFAULT NULL,
  `email_message_id` varchar(191) DEFAULT NULL COMMENT 'Prevent reprocessing same email',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dte_documents_empresa_uuid_unique` (`id_empresa`,`dte_uuid`),
  KEY `dte_documents_user_email_account_id_foreign` (`user_email_account_id`),
  KEY `dte_documents_email_message_id_index` (`email_message_id`),
  CONSTRAINT `dte_documents_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dte_documents_user_email_account_id_foreign` FOREIGN KEY (`user_email_account_id`) REFERENCES `user_email_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dte_tipo_mapeo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dte_tipo_mapeo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cod_pais` char(2) NOT NULL DEFAULT 'SV',
  `codigo_mh` varchar(5) NOT NULL COMMENT '01, 03, 04, 05, 06, 11, etc.',
  `nombre_tipo` varchar(191) NOT NULL COMMENT 'Nombre descriptivo del tipo DTE',
  `tipo_documento` varchar(191) NOT NULL COMMENT 'Factura, Crédito fiscal, Nota de crédito, etc.',
  `destino` enum('compra','gasto') NOT NULL COMMENT 'Where to insert: compras or gastos table',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dte_tipo_mapeo_pais_codigo_unique` (`cod_pais`,`codigo_mh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `egresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `egresos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_generacion` varchar(255) DEFAULT NULL,
  `es_retaceo` tinyint(1) NOT NULL DEFAULT 0,
  `sello_mh` varchar(255) DEFAULT NULL,
  `numero_control` varchar(255) DEFAULT NULL,
  `tipo_dte` varchar(255) DEFAULT NULL,
  `concepto` varchar(1250) DEFAULT NULL,
  `num_identificacion` varchar(80) DEFAULT NULL,
  `forma_pago` varchar(100) DEFAULT NULL,
  `fecha` date NOT NULL,
  `iva` decimal(12,2) DEFAULT 0.00,
  `renta_retenida` decimal(10,2) DEFAULT 0.00,
  `iva_percibido` decimal(9,2) DEFAULT NULL,
  `otros_impuestos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '(DC2Type:json)' CHECK (json_valid(`otros_impuestos`)),
  `sub_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `otros_cargos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `proveedor` varchar(250) DEFAULT NULL,
  `estado` varchar(150) DEFAULT NULL,
  `tipo` varchar(250) DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `tipo_gasto` varchar(255) DEFAULT NULL,
  `sector` varchar(255) DEFAULT NULL,
  `clasificacion` varchar(255) DEFAULT NULL,
  `tipo_operacion` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `tipo_documento` varchar(255) DEFAULT NULL,
  `detalle_banco` varchar(255) DEFAULT NULL,
  `referencia_pago` varchar(255) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `area_empresa` text DEFAULT NULL,
  `recurrente` tinyint(1) DEFAULT 0,
  `id_empresa` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `dte` longtext DEFAULT NULL,
  `dte_invalidacion` longtext DEFAULT NULL,
  `prueba_masiva` tinyint(1) NOT NULL DEFAULT 0,
  `id_sucursal` int(11) NOT NULL,
  `id_activo` int(10) unsigned DEFAULT NULL,
  `pendiente_capitalizacion` tinyint(1) NOT NULL DEFAULT 0,
  `id_proveedor` int(11) NOT NULL,
  `id_proyecto` int(11) DEFAULT NULL,
  `id_area_empresa` bigint(20) unsigned DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL,
  `tipo_clasificacion` varchar(255) DEFAULT NULL,
  `tipo_sector` varchar(255) DEFAULT NULL,
  `tipo_costo_gasto` varchar(255) DEFAULT NULL,
  `iva_retenido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency_code` char(3) DEFAULT NULL,
  `exchange_rate` decimal(18,5) NOT NULL DEFAULT 1.00000,
  `exchange_rate_date` date DEFAULT NULL,
  `equivalent_total` decimal(18,5) DEFAULT NULL,
  `equivalent_iva` decimal(18,5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `egresos_id_area_empresa_index` (`id_area_empresa`),
  KEY `idx_egresos_empresa_sucursal_fecha` (`id_empresa`,`id_sucursal`,`fecha`,`estado`),
  KEY `idx_egresos_empresa_fecha` (`id_empresa`,`fecha`,`estado`),
  KEY `idx_egresos_empresa_sucursal_created` (`id_empresa`,`id_sucursal`,`created_at`),
  KEY `idx_egresos_empresa_num_identificacion` (`id_empresa`,`num_identificacion`),
  CONSTRAINT `egresos_id_area_empresa_foreign` FOREIGN KEY (`id_area_empresa`) REFERENCES `areas_empresa` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ejercicios_fiscales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ejercicios_fiscales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `anio_referencia` smallint(5) unsigned NOT NULL,
  `estado` varchar(32) NOT NULL DEFAULT 'abierto',
  `id_partida_cierre` bigint(20) unsigned DEFAULT NULL,
  `id_partida_reversa` bigint(20) unsigned DEFAULT NULL COMMENT 'Si reapertura por reversa',
  `id_usuario_cierre` bigint(20) unsigned DEFAULT NULL,
  `cerrado_en` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ejercicios_fiscales_id_empresa_anio_referencia_unique` (`id_empresa`,`anio_referencia`),
  CONSTRAINT `ejercicios_fiscales_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_inboxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_inboxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `user_email_account_id` bigint(20) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `email` varchar(191) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'ACTIVE',
  `purpose` varchar(32) NOT NULL DEFAULT 'dte',
  `last_email_at` timestamp NULL DEFAULT NULL,
  `last_dte_at` timestamp NULL DEFAULT NULL,
  `last_error_at` timestamp NULL DEFAULT NULL,
  `last_error_message` varchar(500) DEFAULT NULL,
  `emails_received` int(10) unsigned NOT NULL DEFAULT 0,
  `emails_rejected` int(10) unsigned NOT NULL DEFAULT 0,
  `dtes_imported` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `verification_code` varchar(32) DEFAULT NULL,
  `verification_link` varchar(500) DEFAULT NULL,
  `verification_received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_inboxes_token_unique` (`token`),
  UNIQUE KEY `email_inboxes_email_unique` (`email`),
  KEY `email_inboxes_id_empresa_status_index` (`id_empresa`,`status`),
  KEY `email_inboxes_user_email_account_id_foreign` (`user_email_account_id`),
  CONSTRAINT `email_inboxes_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_inboxes_user_email_account_id_foreign` FOREIGN KEY (`user_email_account_id`) REFERENCES `user_email_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empleados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `dui` varchar(20) NOT NULL,
  `id_type` tinyint(3) unsigned DEFAULT NULL,
  `dui_homologado` varchar(191) DEFAULT NULL,
  `nit` varchar(191) DEFAULT NULL,
  `isss` varchar(20) DEFAULT NULL,
  `afp` varchar(20) DEFAULT NULL,
  `configuracion_descuentos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuracion_descuentos`)),
  `es_pensionado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_nacimiento` date NOT NULL,
  `direccion` varchar(191) DEFAULT NULL,
  `telefono` varchar(191) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `banco` varchar(191) DEFAULT NULL,
  `tipo_cuenta` varchar(191) DEFAULT NULL,
  `numero_cuenta` varchar(191) DEFAULT NULL,
  `titular_cuenta` varchar(191) DEFAULT NULL,
  `forma_pago` varchar(191) DEFAULT NULL,
  `salario_base` decimal(10,2) NOT NULL,
  `tipo_salario` tinyint(3) unsigned DEFAULT 1,
  `tiene_conyuge_dependiente` tinyint(1) NOT NULL DEFAULT 0,
  `cantidad_hijos_dependientes` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `tipo_contrato` int(11) NOT NULL,
  `tipo_jornada` int(11) NOT NULL,
  `horas_jornada` decimal(5,2) DEFAULT NULL,
  `categoria_ocupacional` varchar(50) DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `fecha_baja` date DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `id_departamento` bigint(20) unsigned NOT NULL,
  `id_cargo` bigint(20) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `institucion_previsional` varchar(3) DEFAULT NULL,
  `apellido_casada` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empleados_dui_id_empresa_unique` (`dui`,`id_empresa`),
  KEY `empleados_id_departamento_foreign` (`id_departamento`),
  KEY `empleados_id_cargo_foreign` (`id_cargo`),
  CONSTRAINT `empleados_id_cargo_foreign` FOREIGN KEY (`id_cargo`) REFERENCES `cargos_de_empresa` (`id`),
  CONSTRAINT `empleados_id_departamento_foreign` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos_empresa` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_activos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_activos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `referencia` varchar(191) DEFAULT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_retiro` date DEFAULT NULL,
  `estado` varchar(32) NOT NULL DEFAULT 'En uso',
  `id_categoria` int(10) unsigned NOT NULL,
  `numero_de_serie` varchar(191) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ubicacion` varchar(191) DEFAULT NULL,
  `vida_util` decimal(9,2) DEFAULT NULL,
  `valor_compra` decimal(9,2) NOT NULL,
  `depreciacion_acumulada` decimal(9,2) NOT NULL DEFAULT 0.00,
  `valor_en_libros` decimal(9,2) DEFAULT NULL,
  `valor_residual` decimal(9,2) NOT NULL DEFAULT 0.00,
  `es_usado` tinyint(1) NOT NULL DEFAULT 0,
  `porcentaje_base_usado` decimal(5,2) DEFAULT NULL,
  `fecha_inicio_depreciacion` date DEFAULT NULL,
  `estado_registro` varchar(16) NOT NULL DEFAULT 'activo',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `id_egreso` int(10) unsigned DEFAULT NULL,
  `id_compra_detalle` int(10) unsigned DEFAULT NULL,
  `id_responsable` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_activos_id_empresa_estado_registro_index` (`id_empresa`,`estado_registro`),
  KEY `empresa_activos_id_empresa_id_categoria_index` (`id_empresa`,`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_activos_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_activos_categorias` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `plantilla_id` int(10) unsigned DEFAULT NULL,
  `metodo_depreciacion` varchar(32) NOT NULL DEFAULT 'linea_recta',
  `porcentaje_anual` decimal(5,2) DEFAULT NULL,
  `vida_util_anios` decimal(5,2) DEFAULT NULL,
  `valor_residual_default` decimal(9,2) NOT NULL DEFAULT 0.00,
  `permite_bien_usado` tinyint(1) NOT NULL DEFAULT 0,
  `reglas_bien_usado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reglas_bien_usado`)),
  `metadata_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_schema`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_activos_categorias_id_empresa_nombre_index` (`id_empresa`,`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_activos_configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_activos_configuracion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `frecuencia` varchar(16) NOT NULL DEFAULT 'mensual',
  `dia_corte` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `redondeo_decimales` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_activos_configuracion_id_empresa_unique` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_activos_depreciaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_activos_depreciaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_activo` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `periodo` char(7) NOT NULL,
  `monto` decimal(9,2) NOT NULL,
  `depreciacion_acumulada` decimal(9,2) NOT NULL,
  `valor_en_libros` decimal(9,2) NOT NULL,
  `estado` varchar(16) NOT NULL DEFAULT 'pendiente',
  `id_egreso` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_activos_depreciaciones_id_activo_periodo_unique` (`id_activo`,`periodo`),
  KEY `empresa_activos_depreciaciones_id_empresa_periodo_estado_index` (`id_empresa`,`periodo`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_activos_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_activos_movimientos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_activo` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo` varchar(32) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` text DEFAULT NULL,
  `monto` decimal(9,2) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_activos_movimientos_id_activo_fecha_index` (`id_activo`,`fecha`),
  KEY `empresa_activos_movimientos_id_empresa_tipo_fecha_index` (`id_empresa`,`tipo`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_cargo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_cargo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cargo_id` bigint(20) unsigned NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_cargo_empresa_id_foreign` (`empresa_id`),
  KEY `empresa_cargo_cargo_id_foreign` (`cargo_id`),
  CONSTRAINT `empresa_cargo_cargo_id_foreign` FOREIGN KEY (`cargo_id`) REFERENCES `cargos_de_empresa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `empresa_cargo_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_configuracion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `pais` varchar(3) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`configuracion`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_configuracion_empresa_id_pais_modulo_unique` (`empresa_id`,`pais`,`modulo`),
  KEY `empresa_configuracion_empresa_id_modulo_index` (`empresa_id`,`modulo`),
  CONSTRAINT `empresa_configuracion_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_configuracion_planilla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_configuracion_planilla` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `cod_pais` varchar(3) NOT NULL DEFAULT 'SV',
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`configuracion`)),
  `fecha_vigencia_desde` date DEFAULT NULL,
  `fecha_vigencia_hasta` date DEFAULT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_configuracion_planilla_empresa_id_index` (`empresa_id`),
  KEY `empresa_configuracion_planilla_cod_pais_index` (`cod_pais`),
  KEY `empresa_configuracion_planilla_empresa_id_activo_index` (`empresa_id`,`activo`),
  CONSTRAINT `empresa_configuracion_planilla_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_departamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_departamento` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `departamento_id` bigint(20) unsigned NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_departamento_empresa_id_foreign` (`empresa_id`),
  CONSTRAINT `empresa_departamento_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa_funcionalidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa_funcionalidades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_funcionalidad` bigint(20) unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuracion`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_funcionalidad_idx` (`id_empresa`,`id_funcionalidad`),
  KEY `empresa_funcionalidades_id_funcionalidad_foreign` (`id_funcionalidad`),
  CONSTRAINT `empresa_funcionalidades_id_funcionalidad_foreign` FOREIGN KEY (`id_funcionalidad`) REFERENCES `funcionalidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `giro` text DEFAULT NULL,
  `cod_actividad_economica` varchar(15) DEFAULT NULL,
  `tipo_establecimiento` varchar(255) DEFAULT NULL,
  `mh_pwd_certificado` varchar(255) DEFAULT NULL,
  `mh_usuario` varchar(255) DEFAULT NULL,
  `mh_contrasena` varchar(255) DEFAULT NULL,
  `cod_estable_mh` varchar(255) DEFAULT NULL,
  `cod_estable` varchar(255) DEFAULT NULL,
  `industria` varchar(255) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `cod_municipio` varchar(10) DEFAULT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `cod_departamento` varchar(10) DEFAULT NULL,
  `distrito` varchar(255) DEFAULT NULL,
  `cod_distrito` varchar(100) DEFAULT NULL,
  `pais` varchar(255) DEFAULT NULL,
  `cod_pais` varchar(3) DEFAULT NULL,
  `direccion` varchar(750) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `nombre_propietario` varchar(255) DEFAULT NULL,
  `ncr` varchar(200) DEFAULT NULL,
  `nit` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cobra_iva` varchar(25) DEFAULT NULL,
  `iva` decimal(10,2) NOT NULL,
  `moneda` varchar(100) NOT NULL,
  `cardex` varchar(100) DEFAULT NULL,
  `propina` varchar(100) DEFAULT NULL,
  `propina_porcentaje` decimal(5,2) DEFAULT 0.00,
  `plan` varchar(150) DEFAULT NULL,
  `tipo_plan` varchar(255) DEFAULT NULL,
  `fecha_cancelacion` date DEFAULT NULL,
  `metodo_pago` varchar(191) DEFAULT NULL,
  `pago_recurrente` tinyint(1) NOT NULL DEFAULT 0,
  `referido` varchar(255) DEFAULT NULL,
  `campania` varchar(255) DEFAULT NULL,
  `aliado` varchar(191) DEFAULT NULL,
  `codigo_promocional` varchar(50) DEFAULT NULL,
  `tipo_contribuyente` varchar(255) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frecuencia_pago` varchar(191) DEFAULT NULL,
  `monto_mensual` decimal(10,2) DEFAULT NULL,
  `monto_anual` decimal(10,2) DEFAULT NULL,
  `forma_pago` varchar(255) DEFAULT NULL,
  `link_pago` varchar(255) DEFAULT NULL,
  `fecha_ultimo_pago` date DEFAULT NULL,
  `user_limit` int(11) DEFAULT NULL,
  `sucursal_limit` int(11) DEFAULT NULL,
  `logo` varchar(255) DEFAULT 'empresas/default.jpg',
  `sello` varchar(191) DEFAULT NULL,
  `firma` varchar(191) DEFAULT NULL,
  `mostrar_sello_firma` tinyint(1) NOT NULL DEFAULT 0,
  `tipo_renta_servicios` varchar(255) DEFAULT NULL,
  `tipo_renta_productos` varchar(255) DEFAULT NULL,
  `tipo_sector` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `alerta_suscripcion` tinyint(1) NOT NULL DEFAULT 1,
  `modulo_citas` tinyint(1) DEFAULT 1,
  `modulo_proyectos` tinyint(1) DEFAULT 0,
  `restringir_compras_supervisor_limitado` tinyint(1) NOT NULL DEFAULT 0,
  `restringir_gastos_supervisor_limitado` tinyint(1) NOT NULL DEFAULT 0,
  `custom_empresa` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_empresa`)),
  `modulo_paquetes` tinyint(1) NOT NULL DEFAULT 0,
  `vender_sin_stock` tinyint(1) DEFAULT 1,
  `valor_inventario` varchar(255) NOT NULL DEFAULT 'ultimo',
  `editar_precio_venta` tinyint(1) DEFAULT 1,
  `agrupar_detalles_venta` tinyint(1) DEFAULT 0,
  `vendedor_inventario` tinyint(1) DEFAULT 0,
  `venta_consigna` tinyint(1) DEFAULT 1,
  `validador_dui` varchar(255) DEFAULT NULL,
  `validador_nit` varchar(255) DEFAULT NULL,
  `validador_nrc` varchar(255) DEFAULT NULL,
  `validador_telefono` varchar(255) DEFAULT NULL,
  `vendedor_detalle_venta` tinyint(1) DEFAULT 0,
  `vendedor_asignado` bigint(20) DEFAULT NULL,
  `facturacion_electronica` tinyint(1) DEFAULT 0,
  `fe_ambiente` varchar(10) DEFAULT '00',
  `enviar_dte` tinyint(1) DEFAULT 0,
  `cotizacion_compras_terminos` text DEFAULT NULL,
  `editar_descripcion_venta` tinyint(1) DEFAULT 0,
  `impresion_en_facturacion` tinyint(1) DEFAULT 0,
  `numero_lineas_impresion` int(11) DEFAULT 6,
  `wompi_id` varchar(255) DEFAULT NULL,
  `wompi_identificador` varchar(255) DEFAULT NULL,
  `wompi_aplicativo` varchar(255) DEFAULT NULL,
  `wompi_secret` varchar(255) DEFAULT NULL,
  `id_cliente` bigint(20) DEFAULT NULL,
  `id_documento` bigint(20) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `woocommerce_api_key` varchar(100) DEFAULT NULL,
  `woocommerce_sync_progress` int(11) DEFAULT 0,
  `woocommerce_sync_total_batches` int(11) DEFAULT 0,
  `woocommerce_sync_processed_batches` int(11) DEFAULT 0,
  `woocommerce_store_url` varchar(100) DEFAULT NULL,
  `woocommerce_consumer_key` varchar(100) DEFAULT NULL,
  `woocommerce_consumer_secret` varchar(100) DEFAULT NULL,
  `woocommerce_sync_status` varchar(255) DEFAULT NULL,
  `woocommerce_last_sync` timestamp NULL DEFAULT NULL,
  `woocommerce_error` text DEFAULT NULL,
  `woocommerce_status` varchar(100) DEFAULT NULL,
  `shopify_store_url` varchar(191) DEFAULT NULL,
  `shopify_client_id` varchar(191) DEFAULT NULL,
  `shopify_client_secret` varchar(191) DEFAULT NULL,
  `shopify_consumer_secret` varchar(191) DEFAULT NULL,
  `shopify_access_token` text DEFAULT NULL,
  `shopify_token_expires_at` timestamp NULL DEFAULT NULL,
  `shopify_webhook_secret` varchar(191) DEFAULT NULL,
  `shopify_status` enum('connected','disconnected','connecting') DEFAULT 'disconnected',
  `importacion_productos_shopify` tinyint(1) NOT NULL DEFAULT 0,
  `shopify_canal_id` bigint(20) unsigned DEFAULT NULL,
  `shopify_sync_progress` int(11) DEFAULT 0,
  `shopify_sync_total_batches` int(11) DEFAULT 0,
  `shopify_sync_bidirectional` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Controla si los cambios se sincronizan bidireccionalmente con Shopify',
  `shopify_sync_ventas` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Controla si las ventas de SmartPyme se sincronizan como órdenes en Shopify',
  `shopify_sync_processed_batches` int(11) DEFAULT 0,
  `shopify_sync_status` enum('idle','syncing','completed','error') DEFAULT 'idle',
  `shopify_last_sync` timestamp NULL DEFAULT NULL,
  `shopify_error` text DEFAULT NULL,
  `woocommerce_canal_id` int(11) DEFAULT NULL,
  `woocommerce_sync_mode` varchar(32) NOT NULL DEFAULT 'bidirectional',
  `mostrar_sello_firma_cotizacion` tinyint(1) NOT NULL DEFAULT 0,
  `cambiar_tipo_impuesto_venta` tinyint(1) DEFAULT 0,
  `webhook_paquete_venta_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `webhook_paquete_venta_url` varchar(2048) DEFAULT NULL,
  `webhook_paquete_venta_secret` varchar(255) DEFAULT NULL,
  `webhook_paquete_venta_bearer_token` text DEFAULT NULL,
  `monto_minimo_retencion_iva_gc` int(11) DEFAULT NULL,
  `mes_inicio_ejercicio_fiscal` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `id_cuenta_cierre_resultados` bigint(20) unsigned DEFAULT NULL,
  `numero_patronal_isss` varchar(9) DEFAULT NULL,
  `tipo_persona` varchar(10) DEFAULT NULL,
  `regimen_especial_isss` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_woocommerce_api_key` (`woocommerce_api_key`),
  UNIQUE KEY `empresas_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estados_paises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estados_paises` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `codigo` varchar(10) DEFAULT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `pais_id` int(11) NOT NULL,
  `type` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `estados_paises_pais_id_foreign` (`pais_id`),
  CONSTRAINT `estados_paises_pais_id_foreign` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eventos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(750) NOT NULL,
  `tipo` varchar(150) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime DEFAULT NULL,
  `duracion` varchar(150) NOT NULL,
  `estado` varchar(150) NOT NULL,
  `detalles` varchar(750) DEFAULT NULL,
  `frecuencia` varchar(255) DEFAULT NULL,
  `frecuencia_fin` date DEFAULT NULL,
  `id_servicio` int(11) DEFAULT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `formas_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `id_banco` int(11) DEFAULT NULL,
  `orden` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `funcionalidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `funcionalidades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `funcionalidades_parent_id_foreign` (`parent_id`),
  CONSTRAINT `funcionalidades_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `funcionalidades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gastos_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gastos_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `id_cuenta_contable` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gift_card_redenciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_card_redenciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_gift_card` bigint(20) unsigned NOT NULL,
  `id_venta` bigint(20) unsigned NOT NULL,
  `id_vendedor` bigint(20) unsigned DEFAULT NULL,
  `monto` decimal(14,4) NOT NULL,
  `saldo_resultante` decimal(14,4) NOT NULL,
  `id_categoria` bigint(20) unsigned DEFAULT NULL,
  `id_subcategoria` bigint(20) unsigned DEFAULT NULL,
  `id_comision_movimiento` bigint(20) unsigned DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gift_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `codigo` varchar(64) NOT NULL,
  `monto_inicial` decimal(14,4) NOT NULL,
  `saldo` decimal(14,4) NOT NULL,
  `fecha_emision` timestamp NOT NULL,
  `fecha_vencimiento` timestamp NULL DEFAULT NULL,
  `id_vendedor_emisor` bigint(20) unsigned DEFAULT NULL,
  `id_venta_emision` bigint(20) unsigned NOT NULL,
  `id_detalle_venta_emision` bigint(20) unsigned DEFAULT NULL,
  `id_producto` bigint(20) unsigned DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gift_cards_id_empresa_codigo_unique` (`id_empresa`,`codigo`),
  UNIQUE KEY `gift_card_unique_detalle_emision` (`id_empresa`,`id_detalle_venta_emision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `historial_bajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_bajas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `fecha_baja` date NOT NULL,
  `tipo_baja` int(11) NOT NULL,
  `motivo` text NOT NULL,
  `documento_respaldo` int(11) DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `historial_bajas_id_empleado_index` (`id_empleado`),
  KEY `historial_bajas_tipo_baja_index` (`tipo_baja`),
  KEY `historial_bajas_documento_respaldo_index` (`documento_respaldo`),
  KEY `historial_bajas_estado_index` (`estado`),
  CONSTRAINT `historial_bajas_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `historial_contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_contratos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `tipo_contrato` int(11) NOT NULL,
  `salario` decimal(10,2) NOT NULL,
  `id_cargo` bigint(20) unsigned NOT NULL,
  `motivo_cambio` varchar(191) DEFAULT NULL,
  `documento_respaldo` int(11) DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `historial_contratos_id_cargo_foreign` (`id_cargo`),
  KEY `historial_contratos_id_empleado_fecha_inicio_index` (`id_empleado`,`fecha_inicio`),
  KEY `historial_contratos_tipo_contrato_index` (`tipo_contrato`),
  KEY `historial_contratos_documento_respaldo_index` (`documento_respaldo`),
  KEY `historial_contratos_estado_index` (`estado`),
  CONSTRAINT `historial_contratos_id_cargo_foreign` FOREIGN KEY (`id_cargo`) REFERENCES `cargos_de_empresa` (`id`),
  CONSTRAINT `historial_contratos_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `historial_orden_produccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_orden_produccion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` bigint(20) unsigned NOT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) NOT NULL,
  `id_usuario` bigint(20) NOT NULL,
  `comentarios` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `historial_orden_produccion_id_orden_produccion_foreign` (`id_orden_produccion`),
  CONSTRAINT `historial_orden_produccion_id_orden_produccion_foreign` FOREIGN KEY (`id_orden_produccion`) REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_metricas_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_metricas_historial` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_metrica` enum('empresa','sucursal') NOT NULL,
  `id_metrica` int(10) unsigned NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `notas` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tipo_metrica` (`tipo_metrica`,`id_metrica`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_metricas_mensuales_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_metricas_mensuales_empresas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `ventas_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ventas_con_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `egresos_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `egresos_con_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `costo_venta_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `flujo_efectivo_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `flujo_efectivo_con_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_monto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_porcentaje` decimal(6,2) NOT NULL DEFAULT 0.00,
  `cxc_totales` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxc_vencidas` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxc_vencimiento_30_dias` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxp_totales` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxp_vencidas` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxp_vencimiento_30_dias` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ventas_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `egresos_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `flujo_efectivo_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_vs_presupuesto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ia_metricas_mensuales_empresas_id_empresa_fecha_unique` (`id_empresa`,`fecha`),
  KEY `ia_metricas_mensuales_empresas_fecha_index` (`fecha`),
  KEY `ia_metricas_mensuales_empresas_id_empresa_index` (`id_empresa`),
  CONSTRAINT `ia_metricas_mensuales_empresas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ia_metricas_mensuales_sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ia_metricas_mensuales_sucursales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `ventas_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ventas_con_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `egresos_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `egresos_con_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `costo_venta_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `flujo_efectivo_sin_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `flujo_efectivo_con_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_monto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_porcentaje` decimal(6,2) NOT NULL DEFAULT 0.00,
  `cxc_totales` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxc_vencidas` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxc_vencimiento_30_dias` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxp_totales` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxp_vencidas` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cxp_vencimiento_30_dias` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ventas_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `egresos_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `flujo_efectivo_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rentabilidad_vs_mes_anterior` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ventas_vs_presupuesto` double(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_empresa_sucursal_fecha` (`id_empresa`,`id_sucursal`,`fecha`),
  KEY `ia_metricas_mensuales_sucursales_fecha_index` (`fecha`),
  KEY `idx_empresa_sucursal` (`id_empresa`,`id_sucursal`),
  KEY `ia_metricas_mensuales_sucursales_id_sucursal_index` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `impuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) NOT NULL,
  `porcentaje` decimal(10,2) NOT NULL,
  `codigo_mh` varchar(5) DEFAULT NULL,
  `id_cuenta_contable_ventas` int(11) DEFAULT NULL,
  `id_cuenta_contable_compras` int(11) DEFAULT NULL,
  `aplica_ventas` tinyint(1) NOT NULL DEFAULT 1,
  `aplica_gastos` tinyint(1) NOT NULL DEFAULT 1,
  `aplica_compras` tinyint(1) NOT NULL DEFAULT 1,
  `id_empresa` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `incoterms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `incoterms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(100) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `input_rfm`;
/*!50001 DROP VIEW IF EXISTS `input_rfm`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `input_rfm` AS SELECT
 NULL AS `id_empresa`,
 NULL AS `id_cliente`,
 NULL AS `fecha`,
 NULL AS `sub_total` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `integraciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `integraciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `proveedor` varchar(50) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'disconnected',
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuracion`)),
  `credenciales` text DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `integraciones_id_empresa_proveedor_unique` (`id_empresa`,`proveedor`),
  CONSTRAINT `integraciones_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_bodega` int(11) DEFAULT NULL,
  `stock` decimal(10,2) NOT NULL,
  `stock_minimo` int(11) DEFAULT NULL,
  `stock_maximo` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventarios_producto_bodega` (`id_producto`,`id_bodega`),
  KEY `idx_inventario_updated_deleted` (`updated_at`,`deleted_at`),
  KEY `idx_inventario_bodega` (`id_bodega`),
  KEY `idx_inventario_optimized` (`id_bodega`,`stock`,`updated_at`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_entrada_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_entrada_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_entrada` bigint(20) DEFAULT NULL,
  `id_producto` bigint(20) DEFAULT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Cantidad ingresada',
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo unitario del producto',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total del detalle (cantidad * costo)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`),
  KEY `inventario_entrada_detalles_id_presentacion_foreign` (`id_presentacion`),
  CONSTRAINT `inventario_entrada_detalles_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles de productos en entradas de inventario';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_entradas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_entradas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha de la entrada',
  `id_bodega` bigint(20) DEFAULT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `concepto` varchar(255) NOT NULL COMMENT 'Concepto o motivo de la entrada',
  `tipo` varchar(255) NOT NULL DEFAULT 'Otro' COMMENT 'Tipo de entrada',
  `estado` varchar(255) NOT NULL DEFAULT 'Pendiente' COMMENT 'Estado de la entrada',
  `id_usuario` bigint(20) DEFAULT NULL,
  `corte_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID del corte de caja (opcional)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal de entradas de inventario';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_salida_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salida_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_salida` bigint(20) DEFAULT NULL,
  `id_producto` bigint(20) DEFAULT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Cantidad salida',
  `costo` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo unitario del producto',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total del detalle (cantidad * costo)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`),
  KEY `inventario_salida_detalles_id_presentacion_foreign` (`id_presentacion`),
  CONSTRAINT `inventario_salida_detalles_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalles de productos en salidas de inventario';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_salidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salidas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL COMMENT 'Fecha de la salida',
  `id_bodega` bigint(20) DEFAULT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `concepto` varchar(255) NOT NULL COMMENT 'Concepto o motivo de la salida',
  `tipo` varchar(255) NOT NULL DEFAULT 'Otro' COMMENT 'Tipo de salida',
  `estado` varchar(255) NOT NULL DEFAULT 'Pendiente' COMMENT 'Estado de la salida',
  `id_usuario` bigint(20) DEFAULT NULL,
  `corte_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID del corte de caja (opcional)',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal de salidas de inventario';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kardex_masivo_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kardex_masivo_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `file_path` varchar(191) DEFAULT NULL,
  `file_name` varchar(191) DEFAULT NULL,
  `total_records` int(11) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kardex_masivo_queue_status_created_at_index` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kardexs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kardexs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `id_producto` int(11) NOT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `id_inventario` int(11) NOT NULL,
  `detalle` varchar(255) NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `costo_unitario` decimal(16,6) DEFAULT NULL,
  `entrada_cantidad` decimal(16,6) DEFAULT NULL,
  `entrada_valor` decimal(10,2) DEFAULT NULL,
  `salida_cantidad` decimal(16,6) DEFAULT NULL,
  `salida_valor` decimal(10,2) DEFAULT NULL,
  `total_cantidad` decimal(16,6) NOT NULL,
  `total_valor` decimal(10,2) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `precio_unitario` decimal(16,6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kardexs_producto_inventario_fecha` (`id_producto`,`id_inventario`,`fecha` DESC,`id` DESC),
  KEY `idx_kardex_inventario` (`id_inventario`),
  KEY `idx_kardex_usuario` (`id_usuario`),
  KEY `kardexs_lote_id_index` (`lote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `licencia_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `licencia_empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_licencia` int(11) NOT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `licencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `licencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `num_licencias` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_producto` int(10) unsigned NOT NULL,
  `id_bodega` int(10) unsigned NOT NULL,
  `numero_lote` varchar(255) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_fabricacion` date DEFAULT NULL,
  `stock` decimal(9,2) NOT NULL DEFAULT 0.00,
  `stock_inicial` decimal(9,2) NOT NULL DEFAULT 0.00,
  `id_empresa` int(10) unsigned NOT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lotes_producto` (`id_producto`,`deleted_at`,`fecha_vencimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `materia_prima`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `materia_prima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `costo` double DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `medida` varchar(150) DEFAULT NULL,
  `codigo` varchar(150) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `updated_at` date DEFAULT NULL,
  `created_at` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_spanish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender` enum('user','bot') NOT NULL,
  `content` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_conversation_id_foreign` (`conversation_id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `metodos_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `metodos_pago` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `id_tarjeta` varchar(191) NOT NULL,
  `marca_tarjeta` varchar(50) DEFAULT NULL,
  `ultimos_cuatro` varchar(4) DEFAULT NULL,
  `titular_tarjeta` varchar(191) DEFAULT NULL,
  `nombre_emisor` varchar(100) DEFAULT NULL,
  `codigo_pais` varchar(3) DEFAULT NULL,
  `codigo_estado` varchar(50) DEFAULT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  `es_predeterminado` tinyint(1) NOT NULL DEFAULT 0,
  `esta_activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `metodos_pago_id_usuario_foreign` (`id_usuario`),
  CONSTRAINT `metodos_pago_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_calendario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_calendario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `clave` varchar(120) DEFAULT NULL,
  `nombre` varchar(200) NOT NULL,
  `detalle` varchar(200) DEFAULT NULL,
  `monto` decimal(15,2) NOT NULL,
  `frecuencia` varchar(20) NOT NULL,
  `dia` varchar(32) NOT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `modelado_calendario_id_empresa_clave_index` (`id_empresa`,`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_clasificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_clasificaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `clave` varchar(120) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `rubro` varchar(40) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `pct_ventas` decimal(8,4) DEFAULT NULL,
  `dia_pago` varchar(32) DEFAULT NULL,
  `proveedor` varchar(191) DEFAULT NULL,
  `sucursal` varchar(191) DEFAULT NULL,
  `suspendido` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modelado_clasificaciones_id_empresa_clave_unique` (`id_empresa`,`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_estacionalidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_estacionalidad` (
  `id_empresa` int(10) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `mes` tinyint(3) unsigned NOT NULL,
  `peso` decimal(10,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`,`anio`,`mes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_presupuesto_area_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_presupuesto_area_lineas` (
  `id_empresa` int(10) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `id_area_empresa` int(10) unsigned NOT NULL,
  `rubro` varchar(40) NOT NULL,
  `monto_anual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`,`anio`,`id_area_empresa`,`rubro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_presupuesto_departamento_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_presupuesto_departamento_lineas` (
  `id_empresa` int(10) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `id_departamento` int(10) unsigned NOT NULL,
  `rubro` varchar(40) NOT NULL,
  `monto_anual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`,`anio`,`id_departamento`,`rubro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_presupuesto_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_presupuesto_lineas` (
  `id_empresa` int(10) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `rubro` varchar(40) NOT NULL,
  `monto_anual` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`,`anio`,`rubro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_presupuesto_repartos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_presupuesto_repartos` (
  `id_empresa` int(10) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `reparto` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`,`anio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_presupuesto_sucursal_lineas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_presupuesto_sucursal_lineas` (
  `id_empresa` int(10) unsigned NOT NULL,
  `anio` smallint(5) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `rubro` varchar(40) NOT NULL,
  `monto_anual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`,`anio`,`id_sucursal`,`rubro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modelado_pronosticos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modelado_pronosticos` (
  `id_empresa` int(10) unsigned NOT NULL,
  `efectivo` decimal(15,2) DEFAULT NULL,
  `efectivo_fecha` date DEFAULT NULL,
  `colchon` decimal(15,2) DEFAULT NULL,
  `costo_capital_pct` decimal(8,4) DEFAULT NULL,
  `escenario_conservador_pct` decimal(8,2) NOT NULL DEFAULT -10.00,
  `escenario_optimista_pct` decimal(8,2) NOT NULL DEFAULT 12.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint(20) unsigned DEFAULT NULL,
  `submodule_id` bigint(20) unsigned DEFAULT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `permission_type` enum('base','custom') NOT NULL DEFAULT 'base',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_permissions_module_id_foreign` (`module_id`),
  KEY `module_permissions_submodule_id_foreign` (`submodule_id`),
  KEY `module_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `module_permissions_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_permissions_submodule_id_foreign` FOREIGN KEY (`submodule_id`) REFERENCES `submodules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `display_name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `municipios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `municipios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `cod_departamento` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nif_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nif_categorias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `afecta_resultados` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `norma` varchar(30) NOT NULL DEFAULT 'NIIF_PYMES',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nif_categorias_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nif_mapeo_gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nif_mapeo_gastos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL DEFAULT 0,
  `origen` varchar(10) NOT NULL,
  `clave` varchar(120) NOT NULL,
  `id_nif_categoria` bigint(20) unsigned NOT NULL,
  `revisar` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nif_mapeo_gastos_unico` (`id_empresa`,`origen`,`clave`),
  KEY `nif_mapeo_gastos_id_nif_categoria_foreign` (`id_nif_categoria`),
  CONSTRAINT `nif_mapeo_gastos_id_nif_categoria_foreign` FOREIGN KEY (`id_nif_categoria`) REFERENCES `nif_categorias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notas_estados_financieros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_estados_financieros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `periodo_actual` varchar(32) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_aprobacion_junta` date DEFAULT NULL,
  `incluir_comparativo` tinyint(1) NOT NULL DEFAULT 0,
  `periodo_anterior` varchar(32) DEFAULT NULL,
  `nivel_detalle` enum('completo','resumido') NOT NULL DEFAULT 'completo',
  `notas_a_incluir` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`notas_a_incluir`)),
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuracion`)),
  `contenido_manual` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contenido_manual`)),
  `notas_generadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notas_generadas`)),
  `completitud` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`completitud`)),
  `validaciones_cruzadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validaciones_cruzadas`)),
  `estado` enum('borrador','emitido') NOT NULL DEFAULT 'borrador',
  `id_usuario_creacion` bigint(20) unsigned DEFAULT NULL,
  `id_usuario_emision` bigint(20) unsigned DEFAULT NULL,
  `fecha_emision` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(5000) DEFAULT NULL,
  `tipo` varchar(200) NOT NULL,
  `titulo` varchar(5000) DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `prioridad` varchar(200) NOT NULL,
  `leido` tinyint(1) NOT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `id_egreso` int(11) DEFAULT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `id_dashboard` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `id_orden_produccion` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_empresa_leido` (`id_empresa`,`leido`),
  KEY `idx_notificaciones_empresa_tipo` (`id_empresa`,`tipo`),
  KEY `idx_notificaciones_empresa_categoria` (`id_empresa`,`categoria`),
  KEY `idx_notificaciones_empresa_referencia` (`id_empresa`,`referencia`),
  KEY `idx_notificaciones_empresa_created` (`id_empresa`,`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orden_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orden_compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_bodega` int(11) NOT NULL,
  `tipo_documento` varchar(255) DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `id_authorization` bigint(20) unsigned DEFAULT NULL,
  `id_proyecto` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `estado` varchar(255) NOT NULL,
  `cobrar_impuestos` tinyint(1) DEFAULT 0,
  `cobrar_percepcion` tinyint(1) DEFAULT 0,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_compras_id_authorization_index` (`id_authorization`),
  CONSTRAINT `orden_compras_id_authorization_foreign` FOREIGN KEY (`id_authorization`) REFERENCES `authorizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orden_detalle_restaurante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orden_detalle_restaurante` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sesion_id` bigint(20) unsigned NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `precio_unitario` decimal(12,2) NOT NULL,
  `notas` text DEFAULT NULL COMMENT 'Nota del cliente ej: sin cebolla, término medio',
  `enviado_cocina` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_barra` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_detalle_restaurante_producto_id_foreign` (`producto_id`),
  KEY `orden_detalle_restaurante_sesion_id_index` (`sesion_id`),
  KEY `orden_detalle_rest_sesion_prod_enviado_index` (`sesion_id`,`producto_id`,`enviado_cocina`,`enviado_barra`),
  KEY `orden_detalle_restaurante_id_presentacion_foreign` (`id_presentacion`),
  CONSTRAINT `orden_detalle_restaurante_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orden_detalle_restaurante_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `orden_detalle_restaurante_sesion_id_foreign` FOREIGN KEY (`sesion_id`) REFERENCES `restaurante_sesiones_mesa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orden_produccion_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orden_produccion_documentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` int(11) NOT NULL COMMENT 'Orden de produccion a la que pertenece',
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `tamano` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orden_produccion` (`id_orden_produccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ordenes_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes_pago` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_orden` varchar(50) NOT NULL,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `id_orden_n1co` varchar(191) DEFAULT NULL,
  `id_autorizacion_3ds` varchar(191) DEFAULT NULL,
  `autorizacion_url` varchar(191) DEFAULT NULL,
  `id_plan` bigint(20) unsigned NOT NULL,
  `payment_id` varchar(191) DEFAULT NULL,
  `charge_id` varchar(191) DEFAULT NULL,
  `item_id` varchar(191) DEFAULT NULL,
  `nombre_cliente` varchar(191) DEFAULT NULL,
  `email_cliente` varchar(191) DEFAULT NULL,
  `telefono_cliente` varchar(191) DEFAULT NULL,
  `plan` varchar(191) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` varchar(191) NOT NULL DEFAULT 'pendiente',
  `divisa` varchar(191) NOT NULL DEFAULT 'USD',
  `tipo_pago` varchar(191) DEFAULT NULL,
  `codigo_autorizacion` varchar(191) DEFAULT NULL,
  `fecha_transaccion` datetime DEFAULT NULL,
  `id_venta` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ordenes_produccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes_produccion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `fecha` date NOT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `estado` varchar(191) DEFAULT 'pendiente',
  `id_cotizacion_venta` bigint(20) DEFAULT NULL,
  `id_cliente` bigint(20) NOT NULL,
  `id_usuario` bigint(20) NOT NULL,
  `id_asesor` bigint(20) NOT NULL,
  `id_bodega` bigint(20) NOT NULL,
  `id_empresa` bigint(20) NOT NULL,
  `id_vendedor` bigint(20) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `terminos_condiciones` text DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `total_costo` decimal(10,2) DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `no_sujeta` decimal(10,2) DEFAULT 0.00,
  `excenta` decimal(10,2) DEFAULT 0.00,
  `cuenta_a_terceros` decimal(10,2) DEFAULT 0.00,
  `gravada` decimal(10,2) DEFAULT 0.00,
  `iva` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_codigo_empresa` (`codigo`,`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `output_rfm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `output_rfm` (
  `id_cliente` int(50) NOT NULL,
  `ultima_compra` date DEFAULT NULL,
  `recencia` decimal(15,8) NOT NULL,
  `frecuencia` decimal(15,8) NOT NULL,
  `moneda` decimal(15,8) NOT NULL,
  `recencia_norm` decimal(15,8) NOT NULL,
  `frecuencia_norm` decimal(15,8) NOT NULL,
  `moneda_norm` decimal(15,8) NOT NULL,
  `puntaje_rfm` decimal(15,8) NOT NULL,
  `fecha_de_corrida` date NOT NULL,
  `clasificacion` varchar(100) DEFAULT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`,`fecha_de_corrida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pais_activos_categorias_plantilla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pais_activos_categorias_plantilla` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cod_pais` char(3) NOT NULL,
  `nombre` varchar(191) NOT NULL,
  `metodo_depreciacion` varchar(32) NOT NULL DEFAULT 'linea_recta',
  `porcentaje_anual` decimal(5,2) DEFAULT NULL,
  `vida_util_anios` decimal(5,2) DEFAULT NULL,
  `valor_residual_default` decimal(9,2) NOT NULL DEFAULT 0.00,
  `permite_bien_usado` tinyint(1) NOT NULL DEFAULT 0,
  `reglas_bien_usado` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reglas_bien_usado`)),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pais_activos_categorias_plantilla_cod_pais_nombre_unique` (`cod_pais`,`nombre`),
  KEY `pais_activos_categorias_plantilla_cod_pais_activo_index` (`cod_pais`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pais_configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pais_configuracion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pais` varchar(3) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`configuracion`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pais_configuracion_pais_modulo_unique` (`pais`,`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `paises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paquetes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `paquetes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `wr` varchar(255) DEFAULT NULL,
  `transportista` varchar(255) DEFAULT NULL,
  `consignatario` varchar(255) DEFAULT NULL,
  `transportador` varchar(255) DEFAULT NULL,
  `estado` varchar(255) NOT NULL,
  `embalaje` varchar(255) DEFAULT NULL,
  `num_seguimiento` varchar(255) DEFAULT NULL,
  `num_guia` varchar(255) DEFAULT NULL,
  `piezas` int(11) DEFAULT NULL,
  `peso` decimal(9,2) DEFAULT NULL,
  `precio` decimal(9,2) DEFAULT NULL,
  `volumen` decimal(9,2) DEFAULT NULL,
  `cuenta_a_terceros` decimal(9,2) DEFAULT NULL,
  `otros` decimal(9,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT NULL,
  `nota` text DEFAULT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_venta_detalle` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_asesor` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_paq_venta` (`id_venta`,`transportista`),
  KEY `idx_paq_empresa_fecha` (`id_empresa`,`fecha`),
  KEY `idx_paq_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partida_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partida_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cuenta` int(11) NOT NULL,
  `codigo` varchar(255) DEFAULT NULL,
  `nombre_cuenta` varchar(255) DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `debe` decimal(10,2) DEFAULT 0.00,
  `haber` decimal(10,2) DEFAULT 0.00,
  `saldo` decimal(10,2) DEFAULT 0.00,
  `id_partida` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partida_detalles_partida` (`id_partida`),
  KEY `idx_partida_detalles_cuenta` (`id_cuenta`),
  KEY `idx_partida_detalles_sumas` (`id_partida`,`debe`,`haber`),
  KEY `idx_partida_detalles_codigo` (`codigo`(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `correlativo` text DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `estado` varchar(255) NOT NULL,
  `referencia` varchar(50) DEFAULT NULL,
  `id_referencia` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partidas_empresa_estado` (`id_empresa`,`estado`),
  KEY `idx_partidas_fecha` (`fecha`),
  KEY `idx_partidas_tipo` (`tipo`),
  KEY `idx_partidas_correlativo` (`correlativo`(100)),
  KEY `idx_partidas_optimized` (`id_empresa`,`estado`,`fecha`,`tipo`),
  KEY `idx_partidas_concepto` (`concepto`(50)),
  KEY `idx_partidas_referencia` (`referencia`,`id_referencia`),
  KEY `idx_partidas_created` (`created_at`),
  KEY `idx_partidas_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedido_detalle_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_detalle_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_detalle_id` bigint(20) unsigned NOT NULL,
  `lote_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_detalle_lotes_lote_id_foreign` (`lote_id`),
  KEY `pedido_detalle_lotes_pedido_detalle_id_lote_id_index` (`pedido_detalle_id`,`lote_id`),
  CONSTRAINT `pedido_detalle_lotes_lote_id_foreign` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`),
  CONSTRAINT `pedido_detalle_lotes_pedido_detalle_id_foreign` FOREIGN KEY (`pedido_detalle_id`) REFERENCES `restaurante_pedido_detalles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_revocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_revocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `permission_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_revocations_user_id_permission_name_unique` (`user_id`,`permission_name`),
  KEY `permission_revocations_user_id_index` (`user_id`),
  KEY `permission_revocations_permission_name_index` (`permission_name`),
  CONSTRAINT `permission_revocations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `planes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `duracion_dias` int(11) NOT NULL DEFAULT 30,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_producto` bigint(20) DEFAULT NULL,
  `enlace_n1co` varchar(191) DEFAULT NULL,
  `caracteristicas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`caracteristicas`)),
  `id_enlace_pago_n1co` varchar(191) DEFAULT NULL,
  `n1co_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`n1co_metadata`)),
  `permite_periodo_prueba` tinyint(1) NOT NULL DEFAULT 0,
  `dias_periodo_prueba` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `planes_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `planilla_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planilla_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_planilla` bigint(20) unsigned NOT NULL,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `salario_base` decimal(10,2) NOT NULL,
  `salario_devengado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dias_laborados` decimal(10,2) NOT NULL DEFAULT 0.00,
  `horas_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_horas_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `detalle_horas_extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle_horas_extra`)),
  `comisiones` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bonificaciones` decimal(10,2) NOT NULL DEFAULT 0.00,
  `otros_ingresos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `abonos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `abonos_sin_retencion` tinyint(1) NOT NULL DEFAULT 1,
  `viaticos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `isss_empleado` decimal(10,2) NOT NULL,
  `isss_patronal` decimal(10,2) NOT NULL,
  `afp_empleado` decimal(10,2) NOT NULL,
  `afp_patronal` decimal(10,2) NOT NULL,
  `renta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prestamos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `anticipos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `otros_descuentos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuentos_judiciales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `detalle_otras_deducciones` text DEFAULT NULL,
  `conceptos_personalizados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conceptos_personalizados`)),
  `pais_configuracion` varchar(3) NOT NULL DEFAULT 'SV',
  `total_ingresos` decimal(10,2) NOT NULL,
  `total_descuentos` decimal(10,2) NOT NULL,
  `sueldo_neto` decimal(10,2) NOT NULL,
  `estado` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `planilla_detalles_id_planilla_foreign` (`id_planilla`),
  CONSTRAINT `planilla_detalles_id_planilla_foreign` FOREIGN KEY (`id_planilla`) REFERENCES `planillas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `planillas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `planillas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(191) DEFAULT NULL,
  `tipo_planilla` varchar(191) NOT NULL,
  `version_tabla` varchar(100) DEFAULT NULL,
  `version_decreto` varchar(100) DEFAULT NULL,
  `fecha_vigencia_tabla` date DEFAULT NULL,
  `estado` int(11) NOT NULL,
  `total_salarios` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deducciones` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_neto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_viaticos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_aportes_patronales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_cuenta_banco` int(10) unsigned DEFAULT NULL,
  `id_cuenta_contable` int(10) unsigned DEFAULT NULL,
  `mes` varchar(191) DEFAULT NULL,
  `anio` year(4) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `planillas_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `planillas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pre_cuenta_orden_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_cuenta_orden_detalle` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pre_cuenta_id` bigint(20) unsigned NOT NULL,
  `orden_detalle_id` bigint(20) unsigned NOT NULL,
  `cantidad` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pre_cuenta_orden_detalle_unique` (`pre_cuenta_id`,`orden_detalle_id`),
  KEY `pre_cuenta_orden_detalle_orden_detalle_id_foreign` (`orden_detalle_id`),
  CONSTRAINT `pre_cuenta_orden_detalle_orden_detalle_id_foreign` FOREIGN KEY (`orden_detalle_id`) REFERENCES `orden_detalle_restaurante` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pre_cuenta_orden_detalle_pre_cuenta_id_foreign` FOREIGN KEY (`pre_cuenta_id`) REFERENCES `pre_cuentas_restaurante` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pre_cuentas_restaurante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_cuentas_restaurante` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sesion_id` bigint(20) unsigned NOT NULL,
  `division_cuenta_id` bigint(20) unsigned DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(12,2) NOT NULL DEFAULT 0.00,
  `impuesto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `propina_monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `propina_porcentaje_aplicado` decimal(6,2) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','facturada','anulada') NOT NULL DEFAULT 'pendiente',
  `factura_id` int(10) unsigned DEFAULT NULL COMMENT 'ID de venta cuando se convierte en factura',
  `numero_pre_cuenta` varchar(30) DEFAULT NULL,
  `nombre_pagador` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pre_cuentas_restaurante_division_cuenta_id_foreign` (`division_cuenta_id`),
  KEY `pre_cuentas_restaurante_sesion_id_estado_index` (`sesion_id`,`estado`),
  CONSTRAINT `pre_cuentas_restaurante_division_cuenta_id_foreign` FOREIGN KEY (`division_cuenta_id`) REFERENCES `division_cuenta_restaurante` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pre_cuentas_restaurante_sesion_id_foreign` FOREIGN KEY (`sesion_id`) REFERENCES `restaurante_sesiones_mesa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prestamo_cuotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamo_cuotas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_prestamo` bigint(20) unsigned NOT NULL,
  `numero` smallint(5) unsigned NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `capital` decimal(12,2) NOT NULL,
  `interes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `id_pago` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prestamo_cuotas_id_prestamo_numero_unique` (`id_prestamo`,`numero`),
  CONSTRAINT `prestamo_cuotas_id_prestamo_foreign` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos_empresa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prestamo_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamo_movimientos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_prestamo` bigint(20) unsigned NOT NULL,
  `tipo` varchar(30) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `saldo_despues` decimal(12,2) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `fecha` date NOT NULL,
  `id_planilla_detalle` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prestamo_movimientos_id_prestamo_foreign` (`id_prestamo`),
  KEY `prestamo_movimientos_id_planilla_detalle_foreign` (`id_planilla_detalle`),
  CONSTRAINT `prestamo_movimientos_id_planilla_detalle_foreign` FOREIGN KEY (`id_planilla_detalle`) REFERENCES `planilla_detalles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prestamo_movimientos_id_prestamo_foreign` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos_empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prestamo_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamo_pagos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_prestamo` bigint(20) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `capital` decimal(12,2) NOT NULL,
  `interes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `metodo` varchar(191) DEFAULT NULL,
  `referencia` varchar(191) DEFAULT NULL,
  `detalle_banco` varchar(191) DEFAULT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prestamo_pagos_id_prestamo_foreign` (`id_prestamo`),
  CONSTRAINT `prestamo_pagos_id_prestamo_foreign` FOREIGN KEY (`id_prestamo`) REFERENCES `prestamos_empresa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prestamos_empleados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos_empleados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` bigint(20) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `numero_prestamo` int(10) unsigned NOT NULL,
  `monto_inicial` decimal(12,2) NOT NULL,
  `saldo_actual` decimal(12,2) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `fecha_desembolso` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prestamos_empleados_id_empleado_numero_prestamo_unique` (`id_empleado`,`numero_prestamo`),
  KEY `prestamos_empleados_id_empresa_foreign` (`id_empresa`),
  CONSTRAINT `prestamos_empleados_id_empleado_foreign` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prestamos_empleados_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prestamos_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prestamos_empresa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `id_proveedor` int(10) unsigned DEFAULT NULL,
  `tipo_acreedor` varchar(20) NOT NULL,
  `acreedor` varchar(191) NOT NULL,
  `concepto` varchar(191) DEFAULT NULL,
  `historico` tinyint(1) NOT NULL DEFAULT 0,
  `monto_original` decimal(12,2) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `saldo` decimal(12,2) NOT NULL,
  `genera_interes` tinyint(1) NOT NULL DEFAULT 0,
  `tasa_interes` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `n_cuotas` smallint(5) unsigned NOT NULL,
  `frecuencia` varchar(20) NOT NULL DEFAULT 'mensual',
  `fecha_desembolso` date NOT NULL,
  `fecha_primera_cuota` date NOT NULL,
  `id_cuenta_banco` int(10) unsigned DEFAULT NULL,
  `generar_asiento_desembolso` tinyint(1) NOT NULL DEFAULT 1,
  `clasificacion` varchar(10) NOT NULL DEFAULT 'corto',
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prestamos_empresa_id_empresa_estado_index` (`id_empresa`,`estado`),
  KEY `prestamos_empresa_id_empresa_id_proveedor_index` (`id_empresa`,`id_proveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `presupuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(300) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `ingresos` decimal(10,2) NOT NULL,
  `utilidad` decimal(10,2) NOT NULL DEFAULT 0.00,
  `margen` decimal(10,2) DEFAULT 0.00,
  `id_proyecto` int(11) DEFAULT NULL,
  `egresos` decimal(10,2) NOT NULL,
  `compras` decimal(10,2) DEFAULT 0.00,
  `alquiler` decimal(10,2) DEFAULT 0.00,
  `varios` decimal(10,2) DEFAULT 0.00,
  `mantenimiento` decimal(10,2) DEFAULT 0.00,
  `marketing` decimal(10,2) DEFAULT 0.00,
  `materia` decimal(10,2) DEFAULT 0.00,
  `comisiones` decimal(10,2) DEFAULT 0.00,
  `planilla` decimal(10,2) DEFAULT 0.00,
  `publicidad` decimal(10,2) DEFAULT 0.00,
  `servicios` decimal(10,2) DEFAULT 0.00,
  `combustible` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prestamos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `materia_prima` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo_de_venta` decimal(10,2) NOT NULL DEFAULT 0.00,
  `insumos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gastos_administrativos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_empresa` int(11) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_custom_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `custom_field_id` bigint(20) unsigned NOT NULL,
  `custom_field_value_id` bigint(20) unsigned DEFAULT NULL,
  `cotizacion_venta_detalle_id` int(11) DEFAULT NULL,
  `orden_produccion_detalle_id` bigint(20) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_custom_fields_custom_field_id_foreign` (`custom_field_id`),
  KEY `product_custom_fields_value_id_foreign` (`custom_field_value_id`),
  KEY `product_custom_fields_cotizacion_venta_detalle_id_foreign` (`cotizacion_venta_detalle_id`),
  CONSTRAINT `product_custom_fields_cotizacion_venta_detalle_id_foreign` FOREIGN KEY (`cotizacion_venta_detalle_id`) REFERENCES `detalles_cotizacion_ventas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_custom_fields_custom_field_id_foreign` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_custom_fields_value_id_foreign` FOREIGN KEY (`custom_field_value_id`) REFERENCES `custom_field_values` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_atributo_valores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_atributo_valores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('talla','color','material') NOT NULL COMMENT 'Tipo de atributo',
  `valor` varchar(100) NOT NULL COMMENT 'Valor del atributo',
  `id_empresa` int(11) NOT NULL COMMENT 'Empresa a la que pertenece',
  `enable` tinyint(1) DEFAULT 1 COMMENT 'Estado del valor',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empresa_id` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_composicion_opciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_composicion_opciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_composicion` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_producto_opciones_id_composicion` (`id_composicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_composiciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_composiciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_compuesto` int(11) NOT NULL,
  `cantidad` decimal(9,2) NOT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `producto_composiciones_id_presentacion_foreign` (`id_presentacion`),
  KEY `idx_producto_composiciones_id_producto` (`id_producto`),
  CONSTRAINT `producto_composiciones_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_impuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_impuestos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_producto` int(10) unsigned NOT NULL,
  `id_impuesto` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_impuestos_id_producto_id_impuesto_unique` (`id_producto`,`id_impuesto`),
  KEY `producto_impuestos_id_impuesto_foreign` (`id_impuesto`),
  CONSTRAINT `producto_impuestos_id_impuesto_foreign` FOREIGN KEY (`id_impuesto`) REFERENCES `impuestos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `producto_impuestos_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_precio_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_precio_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_precio` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_precios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `precio` decimal(16,8) NOT NULL,
  `clasificacion` varchar(100) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_producto_precios_id_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_presentaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_presentaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_producto` int(10) unsigned NOT NULL,
  `id_unidad_medida` int(11) NOT NULL,
  `nombre_comercial` varchar(191) NOT NULL,
  `factor_conversion` decimal(16,6) NOT NULL DEFAULT 1.000000,
  `precio_venta` decimal(16,6) NOT NULL DEFAULT 0.000000,
  `codigo_barras` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `producto_presentaciones_id_producto_foreign` (`id_producto`),
  KEY `producto_presentaciones_id_unidad_medida_foreign` (`id_unidad_medida`),
  CONSTRAINT `producto_presentaciones_id_producto_foreign` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `producto_presentaciones_id_unidad_medida_foreign` FOREIGN KEY (`id_unidad_medida`) REFERENCES `unidades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_proveedor`;
/*!50001 DROP VIEW IF EXISTS `producto_proveedor`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `producto_proveedor` AS SELECT
 NULL AS `id_producto`,
 NULL AS `id_proveedor`,
 NULL AS `id_empresa`,
 NULL AS `Proveedor`,
 NULL AS `Producto` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `producto_proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_producto_proveedores_lookup` (`id_producto`,`id` DESC),
  KEY `idx_pp_proveedor` (`id_proveedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `producto_restaurante_pantalla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_restaurante_pantalla` (
  `producto_id` int(10) unsigned NOT NULL,
  `pantalla_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`producto_id`,`pantalla_id`),
  KEY `producto_restaurante_pantalla_pantalla_id_foreign` (`pantalla_id`),
  CONSTRAINT `producto_restaurante_pantalla_pantalla_id_foreign` FOREIGN KEY (`pantalla_id`) REFERENCES `restaurante_pantallas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `producto_restaurante_pantalla_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shopify_product_id` bigint(20) DEFAULT NULL,
  `shopify_variant_id` bigint(20) DEFAULT NULL,
  `shopify_inventory_item_id` bigint(20) DEFAULT NULL,
  `shopify_sku` varchar(191) DEFAULT NULL,
  `syncing_from_shopify` tinyint(1) DEFAULT 0,
  `last_shopify_sync` timestamp NULL DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `nombre_comercial` varchar(191) DEFAULT NULL,
  `nombre_variante` varchar(191) DEFAULT NULL COMMENT 'Nombre específico de la variante del producto',
  `option1_name` varchar(191) DEFAULT NULL,
  `option1_value` varchar(191) DEFAULT NULL,
  `option2_name` varchar(191) DEFAULT NULL,
  `option2_value` varchar(191) DEFAULT NULL,
  `option3_name` varchar(191) DEFAULT NULL,
  `option3_value` varchar(191) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `descripcion_completa` text DEFAULT NULL,
  `marca` varchar(255) DEFAULT NULL,
  `componente_quimico` varchar(191) DEFAULT NULL,
  `costo` decimal(16,6) NOT NULL,
  `costo_promedio` decimal(16,6) NOT NULL DEFAULT 0.000000,
  `costo_anterior` double DEFAULT NULL,
  `precio` decimal(16,8) NOT NULL,
  `precio_sin_iva` decimal(16,8) DEFAULT NULL,
  `precio_con_iva` decimal(16,8) DEFAULT NULL,
  `stock` decimal(10,2) DEFAULT NULL,
  `codigo` varchar(100) DEFAULT NULL,
  `codigo_cabys` varchar(13) DEFAULT NULL,
  `partida_arancelaria` varchar(12) DEFAULT NULL,
  `descripcion_cabys` varchar(512) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `notificar` tinyint(1) DEFAULT NULL,
  `medida` varchar(100) DEFAULT NULL,
  `talla` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `material` varchar(255) DEFAULT NULL,
  `dimensiones` varchar(191) DEFAULT NULL,
  `etiquetas` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT 'Producto',
  `inventario_por_lotes` tinyint(1) DEFAULT 0,
  `id_empresa` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `porcentaje_impuesto` decimal(5,2) DEFAULT NULL,
  `id_subcategoria` int(11) DEFAULT NULL,
  `cod_proveed_prod` varchar(255) DEFAULT NULL,
  `id_promocion` int(11) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `woocommerce_id` varchar(100) DEFAULT NULL,
  `last_woocommerce_sync` timestamp NULL DEFAULT NULL,
  `imported_from_woocommerce_csv` tinyint(1) NOT NULL DEFAULT 0,
  `woocommerce_parent_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID del producto padre en WooCommerce, para variaciones',
  `genera_comanda` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si true, el producto se envía a cocina como comanda',
  `mostrar_en_restaurante` tinyint(1) NOT NULL DEFAULT 1,
  `destino_comanda` varchar(20) NOT NULL DEFAULT 'cocina' COMMENT 'cocina, barra, ambos',
  PRIMARY KEY (`id`),
  KEY `idx_productos_id_categoria` (`id_categoria`),
  KEY `idx_productos_nombre_empresa` (`nombre`,`id_empresa`),
  KEY `idx_productos_main` (`id`,`deleted_at`,`nombre`),
  KEY `idx_productos_categoria` (`id_categoria`),
  KEY `idx_productos_id_empresa_tipo_deleted` (`id_empresa`,`tipo`,`deleted_at`),
  KEY `idx_shopify_producto` (`shopify_product_id`),
  KEY `idx_shopify_sku` (`shopify_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `productos_imagenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos_imagenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopify_image_id` bigint(20) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `img` varchar(255) DEFAULT NULL,
  `src` varchar(1000) DEFAULT NULL COMMENT 'URL original de la imagen en Shopify',
  `hash` varchar(64) DEFAULT NULL COMMENT 'md5 del contenido descargado',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_productos_imagenes_id_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `productos_x_proveedor`;
/*!50001 DROP VIEW IF EXISTS `productos_x_proveedor`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `productos_x_proveedor` AS SELECT
 NULL AS `id_empresa`,
 NULL AS `id_proveedor`,
 NULL AS `Proveedor`,
 NULL AS `id_producto`,
 NULL AS `Producto` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `promocionales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `promocionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(191) NOT NULL,
  `descuento` decimal(10,2) NOT NULL,
  `tipo` enum('porcentaje','monto_fijo') NOT NULL DEFAULT 'porcentaje',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `campania` varchar(191) DEFAULT NULL,
  `aliado` varchar(191) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `planes_permitidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`planes_permitidos`)),
  `opciones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opciones`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `promocionales_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `promociones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `promociones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `precio` double(10,2) NOT NULL,
  `sub_total` double(10,2) NOT NULL,
  `descuento` double(10,2) NOT NULL,
  `enable` tinyint(1) NOT NULL,
  `codigo` varchar(100) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `tipo_persona` varchar(20) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `nombre_empresa` varchar(255) DEFAULT NULL,
  `nombre_comercial` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `cod_departamento` varchar(10) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `cod_municipio` varchar(10) DEFAULT NULL,
  `pais` varchar(255) DEFAULT NULL,
  `cod_pais` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `giro` varchar(255) DEFAULT NULL,
  `cod_giro` varchar(10) DEFAULT NULL,
  `ncr` varchar(550) DEFAULT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `dui` varchar(255) DEFAULT NULL,
  `tipo_contribuyente` varchar(255) DEFAULT NULL,
  `distrito` varchar(255) DEFAULT NULL,
  `cod_distrito` varchar(100) DEFAULT NULL,
  `tipo` varchar(250) DEFAULT 'Persona',
  `etiquetas` varchar(500) DEFAULT NULL,
  `nota` varchar(1500) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_cuenta_contable` int(11) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `id_empresa` int(11) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `banco` varchar(191) DEFAULT NULL,
  `tipo_cuenta` varchar(191) DEFAULT NULL,
  `numero_cuenta` varchar(50) DEFAULT NULL,
  `titular_cuenta` varchar(191) DEFAULT NULL,
  `forma_pago` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proveedores_nombre_apellido_empresa` (`nombre`,`apellido`,`id_empresa`),
  KEY `idx_proveedores_empresa_nombre` (`nombre_empresa`,`id_empresa`),
  KEY `idx_proveedores_empresa_enable_nombre` (`id_empresa`,`enable`,`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proyectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyectos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `enable` tinyint(1) DEFAULT 1,
  `id_cliente` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `puntos_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `puntos_cliente` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `puntos_disponibles` int(11) NOT NULL DEFAULT 0,
  `puntos_totales_ganados` int(11) NOT NULL DEFAULT 0,
  `puntos_totales_canjeados` int(11) NOT NULL DEFAULT 0,
  `fecha_ultima_actividad` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_cliente_id_empresa` (`id_cliente`,`id_empresa`),
  KEY `idx_puntos_cliente` (`id_cliente`),
  KEY `puntos_cliente_id_empresa_puntos_disponibles_index` (`id_empresa`,`puntos_disponibles`),
  KEY `puntos_cliente_fecha_ultima_actividad_index` (`fecha_ultima_actividad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recintos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recintos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(100) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `regimenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `regimenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(100) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `registros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estado` varchar(250) NOT NULL,
  `nombre` varchar(500) NOT NULL,
  `email` varchar(100) NOT NULL,
  `empresa` varchar(500) NOT NULL,
  `plan` varchar(250) NOT NULL,
  `monto` double(10,2) NOT NULL,
  `industria` varchar(250) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `moneda` varchar(50) NOT NULL,
  `iva` decimal(10,2) NOT NULL,
  `telefono` varchar(25) NOT NULL,
  `password` varchar(800) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reporte_configuraciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporte_configuraciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre_reporte` text DEFAULT NULL,
  `tipo_reporte` varchar(255) NOT NULL COMMENT 'Tipo de reporte: ventas-por-vendedor, ventas-diarias, etc.',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Si el envío automático está activo',
  `frecuencia` varchar(50) NOT NULL DEFAULT 'diario' COMMENT 'diario, semanal, mensual',
  `dias_semana` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Para frecuencia semanal, qué días (1-7, donde 1 es lunes)' CHECK (json_valid(`dias_semana`)),
  `dia_mes` int(11) DEFAULT NULL COMMENT 'Para frecuencia mensual, qué día del mes (1-31)',
  `envio_matutino` tinyint(1) NOT NULL DEFAULT 1,
  `hora_matutino` time NOT NULL DEFAULT '08:00:00',
  `envio_mediodia` tinyint(1) NOT NULL DEFAULT 0,
  `hora_mediodia` time NOT NULL DEFAULT '13:00:00',
  `envio_nocturno` tinyint(1) NOT NULL DEFAULT 0,
  `hora_nocturno` time NOT NULL DEFAULT '19:00:00',
  `destinatarios` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Almacena los correos como JSON' CHECK (json_valid(`destinatarios`)),
  `asunto_correo` varchar(255) DEFAULT NULL COMMENT 'Personalización del asunto',
  `id_empresa` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Almacena la configuracion de los reportes' CHECK (json_valid(`configuracion`)),
  `sucursales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sucursales`)),
  PRIMARY KEY (`id`),
  KEY `idx_reporte_config_empresa` (`id_empresa`),
  KEY `idx_reporte_config_activo` (`activo`),
  KEY `idx_reporte_config_tipo` (`tipo_reporte`),
  KEY `fk_reporte_configuraciones_empresa` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reporte_exportaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reporte_exportaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_usuario` bigint(20) unsigned DEFAULT NULL,
  `id_configuracion` bigint(20) unsigned DEFAULT NULL,
  `tipo_reporte` varchar(64) DEFAULT NULL,
  `parametros` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parametros`)),
  `modo` varchar(20) NOT NULL,
  `formato` varchar(20) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pending',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `sucursales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sucursales`)),
  `destinatarios` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`destinatarios`)),
  `ruta_archivo` varchar(191) DEFAULT NULL,
  `nombre_archivo` varchar(191) DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reporte_exportaciones_id_empresa_estado_index` (`id_empresa`,`estado`),
  KEY `reporte_exportaciones_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reservas_restaurante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservas_restaurante` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mesa_id` bigint(20) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `fecha_reserva` date NOT NULL,
  `hora_reserva` time NOT NULL,
  `cliente_nombre` varchar(150) DEFAULT NULL,
  `cliente_telefono` varchar(30) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('pendiente','confirmada','cumplida','cancelada','no_show') NOT NULL DEFAULT 'pendiente',
  `usuario_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Usuario que creó la reserva',
  `cliente_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Cliente del sistema si existe',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reservas_restaurante_id_empresa_foreign` (`id_empresa`),
  KEY `reservas_restaurante_usuario_id_foreign` (`usuario_id`),
  KEY `reservas_restaurante_mesa_id_fecha_reserva_estado_index` (`mesa_id`,`fecha_reserva`,`estado`),
  CONSTRAINT `reservas_restaurante_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reservas_restaurante_mesa_id_foreign` FOREIGN KEY (`mesa_id`) REFERENCES `restaurante_mesas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reservas_restaurante_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rest_item_eliminaciones_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rest_item_eliminaciones_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `orden_detalle_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID antes de anular',
  `sesion_id` bigint(20) unsigned NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `enviado_cocina` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_barra` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_codigo` varchar(50) DEFAULT NULL COMMENT 'error, rechazo_cliente, otro',
  `motivo_detalle` text DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `autorizado_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rest_elim_prod` (`producto_id`),
  KEY `fk_rest_elim_user` (`usuario_id`),
  KEY `fk_rest_elim_autor` (`autorizado_usuario_id`),
  KEY `idx_rest_elim_sesion_created` (`sesion_id`,`created_at`),
  CONSTRAINT `fk_rest_elim_autor` FOREIGN KEY (`autorizado_usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rest_elim_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  CONSTRAINT `fk_rest_elim_user` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rest_item_eliminaciones_log_sesion_id_foreign` FOREIGN KEY (`sesion_id`) REFERENCES `restaurante_sesiones_mesa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_envio_pantalla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_envio_pantalla` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pantalla_id` bigint(20) unsigned NOT NULL,
  `orden_detalle_id` bigint(20) unsigned DEFAULT NULL,
  `pedido_detalle_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_envio_orden_pantalla` (`pantalla_id`,`orden_detalle_id`),
  UNIQUE KEY `uniq_envio_pedido_pantalla` (`pantalla_id`,`pedido_detalle_id`),
  KEY `restaurante_envio_pantalla_orden_detalle_id_foreign` (`orden_detalle_id`),
  KEY `restaurante_envio_pantalla_pedido_detalle_id_foreign` (`pedido_detalle_id`),
  CONSTRAINT `restaurante_envio_pantalla_orden_detalle_id_foreign` FOREIGN KEY (`orden_detalle_id`) REFERENCES `orden_detalle_restaurante` (`id`) ON DELETE CASCADE,
  CONSTRAINT `restaurante_envio_pantalla_pantalla_id_foreign` FOREIGN KEY (`pantalla_id`) REFERENCES `restaurante_pantallas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `restaurante_envio_pantalla_pedido_detalle_id_foreign` FOREIGN KEY (`pedido_detalle_id`) REFERENCES `restaurante_pedido_detalles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_idempotency_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_idempotency_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `operation` varchar(64) NOT NULL,
  `idempotency_key` varchar(128) NOT NULL,
  `status` varchar(16) NOT NULL,
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `response_body` mediumtext DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rest_idempotency` (`id_empresa`,`user_id`,`operation`,`idempotency_key`),
  KEY `restaurante_idempotency_keys_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_mesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_mesas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `numero` varchar(20) NOT NULL,
  `capacidad` smallint(5) unsigned NOT NULL DEFAULT 4,
  `zona_id` bigint(20) unsigned DEFAULT NULL,
  `zona` varchar(50) DEFAULT NULL,
  `estado` enum('libre','ocupada','pendiente_pago','reservada') NOT NULL DEFAULT 'libre',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Orden en el mapa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurante_mesas_id_empresa_id_sucursal_index` (`id_empresa`,`id_sucursal`),
  KEY `restaurante_mesas_zona_id_foreign` (`zona_id`),
  CONSTRAINT `restaurante_mesas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `restaurante_mesas_zona_id_foreign` FOREIGN KEY (`zona_id`) REFERENCES `restaurante_zonas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_pantallas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_pantallas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurante_pantallas_id_empresa_nombre_unique` (`id_empresa`,`nombre`),
  CONSTRAINT `restaurante_pantallas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_pedido_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_pedido_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pedido_id` bigint(20) unsigned NOT NULL,
  `producto_id` int(10) unsigned NOT NULL,
  `id_paquete` int(11) DEFAULT NULL,
  `cantidad` decimal(14,4) NOT NULL,
  `precio` decimal(14,4) NOT NULL,
  `descuento` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `descuento_porcentaje` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `subtotal` decimal(14,4) NOT NULL,
  `total` decimal(14,4) NOT NULL,
  `notas` text DEFAULT NULL,
  `enviado_cocina` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_barra` tinyint(1) NOT NULL DEFAULT 0,
  `meta_inventario` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_inventario`)),
  `lote_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurante_pedido_detalles_pedido_id_foreign` (`pedido_id`),
  KEY `restaurante_pedido_detalles_producto_id_index` (`producto_id`),
  KEY `restaurante_pedido_detalles_lote_id_foreign` (`lote_id`),
  KEY `restaurante_pedido_detalles_id_paquete_foreign` (`id_paquete`),
  CONSTRAINT `restaurante_pedido_detalles_id_paquete_foreign` FOREIGN KEY (`id_paquete`) REFERENCES `paquetes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `restaurante_pedido_detalles_lote_id_foreign` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `restaurante_pedido_detalles_pedido_id_foreign` FOREIGN KEY (`pedido_id`) REFERENCES `restaurante_pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_pedidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `id_bodega` int(10) unsigned DEFAULT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `canal` varchar(100) DEFAULT NULL COMMENT 'Ej. Pedidos Ya',
  `referencia_externa` varchar(150) DEFAULT NULL COMMENT 'ID en plataforma externa',
  `estado` enum('borrador','pendiente_facturar','facturado','anulado') NOT NULL DEFAULT 'borrador',
  `id_venta` int(10) unsigned DEFAULT NULL COMMENT 'Venta generada al procesar',
  `cliente_id` int(10) unsigned DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `subtotal` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `descuento` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `inventario_descontado_at` timestamp NULL DEFAULT NULL,
  `id_bodega_inventario` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurante_pedidos_id_venta_foreign` (`id_venta`),
  KEY `restaurante_pedidos_id_empresa_fecha_index` (`id_empresa`,`fecha`),
  KEY `restaurante_pedidos_id_empresa_estado_index` (`id_empresa`,`estado`),
  CONSTRAINT `restaurante_pedidos_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `restaurante_pedidos_id_venta_foreign` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_sesiones_mesa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_sesiones_mesa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mesa_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL COMMENT 'Mesero asignado',
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `num_comensales` smallint(5) unsigned NOT NULL DEFAULT 1,
  `observaciones` text DEFAULT NULL,
  `estado` enum('abierta','pre_cuenta','cerrada') NOT NULL DEFAULT 'abierta',
  `opened_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `mesa_sesion_activa_id` bigint(20) unsigned GENERATED ALWAYS AS (if(`estado` in ('abierta','pre_cuenta'),`mesa_id`,NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_restaurante_mesa_sesion_activa` (`mesa_sesion_activa_id`),
  KEY `restaurante_sesiones_mesa_id_empresa_estado_index` (`id_empresa`,`estado`),
  KEY `restaurante_sesiones_mesa_mesa_id_estado_index` (`mesa_id`,`estado`),
  CONSTRAINT `restaurante_sesiones_mesa_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `restaurante_sesiones_mesa_mesa_id_foreign` FOREIGN KEY (`mesa_id`) REFERENCES `restaurante_mesas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_side_effects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_side_effects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `type` varchar(64) NOT NULL,
  `resource_type` varchar(32) NOT NULL,
  `resource_id` bigint(20) unsigned NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `last_error` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rest_side_effects_type_resource_uq` (`type`,`resource_type`,`resource_id`),
  KEY `rest_side_effects_empresa_status_idx` (`id_empresa`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurante_zonas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurante_zonas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(80) NOT NULL,
  `orden` smallint(5) unsigned NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurante_zonas_id_empresa_activo_index` (`id_empresa`,`activo`),
  CONSTRAINT `restaurante_zonas_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retaceo_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retaceo_compras` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_retaceo` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retaceo_compras_id_retaceo_id_compra_unique` (`id_retaceo`,`id_compra`),
  UNIQUE KEY `retaceo_compras_id_compra_unique` (`id_compra`),
  CONSTRAINT `retaceo_compras_id_compra_foreign` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id`),
  CONSTRAINT `retaceo_compras_id_retaceo_foreign` FOREIGN KEY (`id_retaceo`) REFERENCES `retaceos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retaceo_distribucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retaceo_distribucion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_retaceo` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_detalle_compra` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_original` decimal(10,2) NOT NULL,
  `valor_fob` decimal(10,2) DEFAULT 0.00,
  `porcentaje_distribucion` decimal(10,2) NOT NULL,
  `porcentaje_dai` decimal(10,2) DEFAULT 0.00,
  `monto_transporte` decimal(10,2) DEFAULT 0.00,
  `monto_seguro` decimal(10,2) DEFAULT 0.00,
  `monto_dai` decimal(10,2) DEFAULT 0.00,
  `monto_otros` decimal(10,2) DEFAULT 0.00,
  `costo_landed` decimal(10,2) DEFAULT 0.00,
  `costo_retaceado` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_retaceo_distribucion_retaceo` (`id_retaceo`),
  KEY `fk_retaceo_distribucion_producto` (`id_producto`),
  KEY `fk_retaceo_distribucion_detalle` (`id_detalle_compra`),
  CONSTRAINT `fk_retaceo_distribucion_detalle` FOREIGN KEY (`id_detalle_compra`) REFERENCES `detalles_compra` (`id`),
  CONSTRAINT `fk_retaceo_distribucion_retaceo` FOREIGN KEY (`id_retaceo`) REFERENCES `retaceos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retaceo_gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retaceo_gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_retaceo` int(11) NOT NULL,
  `id_gasto` int(11) NOT NULL,
  `tipo_gasto` enum('Transporte','Seguro','DAI','Otro') NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_retaceo_gastos_retaceo` (`id_retaceo`),
  KEY `fk_retaceo_gastos_gasto` (`id_gasto`),
  CONSTRAINT `fk_retaceo_gastos_gasto` FOREIGN KEY (`id_gasto`) REFERENCES `egresos` (`id`),
  CONSTRAINT `fk_retaceo_gastos_retaceo` FOREIGN KEY (`id_retaceo`) REFERENCES `retaceos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retaceos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retaceos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(100) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `numero_duca` varchar(100) DEFAULT NULL,
  `tasa_dai` decimal(10,2) DEFAULT NULL,
  `numero_factura` varchar(100) DEFAULT NULL,
  `incoterm` varchar(50) DEFAULT NULL,
  `fecha` date NOT NULL,
  `estado` varchar(100) NOT NULL DEFAULT 'Aplicado',
  `observaciones` text DEFAULT NULL,
  `total_gastos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_retaceado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_bodega` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_retaceo_compra` (`id_compra`),
  CONSTRAINT `fk_retaceo_compra` FOREIGN KEY (`id_compra`) REFERENCES `compras` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `id_empresa` int(10) unsigned DEFAULT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`),
  KEY `roles_id_empresa_name_index` (`id_empresa`,`name`),
  CONSTRAINT `roles_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saldos_mensuales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saldos_mensuales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cuenta` bigint(20) unsigned NOT NULL,
  `codigo_cuenta` varchar(191) NOT NULL,
  `nombre_cuenta` varchar(191) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `saldo_inicial` decimal(15,2) NOT NULL DEFAULT 0.00,
  `debe` decimal(15,2) NOT NULL DEFAULT 0.00,
  `haber` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_final` decimal(15,2) NOT NULL DEFAULT 0.00,
  `naturaleza` varchar(191) NOT NULL,
  `estado` enum('Abierto','Cerrado') NOT NULL DEFAULT 'Abierto',
  `id_empresa` bigint(20) unsigned NOT NULL,
  `id_usuario_cierre` bigint(20) unsigned DEFAULT NULL,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_saldo_mensual` (`id_cuenta`,`year`,`month`,`id_empresa`),
  KEY `saldos_mensuales_id_empresa_year_month_index` (`id_empresa`,`year`,`month`),
  KEY `saldos_mensuales_id_cuenta_year_month_index` (`id_cuenta`,`year`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shopify_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shopify_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `shopify_location_id` bigint(20) unsigned NOT NULL,
  `shopify_location_name` varchar(191) NOT NULL,
  `shopify_active` tinyint(1) NOT NULL DEFAULT 1,
  `id_sucursal` bigint(20) unsigned DEFAULT NULL,
  `id_bodega` bigint(20) unsigned DEFAULT NULL,
  `sincronizar_stock` tinyint(1) NOT NULL DEFAULT 1,
  `es_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_shopify_loc_unique` (`id_empresa`,`shopify_location_id`),
  KEY `shopify_locations_id_empresa_id_bodega_index` (`id_empresa`,`id_bodega`),
  KEY `shopify_locations_id_empresa_id_sucursal_index` (`id_empresa`,`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `submodules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `submodules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `display_name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submodules_module_id_foreign` (`module_id`),
  CONSTRAINT `submodules_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sucursal_bodegas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursal_bodegas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) NOT NULL,
  `tipo_establecimiento` varchar(255) DEFAULT NULL,
  `cod_estable_mh` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `descripcion` varchar(255) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bodegas_empresa_activo` (`id_empresa`,`activo`),
  KEY `idx_sucursal_bodegas_lookup` (`id_sucursal`,`id`),
  KEY `idx_bodegas_empresa_activo_nombre` (`id_empresa`,`activo`,`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) NOT NULL,
  `telefono` varchar(250) DEFAULT NULL,
  `correo` varchar(250) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `departamento` varchar(255) DEFAULT NULL,
  `distrito` varchar(191) DEFAULT NULL,
  `cod_departamento` varchar(10) DEFAULT NULL,
  `cod_municipio` varchar(10) DEFAULT NULL,
  `cod_distrito` varchar(10) DEFAULT NULL,
  `direccion` varchar(250) DEFAULT NULL,
  `tipo_establecimiento` varchar(255) DEFAULT NULL,
  `cod_estable_mh` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_empresa` int(11) NOT NULL,
  `shopify_location_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `codigo_punto_venta` varchar(10) NOT NULL DEFAULT 'P001',
  `cod_actividad_economica` varchar(15) DEFAULT NULL,
  `giro` text DEFAULT NULL,
  `correlativo_centro_trabajo_isss` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sucursales_empresa` (`id_empresa`,`id`,`nombre`),
  KEY `sucursales_id_empresa_shopify_location_id_index` (`id_empresa`,`shopify_location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suscripcion_bajas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suscripcion_bajas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `suscripcion_id` bigint(20) unsigned NOT NULL,
  `empresa_id` int(10) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `motivo` varchar(40) NOT NULL,
  `fecha_baja` timestamp NOT NULL,
  `tipo_plan` varchar(40) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `plan_nombre` varchar(191) DEFAULT NULL,
  `empresa_nombre` varchar(191) DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suscripcion_bajas_fecha_baja_index` (`fecha_baja`),
  KEY `suscripcion_bajas_motivo_fecha_baja_index` (`motivo`,`fecha_baja`),
  KEY `suscripcion_bajas_empresa_id_fecha_baja_index` (`empresa_id`,`fecha_baja`),
  KEY `suscripcion_bajas_evento_idx` (`suscripcion_id`,`motivo`,`fecha_baja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suscripciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suscripciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int(10) unsigned NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `plan_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `metodo_pago` varchar(191) DEFAULT NULL,
  `tipo_plan` varchar(191) NOT NULL,
  `estado` varchar(191) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `id_pago` varchar(191) DEFAULT NULL,
  `id_orden` varchar(191) DEFAULT NULL,
  `n1co_authorization_code` varchar(191) DEFAULT NULL,
  `n1co_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`n1co_metadata`)),
  `requiere_factura` tinyint(1) NOT NULL DEFAULT 0,
  `nit` varchar(191) DEFAULT NULL,
  `nombre_factura` varchar(191) DEFAULT NULL,
  `direccion_factura` varchar(191) DEFAULT NULL,
  `tipo_factura` varchar(191) DEFAULT NULL,
  `fecha_ultimo_pago` timestamp NULL DEFAULT NULL,
  `fecha_proximo_pago` timestamp NULL DEFAULT NULL,
  `dia_pago` int(11) DEFAULT NULL,
  `acceso_temporal_hasta` timestamp NULL DEFAULT NULL,
  `fin_periodo_prueba` timestamp NULL DEFAULT NULL,
  `fecha_cancelacion` timestamp NULL DEFAULT NULL,
  `estado_ultimo_pago` varchar(191) DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `comentarios` text DEFAULT NULL,
  `intentos_cobro` int(11) NOT NULL DEFAULT 0,
  `ultimo_intento_cobro` timestamp NULL DEFAULT NULL,
  `historial_pagos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`historial_pagos`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suscripciones_plan_id_foreign` (`plan_id`),
  KEY `suscripciones_usuario_id_foreign` (`usuario_id`),
  KEY `suscripciones_empresa_id_estado_index` (`empresa_id`,`estado`),
  KEY `suscripciones_id_pago_id_orden_index` (`id_pago`,`id_orden`),
  KEY `suscripciones_id_cliente_foreign` (`id_cliente`),
  CONSTRAINT `suscripciones_empresa_id_foreign` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `suscripciones_id_cliente_foreign` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `suscripciones_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `planes` (`id`),
  CONSTRAINT `suscripciones_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `user_email_account_id` bigint(20) unsigned NOT NULL,
  `started_at` timestamp NOT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `status` enum('running','completed','failed') NOT NULL DEFAULT 'running',
  `emails_scanned` int(11) NOT NULL DEFAULT 0,
  `dtes_found` int(11) NOT NULL DEFAULT 0,
  `dtes_processed` int(11) NOT NULL DEFAULT 0,
  `dtes_duplicates` int(10) unsigned NOT NULL DEFAULT 0,
  `dtes_failed` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `failure_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`failure_details`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sync_logs_id_empresa_foreign` (`id_empresa`),
  KEY `sync_logs_user_email_account_id_started_at_index` (`user_email_account_id`,`started_at`),
  CONSTRAINT `sync_logs_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sync_logs_user_email_account_id_foreign` FOREIGN KEY (`user_email_account_id`) REFERENCES `user_email_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint(20) unsigned NOT NULL,
  `uuid` char(36) NOT NULL,
  `batch_id` char(36) NOT NULL,
  `family_hash` varchar(255) DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT 1,
  `type` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) NOT NULL,
  `tag` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_cliente_base`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_cliente_base` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(191) NOT NULL COMMENT 'STANDARD/VIP/ULTRAVIP',
  `nombre` varchar(191) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) NOT NULL COMMENT '1=STANDARD, 2=VIP, 3=ULTRAVIP',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipos_cliente_base_code_unique` (`code`),
  KEY `tipos_cliente_base_activo_index` (`activo`),
  KEY `tipos_cliente_base_orden_index` (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_cliente_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_cliente_empresa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_tipo_base` bigint(20) unsigned DEFAULT NULL,
  `nivel` int(11) DEFAULT NULL COMMENT '1=STANDARD, 2=VIP, 3=ULTRAVIP',
  `nombre_personalizado` varchar(191) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `puntos_por_dolar` decimal(8,4) NOT NULL DEFAULT 1.0000 COMMENT 'CORE - DEFAULT 1.0000',
  `valor_punto` decimal(8,4) NOT NULL DEFAULT 0.0100 COMMENT 'CORE - DEFAULT 0.0001',
  `minimo_canje` int(11) NOT NULL DEFAULT 100 COMMENT 'CORE - DEFAULT 100',
  `maximo_canje` int(11) NOT NULL DEFAULT 1000 COMMENT 'CORE - DEFAULT 1000',
  `expiracion_meses` int(11) NOT NULL DEFAULT 12 COMMENT 'CORE - DEFAULT 12',
  `configuracion_avanzada` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'HÍBRIDO - flexibilidad futura' CHECK (json_valid(`configuracion_avanzada`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'único por empresa',
  `default_key` varchar(50) GENERATED ALWAYS AS (case when `is_default` = 1 then concat(`id_empresa`,'_',`nivel`) else NULL end) STORED,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_empresa_nivel_default_key` (`id_empresa`,`nivel`,`default_key`),
  KEY `tipos_cliente_empresa_id_tipo_base_foreign` (`id_tipo_base`),
  KEY `tipos_cliente_empresa_id_empresa_activo_index` (`id_empresa`,`activo`),
  KEY `tipos_cliente_empresa_is_default_index` (`is_default`),
  KEY `tipos_cliente_empresa_nivel_index` (`nivel`),
  CONSTRAINT `tipos_cliente_empresa_id_tipo_base_foreign` FOREIGN KEY (`id_tipo_base`) REFERENCES `tipos_cliente_base` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tmp_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tmp_inventario` (
  `id_producto` int(11) NOT NULL,
  `stock` decimal(32,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tmp_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tmp_productos` (
  `nombre` varchar(255) NOT NULL,
  `codigo` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trabajos_pendientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `trabajos_pendientes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(191) NOT NULL,
  `parametros` text NOT NULL,
  `datos` text DEFAULT NULL,
  `intentos` int(11) NOT NULL DEFAULT 0,
  `max_intentos` int(11) NOT NULL DEFAULT 3,
  `estado` enum('pendiente','procesando','completado','fallido') NOT NULL,
  `prioridad` int(11) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL,
  `fecha_procesamiento` timestamp NULL DEFAULT NULL,
  `fecha_inicio` timestamp NULL DEFAULT NULL,
  `fecha_fin` timestamp NULL DEFAULT NULL,
  `resultado` longtext DEFAULT NULL,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `id_empresa` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trabajos_pendientes_estado_tipo_index` (`estado`,`tipo`),
  KEY `trabajos_pendientes_id_empresa_index` (`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `correlativo` int(11) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `metodo_pago` varchar(255) DEFAULT NULL,
  `tipo_documento` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `anombrede` varchar(255) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `descripcion` varchar(500) NOT NULL,
  `cliente` varchar(500) NOT NULL,
  `total` double(10,2) NOT NULL,
  `id_empresa` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transacciones_puntos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transacciones_puntos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_empresa` int(10) unsigned NOT NULL,
  `id_venta` int(10) unsigned DEFAULT NULL,
  `tipo` enum('ganancia','canje','ajuste','expiracion') NOT NULL,
  `puntos` double NOT NULL COMMENT '+ ganancia, - canje/exp',
  `puntos_antes` double NOT NULL,
  `puntos_despues` double NOT NULL,
  `monto_asociado` decimal(10,2) DEFAULT NULL,
  `puntos_consumidos` int(11) NOT NULL DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `fecha_expiracion` date DEFAULT NULL COMMENT 'solo ganancia',
  `idempotency_key` varchar(191) NOT NULL,
  `venta_ganancia_key` int(11) GENERATED ALWAYS AS (case when `tipo` = 'ganancia' and `id_venta` is not null then `id_venta` else NULL end) STORED,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transacciones_puntos_idempotency_key_unique` (`idempotency_key`),
  UNIQUE KEY `venta_ganancia_key` (`id_venta`,`venta_ganancia_key`),
  KEY `idx_transacciones_cliente_created_desc` (`id_cliente`,`created_at` DESC),
  KEY `idx_transacciones_cliente_tipo` (`id_cliente`,`tipo`),
  KEY `idx_transacciones_cliente_created` (`id_cliente`,`created_at`),
  KEY `idx_transacciones_tipo_created` (`tipo`,`created_at`),
  KEY `idx_transacciones_tipo_cliente` (`tipo`,`id_cliente`),
  KEY `transacciones_puntos_id_cliente_created_at_index` (`id_cliente`,`created_at`),
  KEY `transacciones_puntos_id_empresa_tipo_created_at_index` (`id_empresa`,`tipo`,`created_at`),
  KEY `transacciones_puntos_fecha_expiracion_tipo_index` (`fecha_expiracion`,`tipo`),
  KEY `transacciones_puntos_id_venta_tipo_index` (`id_venta`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transformacion_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transformacion_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_transformacion` bigint(20) unsigned NOT NULL,
  `id_producto` bigint(20) unsigned NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `tipo` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transformacion_detalles_id_transformacion_foreign` (`id_transformacion`),
  CONSTRAINT `transformacion_detalles_id_transformacion_foreign` FOREIGN KEY (`id_transformacion`) REFERENCES `transformaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transformaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transformaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` bigint(20) unsigned NOT NULL,
  `id_bodega` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `observacion` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transformaciones_id_usuario_foreign` (`id_usuario`),
  KEY `transformaciones_id_bodega_foreign` (`id_bodega`),
  CONSTRAINT `transformaciones_id_bodega_foreign` FOREIGN KEY (`id_bodega`) REFERENCES `sucursal_bodegas` (`id`),
  CONSTRAINT `transformaciones_id_usuario_foreign` FOREIGN KEY (`id_usuario`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `traslado_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `traslado_lotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `traslado_id` int(10) unsigned NOT NULL,
  `lote_id` int(10) unsigned NOT NULL,
  `lote_id_destino` int(10) unsigned DEFAULT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `traslados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `traslados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_presentacion` bigint(20) unsigned DEFAULT NULL,
  `id_bodega_de` int(11) DEFAULT NULL,
  `lote_id` int(10) unsigned DEFAULT NULL,
  `lote_id_destino` int(10) unsigned DEFAULT NULL,
  `id_bodega` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_empresa` int(11) NOT NULL,
  `concepto` varchar(250) NOT NULL,
  `id_grupo` char(36) DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  `estado` varchar(250) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `traslados_id_presentacion_foreign` (`id_presentacion`),
  KEY `traslados_id_grupo_index` (`id_grupo`),
  KEY `idx_tr_empresa_created` (`id_empresa`,`created_at`),
  KEY `idx_tr_producto` (`id_producto`),
  CONSTRAINT `traslados_id_presentacion_foreign` FOREIGN KEY (`id_presentacion`) REFERENCES `producto_presentaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cod` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_authorization_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_authorization_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `authorization_type_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_authorization_types_user_id_authorization_type_id_unique` (`user_id`,`authorization_type_id`),
  KEY `user_authorization_types_authorization_type_id_foreign` (`authorization_type_id`),
  CONSTRAINT `user_authorization_types_authorization_type_id_foreign` FOREIGN KEY (`authorization_type_id`) REFERENCES `authorization_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_authorization_types_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_email_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_email_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empresa` int(10) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `notification_user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Usuario que recibe alertas de DTEs por revisar',
  `provider` enum('gmail','outlook','imap','forward') NOT NULL,
  `email` varchar(191) NOT NULL,
  `access_token` text DEFAULT NULL COMMENT 'Encrypted - OAuth only',
  `refresh_token` text DEFAULT NULL COMMENT 'Encrypted - OAuth only',
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `imap_host` varchar(191) DEFAULT NULL,
  `imap_port` int(11) DEFAULT NULL,
  `imap_encryption` varchar(191) DEFAULT NULL COMMENT 'ssl, tls, starttls',
  `imap_user` varchar(191) DEFAULT NULL,
  `imap_password` text DEFAULT NULL COMMENT 'Encrypted - IMAP only',
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `id_bodega` int(10) unsigned DEFAULT NULL,
  `actualizar_inventario` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_email_accounts_empresa_email_provider_unique` (`id_empresa`,`email`,`provider`),
  KEY `user_email_accounts_user_id_foreign` (`user_id`),
  KEY `user_email_accounts_notification_user_id_foreign` (`notification_user_id`),
  CONSTRAINT `user_email_accounts_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_email_accounts_notification_user_id_foreign` FOREIGN KEY (`notification_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_email_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `whatsapp_auth_code` varchar(20) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `whatsapp_verification_code` varchar(10) DEFAULT NULL,
  `whatsapp_code_expires_at` timestamp NULL DEFAULT NULL,
  `whatsapp_verified` tinyint(1) NOT NULL DEFAULT 0,
  `tipo` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'usuarios/default.jpg',
  `codigo_autorizacion` int(11) DEFAULT NULL,
  `pending_changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pending_changes`)),
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT 1,
  `codigo` varchar(100) DEFAULT NULL,
  `tour_bienvenida` tinyint(1) NOT NULL DEFAULT 0,
  `id_empresa` int(11) DEFAULT NULL,
  `id_empresa_firma_contador` int(10) unsigned DEFAULT NULL,
  `id_sucursal` int(11) NOT NULL,
  `id_authorization` bigint(20) unsigned DEFAULT NULL,
  `id_bodega` int(11) NOT NULL,
  `id_canal` int(10) unsigned DEFAULT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `woocommerce_status` varchar(100) DEFAULT NULL,
  `shopify_status` enum('connected','disconnected','connecting') DEFAULT 'disconnected',
  PRIMARY KEY (`id`),
  KEY `users_id_authorization_index` (`id_authorization`),
  KEY `idx_users_id_empresa` (`id_empresa`),
  KEY `users_id_empresa_firma_contador_foreign` (`id_empresa_firma_contador`),
  CONSTRAINT `users_id_authorization_foreign` FOREIGN KEY (`id_authorization`) REFERENCES `authorizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_id_empresa_firma_contador_foreign` FOREIGN KEY (`id_empresa_firma_contador`) REFERENCES `empresas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_ajustes_detalle`;
/*!50001 DROP VIEW IF EXISTS `v_ajustes_detalle`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ajustes_detalle` AS SELECT
 NULL AS `fecha`,
 NULL AS `fecha_ordenar`,
 NULL AS `producto`,
 NULL AS `concepto`,
 NULL AS `stock_inicial`,
 NULL AS `stock_real`,
 NULL AS `ajuste`,
 NULL AS `costo_total`,
 NULL AS `id_empresa`,
 NULL AS `id_sucursal`,
 NULL AS `id_categoria`,
 NULL AS `id_producto` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `venta_impuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta_impuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,2) NOT NULL,
  `id_impuesto` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vi_venta` (`id_venta`,`id_impuesto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `venta_metodos_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venta_metodos_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `total` decimal(9,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_venta_metodos_pago_id_venta` (`id_venta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo_generacion` varchar(255) DEFAULT NULL,
  `sello_mh` varchar(255) DEFAULT NULL,
  `numero_control` varchar(255) DEFAULT NULL,
  `tipo_dte` varchar(255) DEFAULT NULL,
  `prueba_masiva` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el documento fue generado como parte de pruebas masivas',
  `estado` varchar(255) NOT NULL,
  `forma_pago` varchar(255) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `observaciones_shopify` text DEFAULT NULL,
  `recurrente` tinyint(1) DEFAULT 0,
  `frecuencia_recurrencia` varchar(10) DEFAULT NULL,
  `dia_generacion_recurrencia` tinyint(3) unsigned DEFAULT NULL,
  `recurrencia_pausada` tinyint(1) DEFAULT 0,
  `id_venta_plantilla` bigint(20) DEFAULT NULL,
  `periodo_recurrencia` varchar(100) DEFAULT NULL,
  `cotizacion` tinyint(1) DEFAULT 0,
  `descripcion_personalizada` tinyint(1) DEFAULT 0,
  `descripcion_impresion` varchar(500) DEFAULT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `num_cotizacion` int(11) DEFAULT NULL,
  `num_orden` varchar(255) DEFAULT NULL,
  `condicion` varchar(255) DEFAULT 'Contado',
  `detalle_banco` varchar(255) DEFAULT NULL,
  `referencia_pago` varchar(191) DEFAULT NULL,
  `id_wompi_link` varchar(255) DEFAULT NULL,
  `id_wompi_transaccion` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `propina` double(10,2) NOT NULL DEFAULT 0.00,
  `total_costo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sub_total` double(10,2) NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `exenta` decimal(9,2) NOT NULL DEFAULT 0.00,
  `gravada` decimal(10,2) DEFAULT 0.00,
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT 0.00,
  `iva` decimal(10,2) NOT NULL,
  `iva_retenido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `renta_retenida` decimal(10,2) DEFAULT 0.00,
  `iva_percibido` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` double(10,2) NOT NULL DEFAULT 0.00,
  `monto_pago` double(10,2) DEFAULT NULL,
  `cambio` double(10,2) DEFAULT NULL,
  `costo_envio` double(10,2) NOT NULL DEFAULT 0.00,
  `fecha_pago` date DEFAULT NULL,
  `fecha_anulacion` date DEFAULT NULL,
  `tipo_anulacion` int(11) DEFAULT NULL,
  `motivo_anulacion` text DEFAULT NULL,
  `codigo_generacion_remplazo` varchar(255) DEFAULT NULL,
  `referencia_shopify` varchar(191) DEFAULT NULL,
  `referencia_woocommerce` varchar(100) DEFAULT NULL,
  `tipo_renta` varchar(255) DEFAULT NULL,
  `tipo_operacion` varchar(255) DEFAULT NULL,
  `id_canal` int(255) DEFAULT NULL,
  `correlativo` int(11) DEFAULT NULL,
  `num_identificacion` varchar(80) DEFAULT NULL,
  `id_documento` int(11) DEFAULT NULL,
  `id_cliente` int(50) DEFAULT NULL,
  `id_proyecto` int(11) DEFAULT NULL,
  `id_bodega` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_usuario_autorizo_descuento` bigint(20) unsigned DEFAULT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `dte` longtext DEFAULT NULL,
  `dte_s3_key` varchar(512) DEFAULT NULL,
  `dte_migrated_at` timestamp NULL DEFAULT NULL,
  `fe_cr_exoneracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fe_cr_exoneracion`)),
  `dte_invalidacion` longtext DEFAULT NULL,
  `dte_invalidacion_s3_key` varchar(512) DEFAULT NULL,
  `dte_invalidacion_migrated_at` timestamp NULL DEFAULT NULL,
  `tipo_item_export` varchar(255) DEFAULT NULL,
  `importado` tinyint(1) NOT NULL DEFAULT 0,
  `cod_incoterm` varchar(255) DEFAULT NULL,
  `incoterm` varchar(255) DEFAULT NULL,
  `recinto_fiscal` varchar(255) DEFAULT NULL,
  `regimen` varchar(255) DEFAULT NULL,
  `seguro` decimal(10,2) DEFAULT 0.00,
  `flete` decimal(10,2) DEFAULT 0.00,
  `id_empresa` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `num_orden_exento` varchar(50) DEFAULT NULL,
  `proforma` varchar(255) DEFAULT NULL,
  `via` varchar(100) DEFAULT NULL,
  `marcas` varchar(255) DEFAULT NULL,
  `pago_descripcion` varchar(500) DEFAULT NULL,
  `pais_origen` varchar(255) DEFAULT NULL,
  `fob` decimal(10,2) DEFAULT 0.00,
  `cpt` decimal(10,2) DEFAULT 0.00,
  `puntos_ganados` int(11) DEFAULT NULL COMMENT 'nuevo',
  `puntos_canjeados` int(11) DEFAULT NULL COMMENT 'nuevo',
  `descuento_puntos` decimal(8,2) DEFAULT NULL COMMENT 'nuevo',
  `currency_code` char(3) DEFAULT NULL,
  `exchange_rate` decimal(18,5) NOT NULL DEFAULT 1.00000,
  `exchange_rate_date` date DEFAULT NULL,
  `equivalent_total` decimal(18,5) DEFAULT NULL,
  `equivalent_iva` decimal(18,5) DEFAULT NULL,
  `clave_sustituida` varchar(50) DEFAULT NULL,
  `codigo_nota_anulacion` varchar(2) DEFAULT NULL,
  `clave_rechazada` varchar(50) DEFAULT NULL,
  `id_corte` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_referencia_woocommerce_empresa` (`referencia_woocommerce`,`id_empresa`),
  KEY `idx_ventas_estado` (`estado`),
  KEY `idx_ventas_empresa_fecha` (`id_empresa`,`fecha`,`estado`),
  KEY `idx_ventas_empresa_sucursal_fecha` (`id_empresa`,`id_sucursal`,`fecha`,`estado`),
  KEY `idx_ventas_fecha_estado` (`fecha`,`estado`),
  KEY `idx_ventas_cliente_estado_fecha` (`id_cliente`,`estado`,`fecha`),
  KEY `idx_ventas_cliente_fecha_estado` (`id_cliente`,`fecha`,`estado`),
  KEY `idx_ventas_export_base` (`id_empresa`,`cotizacion`,`fecha`),
  KEY `idx_ventas_id_vendedor` (`id_vendedor`),
  KEY `idx_ventas_id_usuario` (`id_usuario`),
  KEY `idx_ventas_id_documento` (`id_documento`),
  KEY `idx_ventas_usuario_empresa_cotizacion_fecha` (`id_empresa`,`id_usuario`,`cotizacion`,`fecha` DESC,`id` DESC),
  KEY `idx_ventas_empresa_cotizacion_sucursal_fecha` (`id_empresa`,`cotizacion`,`id_sucursal`,`fecha`),
  KEY `idx_ventas_empresa_cotizacion_cliente_fecha` (`id_empresa`,`cotizacion`,`id_cliente`,`fecha`),
  KEY `idx_ventas_empresa_cotizacion_vendedor_fecha` (`id_empresa`,`cotizacion`,`id_vendedor`,`fecha`),
  KEY `idx_ventas_empresa_sucursal_created` (`id_empresa`,`id_sucursal`,`created_at`),
  KEY `idx_ventas_empresa_num_identificacion` (`id_empresa`,`num_identificacion`),
  KEY `idx_ventas_empresa_cotiz_usuario_fecha` (`id_empresa`,`cotizacion`,`id_usuario`,`fecha`),
  FULLTEXT KEY `ft_ventas_buscador` (`num_orden`,`observaciones`,`forma_pago`,`estado`,`numero_control`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `whatsapp_number` varchar(20) NOT NULL,
  `id_empresa` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `message_type` enum('incoming','outgoing') NOT NULL,
  `message_content` text NOT NULL,
  `is_bot_response` tinyint(1) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_messages_whatsapp_number_created_at_index` (`whatsapp_number`,`created_at`),
  KEY `whatsapp_messages_id_empresa_created_at_index` (`id_empresa`,`created_at`),
  KEY `whatsapp_messages_message_type_index` (`message_type`),
  CONSTRAINT `whatsapp_messages_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `whatsapp_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `whatsapp_number` varchar(20) NOT NULL,
  `id_empresa` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `status` enum('active','pending_code','pending_user','pending_verification','connected','blocked','disconnected') DEFAULT 'pending_code',
  `code_attempts` int(11) NOT NULL DEFAULT 0,
  `user_attempts` int(11) NOT NULL DEFAULT 0,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `session_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`session_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `disconnected_by` varchar(191) DEFAULT NULL,
  `disconnected_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `whatsapp_sessions_whatsapp_number_unique` (`whatsapp_number`),
  KEY `whatsapp_sessions_id_empresa_foreign` (`id_empresa`),
  KEY `whatsapp_sessions_whatsapp_number_status_index` (`whatsapp_number`,`status`),
  CONSTRAINT `whatsapp_sessions_id_empresa_foreign` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zz_inv306_bodegas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zz_inv306_bodegas` (
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zz_inv306_control`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zz_inv306_control` (
  `fase` varchar(10) NOT NULL,
  `filas_totales` bigint(20) DEFAULT NULL,
  `filas_vivas` bigint(20) DEFAULT NULL,
  `grupos_vivos` bigint(20) DEFAULT NULL,
  `suma_stock_vivas` decimal(20,2) DEFAULT NULL,
  `huella_filas_que_usa_la_app` bigint(20) DEFAULT NULL,
  `tomada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`fase`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `input_rfm`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `input_rfm` AS select `t0`.`id_empresa` AS `id_empresa`,`t0`.`id_cliente` AS `id_cliente`,`t0`.`fecha` AS `fecha`,`t0`.`sub_total` AS `sub_total` from (`ventas` `t0` join `empresas` `t1` on(`t0`.`id_empresa` = `t1`.`id`)) where 1 = 1 and `t1`.`activo` = 1 and `t0`.`id_cliente` not in (0,1) and `t0`.`sub_total` > 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `producto_proveedor`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `producto_proveedor` AS select `t0`.`id_producto` AS `id_producto`,`t0`.`id_proveedor` AS `id_proveedor`,`t1`.`id_empresa` AS `id_empresa`,`t1`.`nombre` AS `Proveedor`,`t2`.`nombre` AS `Producto` from ((`producto_proveedores` `t0` left join `proveedores` `t1` on(`t0`.`id_proveedor` = `t1`.`id`)) left join `productos` `t2` on(`t0`.`id_producto` = `t2`.`id`)) group by `t0`.`id_producto`,`t0`.`id_proveedor`,`t1`.`id_empresa`,`t1`.`nombre`,`t2`.`nombre` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `productos_x_proveedor`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `productos_x_proveedor` AS select `t4`.`id` AS `id_empresa`,`t3`.`id` AS `id_proveedor`,`t3`.`nombre` AS `Proveedor`,`t2`.`id` AS `id_producto`,`t2`.`nombre` AS `Producto` from ((((`compras` `t0` join `detalles_compra` `t1` on(`t0`.`id` = `t1`.`id_compra`)) join `productos` `t2` on(`t1`.`id_producto` = `t2`.`id`)) join `proveedores` `t3` on(`t0`.`id_proveedor` = `t3`.`id`)) join `empresas` `t4` on(`t0`.`id_empresa` = `t4`.`id`)) group by `t4`.`id`,`t3`.`id`,`t3`.`nombre`,`t2`.`id`,`t2`.`nombre` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_ajustes_detalle`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY DEFINER */
/*!50001 VIEW `v_ajustes_detalle` AS select date_format(`a`.`created_at`,'%m/%d/%Y') AS `fecha`,`a`.`created_at` AS `fecha_ordenar`,`p`.`nombre` AS `producto`,`a`.`concepto` AS `concepto`,`a`.`stock_actual` AS `stock_inicial`,`a`.`stock_real` AS `stock_real`,`a`.`ajuste` AS `ajuste`,round(`a`.`costo` * abs(`a`.`ajuste`),2) AS `costo_total`,`a`.`id_empresa` AS `id_empresa`,`a`.`id_bodega` AS `id_sucursal`,`p`.`id_categoria` AS `id_categoria`,`a`.`id_producto` AS `id_producto` from (`ajustes` `a` join `productos` `p` on(`p`.`id` = `a`.`id_producto`)) where `a`.`estado` = 'Confirmado' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2022_01_03_152244_empresas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2022_01_03_152313_proveedores_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2022_01_03_152334_documentos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2022_01_03_152352_clientes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2022_01_03_152411_productos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2022_01_03_152457_ventas_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2022_01_03_152543_detalles_venta_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2022_01_03_152912_compras_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2022_01_03_152927_detalles_compra_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2022_01_03_153017_promociones_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2022_01_03_153040_detalles_promocion_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2022_01_03_153058_categorias_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2018_08_08_100000_create_telescope_entries_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2019_08_19_000000_create_failed_jobs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_01_09_150759_create_planillas_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_01_09_151343_create_planilla_detalles_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_01_09_132507_create_departamentos_de_empresa_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_01_09_132518_create_cargos_de_empresa_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_01_09_144000_create_empleados_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_02_11_095735_create_contacto_emergencias_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_02_11_101236_create_documento_empleados_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_02_11_101345_create_historial_contratos_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_02_11_101445_create_historial_bajas_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_02_27_140338_create_conversations_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_02_27_140356_create_messages_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_03_03_105730_create_ia_metrica_mensual_empresas_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_03_04_114525_create_ia_metricas_sucursales_v3_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_03_04_115557_create_ia_metricas_historial_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_03_12_162429_create_funcionalidades_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_03_12_162437_create_empresa_funcionalidades_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_03_14_103204_create_costos_ia_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_03_20_132126_add_fecha_baja_to_empleados_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_04_16_142607_add_codigo_cliente_to_clientes_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_01_10_165941_create_plans_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_01_22_162549_create_metodos_pago_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_01_14_160725_create_orden_pagos_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2017_10_12_100007_create_suscripciones_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_02_07_163603_add_metodo_pago_to_empresas_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_03_09_204252_add_pago_recurrente_to_empresas_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_03_11_155659_add_tipo_pago_to_ordenes_pago_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_04_29_113121_add_id_venta_to_ordenes_pago_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_04_29_113059_add_id_cliente_to_empresas_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_04_29_113108_add_id_documento_to_empresas_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_04_29_113115_add_id_producto_to_planes_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_04_30_155408_add_alerta_suscripcion_to_empresas_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_04_24_124528_add_es_retaceo_to_gastos_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_04_24_131023_add_es_retaceo_to_compras_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_04_29_170621_add_sucursales_to_reportes_configuracion_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_03_24_105639_add_prueba_masiva_to_ventas_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_04_04_140000_create_trabajos_pendientes_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2025_05_07_152253_create_estados_paises_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2025_05_07_105301_add_area_empresa_to_egresos_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_05_14_105202_add_nombre_reporte_to_reporte_configuraciones_table',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_05_27_213346_change_estado_column_in_ordenes_produccion_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_06_01_095957_add_codigo_to_empresas_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_06_01_100023_create_whatsapp_sessions_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_06_01_100154_create_whatsapp_messages_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_06_14_170548_add_whatsapp_verification_to_users_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_06_17_150728_add_disconnect_fields_to_whatsapp_sessions_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_06_17_150532_update_whatsapp_sessions_status_enum',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2025_06_04_103354_add_custom_empresa_column_to_empresas_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2025_06_04_153403_add_num_identificacion_to_egresos_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2025_06_04_161047_add_num_identificacion_to_ventas_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2025_06_04_161136_add_num_identificacion_to_compras_table',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_06_04_135735_change_otros_impuestos_to_json_in_egresos_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_06_02_092423_create_areas_empresa_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_06_03_115212_change_area_empresa_to_id_area_empresa_in_egresos_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_06_27_155347_add_prueba_masiva_to_devoluciones_venta_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_07_03_144629_make_nit_nullable_in_empleados_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2017_10_12_100007_create_caja_cortes_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2017_10_12_100007_create_caja_documentos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2017_10_12_100007_create_cajas_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2017_10_12_100007_create_categoria_subcategorias_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2017_10_12_100007_create_categorias_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2017_10_12_100007_create_clientes_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2017_10_12_100007_create_compra_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2017_10_12_100007_create_compra_devolucion_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2017_10_12_100007_create_compras_devoluciones_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2017_10_12_100007_create_compras_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2017_10_12_100007_create_empresa_activos_categorias_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2017_10_12_100007_create_empresa_activos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2017_10_12_100007_create_empresa_bancos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2017_10_12_100007_create_empresa_canales_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2017_10_12_100007_create_empresa_dashboards_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2017_10_12_100007_create_empresa_formas_de_pago_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2017_10_12_100007_create_empresa_impuestos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2017_10_12_100007_create_empresa_presupuestos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2017_10_12_100007_create_empresa_sucursal_bodegas_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2017_10_12_100007_create_empresa_sucursal_caja_chica_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2017_10_12_100007_create_empresa_sucursal_cajas_chicas_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2017_10_12_100007_create_empresa_sucursales_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2017_10_12_100007_create_empresa_wompi_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2017_10_12_100007_create_empresas_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2017_10_12_100007_create_evento_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2017_10_12_100007_create_eventos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2017_10_12_100007_create_gastos_categorias_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2017_10_12_100007_create_gastos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2017_10_12_100007_create_notificaciones_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2017_10_12_100007_create_password_resets_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2017_10_12_100007_create_producto_ajustes_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2017_10_12_100007_create_producto_composiciones_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2017_10_12_100007_create_producto_imagenes_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2017_10_12_100007_create_producto_inventarios_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2017_10_12_100007_create_producto_kardex_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2017_10_12_100007_create_producto_precios_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2017_10_12_100007_create_producto_precios_usuarios_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2017_10_12_100007_create_producto_promociones_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2017_10_12_100007_create_producto_sucursales_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2017_10_12_100007_create_producto_traslado_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2017_10_12_100007_create_producto_traslados_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2017_10_12_100007_create_productos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2017_10_12_100007_create_proveedores_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2017_10_12_100007_create_transacciones_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2017_10_12_100007_create_transporte_flete_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2017_10_12_100007_create_transporte_fletes_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2017_10_12_100007_create_transporte_flotas_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2017_10_12_100007_create_transporte_mantenimiento_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2017_10_12_100007_create_transporte_mantenimientos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2017_10_12_100007_create_users_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2017_10_12_100007_create_usuario_accesos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2017_10_12_100007_create_venta_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2017_10_12_100007_create_venta_devolucion_detalles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2017_10_12_100007_create_ventas_abonos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2017_10_12_100007_create_ventas_devoluciones_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2017_10_12_100007_create_ventas_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2025_01_07_111225_create_permission_tables',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2025_01_10_140311_create_modules_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2025_01_10_140425_create_submodules_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2025_01_10_140453_create_module_permissions_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2025_01_14_134428_create_permission_revocations_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2025_05_07_112100_add_area_empresa_to_egresos_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2025_06_10_135835_add_id_empresa_to_roles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2025_06_13_101602_create_authorization_types_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2025_06_13_101610_create_user_authorization_types_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2025_06_13_101617_create_authorizations_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2025_06_20_140535_add_id_authorization_to_compras_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2025_06_23_142416_add_id_authorization_to_orden_compras_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2025_06_24_094723_add_id_authorization_to_users_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2025_06_24_104753_add_pending_changes_to_users_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2025_07_10_110004_add_correlativo_to_partidas_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2025_07_16_184820_add_seal_and_signature_fields_to_empresas_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2025_07_19_165342_add_mostrar_sello_firma_cotizacion_to_empresas_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2025_07_22_145205_create_empresa_configuracion_planilla_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2025_07_22_153617_add_cod_pais_to_empresas_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2025_07_24_214243_add_conceptos_personalizados_to_planilla_detalles_table',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2025_07_25_172805_add_datos_bancarios_to_empleados_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2025_07_26_084356_make_direccion_telefono_nullable_in_empleados_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2025_08_08_145610_change_dimensiones_column_type_in_productos_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2025_08_09_125157_add_shopify_integration_columns',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2025_08_09_164929_add_shopify_shopifyids_to_productos_table',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2025_08_16_235737_add_shopify_image_id_to_productos_imagenes_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2025_08_17_174950_add_syncing_flag_to_productos_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2025_10_01_155312_add_indexes_to_kardex_table',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2025_10_02_151532_create_kardex_masivo_queue_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2025_10_10_163034_ensure_referencia_shopify_column_in_ventas_table',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2025_10_21_103954_add_importacion_productos_shopify_to_empresas_table',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2025_10_21_171650_add_nombre_variante_to_productos_table',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2025_10_22_095052_add_missing_columns_to_trabajos_pendientes_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2025_10_24_163628_add_shopify_customer_id_to_clientes_table',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2025_10_30_164954_add_prueba_masiva_to_egresos_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2025_11_04_000001_add_precio_sin_iva_to_productos_table',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2025_11_04_000002_add_precio_sin_iva_to_detalles_venta_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2025_11_04_000003_add_descripcion_completa_to_productos_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2025_11_03_135733_add_shopify_sync_bidirectional_to_empresas_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2025_11_17_112653_create_abonos_gastos_table',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2025_12_01_102650_add_codigo_promocional_to_empresas_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2025_12_11_195339_add_nuevos_campos_suscripciones_to_empresa_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2025_12_12_131150_create_promocionales_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2025_12_17_095006_add_vendedor_asignado_to_empresas_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2025_12_22_143020_add_configuracion_descuentos_to_empleados_table',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2025_12_18_160001_create_aguinaldo_detalles_table',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2026_01_09_170110_add_propina_porcentaje_to_empresas_table',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_01_22_133902_add_aplicacion_fields_to_impuestos_table',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_02_02_123024_add_detalle_horas_extra_to_planilla_detalles_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2026_02_04_134923_add_importado_to_ventas_table',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_02_18_132324_unique_dui_por_empresa_empleados',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_02_20_000000_add_credito_fields_to_clientes_table',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2025_09_05_150954_create_tipos_cliente_base_table',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2025_09_05_151004_create_tipos_cliente_empresa_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2025_09_05_151011_create_puntos_cliente_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2025_09_05_151018_create_transacciones_puntos_table',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2025_09_05_151024_create_consumo_puntos_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2025_09_05_151126_add_puntos_fields_to_ventas_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2025_09_05_151138_add_tipo_cliente_id_to_clientes_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2025_09_23_214407_create_cliente_metricas_rfm_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2025_09_23_214614_create_cliente_productos_top_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2025_09_23_214636_create_cliente_ventas_mensuales_table',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2025_09_23_214701_create_cliente_fidelizacion_snapshot_table',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2025_09_23_214725_create_cliente_categorias_preferidas_table',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2025_09_23_214742_create_cliente_actividad_reciente_table',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2025_09_24_104445_add_indexes_for_cliente360_performance',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2025_10_03_140928_add_abc_classification_and_trends_to_cliente_metricas_rfm_table',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2025_09_24_170101_create_cliente_notas_table',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2025_09_24_1703001_create_cliente_visitas_table',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2025_09_24_1704001_create_cliente_notas_categorias_table',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2026_02_24_130538_add_abonos_to_planilla_detalles_table',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2026_02_26_000000_add_woocommerce_sync_fields_to_productos_table',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2026_02_27_000000_add_referencia_woocommerce_to_ventas_table',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2026_03_03_103012_create_prestamos_empleados_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2026_03_03_103342_create_prestamo_movimientos_table',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2026_03_09_000001_add_iva_to_detalles_devolucion_compra_table',121);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2026_03_06_000000_add_componente_quimico_to_productos_table',122);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2026_03_10_082030_add_fulltext_indexes_ventas_clientes',123);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2026_03_10_100000_create_detalle_egresos_table',124);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2026_03_10_120000_add_tipo_gravado_to_detalle_egresos_table',125);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2026_03_19_11102_add_total_viaticos_to_planillas_table',126);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2026_03_19_120139_add_viaticos_to_planilla_detalles_table',127);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2026_03_21_083001_add_id_empresa_to_cliente_fidelizacion_snapshot',128);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2026_03_18_100001_create_mesas_table',129);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2026_03_18_100002_create_sesiones_mesa_table',130);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2026_03_18_100003_create_orden_detalle_restaurante_table',131);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2026_03_18_100004_create_comandas_restaurante_table',132);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2026_03_18_100005_create_comanda_detalle_restaurante_table',133);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2026_03_18_100006_create_division_cuenta_restaurante_table',134);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2026_03_18_100007_create_pre_cuentas_restaurante_table',135);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2026_03_18_100008_create_pre_cuenta_orden_detalle_table',136);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2026_03_18_100009_create_reservas_restaurante_table',137);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2026_03_18_100010_add_genera_comanda_to_productos_table',138);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2026_03_24_100000_create_restaurante_pedidos_table',139);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2026_03_24_100001_create_restaurante_pedido_detalles_table',140);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2026_03_24_120000_add_propina_to_pre_cuentas_restaurante_table',141);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2026_03_30_032312_add_indexes_to_ventas_table_for_export_optimization',142);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2026_03_31_120000_add_inventario_pedido_restaurante',143);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2026_03_31_024515_add_comentarios_to_suscripciones_table',144);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2026_04_07_120000_add_webhook_paquete_venta_to_empresas_table',145);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (284,'2026_03_27_120000_add_cabys_fields_to_productos_table',146);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (285,'2026_03_27_162000_widen_empresa_cabys_actividad_giro_columns',147);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (286,'2026_04_10_170000_ensure_descripcion_cabys_on_productos_table',148);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (287,'2026_04_17_033144_add_acceso_temporal_hasta_to_suscripciones_table',149);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (288,'2026_04_24_000000_add_woocommerce_sync_mode_to_empresas_table',150);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (289,'2026_04_24_120000_create_retaceo_compras_table',151);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (290,'2026_04_28_100000_pedidos_canal_inventario_columns',152);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (291,'2026_05_04_120000_add_restringir_gastos_supervisor_limitado_to_empresas_table',153);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (292,'2026_05_05_120000_add_estado_resultados_prefijos_to_contabilidad_configuracion_table',154);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (293,'2026_05_05_120000_add_dte_s3_columns_to_ventas_and_compras_tables',155);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (294,'2026_05_11_120000_add_iva_retenido_to_egresos_and_detalle_egresos',156);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (295,'2026_05_07_000001_create_producto_presentaciones_table',157);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (296,'2026_05_08_161252_add_id_presentacion_to_detalles_table',158);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (297,'2026_05_11_000002_create_transformaciones_table',159);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (298,'2026_05_11_000003_create_transformacion_detalles_table',160);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (299,'2026_05_14_000001_add_soft_deletes_to_catalogo_cuentas_table',161);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (300,'2026_05_12_234135_add_id_cliente_to_suscripciones_table',162);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (301,'2026_05_14_080218_add_tipo_factura_to_suscripciones_table',163);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (302,'2026_05_14_120000_add_cantidad_to_pre_cuenta_orden_detalle_table',164);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (303,'2026_05_14_120000_restaurante_mejoras_operacion',165);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (304,'2026_05_14_130000_add_eliminacion_motivo_to_comandas_restaurante',166);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (305,'2026_05_19_081041_add_observaciones_shopify_to_ventas_table',167);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (306,'2026_05_25_161704_add_dia_pago_to_suscripciones_table',168);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (307,'2026_05_28_120000_add_restringir_compras_supervisor_limitado_to_empresas_table',169);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (308,'2026_05_28_130000_add_correlativo_to_abonos_gastos_table',170);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (309,'2025_01_09_133520_create_empresa_departamento_table',171);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (310,'2025_01_09_133528_create_empresa_cargo_table',171);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (311,'2026_05_28_120000_add_fe_cr_exoneracion_to_detalles_venta_table',172);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (312,'2026_05_27_000001_create_compra_impuestos_table',173);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (313,'2026_05_26_120000_add_otros_cargos_to_compras_table',174);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (314,'2026_05_27_120000_add_otros_cargos_to_egresos_table',175);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (315,'2026_03_20_100001_create_user_email_accounts_table',176);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (316,'2026_03_20_100002_create_dte_documents_table',177);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (317,'2026_03_20_100003_create_sync_logs_table',178);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (318,'2026_03_20_100004_create_dte_tipo_mapeo_table',179);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (319,'2026_03_23_190000_add_failure_details_to_sync_logs_table',180);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (320,'2026_03_23_210000_add_destino_to_dte_documents_table',181);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (321,'2026_03_23_210001_backfill_destino_in_dte_documents',182);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (322,'2026_05_29_100000_add_notification_user_id_to_user_email_accounts_table',183);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (323,'2026_05_29_120000_add_anulado_to_dte_documents_processing_status',184);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (324,'2026_06_02_120000_restaurante_zonas_y_comandas_pedido',185);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (325,'2026_06_02_174519_change_concepto_column_type_in_detalle_egresos_table',186);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (326,'2026_06_02_100002_add_codigo_mh_to_impuestos_table',187);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (327,'2026_06_08_1234021_add_dui_homologado_to_empleados_table',188);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (328,'2026_06_02_100001_create_producto_impuestos_table',189);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (329,'2026_07_01_120000_add_proyecto_categoria_to_dte_documents',190);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (330,'2026_07_01_120001_add_dtes_duplicates_to_sync_logs',191);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (331,'2026_07_02_120000_add_costa_rica_fields_to_dte_module',192);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (332,'2026_07_02_160000_widen_iva_column_on_egresos',193);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (333,'2026_07_03_120000_create_detalle_venta_lotes_table',194);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (334,'2026_07_03_120001_add_lote_id_to_kardexs_table',195);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (335,'2026_07_06_120002_create_pedido_detalle_lotes_table',196);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (336,'2026_06_09_164134_create_integraciones_table',197);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (337,'2026_06_10_162419_create_direcciones_origen_table',198);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (338,'2026_06_16_165612_create_boxful_shipments_table',199);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (339,'2026_06_16_165702_create_boxful_parcels_table',200);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (340,'2026_06_21_111722_add_id_paquete_to_restaurante_pedido_detalles_table',201);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (341,'2026_07_03_230907_create_audits_table',202);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (342,'2026_07_03_231000_add_empresa_module_to_audits_table',203);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (343,'2026_07_09_120000_ensure_caja_operativa_tables_exist',204);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (344,'2026_07_15_000000_add_woocommerce_api_key_to_empresas_table',205);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (345,'2026_07_15_000001_add_tipo_fiscal_to_clientes_table',206);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (346,'2026_07_20_160000_add_id_cuenta_cuenta_a_terceros_to_contabilidad_configuracion_table',207);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (347,'2026_06_18_000001_add_origen_stock_to_detalles_venta_table',208);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (348,'2026_07_10_140000_add_iva_retenido_to_compras_table',209);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (349,'2026_07_17_120000_add_es_consigna_to_compras_table',210);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (350,'2026_07_28_173000_create_suscripcion_bajas_table',211);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (351,'2026_07_30_000001_create_reporte_exportaciones_table',212);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (352,'2026_07_30_000002_create_jobs_table',213);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (353,'2026_08_04_100000_add_numero_emision_to_documentos_table',214);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (354,'2026_08_04_120000_add_servido_to_comandas_restaurante_estado',215);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (355,'2026_08_04_203500_add_img_to_categorias_table',216);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (356,'2026_07_31_210000_create_empresa_configuracion_table',217);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (357,'2026_08_03_150000_create_pais_configuracion_table',218);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (358,'2026_08_09_120000_add_unique_active_sesion_mesa',219);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (359,'2026_08_09_140000_add_id_empresa_to_comandas_restaurante',220);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (360,'2026_08_09_140001_create_restaurante_idempotency_keys_table',221);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (361,'2026_08_10_100000_add_fase3_restaurante_performance_indexes',222);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (362,'2026_08_10_150000_create_restaurante_side_effects_table',223);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (363,'2026_08_14_141100_add_id_grupo_to_traslados_table',224);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (364,'2026_07_27_160000_create_comision_categoria_config_table',225);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (365,'2026_07_27_160001_create_comision_subcategoria_config_table',226);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (366,'2026_07_27_160002_create_comision_periodos_table',227);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (367,'2026_07_27_160003_create_comision_movimientos_table',228);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (368,'2026_07_27_160004_create_comision_liquidaciones_table',229);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (369,'2026_07_27_170000_create_gift_cards_table',230);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (370,'2026_07_27_170001_create_gift_card_redenciones_table',231);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (371,'2026_07_27_180000_create_bono_reglas_table',232);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (372,'2026_07_27_180001_create_bono_generados_table',233);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (373,'2026_07_27_180002_create_bono_evaluaciones_table',234);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (374,'2026_07_28_120000_add_reversed_at_to_gift_card_redenciones_table',235);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (375,'2026_07_28_140000_add_alcance_to_bono_reglas_table',236);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (376,'2026_08_14_120000_create_comision_reglas_and_extend_configs',237);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (377,'2026_08_14_121000_adjust_comision_movimientos_redencion_unique',238);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (378,'2026_08_14_121100_comision_movimientos_redencion_unique_ifnull',239);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (379,'2026_08_14_121200_backfill_id_regla_on_comision_movimientos',240);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (380,'2026_08_14_130000_extend_bono_reglas_alcance',241);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (381,'2026_08_14_140000_add_abono_origen_to_comision_movimientos',242);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (382,'2026_08_17_140000_add_nombre_pagador_to_pre_cuentas_restaurante',243);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (383,'2026_08_17_140100_add_id_presentacion_to_orden_detalle_restaurante',244);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (384,'2026_08_18_171500_add_giro_to_sucursales_table',245);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (385,'2026_08_07_100000_add_campos_cr_to_empleados_table',246);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (386,'2026_08_24_120000_add_datos_bancarios_to_proveedores_table',247);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (387,'2026_08_24_150000_create_credito_contratos_tables',248);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (388,'2026_08_24_160000_add_id_documento_to_credito_contratos',249);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (389,'2026_08_26_000000_add_shopify_variants_to_productos_table',250);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (390,'2026_08_26_000001_add_src_hash_to_productos_imagenes_table',251);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (391,'2026_08_31_100000_actualizar_cat020_paises_iso',252);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (392,'2026_08_28_145513_add_es_pensionado_to_empleados_table',253);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (393,'2026_09_01_120000_create_detalle_compra_lotes_table',254);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (394,'2026_09_01_150000_add_descuento_porcentaje_to_restaurante_pedido_detalles_table',255);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (395,'2026_09_01_194500_add_predeterminado_to_canales_table',256);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (396,'2026_09_01_194501_add_id_canal_to_users_table',257);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (397,'2026_09_01_153000_add_tipo_factura_preferida_to_clientes_table',258);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (398,'2026_09_08_100000_add_forward_to_user_email_accounts_provider',259);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (399,'2026_09_08_100001_create_email_inboxes_table',260);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (400,'2026_09_08_120000_add_verification_to_email_inboxes',261);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (401,'2026_09_08_000000_add_shopify_token_auto_renew_to_empresas_table',262);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (402,'2026_09_07_160000_create_prestamos_empresa_tables',263);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (403,'2026_09_08_120000_add_referencia_to_prestamo_pagos_table',264);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (404,'2026_09_10_210000_add_mostrar_en_restaurante_to_categorias_and_productos',265);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (405,'2026_09_14_000001_create_activos_fijos_tables',266);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (406,'2026_09_14_000002_create_activos_depreciaciones_table',267);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (407,'2026_09_14_000003_add_capitalizacion_egresos',268);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (408,'2026_09_14_000004_create_activos_movimientos_table',269);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (409,'2026_09_14_000005_add_capitalizacion_detalles_compra',270);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (410,'2026_09_16_000001_create_empresa_activos_configuracion_table',271);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (411,'2026_09_16_000002_create_pais_activos_categorias_plantilla_table',272);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (412,'2026_09_17_120000_add_iva_split_and_abonos_cartera_to_contabilidad_configuracion',273);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (413,'2026_09_19_151000_add_ubicacion_dgt_to_sucursales_table',274);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (414,'2026_09_22_120000_add_id_proveedor_to_prestamos_empresa',275);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (415,'2026_09_15_000000_create_shopify_locations_table',276);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (416,'2026_09_17_150000_add_shopify_sync_ventas_to_empresas_table',277);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (417,'2026_09_24_121800_add_nombre_comercial_to_clientes_y_proveedores',278);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (418,'2026_09_25_120000_restaurante_pantallas_comanda',279);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (419,'2026_09_30_120000_comanda_estado_tiempos',280);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (420,'2026_09_30_150000_add_parent_id_to_funcionalidades_table',281);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (421,'2026_10_01_120000_create_analisis_ventas_mensual_queue_table',282);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (422,'2026_09_30_160000_create_clinica_pacientes_tables',283);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (423,'2026_09_30_180000_create_clinica_responsables_y_profesionales',284);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (424,'2026_10_05_173900_create_campanias_table',285);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (425,'2026_10_06_091600_create_aliados_table',286);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (426,'2026_10_06_092500_add_aliado_to_empresas_and_promocionales_table',287);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (427,'2026_10_02_120000_add_sucursal_apertura_to_clinica_expedientes',288);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (428,'2026_10_05_110000_create_clinica_consultas',289);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (429,'2026_10_06_140000_add_addendum_and_id_evento_to_clinica_consultas',290);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (430,'2026_10_05_100000_create_clinica_historial_eventos',291);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (431,'2026_10_06_160000_create_clinica_tratamientos_tables',292);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (432,'2026_10_06_170000_create_clinica_diagnosticos_tables',293);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (433,'2026_10_05_170000_contador_portal_schema',294);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (434,'2026_10_12_120000_create_contador_empresa_invitaciones_table',295);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (435,'2026_10_13_120000_add_id_empresa_firma_contador_to_users_table',296);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (436,'2026_10_16_000000_add_nombre_comercial_to_productos_table',297);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (437,'2026_10_22_120000_add_ventas_limitado_role',298);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (438,'2026_10_24_000001_extend_reporte_exportaciones_contabilidad',299);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (439,'2026_08_10_083001_add_versioning_to_planillas_table',300);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (440,'2026_11_03_140000_add_referencia_shopify_to_cotizacion_ventas',301);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (441,'2026_11_06_120000_add_otras_entradas_salidas_permissions',302);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (442,'2026_11_06_143000_extend_otras_entradas_salidas_permissions_and_ver_costos',303);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (443,'2026_11_06_153000_sync_productos_module_permissions_from_config',304);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (444,'2026_11_09_210000_add_auditoria_permissions',305);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (445,'2026_11_10_104800_add_indicador_dash_indexes',306);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (446,'2026_11_12_170000_add_proveedores_list_index',307);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (447,'2026_11_09_090000_corregir_codigos_municipios_cabanas_cat013',308);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (448,'2026_11_09_140000_agregar_evento_retorno_documentos_sv',309);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (449,'2026_11_11_120000_add_dte_to_devoluciones_compra_table',310);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (450,'2026_11_13_120000_add_spu_fields_to_empresas_sucursales_empleados',311);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (451,'2026_11_11_100000_create_consigna_movimientos_table',312);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (452,'2026_11_11_110000_add_inventario_consigna_kardex_permissions',313);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (453,'2026_11_11_120000_add_lote_id_to_consigna_movimientos_table',314);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (454,'2026_11_12_100000_create_consigna_custodias_table',315);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (455,'2026_11_17_112515_add_empresa_correo_to_clientes_table',316);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (456,'2026_11_20_120000_grant_inteligencia_negocios_to_gerente_roles',317);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (457,'2026_11_21_120000_add_precio_con_iva_to_detalles_cotizacion_ventas',318);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (458,'2026_10_08_100000_create_clinica_producto_perfiles',319);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (459,'2026_10_13_100000_create_clinica_recetas_tables',320);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (460,'2026_10_13_110000_create_clinica_vacunas_tables',321);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (461,'2026_10_28_160000_create_modelado_tables',322);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (462,'2026_10_29_160000_add_monto_to_modelado_clasificaciones',323);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (463,'2026_10_30_170000_add_pct_dia_to_modelado_clasificaciones',324);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (464,'2026_10_30_180000_create_modelado_presupuesto_sucursal_lineas',325);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (465,'2026_11_17_160000_add_proveedor_to_modelado_clasificaciones',326);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (466,'2026_11_17_190000_create_nif_mapeo_gastos',327);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (467,'2026_11_18_090000_add_sucursal_to_modelado_clasificaciones',328);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (468,'2026_11_21_000000_add_suspendido_to_modelado_clasificaciones',329);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (469,'2026_11_21_010000_create_modelado_presupuesto_area_lineas',330);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (470,'2026_11_21_010500_create_modelado_presupuesto_departamento_lineas_table',331);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (471,'2025_01_10_170127_create_suscripciones_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (472,'2025_01_28_000000_add_unique_inventario_producto_bodega_and_remove_duplicates',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (473,'2025_05_02_155738_add_otros_impuestos_to_egresos_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (474,'2025_07_07_000001_create_saldos_mensuales_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (475,'2025_08_07_155225_add_indexes_for_product_import_optimization',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (476,'2025_09_05_151131_add_indexes_to_fidelizacion_tables',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (477,'2025_09_24_092057_add_propina_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (478,'2025_12_18_160000_create_aguinaldos_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (479,'2025_12_20_105005_add_tiempo_pago_to_clientes_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (480,'2026_01_08_170428_add_anulacion_fields_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (481,'2026_01_09_092601_create_lotes_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (482,'2026_01_09_092613_add_inventario_por_lotes_to_productos_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (483,'2026_01_09_092620_add_lote_id_to_detalles_compra_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (484,'2026_01_09_092632_add_lote_id_to_detalles_venta_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (485,'2026_01_09_123933_add_lote_id_to_ajustes_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (486,'2026_01_12_150130_add_lote_id_to_traslados_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (487,'2026_01_13_083310_add_lote_id_to_devoluciones_compra_detalles_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (488,'2026_01_13_083329_add_lote_id_to_devoluciones_venta_detalles_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (489,'2026_01_15_085346_add_lote_id_to_inventario_entrada_detalles_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (490,'2026_01_15_085429_add_lote_id_to_inventario_salida_detalles_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (491,'2026_01_15_092946_add_id_vendedor_to_clientes_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (492,'2026_02_02_000000_add_exportacion_fields_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (493,'2026_02_03_000001_add_tipo_gravado_and_sub_total_to_detalles_venta_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (494,'2026_02_03_000002_add_cambiar_tipo_impuesto_venta_to_empresas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (495,'2026_02_25_000002_add_porcentaje_impuesto_to_detalles_venta_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (496,'2026_02_25_100001_add_porcentaje_impuesto_to_productos_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (497,'2026_03_05_100001_add_porcentaje_impuesto_to_detalles_compra_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (498,'2026_03_06_175100_add_iva_to_detalles_compra_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (499,'2026_04_08_120000_add_fe_cr_exoneracion_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (500,'2026_05_13_120000_add_fiscal_year_fields_to_empresas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (501,'2026_05_13_120001_create_ejercicios_fiscales_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (502,'2026_05_26_120000_create_notas_estados_financieros_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (503,'2026_07_06_120000_create_detalle_salida_lotes_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (504,'2026_07_06_120001_create_traslado_lotes_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (505,'2026_08_06_110000_add_currency_fields_to_ventas_compras_gastos',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (506,'2026_08_10_081142_add_planilla_accounts_to_contabilidad_configuracion_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (507,'2026_08_31_120000_add_referencia_pago_to_ventas_compras_gastos',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (508,'2026_09_01_140000_add_id_usuario_autorizo_descuento_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (509,'2026_09_30_210500_add_recurrencia_automatica_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (510,'2026_10_02_120000_add_dia_generacion_recurrencia_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (511,'2026_10_14_204800_seed_sv_url_firmador_pais_configuracion',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (512,'2026_10_22_120000_add_motivo_fe_cr_to_devoluciones_venta_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (513,'2026_10_22_120000_add_partida_arancelaria_to_productos_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (514,'2026_10_22_120000_add_referencia_sustitucion_fe_cr_to_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (515,'2026_10_29_120000_add_cuentas_to_planillas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (516,'2025_06_14_170145_update_whatsapp_sessions_status_enum',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (517,'2025_06_17_101000_update_whatsapp_sessions_status_enum',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (518,'2025_10_28_111804_add_unique_index_to_referencia_shopify_in_ventas_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (519,'2026_04_29_091726_update_precision_inventario_table',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (520,'2026_10_30_120000_widen_monetary_columns_crc_large_amounts',332);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (521,'2025_06_14_163145_add_telefono_to_users_table',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (522,'2026_03_25_120000_extend_documentos_nota_column',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (523,'2026_07_09_130000_add_id_corte_to_ventas_if_missing',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (524,'2026_08_21_151523_change_num_identificacion_to_varchar_and_add_index',333);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (525,'2026_10_28_120000_add_ventas_listado_usuario_index',333);
