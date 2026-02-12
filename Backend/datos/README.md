# Importación masiva de datos

Guía rápida para ejecutar las importaciones de clientes/ventas y de proveedores/compras desde los archivos PHP exportados.

## Archivos requeridos

En esta carpeta `Backend/datos/` deben estar los archivos exportados (PHPMyAdmin):

| Importación | Archivos |
|-------------|----------|
| Ventas | `clientes.php`, `ventas.php`, `detalles_venta.php` |
| Compras | `proveedores.php`, `compras.php`, `detalles_compra.php` |

Cada archivo debe definir su variable: `$clientes`, `$ventas`, `$detalles_venta`, `$proveedores`, `$compras`, `$detalles_compra`.

## Cómo ejecutar

Las importaciones se hacen por lotes desde el navegador. **No requiere autenticación.**

### 1. Importación de ventas (clientes → ventas → detalles)

**URL inicial:**
```
https://api.smartpyme.test/api/import-masivo?step=clientes&offset=0
```

### 2. Importación de compras (proveedores → compras → detalles)

**URL inicial:**
```
https://api.smartpyme.test/api/import-masivo-compras?step=proveedores&offset=0
```

## Pasos

1. Abre la URL inicial en el navegador.
2. La respuesta viene en JSON con un campo `next_url`.
3. Abre ese `next_url` para continuar.
4. Repite hasta que la respuesta indique `"done": true`.

## Ejemplo de respuesta

```json
{
  "step": "clientes",
  "offset": 100,
  "total": 5000,
  "processed": 100,
  "done": false,
  "next_url": "https://api.smartpyme.test/api/import-masivo?step=clientes&offset=100",
  "message": "Clientes 100/5000"
}
```

Cuando termine:

```json
{
  "done": true,
  "message": "Importación completada."
}
```

## Notas

- **Orden:** Hay que completar un paso antes del siguiente (clientes → ventas → detalles).
- **Reintentos:** Si falla, puedes reanudar con el mismo `step` y `offset` donde quedaste.
- **Stock en ventas:** La importación de ventas descuenta inventario y actualiza el kardex. Si no hay stock suficiente, permite continuar (puede quedar stock negativo).
- **Compras:** La importación de compras suma al inventario y actualiza costo promedio del producto.
- **Proveedores faltantes:** Si una compra referencia un `id_proveedor` que no está en `proveedores.php`, primero se busca en la BD si ya existe un proveedor con ese `id` e `id_empresa`. Si existe, se usa. Si no, se crea un placeholder ("Proveedor importación #...").
- **Reimportar compras incompletas:** Si solo se importaron parte de las compras (p. ej. 21 de 32), borra `Backend/storage/app/import_masivo_compras_progress.json`, elimina manualmente las compras/detalles ya importados en BD si lo deseas, y vuelve a ejecutar desde `?step=proveedores&offset=0`.
