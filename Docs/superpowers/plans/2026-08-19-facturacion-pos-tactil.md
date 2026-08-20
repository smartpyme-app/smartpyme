# Facturación POS táctil Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nueva vista `/ventas-pos/crear` con catálogo táctil y checkout simplificado, activable como tercera opción en Mi cuenta, reutilizando motor fiscal v2 (IVA, DTE, crédito, lotes, consigna, presentaciones).

**Architecture:** Shell `FacturacionPosComponent` con layout tipo restaurante. Catálogo vía API `inventario/pos-menu/*`. Tap producto → mapper → `VentaDetallesV2Component.productoSelect`. Opciones fiscales secundarias en modal. Guard extiende redirect para `version_facturacion === 'pos'`.

**Tech Stack:** Angular 17+ (standalone components en v2), Laravel API, ngx-bootstrap modal, Jasmine, PHPUnit.

**Spec:** `Docs/superpowers/specs/2026-08-19-facturacion-pos-tactil-design.md`

## Global Constraints

- `version_facturacion`: `original` | `v2` | `pos` — una sola activa.
- POS usa motor v2 (precio con IVA, `impuestos-venta.util`).
- No reutilizar rutas `restaurante/pos-menu/*` ni permiso `restaurante.ver`.
- Layout A: catálogo ~60% + ticket derecha; móvil apilado (&lt;768px).
- Opciones avanzadas en **modal** (sheet en móvil), no acordeón inline.
- Tap catálogo debe pasar por `productoSelect` (origen stock, lotes, consigna, stock).
- No tocar `Frontend/src/environments/environment.ts`.
- Cambios mínimos en v2: extraer mapper compartido; `@Input()` para ocultar buscador si hace falta.
- Commits frecuentes por tarea; no amend salvo reglas del usuario.

## File map

| File | Role |
|------|------|
| `Backend/app/Http/Controllers/Api/Inventario/PosMenuVentasController.php` | Catálogo POS ventas + ficha producto |
| `Backend/app/Support/Inventario/PosMenuCatalog.php` | Queries compartidas con restaurante (trait/static) |
| `Backend/routes/modulos/inventario/productos.php` | Rutas `pos-menu/*` |
| `Backend/tests/Feature/Inventario/PosMenuVentasTest.php` | Tests catálogo ventas |
| `Frontend/src/app/guards/facturacion-version.guard.ts` | Redirect `pos` |
| `Frontend/src/app/views/admin/empresa/empresa.component.{ts,html}` | Tercera opción select |
| `Frontend/src/app/views/ventas/ventas.routing.module.ts` | Ruta `/ventas-pos/crear` |
| `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/utils/producto-detalle-v2.mapper.ts` | Mapper producto → detalle (extraído del buscador) |
| `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/utils/producto-detalle-v2.mapper.spec.ts` | Tests mapper |
| `Frontend/src/app/services/pos-menu-ventas.service.ts` | Cliente HTTP catálogo |
| `Frontend/src/app/views/ventas/facturacion/facturacion-pos/*` | Shell POS + subcomponentes |
| `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/detalles/venta-detalles-v2.component.ts` | `@Input() ocultarEntrada` + pipeline |
| `Frontend/src/app/views/ventas/facturacion/facturacion.module.ts` | Declaraciones / imports |

---

### Task 1: Config Mi cuenta + guard de ruta

**Files:**
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.html:737-743`
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.ts:1913-1932`
- Modify: `Frontend/src/app/guards/facturacion-version.guard.ts`
- Modify: `Frontend/src/app/guards/facturacion-version.guard.spec.ts` (create if missing)
- Modify: `Frontend/src/app/views/ventas/ventas.routing.module.ts`

**Interfaces:**
- Produces: guard redirige a `/ventas-pos/crear` cuando `version_facturacion === 'pos'`
- Produces: ruta lazy/declarada `FacturacionPosComponent` (placeholder inicial OK)

- [ ] **Step 1: Añadir opción `pos` en Mi cuenta**

En `empresa.component.html`, agregar tercera opción al select:

