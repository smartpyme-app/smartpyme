-- ============================================================================
-- CONSULTA PARA PILOTO ABACO CAPITAL
-- SmartPyme x Abaco Capital - Facturas a Crédito Pagadas en <30 días
-- ============================================================================
-- 
-- Descripción: Esta consulta obtiene facturas creadas como "a crédito" que 
-- fueron pagadas en menos de 30 días, con información completa de la PYME,
-- la factura y el cliente (solo B2B con NIT válido).
--
-- Filtros aplicados:
-- ✅ Solo facturas "a crédito" (condicion = 'Crédito')
-- ✅ Solo facturas pagadas (estado = 'Pagada')
-- ✅ Solo facturas pagadas en <30 días
-- ✅ Solo clientes con NIT válido (excluir consumidor final)
-- ============================================================================

SELECT 
    -- ========================================================================
    -- A. INFORMACIÓN DE LA PYME (EMISOR)
    -- ========================================================================
    e.nit AS nit_pyme,
    e.nombre AS nombre_pyme,
    -- Tamaño PYME: Se debe validar/clasificar según número de empleados
    -- Micro (1-10), Pequeña (11-50), Mediana (51-200)
    CASE 
        WHEN e.user_limit <= 10 THEN 'Micro'
        WHEN e.user_limit <= 50 THEN 'Pequeña'
        WHEN e.user_limit <= 200 THEN 'Mediana'
        ELSE 'No clasificado'
    END AS tamano_pyme,
    -- Actividad Económica de la PYME
    COALESCE(e.giro, e.sector, 'No especificado') AS actividad_economica_pyme,
    e.cod_actividad_economica AS cod_actividad_economica_pyme,
    
    -- ========================================================================
    -- B. INFORMACIÓN DE LA FACTURA
    -- ========================================================================
    v.id AS id_factura,
    v.correlativo AS numero_factura,
    -- Fecha de Creación (cuando se emitió la factura)
    DATE(v.created_at) AS fecha_creacion,
    v.fecha AS fecha_factura,
    
    -- Fecha de Pago (cuando se registró el pago)
    -- Prioriza la fecha del último abono confirmado, si no existe usa fecha_pago de venta
    COALESCE(
        (SELECT MAX(av.fecha) 
         FROM abonos_ventas av 
         WHERE av.id_venta = v.id 
           AND av.estado = 'Confirmado'),
        v.fecha_pago,
        DATE(v.updated_at)
    ) AS fecha_pago,
    
    -- Días a Pago (diferencia entre pago y creación)
    DATEDIFF(
        COALESCE(
            (SELECT MAX(av.fecha) 
             FROM abonos_ventas av 
             WHERE av.id_venta = v.id 
               AND av.estado = 'Confirmado'),
            v.fecha_pago,
            DATE(v.updated_at)
        ),
        DATE(v.created_at)
    ) AS dias_a_pago,
    
    -- Monto sin IVA (subtotal)
    v.sub_total AS monto_sin_iva,
    
    -- Monto con IVA (total)
    v.total AS monto_con_iva,
    
    -- Información adicional de la factura
    v.estado AS estado_factura,
    v.condicion AS condicion_factura,
    v.forma_pago AS forma_pago,
    
    -- ========================================================================
    -- C. INFORMACIÓN DEL CLIENTE (RECEPTOR)
    -- ========================================================================
    -- NIT Cliente (solo si tiene NIT válido, excluir consumidor final)
    c.nit AS nit_cliente,
    c.nombre_empresa AS nombre_cliente_empresa,
    CASE 
        WHEN c.tipo = 'Empresa' THEN c.nombre_empresa
        WHEN c.tipo = 'Persona' THEN CONCAT(c.nombre, ' ', COALESCE(c.apellido, ''))
        ELSE 'Consumidor Final'
    END AS nombre_cliente_completo,
    
    -- Tamaño Cliente (solo si tiene NIT registrado)
    -- Nota: Este campo probablemente necesita ser capturado manualmente
    c.clasificacion AS tamano_cliente,
    
    -- Actividad Económica del Cliente (solo si tiene NIT registrado)
    COALESCE(c.giro, 'No especificado') AS actividad_economica_cliente,
    c.cod_giro AS cod_actividad_economica_cliente,
    c.tipo AS tipo_cliente,
    
    -- ========================================================================
    -- INFORMACIÓN ADICIONAL PARA ANÁLISIS
    -- ========================================================================
    v.id_empresa,
    v.id_cliente,
    v.id_sucursal,
    s.nombre AS nombre_sucursal

FROM ventas v
    -- JOIN con Empresa (PYME emisora)
    INNER JOIN empresas e ON v.id_empresa = e.id
    
    -- JOIN con Cliente (receptor)
    INNER JOIN clientes c ON v.id_cliente = c.id
    
    -- JOIN con Sucursal (opcional, para información adicional)
    LEFT JOIN sucursales s ON v.id_sucursal = s.id

WHERE 
    -- ✅ Solo facturas "a crédito"
    v.condicion = 'Crédito'
    
    -- ✅ Solo facturas pagadas
    AND v.estado = 'Pagada'
    
    -- ✅ Solo clientes con NIT válido (excluir consumidor final)
    AND c.nit IS NOT NULL 
    AND c.nit != ''
    AND c.nit != 'CF'  -- Excluir "Consumidor Final"
    AND c.nit != 'C/F'
    
    -- ✅ Solo facturas pagadas en <30 días
    AND DATEDIFF(
        COALESCE(
            (SELECT MAX(av.fecha) 
             FROM abonos_ventas av 
             WHERE av.id_venta = v.id 
               AND av.estado = 'Confirmado'),
            v.fecha_pago,
            DATE(v.updated_at)
        ),
        DATE(v.created_at)
    ) < 30
    
    -- Excluir facturas anuladas o en otros estados no válidos
    AND v.estado != 'Anulada'
    
    -- Excluir cotizaciones
    AND (v.cotizacion IS NULL OR v.cotizacion = 0)

-- Ordenar por fecha de creación (más recientes primero)
ORDER BY v.created_at DESC, v.id DESC;

-- ============================================================================
-- NOTAS IMPORTANTES:
-- ============================================================================
-- 
-- 1. TAMAÑO DE LA PYME:
--    - Actualmente se usa 'user_limit' como aproximación
--    - Se recomienda crear un campo específico 'tamano_empresa' en la tabla empresas
--    - Valores sugeridos: 'Micro', 'Pequeña', 'Mediana'
--
-- 2. TAMAÑO DEL CLIENTE:
--    - El campo 'clasificacion' en la tabla clientes puede contener esta información
--    - Si no está disponible, se muestra como NULL
--    - Se recomienda validar y completar esta información
--
-- 3. FECHA DE PAGO:
--    - Se prioriza la fecha del último abono confirmado
--    - Si no hay abonos, se usa 'fecha_pago' de la venta
--    - Como último recurso, se usa 'updated_at'
--
-- 4. ACTIVIDAD ECONÓMICA:
--    - Para PYME: Se usa 'giro' o 'sector' de la tabla empresas
--    - Para Cliente: Se usa 'giro' de la tabla clientes
--    - Ambos pueden tener 'cod_actividad_economica' o 'cod_giro' respectivamente
--
-- 5. VALIDACIONES ADICIONALES RECOMENDADAS:
--    - Verificar que las empresas tengan NIT válido
--    - Validar que los montos sean positivos
--    - Confirmar que las fechas sean coherentes
-- ============================================================================

