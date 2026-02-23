# Revisión de implementación de lotes en inventario

Revisión post-merge de la rama de lotes a `main`. Se verifican los hallazgos reportados por Cursor bot.

---

## 1. FEFO fallback falla por mutación del query (Severidad: Media) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Ventas/VentasController.php`

**Producto principal (aprox. líneas 655-664):**
```php
case 'FEFO':
    $loteSeleccionado = $lotesQuery->whereNotNull('fecha_vencimiento')
        ->orderBy('fecha_vencimiento', 'asc')
        ->first();
    if (!$loteSeleccionado) {
        $loteSeleccionado = $lotesQuery->orderBy('created_at', 'asc')->first();  // BUG: $lotesQuery ya tiene whereNotNull
    }
```

**Productos compuestos (aprox. líneas 767-772):** Mismo patrón.

**Problema:** `$lotesQuery` se modifica con `whereNotNull('fecha_vencimiento')`. Al reutilizarlo en el fallback FIFO, el query sigue teniendo esa condición, por lo que los lotes sin fecha de vencimiento nunca se consideran y la venta puede fallar para productos que solo tienen lotes sin vencimiento.

**Recomendación:** Usar un query nuevo para el fallback, por ejemplo:
- `$loteSeleccionado = \App\Models\Inventario\Lote::where(...)->where('stock', '>', 0)->orderBy('created_at', 'asc')->first();`
- O clonar el query antes del `switch`: `$lotesQueryFifo = clone $lotesQuery` y usar el clon solo en el fallback FEFO.

---

## 2. Cancelación de traslado ignora lote destino almacenado (Severidad: Media) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Inventario/TrasladosController.php`, método `delete()` (aprox. líneas 401-421).

**Código actual:**
```php
$loteDestino = Lote::where('id_producto', $producto->id)
    ->where('id_bodega', $traslado->id_bodega)
    ->where('numero_lote', $loteOrigen->numero_lote)
    ->first();
```

**Problema:** El modelo `Traslado` tiene `lote_id_destino` (y relación `loteDestino()`). Si el traslado se creó con un lote destino explícito distinto (por ejemplo otro `numero_lote`), aquí siempre se busca por `numero_lote` del origen. Se puede estar revirtiendo stock en un lote equivocado o no encontrar lote y dejar el stock del lote destino real inflado.

**Recomendación:** Priorizar el lote guardado:
- Si `$traslado->lote_id_destino` está definido, usar `Lote::find($traslado->lote_id_destino)` y restar ahí.
- Si no, mantener la búsqueda actual por `numero_lote` (comportamiento para traslados sin lote destino explícito).

---

## 3. Ajuste de lote actualiza lote sin validar producto/bodega (Severidad: Media) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Inventario/AjustesController.php`, método `storeLote()` (aprox. líneas 171-198).

**Código actual:** Se valida `id_producto`, `id_bodega`, `lote_id`, etc., pero no se comprueba que el lote pertenezca a ese producto y bodega.

```php
$lote = Lote::findOrFail($request->lote_id);
$lote->stock = $request->stock_real;
// ... sin verificar lote->id_producto == request->id_producto ni lote->id_bodega == request->id_bodega
```

**Problema:** Un request manipulado podría enviar `lote_id` de otro producto/bodega. El ajuste se guarda con un producto/bodega y el stock se modifica en otro lote, generando inconsistencia entre kardex/ajustes y stock real del lote.

**Recomendación:** Tras `Lote::findOrFail($request->lote_id)` añadir:
- `$lote->id_producto != $request->id_producto` → 400.
- `$lote->id_bodega != $request->id_bodega` → 400.

---

## 4. Traslado multi-producto no guarda lote destino (Severidad: Media) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Inventario/TrasladosController.php`, método `storeConDetalles()` (aprox. 341-354).

