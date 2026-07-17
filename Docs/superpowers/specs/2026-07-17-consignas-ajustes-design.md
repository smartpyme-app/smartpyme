# Diseño: Ajustes de consignas (listado, stock, revisión, canal en reporte)

**Fecha:** 2026-07-17  
**Estado:** Aprobado — plan de implementación en curso  
**Tipo:** Bug fix + mejora UX  
**Fuera de alcance:** Documento de remisión en ventas (ya funciona; omitido)

---

## 1. Contexto y problema

Consigna de compra usa un pool virtual (`ConsignaDisponibleService`: entradas en compras `estado = Consigna` menos ventas con `origen_stock = consigna_compra`), aparte del stock físico (kardex).

Problemas actuales:

1. **Listado `/consignas` tab Compras** agrega por producto; el usuario necesita ver **compras en consigna** (como el listado de compras), con Revisar y Detalles por compra.
2. **Stock virtual mal calculado** tras pago parcial: recibir 10 → vender 4 → pagar 4 deja disponible = 2 en lugar de 6, porque la entrada baja a 6 pero la salida histórica (4) sigue restando.
3. **Revisión de consigna:** el botón “Ajustar a vendido” está deshabilitado si vendido ≤ 0; se debe poder poner cantidad **0** para dejar el producto entero en la consigna remanente.
4. **Reporte ventas totales:** no existe canal de catálogo “Consigna”; al vender desde pool de compra en consigna el canal mostrado es el fiscal elegido. Se requiere etiqueta **“Consigna”** en el reporte sin cambiar `id_canal`.

---

## 2. Objetivos

1. Tab Compras de consignas = listado de compras `estado = Consigna`, con Revisar y Detalles (productos de esa compra).
2. Corregir `disponible` del pool virtual para que lo ya pagado no vuelva a restarse contra lo remanente.
3. Permitir ajustar cantidad a 0 en revisión cuando vendido = 0.
4. En detalle de ventas totales, mostrar canal **“Consigna”** si la venta tiene al menos una línea con `origen_stock = consigna_compra`.

---

## 3. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Remisión en ventas | Fuera de alcance (ya OK) |
| Listado consignas / Compras | Opción A: solo compras en consigna (reemplaza agregación por producto) |
| Fix stock | Enfoque 1: restar solo ventas no liquidadas |
| Ajuste a 0 | Habilitar “Ajustar a vendido” con vendido = 0 → cantidad 0 |
| Canal en reporte | Opción B: no tocar `id_canal`; etiqueta display “Consigna” |
| Cuándo etiqueta Consigna | Si la venta tiene ≥1 línea con `origen_stock = consigna_compra` |

---

## 4. Arquitectura por pieza

### 4.1 Listado de consignas — tab Compras

**Backend**

- Cambiar `ConsignasController::indexCompras` para devolver **compras** con `estado = Consigna`, `cotizacion = 0`, con relaciones útiles (`proveedor`, `bodega`, `sucursal`, `detalles.producto`).
- Respuesta orientada a fila de compra (no `groupBy` producto), p. ej.: `id`, `uuid`, `fecha`, `proveedor`, `tipo_documento`, `referencia`, `fecha_pago`, `bodega`, `sucursal`, `total`, `detalles[]` (producto, cantidad, costo, total).
- Alinear `ConsignasComprasExport` al mismo criterio (una fila por compra o detalle de compra; consistente con la UI).
- Mantener endpoints de disponible / ventas-consigna usados por facturación; no romper ventas.

**Frontend**

- Reescribir UI de `ProductosConsignasComprasComponent` (y template) al patrón del listado de compras:
  - Tabla de compras en consigna.
  - **Revisar** → navegar a `/compra/consigna/revisar/:id` (ruta existente).
  - **Detalles** → modal con productos de esa compra (`detalles`).
- Tab Ventas (`/consignas/ventas`) sin cambios en este spec.
- Actualizar specs del componente si existen.

### 4.2 Fix stock — ventas no liquidadas

**Fórmula objetivo**

```
entrada_abierta = Σ cantidad en detalles de compras estado=Consigna (producto, bodega, no cotización)

ventas_consigna = Σ cantidad base de detalles_venta con origen_stock=consigna_compra (misma bodega)

liquidado = Σ cantidad en detalles de compras que fueron consigna y ya están Pagadas
            (misma producto/bodega, no cotización)

salida_efectiva = max(0, ventas_consigna − liquidado)

disponible = min(max(0, entrada_abierta − salida_efectiva), stock_fisico)
```

**Escenario canónico**

| Paso | entrada | ventas | liquidado | disponible |
|------|---------|--------|-----------|------------|
| Recibe 10 | 10 | 0 | 0 | 10 |
| Vende 4 | 10 | 4 | 0 | 6 |
| Paga 4 | 6 | 4 | 4 | 6 |

