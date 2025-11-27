# Consulta SQL para Piloto Abaco Capital

## 📋 Descripción

Este documento contiene las consultas SQL desarrolladas para el piloto de alianza estratégica entre SmartPyme y Abaco Capital. El objetivo es obtener facturas creadas como "a crédito" que fueron pagadas en menos de 30 días.

## 📁 Archivos

1. **`consulta_abaco_capital.sql`** - Consulta completa con lógica avanzada para determinar fecha de pago
2. **`consulta_abaco_capital_simple.sql`** - Versión simplificada usando directamente `fecha_pago`

## 🎯 Criterios de Filtrado

### ✅ Facturas Incluidas

- **Condición**: Solo facturas "a crédito" (`condicion = 'Crédito'`)
- **Estado**: Solo facturas pagadas (`estado = 'Pagada'`)
- **Tiempo de pago**: Pagadas en menos de 30 días desde la creación
- **Cliente**: Solo clientes con NIT válido (excluye "Consumidor Final")

### ❌ Facturas Excluidas

- Facturas anuladas
- Cotizaciones
- Facturas de contado
- Facturas pendientes
- Facturas de consumidor final (sin NIT)

## 📊 Campos Retornados

### A. Información de la PYME (Emisor)

| Campo | Descripción | Fuente |
|-------|-------------|--------|
| `nit_pyme` | Número de identificación tributaria | `empresas.nit` |
| `tamano_pyme` | Clasificación: Micro/Pequeña/Mediana | Calculado desde `empresas.user_limit` |
| `actividad_economica_pyme` | Sector/industria | `empresas.giro` o `empresas.sector` |
| `cod_actividad_economica_pyme` | Código de actividad económica | `empresas.cod_actividad_economica` |

### B. Información de la Factura

| Campo | Descripción | Cálculo |
|-------|-------------|---------|
| `fecha_creacion` | Cuando se emitió la factura | `ventas.created_at` |
| `fecha_pago` | Cuando se registró el pago | Último abono confirmado o `ventas.fecha_pago` |
| `dias_a_pago` | Diferencia entre pago y creación | `fecha_pago - fecha_creacion` |
| `monto_sin_iva` | Subtotal de la factura | `ventas.sub_total` |
| `monto_con_iva` | Total con impuestos | `ventas.total` |

### C. Información del Cliente (Receptor)

| Campo | Descripción | Nota |
|-------|-------------|------|
| `nit_cliente` | NIT del cliente | ⚠️ Solo si tiene NIT válido |
| `tamano_cliente` | Clasificación del cliente | Solo si tiene NIT registrado |
| `actividad_economica_cliente` | Sector del cliente | Solo si tiene NIT registrado |

## 🔍 Lógica de Fecha de Pago

La consulta completa (`consulta_abaco_capital.sql`) usa la siguiente lógica para determinar la fecha de pago:

1. **Prioridad 1**: Fecha del último abono confirmado en `abonos_ventas`
2. **Prioridad 2**: Campo `fecha_pago` de la tabla `ventas`
3. **Prioridad 3**: Campo `updated_at` de la tabla `ventas` (último recurso)

La versión simple usa directamente `ventas.fecha_pago`.

## ⚠️ Consideraciones Importantes

### 1. Tamaño de la PYME

**Problema actual**: Se usa `user_limit` como aproximación, pero este campo representa el límite de usuarios, no necesariamente el número de empleados.

**Recomendación**: 
- Crear un campo `tamano_empresa` en la tabla `empresas`
- Clasificar manualmente o mediante un proceso automatizado basado en:
  - Micro: 1-10 empleados
  - Pequeña: 11-50 empleados
  - Mediana: 51-200 empleados

### 2. Tamaño del Cliente

**Problema actual**: El campo `clasificacion` en la tabla `clientes` puede no estar completo.

**Recomendación**:
- Validar y completar el campo `clasificacion` para todos los clientes B2B
- Implementar un proceso de clasificación automática si es posible

### 3. Actividad Económica

**Estado actual**: 
- PYME: Disponible en `empresas.giro` o `empresas.sector`
- Cliente: Disponible en `clientes.giro`

**Recomendación**: Validar que estos campos estén completos y actualizados.

### 4. Validación de NIT

**Filtros aplicados**:
- Excluye valores NULL o vacíos
- Excluye "CF" y "C/F" (Consumidor Final)

**Recomendación**: Implementar validación adicional de formato de NIT según el país.

## 🚀 Uso

### Ejecutar la consulta completa:

```sql
-- Cargar el archivo
SOURCE consulta_abaco_capital.sql;

-- O ejecutar directamente
mysql -u usuario -p nombre_base_datos < consulta_abaco_capital.sql
```

### Ejecutar la versión simple:

```sql
SOURCE consulta_abaco_capital_simple.sql;
```

## 📈 Próximos Pasos

1. **Validar datos**: Ejecutar la consulta y revisar los resultados
2. **Completar información faltante**: 
   - Tamaño de PYME
   - Tamaño de cliente
   - Actividad económica
3. **Exportar resultados**: Generar CSV o Excel para análisis
4. **Análisis de calidad**: Verificar que los datos cumplan con los requisitos de Abaco

## 📝 Notas Adicionales

- La consulta está optimizada para MySQL/MariaDB
- Se recomienda crear índices en:
  - `ventas.condicion`
  - `ventas.estado`
  - `ventas.id_cliente`
  - `clientes.nit`
  - `abonos_ventas.id_venta` y `abonos_ventas.estado`

## 🔗 Referencias

- Documento de requisitos: Alianza SmartPyme x Abaco Capital
- Tablas principales: `ventas`, `empresas`, `clientes`, `abonos_ventas`

