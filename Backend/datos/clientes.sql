-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 19-02-2026 a las 04:06:45
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
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `codigo_cliente` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tiempo_pago` int DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clasificacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellido` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_empresa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shopify_customer_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departamento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_departamento` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_persona` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `tipo_documento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_municipio` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncr` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dui` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_contribuyente` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distrito` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_distrito` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT 'Persona',
  `giro` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_giro` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enable` tinyint(1) NOT NULL DEFAULT '1',
  `direccion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pais` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_pais` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota` varchar(1500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `red_social` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_cuenta_contable` int DEFAULT NULL,
  `etiquetas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_cumpleanos` date DEFAULT NULL,
  `empresa_telefono` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa_direccion` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_empresa` int NOT NULL,
  `id_vendedor` int UNSIGNED DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clientes_codigo_cliente_index` (`codigo_cliente`),
  ADD KEY `clientes_shopify_customer_id_index` (`shopify_customer_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