```html
<option value="pos">POS táctil (precio con IVA)</option>
```

Actualizar mensaje en `updateVersionFacturacion` para reconocer `pos`.

- [ ] **Step 2: Extender guard**

```typescript
// facturacion-version.guard.ts — dentro del if venta/crear
if (versionFacturacion === 'pos') {
  this.router.navigate(['/ventas-pos/crear'], { queryParams: next.queryParams, state: nav?.extras?.state });
  return false;
}
if (versionFacturacion === 'v2') {
  // existente
}
```

- [ ] **Step 3: Registrar ruta**

```typescript
{ path: 'ventas-pos/crear', component: FacturacionPosComponent, title: 'Facturación POS' },
```

Importar componente (placeholder vacío hasta Task 5).

- [ ] **Step 4: Test guard**

```typescript
it('redirige a ventas-pos/crear cuando version_facturacion es pos', () => {
  // mock auth_user custom_empresa.configuraciones.version_facturacion = 'pos'
  expect(guard.canActivate(...)).toBe(false);
  expect(router.navigate).toHaveBeenCalledWith(['/ventas-pos/crear'], jasmine.any(Object));
});
```

Run: `cd Frontend && npx ng test --include='**/facturacion-version.guard.spec.ts' --browsers=ChromeHeadless --watch=false`

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/guards/facturacion-version.guard.ts Frontend/src/app/views/admin/empresa/ Frontend/src/app/views/ventas/ventas.routing.module.ts
git commit -m "feat: add pos billing version flag and route guard"
```

---

### Task 2: Backend catálogo POS ventas

**Files:**
- Create: `Backend/app/Support/Inventario/PosMenuCatalog.php`
- Create: `Backend/app/Http/Controllers/Api/Inventario/PosMenuVentasController.php`
- Modify: `Backend/routes/modulos/inventario/productos.php`
- Create: `Backend/tests/Feature/Inventario/PosMenuVentasTest.php`

**Interfaces:**
- Produces: `GET inventario/pos-menu/categorias` → `[{ id, nombre, img, subcategorias_count }]`
- Produces: `GET inventario/pos-menu/categorias/{id}/contenido` → `{ modo, items }`
- Produces: `GET inventario/pos-menu/subcategorias/{id}/productos` → `[{ id, id_presentacion, nombre, precio_con_iva, img, tipo }]`
- Produces: `GET inventario/pos-menu/buscar?q=` → array fichas (límite 30)
- Produces: `GET inventario/pos-menu/productos/{id}?id_bodega=` → producto completo para venta (impuestos, inventarios, lotes)

- [ ] **Step 1: Extraer PosMenuCatalog**

Mover métodos estáticos reutilizables desde `PosMenuController` (`queryCategoriasRaiz`, `querySubcategorias`, `queryProductos`, `modoContenido`, `mapProductos`) a `PosMenuCatalog`. Actualizar `PosMenuController` para delegar (diff pequeño, sin cambiar comportamiento restaurante).

- [ ] **Step 2: PosMenuVentasController**

```php
// Precio con IVA para tiles v2
private function precioConIva(Producto $p, ?float $precioBase = null): float
{
    $sinIva = (float) ($precioBase ?? $p->precio);
    $pct = resolverPorcentajeImpuestoVenta($p->porcentaje_impuesto, $empresaIva);
    return $pct > 0 ? round($sinIva * (1 + $pct / 100), 2) : $sinIva;
}
```

Usar helper existente de impuestos si hay uno en PHP; si no, duplicar fórmula mínima alineada con v2.

Endpoint `productos/{id}`: reutilizar lógica de `ProductosController::searchByQuery` para un solo id (with inventarios, lotes, impuestos, presentaciones).

- [ ] **Step 3: Rutas**

```php
Route::get('/inventario/pos-menu/categorias', [PosMenuVentasController::class, 'categorias']);
Route::get('/inventario/pos-menu/categorias/{id}/contenido', [PosMenuVentasController::class, 'contenidoCategoria']);
Route::get('/inventario/pos-menu/subcategorias/{id}/productos', [PosMenuVentasController::class, 'productosSubcategoria']);
Route::get('/inventario/pos-menu/buscar', [PosMenuVentasController::class, 'buscar']);
Route::get('/inventario/pos-menu/productos/{id}', [PosMenuVentasController::class, 'productoParaVenta']);
```

- [ ] **Step 4: Tests PHPUnit**

```php
public function test_categorias_raiz_solo_empresa_autenticada(): void { ... }
public function test_producto_para_venta_incluye_impuestos_y_lotes(): void { ... }
public function test_precio_tile_incluye_iva_cuando_empresa_cobra_iva(): void { ... }
```

Run: `cd Backend && php artisan test --filter=PosMenuVentasTest`

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Support/Inventario/PosMenuCatalog.php Backend/app/Http/Controllers/Api/Inventario/PosMenuVentasController.php Backend/routes/modulos/inventario/productos.php Backend/tests/Feature/Inventario/PosMenuVentasTest.php Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php
git commit -m "feat: add inventario pos-menu catalog for billing POS"
```

