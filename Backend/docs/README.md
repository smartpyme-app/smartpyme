# 📚 Documentación API Externa SmartPYME

Esta carpeta contiene toda la documentación para la API Externa de SmartPYME, que permite a proveedores externos acceder a datos de ventas e inventario.

## 📁 Estructura de Archivos

```
docs/
├── API_EXTERNAL.md           # Documentación principal completa
├── DATA_DICTIONARY.md        # 📊 NUEVO: Diccionario completo de datos
├── RATE_LIMITS.md           # Información sobre límites de requests
├── README.md                # Este archivo
├── examples/
│   ├── curl_examples.md     # Ejemplos usando cURL
└── schemas/
    ├── sale_response.json   # Schema JSON para respuestas de ventas
    ├── inventory_response.json # Schema JSON para respuestas de inventario
    ├── data_dictionary.json # 🔧 Diccionario en formato JSON
    └── data_dictionary.csv  # 📊 Diccionario en formato CSV
```

## 🎯 Diccionario de Datos - Múltiples Formatos

### 📝 Markdown (`DATA_DICTIONARY.md`)
- ✅ **Mejor para**: Lectura humana, documentación web
- ✅ **Incluye**: Tablas organizadas, ejemplos, notas detalladas

### 🔧 JSON (`schemas/data_dictionary.json`)
- ✅ **Mejor para**: Integración programática, validación automática
- ✅ **Incluye**: Estructura completa con metadatos

### 📊 CSV (`schemas/data_dictionary.csv`)
- ✅ **Mejor para**: Excel, Google Sheets, análisis de datos
- ✅ **Compatible con**: Hojas de cálculo, herramientas de BI

## 🚀 Inicio Rápido

1. **Para desarrolladores**: Lee [API_EXTERNAL.md](API_EXTERNAL.md) y [DATA_DICTIONARY.md](DATA_DICTIONARY.md)
2. **Para análisis**: Abre `data_dictionary.csv` en Excel
3. **Para integración**: Usa `data_dictionary.json` en tu código
4. **Obtén tu API Key**: Se puede encontrar en app.smartpyme.site, en la sección Configuraciones->Mi cuenta, en el tab de Integraciones
5. **Prueba con cURL**: Revisa [curl_examples.md](examples/curl_examples.md)

## 📊 Endpoints Disponibles

### Ventas
- `GET /api/external/v1/sales` - Listar ventas
- `GET /api/external/v1/sales/{id}` - Venta específica
- `GET /api/external/v1/sales/summary` - Resumen de ventas

### Inventario
- `GET /api/external/v1/inventory` - Listar productos
- `GET /api/external/v1/inventory/{id}` - Producto específico
- `GET /api/external/v1/inventory/summary` - Resumen de inventario

### Devoluciones
- `GET /api/external/v1/returns` - Listar devoluciones
- `GET /api/external/v1/returns/{id}` - Devolución específica
- `GET /api/external/v1/returns/summary` - Resumen de devoluciones

## 🔐 Autenticación

```bash
Authorization: Bearer {tu_api_key}
```

## 📝 Ejemplo Rápido

```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://api.smartpyme.site/api/external/v1/sales?per_page=10"
```

## 🛠️ Herramientas Útiles

- **Validador JSON Schema**: https://www.jsonschemavalidator.net/
- **jq**: Para procesar respuestas JSON en terminal


