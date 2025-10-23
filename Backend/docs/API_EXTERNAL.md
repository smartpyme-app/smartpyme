# API Externa SmartPYME - Documentación Completa

## Introducción

La API Externa de SmartPYME permite a proveedores externos acceder de forma segura a los datos de ventas e inventario de las empresas. Esta API está diseñada para integraciones B2B y proporciona acceso de solo lectura a la información.

### Características Principales
- ✅ **Solo Lectura**: Acceso exclusivo para consultar datos, no para modificarlos
- ✅ **Autenticación Segura**: Basada en API Keys únicos por empresa
- ✅ **Rate Limiting**: Control de velocidad para proteger el sistema
- ✅ **Paginación**: Manejo eficiente de grandes volúmenes de datos
- ✅ **Filtrado Avanzado**: Múltiples opciones de búsqueda y filtrado
- ✅ **Logging Completo**: Auditoría de todos los accesos

### URL Base
```
https://api.smartpyme.site/api/external/v1/
```

---

## Autenticación

### Obtención del API Key
El API Key es único por empresa y Se puede encontrar en app.smartpyme.site, en la sección Configuraciones->Mi cuenta, en el tab de Integraciones.

### Uso del API Key
Incluye el API Key en el header `Authorization` de todas las requests:

```http
Authorization: Bearer {tu_api_key}
```

### Ejemplo
```bash
curl -H "Authorization: Bearer G8RCjH11DabBNjnX7wO5" \
     https://api.smartpyme.site/api/external/v1/sales
```

⚠️ **Importante**: 
- El API Key debe mantenerse seguro y no exponerse públicamente
- Solo funciona para la empresa a la que pertenece
- La empresa debe estar activa en el sistema

---

## Rate Limiting

### Límites por Hora
- **Sin filtros de fecha**: 100 requests por hora
- **Con filtros de fecha**: 200 requests por hora
- **Reset**: Cada hora desde la primera request

### Headers de Respuesta
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

### Respuesta al Exceder Límite
```json
{
  "success": false,
  "error": "Rate limit excedido. Máximo 1000 requests por hora (2000 con filtros de fecha)",
  "code": 429
}
```

---

## Formato de Respuestas

### Estructura Estándar
Todas las respuestas siguen el mismo formato:

```json
{
  "success": true|false,
  "data": [...], // Datos solicitados
  "pagination": {...}, // Solo en endpoints paginados
  "meta": {
    "empresa": "Nombre de la Empresa",
    "timestamp": "2025-01-15T10:30:00Z",
    "filters_applied": {...}
  }
}
```

### Paginación
```json
{
  "pagination": {
    "current_page": 1,
    "per_page": 100,
    "total": 1250,
    "total_pages": 13,
    "has_next": true,
    "has_prev": false,
    "from": 1,
    "to": 100
  }
}
```

---

## Endpoints de Ventas

### 📊 Listar Ventas
```http
GET /api/external/v1/sales
```

**Parámetros de Query:**
| Parámetro | Tipo | Descripción | Ejemplo |
|-----------|------|-------------|---------|
| `fecha_inicio` | string | Fecha inicio (Y-m-d) | `2025-01-01` |
| `fecha_fin` | string | Fecha fin (Y-m-d) | `2025-01-31` |
| `estado` | string | Estado de la venta | `Completada`, `Pendiente`, `Anulada`, `Cotizacion` |
| `page` | integer | Número de página | `1` |
| `per_page` | integer | Registros por página (1-200) | `100` |
| `order_by` | string | Campo de ordenamiento | `fecha`, `total`, `correlativo`, `created_at` |
| `order_direction` | string | Dirección del orden | `asc`, `desc` |

**Ejemplo de Request:**
```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://api.smartpyme.site/api/external/v1/sales?fecha_inicio=2025-01-01&estado=Completada&per_page=50"
```