---

### Task 3: Mapper producto → detalle v2 (compartido)

**Files:**
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/utils/producto-detalle-v2.mapper.ts`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/utils/producto-detalle-v2.mapper.spec.ts`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/buscador/tienda-venta-buscador-v2.component.ts`

**Interfaces:**
- Produces: `armarDetalleDesdeProductoV2(producto, venta, ctx): DetalleV2Payload`
- Consumes: `ApiService`, `SumPipe` (stock compuesto), `impuestos-venta.util`

- [ ] **Step 1: Extraer lógica de `selectProducto`**

Mover `armarPreciosDetalleV2`, `armarListaPreciosDetalleV2`, bloque presentaciones/stock/lotes a `producto-detalle-v2.mapper.ts`. Buscador v2 llama al mapper (sin cambio de comportamiento).

- [ ] **Step 2: Tests mapper**

```typescript
it('calcula precio_iva desde porcentaje_impuesto del producto', () => {
  const det = armarDetalleDesdeProductoV2({ precio: 100, porcentaje_impuesto: 13, tipo: 'Producto' }, venta, ctx);
  expect(parseFloat(det.precio_iva)).toBe(113);
});
it('marca id_presentacion y factor_conversion', () => { ... });
```

Run: `cd Frontend && npx ng test --include='**/producto-detalle-v2.mapper.spec.ts' --browsers=ChromeHeadless --watch=false`

- [ ] **Step 3: Commit**

```bash
git commit -m "refactor: extract v2 product-to-detail mapper for reuse in POS"
```

---

### Task 4: Servicio + catálogo frontend

**Files:**
- Create: `Frontend/src/app/services/pos-menu-ventas.service.ts`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-catalogo-ventas/pos-catalogo-ventas.component.ts`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-catalogo-ventas/pos-catalogo-ventas.component.html`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-catalogo-ventas/pos-catalogo-ventas.component.css`
- Reuse: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.ts`

**Interfaces:**
- Produces: `@Output() productoElegido` con ficha tile `{ id, id_presentacion, nombre, precio_con_iva, img }`
- Consumes: `PosMenuVentasService.posMenuCategorias()`, etc.

- [ ] **Step 1: PosMenuVentasService**

```typescript
@Injectable({ providedIn: 'root' })
export class PosMenuVentasService {
  private base = 'inventario/pos-menu';
  constructor(private api: ApiService) {}
  categorias(idBodega?: number) { return this.api.getAll(`${this.base}/categorias`, { id_bodega: idBodega }); }
  // ... contenido, subcategoria, buscar, productoParaVenta(id, { id_bodega })
}
```

- [ ] **Step 2: PosCatalogoVentasComponent**

Copiar estructura de `PosCatalogoComponent`; cambiar service; mostrar `precio_con_iva | currency`; pasar `id_bodega` desde `@Input() venta`.

- [ ] **Step 3: Commit**

```bash
git commit -m "feat: add billing POS catalog component and service"
```

---

### Task 5: Shell FacturacionPosComponent + ticket

**Files:**
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/facturacion-pos.component.ts`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/facturacion-pos.component.html`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/facturacion-pos.component.css`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-ticket-ventas/*`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/detalles/venta-detalles-v2.component.ts`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/detalles/venta-detalles-v2.component.html`

**Interfaces:**
- Consumes: métodos públicos de `FacturacionV2Component` vía composición (ver Step 2)
- Produces: layout `.pos-shell` (copiar CSS de `cuenta-mesa.component.css`)

- [ ] **Step 1: `@Input() ocultarEntradaProductos` en VentaDetallesV2**

Cuando `true`, ocultar bloque buscador/botones (`*ngIf="!ocultarEntradaProductos"`) pero mantener tabla oculta también — solo modales (lote, supervisor) y métodos públicos.

Alternativa aceptable: `@ViewChild(VentaDetallesV2Component)` sin renderizar tabla; POS ticket propio llama `productoSelect`.

- [ ] **Step 2: FacturacionPosComponent — orquestación**

Opción recomendada (lazy): crear `FacturacionVentaCoreService` extrayendo de v2 solo:
- `venta` state
- `cargarDatosIniciales`, `loadData`, `sumTotal`, `setCliente`, `setCredito`, `onFacturar`, `setDocumento`, `setBodega`

Si el extract es muy grande para una tarea, **composición temporal**: `FacturacionPosComponent` extiende lógica copiando imports de servicios y delegando a métodos privados compartidos en un archivo `facturacion-venta-core.ts` exportando funciones puras + un servicio delgado.

Flujo tap catálogo:

```typescript
async onProductoCatalogo(tile: PosMenuProducto): Promise<void> {
  const full = await firstValueFrom(
    this.posMenuVentas.productoParaVenta(tile.id, { id_bodega: this.venta.id_bodega, id_presentacion: tile.id_presentacion })
  );
  const detallePayload = armarDetalleDesdeProductoV2(full, this.venta, ctx);
  this.ventaDetalles.productoSelect(detallePayload);
  this.sumTotal();
}
```

- [ ] **Step 3: PosTicketVentasComponent**

Lista `venta.detalles` con +/- cantidad (emit `cantidadChange`), botón lote (emit `editarLote`), eliminar. Totales copiados de sección v2 (subtotal, impuestos, total).

- [ ] **Step 4: Cabecera compacta**

Fecha, bodega, documento, correlativo en una fila scrollable (como v2 pero más densa).

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: add billing POS shell with catalog and ticket panel"
```

---

### Task 6: Modal opciones avanzadas

**Files:**
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-opciones-avanzadas/pos-opciones-avanzadas.component.ts`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-opciones-avanzadas/pos-opciones-avanzadas.component.html`
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-opciones-avanzadas/pos-opciones-avanzadas.component.css`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/facturacion-pos.component.html`

**Interfaces:**
- Inputs: `venta`, flags empresa (propina, consigna, FE país, etc.)
- Outputs: `ventaChange`, `sumTotal`, `setCredito`, `setConsigna`, ...
- Produces: `contarOpcionesAvanzadasActivas(venta): number` para badge

- [ ] **Step 1: Extraer campos de facturacion-v2.component.html**

Copiar bloques de retención, renta, consigna, recurrente, cobrar_impuestos, cuenta terceros, propina, canal, proyecto, exportación — condicionados igual que v2.

- [ ] **Step 2: Modal + footer total**

```html
<div class="modal-footer d-flex justify-content-between">
  <strong>Total: {{ venta.total | currency }}</strong>
  <button type="button" class="btn btn-primary" (click)="aplicar()">Aplicar</button>
</div>
```

`aplicar()` → `sumTotal.emit()` → `modalRef.hide()`.

- [ ] **Step 3: Sheet móvil**

Clase `.pos-opciones-sheet` con `@media (max-width: 767.98px)` fixed bottom; mismo contenido.

- [ ] **Step 4: Test badge**

```typescript
expect(contarOpcionesAvanzadasActivas({ retencion: true, cobrar_propina: false })).toBe(1);
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: add advanced options modal for billing POS"
```

---

### Task 7: Checkout panel (cliente, crédito, pago, facturar)

**Files:**
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/facturacion-pos.component.html`
- Reuse: `BuscadorClientesComponent`, `CrearClienteComponent`, `MetodosDePagoComponent`

- [ ] **Step 1: Panel checkout**

Cliente + crear cliente + badge puntos (si fidelización). Switch crédito. Alerta estado cuenta (copiar v2). Método pago + banco/gift card. Observaciones. Botones Opciones avanzadas + Facturar.

- [ ] **Step 2: Wire facturar**

`(click)="onFacturar()"` — mismo método que v2 (validaciones fecha pago, banco, gift card).

- [ ] **Step 3: Búsqueda/escáner en catálogo**

Input search en catálogo: debounce → `posMenuBuscar`; código exacto → cargar producto y `productoSelect` (reutilizar normalize del buscador v2).

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: wire checkout and invoicing in billing POS"
```

---

### Task 8: Línea venta — lote, presentación, editar

**Files:**
- Create: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-linea-venta-sheet/*`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-pos/pos-ticket-ventas/*`

- [ ] **Step 1: Acciones por línea**

Botón ⋮ → sheet con: cantidad, descuento (si permitido), tipo gravado, **Elegir lote** (llama `ventaDetalles.abrirModalLoteVenta`).

- [ ] **Step 2: Presentación múltiple**

Si tile padre tiene `presentaciones.length > 1` sin `id_presentacion`, sheet selector antes de agregar.

- [ ] **Step 3: Verificar origen consigna**

Manual QA + test integración: producto con consigna activa muestra diálogo origen al agregar (viene de `productoSelect`, no reimplementar).

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: line actions for lots and presentations in billing POS"
```

---

### Task 9: Module wiring + i18n + graphify

**Files:**
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion.module.ts`
- Modify: assets i18n si hay claves nuevas para label POS
- Run: `graphify update .`

- [ ] **Step 1: Registrar componentes en facturacion.module**

- [ ] **Step 2: Añadir traducción opción Mi cuenta (si usan i18n para v2 label)**

- [ ] **Step 3: `graphify update .`**

- [ ] **Step 4: Commit**

```bash
git commit -m "chore: wire billing POS module and update graphify"
```

---

### Task 10: QA manual checklist

- [ ] Mi cuenta → POS → menú venta abre `/ventas-pos/crear`
- [ ] Tap producto simple → +1 línea, total con IVA correcto
- [ ] Producto con presentaciones → ficha o selector
- [ ] Producto con lotes Manual → modal lote obligatorio antes de facturar
- [ ] Producto consigna → diálogo bodega vs consigna
- [ ] Cliente con crédito → auto marca crédito + fecha pago
- [ ] Modal avanzadas: retención/propina cambian total en footer
- [ ] Facturar contado → venta creada + DTE si aplica
- [ ] Escaneo barcode exacto → agrega sin buscar en grilla
- [ ] Móvil: layout apilado, sheet opciones avanzadas

---

## Spec coverage (self-review)

| Requisito spec | Task |
|----------------|------|
| Tercera opción Mi cuenta | Task 1 |
| Guard redirect pos | Task 1 |
| Catálogo inventario API | Task 2 |
| Layout POS 60/40 | Task 5 |
| Motor v2 facturar | Task 5, 7 |
| Modal opciones avanzadas | Task 6 |
| Presentaciones | Task 2, 4, 8 |
| Lotes | Task 5, 8 |
| Origen consigna | Task 3, 5 (productoSelect) |
| Cliente + crédito | Task 7 |
| Precio con IVA tiles | Task 2 |
| Fuera alcance cotización | No task (documentado) |

## Execution Handoff

Plan complete and saved to `Docs/superpowers/plans/2026-08-19-facturacion-pos-tactil.md`.

**Two execution options:**

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