**Código actual:** Se asigna `lote_id` al `Traslado` pero no `lote_id_destino`:
```php
$traslado->lote_id = $detalleData['lote_id'];  // solo esto
// falta: $traslado->lote_id_destino = $detalleData['lote_id_destino'] ?? null;
$traslado->save();
```

**Problema:** En traslados con varios productos, cuando se especifica `lote_id_destino` por detalle, se actualiza el stock del lote destino en memoria pero no se persiste en el registro del traslado. Al cancelar (método `delete`), no hay forma de saber qué lote destino usar y se recurre a `numero_lote`, que puede ser incorrecto si el destino era un lote explícito distinto.

**Recomendación:** Asignar y guardar el lote destino en el mismo bloque donde se asigna `lote_id`:
- `$traslado->lote_id_destino = $detalleData['lote_id_destino'] ?? null;` (o el campo que use el request) antes de `$traslado->save()`.

---

## 5. Lógica duplicada de "lotes activo" en controladores (Severidad: Alta) — **CONFIRMADO**

**Ubicaciones (mismo patrón ~8 líneas):**
- `VentasController.php` (aprox. 506-512)
- `DevolucionVentasController.php` (133, 426)
- `DevolucionComprasController.php` (96, 215)
- `ComprasController.php` (306)
- `Salida.php` (96, 164), `Entrada.php` (97, 175), `Inventario.php` (227)

**Patrón repetido:**
```php
$lotesActivo = false;
if ($empresa && $empresa->custom_empresa) {
    $customConfig = is_string($empresa->custom_empresa) ? json_decode($empresa->custom_empresa, true) : $empresa->custom_empresa;
    $lotesActivo = $customConfig['configuraciones']['lotes_activo'] ?? false;
}
```

**Recomendación:** Centralizar en el modelo `Empresa`, por ejemplo:
- `$empresa->isLotesActivo()` que lea `custom_empresa`, parsee JSON si aplica y devuelva `configuraciones.lotes_activo`. Usar este método en todos los controladores y modelos citados.

---

## 6. Método isLotesActivo duplicado en frontend (Severidad: Media) — **CONFIRMADO**

**Componentes con lógica idéntica o muy similar:**  
`devolucion-venta-detalles.component.ts`, `traslados.component.ts`, `traslado.component.ts`, `productos.component.ts`, `inventario-salida.component.ts`, `lotes.component.ts`, `producto-informacion.component.ts`, `kardex.component.ts`, `inventario-entrada.component.ts`, `ajustes.component.ts`, `compra-detalles.component.ts`, `devolucion-compra-detalles.component.ts`, `crear-producto.component.ts`, `sidebar.component.ts`, `activar-lotes-masivo.component.ts`.  
`empresa.component.ts` tiene una variante con `getCustomConfig`.

**Recomendación:** Extraer a un servicio (por ejemplo en `ApiService` o un `ConfiguracionEmpresaService`) un método `isLotesActivo(): boolean` que use `custom_empresa` y devuelva `configuraciones.lotes_activo`. Inyectar el servicio en los componentes y reemplazar las implementaciones locales.

---

## 7. Falta filtro por empresa en getProximosAVencer (Severidad: Media) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Inventario/LotesController.php`.

- `getProximosAVencer()` (aprox. 238-243): no aplica `->where('id_empresa', Auth::user()->id_empresa)`.
- `getVencidos()` (aprox. 253-258): sí incluye `->where('id_empresa', Auth::user()->id_empresa)`.

**Problema:** El modelo `Lote` tiene un global scope que filtra por `id_empresa` cuando `Auth::check()`. En peticiones web normales el resultado puede ser correcto, pero la inconsistencia con `getVencidos()` sugiere un descuido y en contextos sin usuario (p. ej. colas, jobs) el scope no aplicaría y se podrían devolver lotes de otras empresas.

**Recomendación:** Añadir en `getProximosAVencer()` el mismo filtro explícito: `->where('id_empresa', Auth::user()->id_empresa)` (o usar un scope reutilizable en ambos métodos).