**Ejemplo de Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "fecha": "2025-01-15",
      "correlativo": "FAC-001",
      "estado": "Completada",
      "forma_pago": "Efectivo",
      "total": 150.75,
      "iva": 13.50,
      "subtotal": 137.25,
      "descuento": 0.00,
      "nombre_cliente": "Juan Pérez",
      "nombre_usuario": "Admin User",
      "nombre_vendedor": "Vendedor 1",
      "detalles": [
        {
          "nombre_producto": "Producto A",
          "codigo_producto": "PROD-A-001",
          "marca_producto": "Marca A",
          "cantidad": 2.00,
          "precio": 75.00,
          "total": 150.00,
        }
      ],
      "created_at": "2025-01-15T10:30:00Z",
      "updated_at": "2025-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 125,
    "total_pages": 3
  },
  "meta": {
    "empresa": "Mi Empresa S.A.",
    "timestamp": "2025-01-15T15:30:00Z",
    "filters_applied": {
      "fecha_inicio": "2025-01-01",
      "estado": "Completada"
    }
  }
}
```

### 📋 Obtener Venta Específica
```http
GET /api/external/v1/sales/{id}
```

**Parámetros de URL:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | ID de la venta |

**Ejemplo:**
```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://api.smartpyme.site/api/external/v1/sales/123"
```

### 📈 Resumen de Ventas
```http
GET /api/external/v1/sales/summary
```

**Parámetros de Query:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `fecha_inicio` | string | Fecha inicio (Y-m-d) |
| `fecha_fin` | string | Fecha fin (Y-m-d) |
| `estado` | string | Filtrar por estado |

**Ejemplo de Respuesta:**
```json
{
  "success": true,
  "data": {
    "cantidad_ventas": 125,
    "total_ventas": 18750.50,
    "total_iva": 1687.55,
    "total_descuentos": 250.00,
    "promedio_venta": 150.00,
    "ventas_por_estado": [
      {
        "estado": "Completada",
        "cantidad": 120,
        "total": 18000.00
      },
      {
        "estado": "Pendiente",
        "cantidad": 5,
        "total": 750.50
      }
    ]
  }
}
```

---

## Endpoints de Inventario

### 📦 Listar Productos con Inventario
```http
GET /api/external/v1/inventory
```

**Parámetros de Query:**
| Parámetro | Tipo | Descripción | Ejemplo |
|-----------|------|-------------|---------|
| `codigo` | string | Buscar por código | `PROD-001` |
| `nombre` | string | Buscar por nombre | `Producto A` |
| `categoria` | string | Filtrar por categoría | `Electrónicos` |
| `enable` | string | Estado del producto | `0`, `1` |
| `con_stock` | boolean | Solo productos con stock | `true` |
| `stock_minimo` | boolean | Productos con stock bajo | `true` |
| `page` | integer | Número de página | `1` |
| `per_page` | integer | Registros por página (1-200) | `100` |
| `order_by` | string | Campo de ordenamiento | `nombre`, `codigo`, `precio`, `costo`, `created_at` |
| `order_direction` | string | Dirección del orden | `asc`, `desc` |

**Ejemplo de Request:**
```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://api.smartpyme.site/api/external/v1/inventory?con_stock=true&categoria=Electrónicos"
```

**Ejemplo de Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "nombre": "Smartphone Samsung Galaxy",
      "descripcion": "Teléfono inteligente de última generación",
      "codigo": "PHONE-001",
      "barcode": "1234567890123",
      "precio": 599.99,
      "costo": 450.00,
      "marca": "Samsung",
      "tipo": "Producto",
      "enable": "1",
      "nombre_categoria": "Electrónicos",
      "inventarios": [
        {
          "stock": 25.00,
          "stock_minimo": 5.00,
          "stock_maximo": 100.00,
          "nombre_bodega": "Bodega Principal",
          "nombre_sucursal": "Sucursal Centro",
          "created_at": "2025-01-01T00:00:00Z"
        }
      ],
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    }
  ]
}
```

### 📱 Obtener Producto Específico
```http
GET /api/external/v1/inventory/{id}
```

### 📊 Resumen de Inventario
```http
GET /api/external/v1/inventory/summary
```

**Ejemplo de Respuesta:**
```json
{
  "success": true,
  "data": {
    "productos": {
      "total": 500,
      "activos": 480,
      "inactivos": 20
    },
    "inventario": {
      "stock_total": 15750.50,
      "valor_total": 125000.75,
      "productos_con_stock": 450,
      "productos_sin_stock": 30,
      "productos_stock_bajo": 15
    },
    "productos_por_categoria": [
      {
        "categoria": "Electrónicos",
        "cantidad": 150
      },
      {
        "categoria": "Ropa",
        "cantidad": 200
      }
    ]
  }
}
```

---

## Códigos de Error

### Códigos HTTP
| Código | Descripción | Causa Común |
|--------|-------------|-------------|
| `200` | OK | Request exitoso |
| `400` | Bad Request | Parámetros inválidos |
| `401` | Unauthorized | API Key inválido o faltante |
| `403` | Forbidden | Empresa inactiva o sin permisos |
| `404` | Not Found | Recurso no encontrado |
| `429` | Too Many Requests | Rate limit excedido |
| `500` | Internal Server Error | Error del servidor |

### Ejemplos de Errores

**API Key Inválido:**
```json
{
  "success": false,
  "error": "API key inválido o empresa inactiva",
  "code": 401
}
```

**Parámetros Inválidos:**
```json
{
  "success": false,
  "error": "Parámetros inválidos",
  "details": {
    "fecha_inicio": ["El campo fecha inicio debe ser una fecha válida."],
    "per_page": ["El campo per page no puede ser mayor que 200."]
  },
  "code": 400
}
```

---

## Ejemplos Prácticos

### cURL Examples

**Obtener ventas del último mes:**
```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://api.smartpyme.site/api/external/v1/sales?fecha_inicio=2025-01-01&fecha_fin=2025-01-31"
```

**Productos con stock bajo:**
```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://api.smartpyme.site/api/external/v1/inventory?stock_minimo=true"
```

---

## Troubleshooting

### Problemas Comunes

**1. Error 401 - API Key Inválido**
- Verifica que el API Key sea correcto
- Confirma que la empresa esté activa
- Asegúrate de incluir "Bearer " antes del token

**2. Error 429 - Rate Limit**
- Espera hasta el reset del límite
- Implementa delays entre requests
- Usa filtros de fecha para obtener límites más altos

**3. Respuestas Vacías**
- Verifica los filtros aplicados
- Confirma que existan datos en el rango solicitado
- Revisa los permisos de la empresa

**4. Timeouts**
- Reduce el `per_page` para consultas grandes
- Usa filtros más específicos
- Implementa paginación en tu código

### Mejores Prácticas

1. **Implementa Rate Limiting** en tu aplicación
2. **Usa filtros** siempre que sea posible
3. **Maneja errores** apropiadamente
4. **Implementa logging** de tus requests
5. **Usa paginación** para grandes volúmenes
6. **Cachea respuestas** cuando sea apropiado

## Changelog

### v1.0.0 (2025-10-16)
- ✅ Endpoints de ventas implementados
- ✅ Endpoints de inventario implementados
- ✅ Autenticación por API Key
- ✅ Rate limiting implementado
- ✅ Documentación completa

---

*Última actualización: 16 de Octubre, 2025*


