-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 19-02-2026 a las 04:07:17
-- Versión del servidor: 9.5.0
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `vps`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int UNSIGNED NOT NULL,
  `codigo_generacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sello_mh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_control` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_dte` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prueba_masiva` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indica si el documento fue generado como parte de pruebas masivas',
  `estado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `forma_pago` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `recurrente` tinyint(1) DEFAULT '0',
  `cotizacion` tinyint(1) DEFAULT '0',
  `descripcion_personalizada` tinyint(1) DEFAULT '0',
  `descripcion_impresion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_expiracion` date DEFAULT NULL,
  `num_cotizacion` int DEFAULT NULL,
  `num_orden` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Contado',
  `detalle_banco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_wompi_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_wompi_transaccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date NOT NULL,
  `propina` double(10,2) NOT NULL DEFAULT '0.00',
  `total_costo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sub_total` double(10,2) NOT NULL,
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT '0.00',
  `exenta` decimal(9,2) NOT NULL DEFAULT '0.00',
  `gravada` decimal(10,2) DEFAULT '0.00',
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT '0.00',
  `iva` decimal(10,2) NOT NULL,
  `iva_retenido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `renta_retenida` decimal(10,2) DEFAULT '0.00',
  `iva_percibido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `descuento` double(10,2) NOT NULL DEFAULT '0.00',
  `monto_pago` double(10,2) DEFAULT NULL,
  `cambio` double(10,2) DEFAULT NULL,
  `costo_envio` double(10,2) NOT NULL DEFAULT '0.00',
  `fecha_pago` date DEFAULT NULL,
  `fecha_anulacion` date DEFAULT NULL,
  `tipo_anulacion` int DEFAULT NULL,
  `motivo_anulacion` text COLLATE utf8mb4_unicode_ci,
  `codigo_generacion_remplazo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_shopify` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_renta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_operacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_canal` int DEFAULT NULL,
  `correlativo` int DEFAULT NULL,
  `num_identificacion` text COLLATE utf8mb4_unicode_ci,
  `id_documento` int DEFAULT NULL,
  `id_cliente` int DEFAULT NULL,
  `id_proyecto` int DEFAULT NULL,
  `id_bodega` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_vendedor` int DEFAULT NULL,
  `dte` longtext COLLATE utf8mb4_unicode_ci,
  `dte_invalidacion` longtext COLLATE utf8mb4_unicode_ci,
  `tipo_item_export` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `importado` tinyint(1) NOT NULL DEFAULT '0',
  `cod_incoterm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incoterm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recinto_fiscal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seguro` decimal(10,2) DEFAULT '0.00',
  `flete` decimal(10,2) DEFAULT '0.00',
  `id_empresa` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `num_orden_exento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proforma` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marcas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pago_descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ventas_estado` (`estado`),
  ADD KEY `idx_ventas_id_empresa` (`id_empresa`),
  ADD KEY `idx_ventas_fecha` (`fecha`),
  ADD KEY `idx_ventas_id_sucursal` (`id_sucursal`),
  ADD KEY `idx_ventas_empresa_fecha` (`id_empresa`,`fecha`,`estado`),
  ADD KEY `idx_ventas_empresa_sucursal_fecha` (`id_empresa`,`id_sucursal`,`fecha`,`estado`),
  ADD KEY `idx_ventas_fecha_estado` (`fecha`,`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
