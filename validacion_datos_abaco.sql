-- ============================================================================
-- CONSULTAS DE VALIDACIÓN PARA PILOTO ABACO CAPITAL
-- Ejecutar estas consultas antes de la consulta principal para validar datos
-- ============================================================================

-- ============================================================================
-- 1. VERIFICAR FACTURAS A CRÉDITO PAGADAS
-- ============================================================================
SELECT 
    COUNT(*) AS total_facturas_credito_pagadas,
    COUNT(DISTINCT v.id_empresa) AS total_pymes,
    COUNT(DISTINCT v.id_cliente) AS total_clientes_b2b,
    MIN(DATEDIFF(
        COALESCE(v.fecha_pago, DATE(v.updated_at)), 
        DATE(v.created_at)
    )) AS dias_minimos_pago,
    MAX(DATEDIFF(
        COALESCE(v.fecha_pago, DATE(v.updated_at)), 
        DATE(v.created_at)
    )) AS dias_maximos_pago,
    AVG(DATEDIFF(
        COALESCE(v.fecha_pago, DATE(v.updated_at)), 
        DATE(v.created_at)
    )) AS dias_promedio_pago
FROM ventas v
INNER JOIN clientes c ON v.id_cliente = c.id
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND v.estado != 'Anulada'
    AND (v.cotizacion IS NULL OR v.cotizacion = 0);

-- ============================================================================
-- 2. VERIFICAR FACTURAS QUE CUMPLEN CRITERIO DE <30 DÍAS
-- ============================================================================
SELECT 
    COUNT(*) AS facturas_pagadas_menos_30_dias,
    SUM(v.total) AS monto_total,
    AVG(v.total) AS monto_promedio
FROM ventas v
INNER JOIN clientes c ON v.id_cliente = c.id
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND v.fecha_pago IS NOT NULL
    AND DATEDIFF(v.fecha_pago, DATE(v.created_at)) < 30
    AND v.estado != 'Anulada'
    AND (v.cotizacion IS NULL OR v.cotizacion = 0);

-- ============================================================================
-- 3. VERIFICAR EMPRESAS SIN TAMAÑO CLASIFICADO
-- ============================================================================
SELECT 
    e.id,
    e.nit,
    e.nombre,
    e.user_limit,
    CASE 
        WHEN e.user_limit <= 10 THEN 'Micro'
        WHEN e.user_limit <= 50 THEN 'Pequeña'
        WHEN e.user_limit <= 200 THEN 'Mediana'
        ELSE 'No clasificado'
    END AS tamano_estimado,
    COUNT(v.id) AS total_facturas_calificadas
FROM empresas e
INNER JOIN ventas v ON e.id = v.id_empresa
INNER JOIN clientes c ON v.id_cliente = c.id
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND v.fecha_pago IS NOT NULL
    AND DATEDIFF(v.fecha_pago, DATE(v.created_at)) < 30
GROUP BY e.id, e.nit, e.nombre, e.user_limit
ORDER BY total_facturas_calificadas DESC;

-- ============================================================================
-- 4. VERIFICAR CLIENTES SIN CLASIFICACIÓN
-- ============================================================================
SELECT 
    c.id,
    c.nit,
    c.nombre_empresa,
    c.clasificacion,
    c.giro AS actividad_economica,
    COUNT(v.id) AS total_facturas_calificadas
FROM clientes c
INNER JOIN ventas v ON c.id = v.id_cliente
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND v.fecha_pago IS NOT NULL
    AND DATEDIFF(v.fecha_pago, DATE(v.created_at)) < 30
GROUP BY c.id, c.nit, c.nombre_empresa, c.clasificacion, c.giro
ORDER BY total_facturas_calificadas DESC;

-- ============================================================================
-- 5. DISTRIBUCIÓN POR DÍAS DE PAGO
-- ============================================================================
SELECT 
    CASE 
        WHEN DATEDIFF(v.fecha_pago, DATE(v.created_at)) <= 7 THEN '1-7 días'
        WHEN DATEDIFF(v.fecha_pago, DATE(v.created_at)) <= 14 THEN '8-14 días'
        WHEN DATEDIFF(v.fecha_pago, DATE(v.created_at)) <= 21 THEN '15-21 días'
        WHEN DATEDIFF(v.fecha_pago, DATE(v.created_at)) <= 30 THEN '22-30 días'
        ELSE 'Más de 30 días'
    END AS rango_dias,
    COUNT(*) AS cantidad_facturas,
    SUM(v.total) AS monto_total,
    AVG(v.total) AS monto_promedio
FROM ventas v
INNER JOIN clientes c ON v.id_cliente = c.id
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND v.fecha_pago IS NOT NULL
    AND v.estado != 'Anulada'
    AND (v.cotizacion IS NULL OR v.cotizacion = 0)
GROUP BY rango_dias
ORDER BY 
    CASE rango_dias
        WHEN '1-7 días' THEN 1
        WHEN '8-14 días' THEN 2
        WHEN '15-21 días' THEN 3
        WHEN '22-30 días' THEN 4
        ELSE 5
    END;

-- ============================================================================
-- 6. VERIFICAR FACTURAS CON FECHA DE PAGO NULL O INVÁLIDA
-- ============================================================================
SELECT 
    COUNT(*) AS facturas_sin_fecha_pago,
    COUNT(DISTINCT v.id_empresa) AS pymes_afectadas
FROM ventas v
INNER JOIN clientes c ON v.id_cliente = c.id
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND (v.fecha_pago IS NULL OR v.fecha_pago = '0000-00-00')
    AND v.estado != 'Anulada'
    AND (v.cotizacion IS NULL OR v.cotizacion = 0);

-- ============================================================================
-- 7. VERIFICAR ABONOS CONFIRMADOS POR FACTURA
-- ============================================================================
SELECT 
    v.id AS id_factura,
    v.correlativo,
    v.fecha_pago AS fecha_pago_venta,
    COUNT(av.id) AS total_abonos,
    MAX(av.fecha) AS fecha_ultimo_abono,
    SUM(av.total) AS total_abonado
FROM ventas v
LEFT JOIN abonos_ventas av ON v.id = av.id_venta AND av.estado = 'Confirmado'
INNER JOIN clientes c ON v.id_cliente = c.id
WHERE 
    v.condicion = 'Crédito'
    AND v.estado = 'Pagada'
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'
    AND c.nit != 'C/F'
    AND v.fecha_pago IS NOT NULL
    AND DATEDIFF(v.fecha_pago, DATE(v.created_at)) < 30
GROUP BY v.id, v.correlativo, v.fecha_pago
HAVING total_abonos > 0
ORDER BY total_abonos DESC
LIMIT 50;

