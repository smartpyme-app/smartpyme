-- ============================================================================
-- CONSULTA SIMPLIFICADA PARA PILOTO ABACO CAPITAL
-- Versión alternativa usando directamente fecha_pago de la tabla ventas
-- ============================================================================

SELECT 
    -- A. INFORMACIÓN DE LA PYME (EMISOR)
    e.nit AS nit_pyme,
    CASE 
        WHEN e.user_limit <= 10 THEN 'Micro'
        WHEN e.user_limit <= 50 THEN 'Pequeña'
        WHEN e.user_limit <= 200 THEN 'Mediana'
        ELSE 'No clasificado'
    END AS tamano_pyme,
    COALESCE(e.giro, e.sector, 'No especificado') AS actividad_economica_pyme,
    
    -- B. INFORMACIÓN DE LA FACTURA
    v.id AS id_factura,
    v.correlativo AS numero_factura,
    DATE(v.created_at) AS fecha_creacion,
    v.fecha_pago AS fecha_pago,
    DATEDIFF(v.fecha_pago, DATE(v.created_at)) AS dias_a_pago,
    v.sub_total AS monto_sin_iva,
    v.total AS monto_con_iva,
    
    -- C. INFORMACIÓN DEL CLIENTE (RECEPTOR)
    c.nit AS nit_cliente,
    c.clasificacion AS tamano_cliente,
    COALESCE(c.giro, 'No especificado') AS actividad_economica_cliente,
    c.tipo AS tipo_cliente

FROM ventas v
INNER JOIN empresas e ON v.id_empresa = e.id
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
    AND (v.cotizacion IS NULL OR v.cotizacion = 0)

ORDER BY v.created_at DESC;

