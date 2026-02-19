-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 19-02-2026 a las 04:07:07
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
-- Estructura de tabla para la tabla `detalles_venta`
--

CREATE TABLE `detalles_venta` (
  `id` int NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo` decimal(9,2) NOT NULL DEFAULT '0.00',
  `precio` double NOT NULL,
  `precio_sin_iva` decimal(10,4) DEFAULT NULL,
  `precio_con_iva` decimal(10,4) DEFAULT NULL,
  `total` decimal(9,2) NOT NULL,
  `total_costo` decimal(9,2) DEFAULT '0.00',
  `descuento` double NOT NULL DEFAULT '0',
  `sub_total` decimal(12,4) DEFAULT NULL COMMENT 'cantidad * precio antes de descuento',
  `no_sujeta` decimal(9,2) NOT NULL DEFAULT '0.00',
  `exenta` decimal(9,2) NOT NULL DEFAULT '0.00',
  `cuenta_a_terceros` decimal(9,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `gravada` decimal(10,2) DEFAULT '0.00',
  `iva` decimal(9,4) DEFAULT '0.0000',
  `tipo_gravado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'gravada' COMMENT 'gravada, exenta, no_sujeta',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `id_producto` int NOT NULL,
  `lote_id` int UNSIGNED DEFAULT NULL,
  `id_venta` int NOT NULL,
  `id_vendedor` int DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `detalles_venta`
--
ALTER TABLE `detalles_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_detalles_venta_id_producto` (`id_producto`),
  ADD KEY `idx_detalles_venta_id_vendedor` (`id_vendedor`),
  ADD KEY `idx_detalles_venta_id_venta` (`id_venta`),
  ADD KEY `idx_detalles_id_venta` (`id_venta`),
  ADD KEY `idx_detalles_id_producto` (`id_producto`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `detalles_venta`
--
ALTER TABLE `detalles_venta`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
