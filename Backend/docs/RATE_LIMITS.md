# 🚦 Rate Limits - API Externa SmartPYME

## 📊 Límites Actuales

### **Límites Estándar**
- **1000 requests por hora** para consultas normales
- **2000 requests por hora** cuando se usan filtros de fecha (`fecha_inicio` y `fecha_fin`)

### **Ventana de Tiempo - Sistema de Reset Fijo**
- **60 minutos** - Ventana fija por hora (ej: 14:00-15:00, 15:00-16:00)
- **Reset exacto**: Los contadores se resetean automáticamente al inicio de cada hora
- **Ejemplo**: Si empiezas a las 14:30, el límite se resetea a las 15:00 exactas

## 🔍 Cómo Verificar tu Estado Actual

### **Endpoint de Monitoreo**
```
GET /api/external/v1/system/rate-limit
Authorization: Bearer TU_API_KEY
```

### **Respuesta de Ejemplo**
```json
{
    "success": true,
    "data": {
        "requests_used": 25,
        "max_requests": 1000,
        "remaining_requests": 975,
        "reset_time": "2024-01-01T15:00:01Z",
        "window_minutes": 60,
        "limits": {
            "standard": 1000,
            "with_date_filters": 2000
        },
        "current_limit_type": "standard",
        "empresa_id": 324,
        "empresa_nombre": "Mi Empresa"
    }
}
```

## ⏰ Cómo Funciona el Reset del Rate Limit

### **Sistema de Ventana Fija**
El sistema usa **ventanas fijas de 1 hora** que se resetean exactamente al inicio de cada hora:

```
13:00:00 - 13:59:59 → Ventana 1 (límite: 1000/2000 requests)
14:00:00 - 14:59:59 → Ventana 2 (límite: 1000/2000 requests)
15:00:00 - 15:59:59 → Ventana 3 (límite: 1000/2000 requests)
```

### **Ejemplos Prácticos**

#### **Escenario 1: Empezar a mitad de hora**
```
14:30 - Haces 500 requests
14:59 - Llegas al límite (1000 requests)
15:00 - ✅ RESET AUTOMÁTICO - Contador vuelve a 0
15:01 - Puedes hacer otros 1000/2000 requests
```

#### **Escenario 2: Monitoreo del reset**
```bash
# Consultar a las 14:45
GET /system/rate-limit
Response: "reset_time": "2024-10-21T15:00:01Z"

# Consultar a las 15:01 (después del reset)
GET /system/rate-limit  
Response: "requests_used": 1, "reset_time": "2024-10-21T16:00:01Z"
```

### **Ventajas del Sistema Fijo**
- ✅ **Predecible**: Sabes exactamente cuándo se resetea
- ✅ **Justo**: Todos los usuarios se resetean al mismo tiempo
- ✅ **Eficiente**: No hay acumulación de contadores antiguos

## 📈 Optimización de Consultas

### **Para Maximizar tu Límite**
1. **Usa filtros de fecha** cuando sea posible:
   ```
   GET /api/external/v1/sales?fecha_inicio=2024-01-01&fecha_fin=2024-01-31
   ```
   ✅ Esto te da **2000 requests/hora** en lugar de 1000

2. **Usa paginación eficiente**:
   ```
   GET /api/external/v1/sales?page=1&per_page=100
   ```
   ✅ Obtén más datos por request

3. **Consulta resúmenes primero**:
   ```
   GET /api/external/v1/sales/summary
   GET /api/external/v1/inventory/summary
   ```
   ✅ Obtén estadísticas generales con menos requests

## ⚠️ Error 429 - Rate Limit Excedido

### **Respuesta de Error**
```json
{
    "success": false,
    "error": "Rate limit excedido. Máximo 1000 requests por hora (2000 con filtros de fecha)",
    "code": 429,
    "details": {
        "standard_limit": 1000,
        "with_date_filters_limit": 2000,
        "window": "60 minutos",
        "reset_info": "El límite se resetea cada hora"
    }
}
```

### **Qué Hacer**
1. **Espera** hasta que se resetee el límite (máximo 1 hora)
2. **Verifica tu estado** con el endpoint `/system/rate-limit`
3. **Optimiza tus consultas** usando los consejos anteriores

## 🛠️ Mejores Prácticas

### **1. Monitoreo Proactivo**
```bash
# Verifica tu estado antes de hacer consultas masivas
curl -H "Authorization: Bearer TU_API_KEY" \
     "https://tu-dominio.com/api/external/v1/system/rate-limit"
```

### **2. Manejo de Errores**
```php
// Ejemplo en PHP
$response = makeApiCall($url, $headers);

if ($response['status'] == 429) {
    // Rate limit excedido
    $resetTime = $response['data']['reset_time'] ?? null;
    echo "Rate limit excedido. Resetea en: " . $resetTime;
    
    // Esperar o programar retry
    sleep(3600); // Esperar 1 hora
}
```

### **3. Batch Processing**
```php
// Procesa en lotes para optimizar requests
$batchSize = 100;
$pages = ceil($totalRecords / $batchSize);

for ($page = 1; $page <= $pages; $page++) {
    $url = "/api/external/v1/sales?page={$page}&per_page={$batchSize}";
    $response = makeApiCall($url);
    
    // Procesar datos
    processData($response['data']);
    
    // Opcional: pequeña pausa entre requests
    usleep(100000); // 0.1 segundos
}
```

## 📞 Soporte

Si necesitas límites más altos para tu caso de uso específico, contacta al equipo de soporte:

- **Email**: soporte@smartpyme.com
- **Incluye**: Tu empresa ID, caso de uso, y volumen estimado de requests

---

## 🔄 Historial de Cambios

### **v1.2 - Octubre 2024**
- ✅ Aumentado de 500 a **1000 requests/hora** estándar
- ✅ Aumentado de 1000 a **2000 requests/hora** con filtros de fecha
- ✅ **Nuevo sistema de ventana fija**: Reset exacto cada hora (ej: 14:00, 15:00, 16:00)
- ✅ Mejorada predictibilidad del reset de límites
- ✅ Optimización para mayor volumen de datos

### **v1.1 - Octubre 2024**
- ✅ Aumentado de 100 a **500 requests/hora** estándar
- ✅ Aumentado de 200 a **1000 requests/hora** con filtros de fecha
- ✅ Agregado endpoint de monitoreo `/system/rate-limit`
- ✅ Mejorados mensajes de error con más detalles

### **v1.0 - Octubre 2024**
- 🚀 Lanzamiento inicial con límites básicos
