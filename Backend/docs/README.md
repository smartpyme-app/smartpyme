# Documentación API Externa SmartPYME

Esta carpeta contiene toda la documentación para la API Externa de SmartPYME, que permite a proveedores externos acceder a datos de ventas e inventario.

## 📁 Estructura de Archivos

```
docs/
├── API_EXTERNAL.md           # Documentación principal completa
├── README.md                 # Este archivo
├── examples/
│   ├── curl_examples.md      # Ejemplos usando cURL
└── schemas/
    ├── sale_response.json    # Schema JSON para respuestas de ventas
    └── inventory_response.json # Schema JSON para respuestas de inventario
```

## 🚀 Inicio Rápido

1. **Lee la documentación principal**: [API_EXTERNAL.md](API_EXTERNAL.md)
2. **Obtén tu API Key**: Se puede encontrar en app.smartpyme.site, en la sección Configuraciones->Mi cuenta, en el tab de Integraciones
3. **Prueba con cURL**: Revisa [curl_examples.md](examples/curl_examples.md)

## 📊 Endpoints Disponibles

### Ventas
- `GET /api/external/v1/sales` - Listar ventas
- `GET /api/external/v1/sales/{id}` - Venta específica
- `GET /api/external/v1/sales/summary` - Resumen de ventas

### Inventario
- `GET /api/external/v1/inventory` - Listar productos
- `GET /api/external/v1/inventory/{id}` - Producto específico
- `GET /api/external/v1/inventory/summary` - Resumen de inventario

## 🔐 Autenticación

```bash
Authorization: Bearer {tu_api_key}
```

## 📝 Ejemplo Rápido

```bash
curl -H "Authorization: Bearer tu_api_key" \
  "https://app.smartpyme.site/api/external/v1/sales?per_page=10"
```

## 🛠️ Herramientas Útiles

- **Validador JSON Schema**: https://www.jsonschemavalidator.net/
- **jq**: Para procesar respuestas JSON en terminal


