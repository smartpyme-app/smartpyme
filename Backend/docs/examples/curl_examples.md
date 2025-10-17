# Ejemplos de cURL para API Externa SmartPYME

## Variables de Configuración
```bash
# Configura estas variables antes de usar los ejemplos
API_KEY="tu_api_key_aqui"
BASE_URL="https://tu-dominio.com/api/external/v1"
```

## Ejemplos de Ventas

### Obtener todas las ventas (paginadas)
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales"
```

### Ventas del último mes
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales?fecha_inicio=2025-01-01&fecha_fin=2025-01-31"
```

### Ventas completadas con paginación personalizada
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales?estado=Completada&per_page=50&page=2"
```

### Ventas ordenadas por total (mayor a menor)
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales?order_by=total&order_direction=desc"
```

### Obtener venta específica
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales/123"
```

### Resumen de ventas del mes actual
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales/summary?fecha_inicio=2025-01-01&fecha_fin=2025-01-31"
```

## Ejemplos de Inventario

### Obtener todos los productos
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory"
```

### Productos con stock disponible
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?con_stock=true"
```

### Productos con stock bajo el mínimo
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?stock_minimo=true"
```

### Buscar productos por código
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?codigo=PROD-001"
```

### Buscar productos por nombre
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?nombre=Samsung"
```

### Filtrar por categoría y marca
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?categoria=Electrónicos&marca=Samsung"
```

### Solo productos activos
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?enable=1"
```

### Productos ordenados por precio
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?order_by=precio&order_direction=desc"
```

### Obtener producto específico
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory/456"
```

### Resumen completo del inventario
```bash
curl -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory/summary"
```

## Ejemplos Combinados

### Script para obtener resumen completo
```bash
#!/bin/bash

API_KEY="tu_api_key_aqui"
BASE_URL="https://tu-dominio.com/api/external/v1"

echo "=== RESUMEN DE VENTAS ==="
curl -s -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/sales/summary" | jq '.data'

echo -e "\n=== RESUMEN DE INVENTARIO ==="
curl -s -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory/summary" | jq '.data'

echo -e "\n=== PRODUCTOS CON STOCK BAJO ==="
curl -s -H "Authorization: Bearer $API_KEY" \
  "$BASE_URL/inventory?stock_minimo=true&per_page=10" | jq '.data[] | {nombre, codigo, inventarios: .inventarios[0]}'
```

### Verificar conectividad y permisos
```bash
#!/bin/bash

API_KEY="tu_api_key_aqui"
BASE_URL="https://tu-dominio.com/api/external/v1"

# Test de conectividad
echo "Probando conectividad..."
response=$(curl -s -w "%{http_code}" -H "Authorization: Bearer $API_KEY" "$BASE_URL/sales?per_page=1")
http_code="${response: -3}"

if [ "$http_code" -eq 200 ]; then
    echo "✅ Conectividad exitosa"
else
    echo "❌ Error de conectividad. Código HTTP: $http_code"
    echo "Respuesta: $response"
fi
```

## Manejo de Errores

### Verificar respuesta y manejar errores
```bash
#!/bin/bash

API_KEY="tu_api_key_aqui"
BASE_URL="https://tu-dominio.com/api/external/v1"

response=$(curl -s -H "Authorization: Bearer $API_KEY" "$BASE_URL/sales")
success=$(echo $response | jq -r '.success')

if [ "$success" = "true" ]; then
    echo "✅ Request exitoso"
    echo $response | jq '.data | length'
    echo "registros obtenidos"
else
    echo "❌ Error en request:"
    echo $response | jq -r '.error'
fi
```

## Rate Limiting - Manejo de Límites

### Script con control de rate limiting
```bash
#!/bin/bash

API_KEY="tu_api_key_aqui"
BASE_URL="https://tu-dominio.com/api/external/v1"

make_request() {
    local url=$1
    local retry_count=0
    local max_retries=3
    
    while [ $retry_count -lt $max_retries ]; do
        response=$(curl -s -w "%{http_code}" -H "Authorization: Bearer $API_KEY" "$url")
        http_code="${response: -3}"
        body="${response%???}"
        
        if [ "$http_code" -eq 429 ]; then
            echo "Rate limit alcanzado. Esperando 60 segundos..."
            sleep 60
            retry_count=$((retry_count + 1))
        elif [ "$http_code" -eq 200 ]; then
            echo "$body"
            return 0
        else
            echo "Error HTTP $http_code: $body"
            return 1
        fi
    done
    
    echo "Máximo de reintentos alcanzado"
    return 1
}

# Usar la función
make_request "$BASE_URL/sales?per_page=100"
```