---

## 8. Lógica de filtros duplicada en LotesController (Severidad: Baja) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Inventario/LotesController.php`.

- `index()` (aprox. 20-57) y `getByProducto()` (aprox. 163-197) aplican los mismos filtros: `id_bodega`, `numero_lote`, `vencimiento_proximo`, `vencidos`, `con_stock`, `sin_stock`, con la misma implementación.

**Recomendación:** Extraer a un método privado, por ejemplo `applyFilters(Builder $query, Request $request): Builder`, y llamarlo desde `index()` y `getByProducto()` para evitar duplicación y futuras desincronizaciones.

---

## 9. Falta verificación lotesActivo para producto principal en ventas (Severidad: Media) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Ventas/VentasController.php`.

- Producto principal (aprox. línea 622): solo se comprueba `$producto->inventario_por_lotes` para entrar al flujo de lotes.
- Productos compuestos (aprox. línea 739): se comprueba `$productoCompuesto->inventario_por_lotes && $lotesActivo`.

**Problema:** Si la empresa tiene lotes desactivados (`lotes_activo = false`) pero el producto tiene `inventario_por_lotes = true`, el producto principal seguiría procesando lotes (descuento por lote, etc.) mientras que los compuestos no. Comportamiento inconsistente y posible uso de lotes cuando la empresa los tiene desactivados.

**Recomendación:** En el bloque del producto principal, usar la misma condición que en compuestos: `if ($producto && $producto->inventario_por_lotes && $lotesActivo)` (o equivalente con `$empresa->isLotesActivo()` cuando se centralice).

---

## 10. Falta rollback de transacción tras error de validación en store de traslados (Severidad: Alta) — **CONFIRMADO**

**Ubicación:** `Backend/app/Http/Controllers/Api/Inventario/TrasladosController.php`, método `store()` (un producto).

- Línea 86: `DB::beginTransaction()`.
- Líneas 122-124: se descuenta el lote de origen y se hace `save()`.
- Líneas 130-136: si `lote_id_destino` viene en el request y falla la validación (bodega o producto), se hace `return Response()->json([...], 400)` sin `DB::rollBack()`.

**Problema:** La transacción queda abierta; el descuento del lote de origen puede quedar persistido (dependiendo del driver y autocommit), dejando inventario inconsistente.

**Recomendación:** No hacer `return` directo en esos casos. Lanzar una excepción (por ejemplo `throw new \Exception('...')`) para que el `catch` existente ejecute `DB::rollBack()`, de forma análoga a `storeConDetalles()`. Opcionalmente, antes del `return` llamar explícitamente a `DB::rollBack()` si se quiere mantener el `return` (menos limpio que un único flujo por excepción).

---

## Resumen

| # | Hallazgo | Severidad | Estado |
|---|----------|-----------|--------|
| 1 | FEFO fallback por mutación del query | Media | **Corregido** (clone del query) |
| 2 | Cancelación traslado ignora lote destino | Media | **Corregido** (usa lote_id_destino si existe) |
| 3 | Ajuste lote sin validar producto/bodega | Media | **Corregido** (validación en storeLote) |
| 4 | storeConDetalles no guarda lote_id_destino | Media | **Corregido** (se guarda en Traslado) |
| 5 | Duplicación check lotes_activo (backend) | Alta | **Corregido** (Empresa::isLotesActivo()) |
| 6 | Duplicación isLotesActivo (frontend) | Media | **Corregido** (ApiService.isLotesActivo()) |
| 7 | getProximosAVencer sin filtro empresa | Media | **Corregido** (filtro id_empresa) |
| 8 | Filtros duplicados en LotesController | Baja | **Corregido** (método applyFilters) |
| 9 | Producto principal sin check lotesActivo | Media | **Corregido** (condición unificada) |
| 10 | Sin rollback en store() traslados | Alta | **Corregido** (throw en lugar de return) |

Todas las mejoras han sido implementadas.