**Cómo identificar `liquidado` sin ambigüedad**

- Agregar flag durable en `compras`: `es_consigna` (boolean, default false).
- Al crear/guardar compra con `estado = Consigna`, set `es_consigna = true`.
- Al pagar en `facturacionConsigna`, **no** limpiar el flag (la compra Pagada sigue siendo liquidación de consigna); la remanente nueva también `es_consigna = true` y `estado = Consigna`.
- Migración: columna + backfill:
  1. `es_consigna = 1` donde `estado = Consigna`.
  2. `es_consigna = 1` en compras `Pagada` que tengan al menos un kardex asociado con detalle que contenga `Compra a consigna`.
  3. Compras Pagadas sin ese kardex quedan en `false` (no se inventa liquidación).
- `liquidado` = suma de detalles en compras con `es_consigna = true` y `estado = Pagada` (producto + bodega).

**Qué no cambia**

- Stock físico / kardex en recepción y en venta: igual.
- `facturacionConsigna` sigue sin mover inventario físico.

**Tests**

- Unit test de `ConsignaDisponibleService` con el escenario 10 → vende 4 → paga 4 → disponible 6.
- Caso sin ventas: paga 4 de 10 → disponible 6.
- Caso vende 4 sin pagar → disponible 6.

### 4.3 Ajuste a 0 en revisión de consigna (compras)

**Frontend** (`facturacion-compra-consigna`)

- Quitar el disable `cantidadVendidaDetalle <= 0` del botón “Ajustar a vendido” (o permitir explícitamente cuando vendido === 0).
- `ajustarCantidadVendida()` asigna `cantidad = cantidadVendidaDetalle` (incluido 0).
- Comportamiento al guardar: líneas con cantidad 0 no se incluyen en la compra Pagada (ya lo hace el backend con `if ($detalle['cantidad'] > 0)`); el remanente recibe la diferencia (cantidad original − 0 = toda la cantidad) → producto sigue en consigna.

**Backend**

- Sin regla nueva obligatoria en este spec; verificar que qty 0 no rompe el split cuando otras líneas sí se pagan.
- Opcional (no bloqueante): si todas las líneas van a 0, rechazar o no crear Pagada vacía — solo si el flujo actual ya falla; si no, dejar como está.

### 4.4 Etiqueta de canal “Consigna” en ventas totales

**Alcance:** export / reporte `detalle-ventas-totales` (`VentasDetallesExport` y cualquier vista que alimente el mismo reporte).

**Regla de display**

- Si la **venta** tiene al menos un detalle con `origen_stock = consigna_compra` → columna Canal = `"Consigna"`.
- Si no → `venta.canal.nombre` como hoy.
- **No** modificar `id_canal` en facturación ni crear canal de catálogo.

**Eager load:** incluir `origen_stock` en detalles (o exists) para no N+1.

**Filtro por canal en el reporte:** fuera de alcance cambiar filtros a “Consigna” virtual; solo la columna/etiqueta de salida. (Si el usuario filtra por un `id_canal` real, el comportamiento de filtro sigue siendo por FK.)

---

## 5. Archivos principales a tocar

| Área | Archivos |
|------|----------|
| Stock | `Backend/app/Services/Inventario/ConsignaDisponibleService.php`, migración `es_consigna`, `ComprasController` (set flag), tests unitarios nuevos |
| Listado API | `Backend/app/Http/Controllers/Api/Inventario/ConsignasController.php`, `Backend/app/Exports/ConsignasComprasExport.php` |
| Listado UI | `Frontend/.../consignas-compras/productos-consignas-compras.component.{ts,html,spec.ts}` |
| Revisión | `Frontend/.../facturacion-consigna/facturacion-compra-consigna.component.{ts,html}` |
| Reporte | `Backend/app/Exports/VentasDetallesExport.php` (+ test si existe patrón) |

---

## 6. Criterios de aceptación

1. En `/consignas`, tab Compras, se ven compras en consigna; Revisar abre revisión; Detalles muestra productos de esa compra.
2. Recibir 10, vender 4 desde consigna, pagar 4 → disponible de consigna = 6 (UI/API disponible y validación al vender).
3. En revisión, con vendido 0, se puede ajustar a 0 y al guardar ese producto permanece en consigna remanente.
4. Una venta con línea `origen_stock = consigna_compra` muestra Canal = “Consigna” en detalle de ventas totales; `id_canal` en BD no cambia.
5. Remisión en ventas no se modifica.

---

## 7. Fuera de alcance

- Emisión MH DTE Nota de Remisión (`04`).
- Crear canal de catálogo “Consigna” o reasignar `id_canal` al facturar.
- Rediseño del tab Ventas de consignas.
- Validación dura “cantidad a pagar ≥ cantidad vendida” (mejora futura).
- Corregir filtros del reporte para filtrar por etiqueta virtual “Consigna”.
