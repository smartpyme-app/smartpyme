# Diseño: Procesar DTE en detalle (pago, crédito, retaceo, productos)

**Fecha:** 2026-08-26  
**Estado:** Aprobado  
**Ticket:** [SPT-419](https://smartpyme.atlassian.net/browse/SPT-419)  
**Tipo:** Corrección + ampliación de la vista de detalle DTE

---

## 1. Problema

Al procesar un DTE desde `/dte-management/dtes/:id` la pantalla no ofrece lo mismo que crear compra/gasto o importar JSON:

- Categoría de gasto (parcialmente; falta el resto de clasificación operativa)
- Contado vs crédito
- Forma de pago (y banco si aplica)
- Es para retaceo
- En compras: vincular líneas a productos de inventario y editar cantidades

Si los productos no coinciden, el DTE queda en `pendiente_clasificacion` y `DteToIvaService::insertFromDteDocument` **omite la compra** también en el proceso **manual**. El usuario no puede terminar.

La importación JSON individual/masiva de compras (`DocumentoImportService` + `facturacion-compra`) ya resuelve match de productos, forma de pago y crédito. El detalle DTE no reutiliza esa lógica en UI.

## 2. Objetivo

Procesar el DTE **en el detalle**, con los mismos campos operativos de compra/gasto y, si el destino es compra, el mismo match de productos que la importación JSON.

## 3. Fuera de alcance

- Consigna, compra/gasto recurrente, otros cargos, percepción/renta extra
- Extraer un componente Angular compartido del modal de conciliación
- Crear producto nuevo desde el detalle (sí se busca/asigna uno existente)
- Columnas nuevas en `dte_documents` (pago/retaceo/mappings no se persisten hasta Procesar)
- Cambiar el job automático: sin mappings sigue omitiendo compras en `pendiente_clasificacion`

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Superficie | Solo `dte-detail` (opción 1) |
| Reuso | APIs de importación JSON (`productos/resolver-importacion-dte`, `productos/buscar-modal`, catálogo formas de pago / bancos) |
| Persistencia intermedia | No. Destino/categoría/proyecto siguen en autosave; pago/crédito/retaceo/mappings van en `POST .../procesar` |
| Crédito | Switch. On → estado Pendiente + fecha de pago. Off → Pagada (compra) o Confirmado (gasto) |
| Forma de pago | Precargar de `resumen.pagos[0]` si existe; si no, Efectivo |
| Banco | Obligatorio si forma de pago ≠ Efectivo y ≠ Wompi |
| Retaceo | Switch `es_retaceo` si contabilidad habilitada; default false |
| Productos | Solo destino `compra`. Tabla con selector y cantidad editable. Procesar bloquea si falta producto o cantidad ≤ 0 |
| Job automático | Sin `line_mappings` + `pendiente_clasificacion` → skip (igual que hoy) |
| Proceso manual | Con `line_mappings` **no** skip; crea la compra |

## 5. Payload `POST /api/dtes/{id}/procesar`

Además de `destino`, `id_proyecto`, `id_categoria`, `tipo_gasto`, `tipo_costo_gasto`:

```json
{
  "forma_pago": "Transferencia",
  "credito": true,
  "fecha_pago": "2026-09-15",
  "detalle_banco": "Banco Agrícola",
  "es_retaceo": false,
  "line_mappings": [
    { "index": 0, "id_producto": 12, "cantidad": 3 }
  ]
}
```

`line_mappings` es obligatorio en destino compra si el DTE tiene líneas en `cuerpoDocumento`. Cada índice `0..n-1` debe tener `id_producto` y `cantidad > 0`.

## 6. Backend

- `DteProcesoOpciones`: parseo, estados, skip vs proceso manual, validación de líneas.
- `DteToIvaService::insertFromDteDocument($document, ?DteProcesoOpciones $opciones = null)`.
- `createCompra` / `createGasto` aplican `forma_pago`, `estado`, `fecha_pago`, `detalle_banco`, `es_retaceo`.
- Compra: si hay mappings, usa `id_producto` + `cantidad` del mapping; no exige match automático.
- `GET /api/dtes/{id}` incluye `pago_sugerido` (`forma_pago`, `credito`) inferido del JSON.

## 7. Frontend

En el bloque Procesar de `dte-detail`:

- Forma de pago, crédito, fecha de pago, banco, retaceo
- Destino compra: columna Producto (`ng-select` typeahead) y cantidad editable; resolver automático al cargar / al cambiar a compra
- Destino gasto: líneas solo descriptivas
- Botón Procesar deshabilitado / error si compra con líneas sin producto

## 8. Errores

- Compra con línea sin producto o cantidad ≤ 0 → 422, no crea registro
- Crédito sin fecha de pago → 422
- Forma de pago que requiere banco sin `detalle_banco` → 422
- Duplicado por `codigo_generacion` → se marca procesado y se avisa (actual)

## 9. Pruebas

Backend: `DteProcesoOpciones` (skip, estados, validación de mappings y pago).

Frontend: payload de procesar, visibilidad compra vs gasto, `requiereBanco`, bloqueo si faltan productos.
