# Restaurante POS v2 — cuenta mesa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rediseñar la pantalla de mesa abierta como POS táctil (catálogo por categorías + orden al lado + división/precuenta/facturación), reutilizando las APIs de sesión actuales.

**Architecture:** Misma ruta `restaurante/cuenta/:id`. Shell `cuenta-mesa` con layout A. Lógica pura de navegación/división en helpers testeables. Endpoint ligero `restaurante/pos-menu/*` para catálogo (categorías → subcategorías o productos). Sheet de agregar (qty + nota). Flujo de cuenta táctil que arma el mismo payload `dividir` que hoy entiende `PreCuentaController`.

**Tech Stack:** Angular (módulo `RestauranteModule`, `standalone: false`), Laravel API, Jasmine unit tests para helpers TS, PHPUnit para el menú POS.

**Spec:** `Docs/superpowers/specs/2026-08-04-restaurante-pos-v2-cuenta-mesa-design.md`

## Global Constraints

- No filtrar catálogo por `genera_comanda` (solo afecta Enviar cocina/barra).
- Catálogo único: productos + servicios (`enable = true`), juntos por categoría/subcategoría.
- Agregar ítem: cantidad + nota libre; sin modificadores.
- Layout A: catálogo ~60% + orden derecha; móvil apilado (&lt;768px).
- División: completo + equitativa + por ítems táctil + partir línea; payload compatible con `solicitarCuenta` actual.
- No rediseñar mapa ni cocina; no flag “visible en restaurante”; no offline.
- No tocar `Frontend/src/environments/environment.ts`.
- Componentes hijos: `standalone: false`, declarar en `RestauranteModule`.
- Precio de lista en menú: campo `producto.precio` (mismo que usa `OrdenDetalleController::store`).

## File map

| File | Role |
|------|------|
| `Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php` | Catálogo POS: categorías, hijos, productos, búsqueda |
| `Backend/routes/modulos/restaurante.php` | Rutas `pos-menu/*` |
| `Backend/tests/Unit/Restaurante/PosMenuQueryTest.php` | Self-check query/mapeo (o Feature si se prefiere HTTP) |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.ts` | Decide siguiente nivel (subcats vs productos) |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.spec.ts` | Tests navegación |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.ts` | Asignación táctil + validación sumas |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.spec.ts` | Tests división |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/*` | Grilla categorías/subcats/productos + buscador |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/*` | Modal qty + nota |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/*` | UI solicitar cuenta / dividir / precuentas |
| `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.{ts,html,css}` | Shell layout A + orquestación |
| `Frontend/src/app/views/restaurante/restaurante.module.ts` | Declarar componentes |
| `Frontend/src/app/services/restaurante.service.ts` | Métodos `posMenu*` |

---

### Task 1: Helpers puros — navegación menú + división

**Files:**
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.ts`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.spec.ts`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.ts`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.spec.ts`

**Interfaces:**
- Produces: `resolveCategoriaTap(subcategoriasCount: number): 'subcategorias' | 'productos'`
- Produces: `asignarUnidades(matriz: Record<number, Record<number, number>>, ordenDetalleId: number, persona: number, cantidad: number, maxLinea: number): Record<number, Record<number, number>>`
  - Clampa cantidad a `[0, maxLinea]`; no permite que la suma de la línea exceda `maxLinea` (recorta el valor pedido).
- Produces: `sumaPersonaLinea(matriz, ordenDetalleId, numPersonas): number`
- Produces: `lineaCompleta(matriz, ordenDetalleId, cantidadLinea, numPersonas): boolean` — true si `|suma - cantidadLinea| < 0.0001`
- Produces: `matrizValida(items: {id:number; cantidad:number}[], matriz, numPersonas): boolean`
- Produces: `buildAsignaciones(items, matriz, numPersonas): { orden_detalle_id: number; pagador_index: number; cantidad: number }[]` — solo qty &gt; 0; redondeo a 4 decimales como `cuenta-mesa` actual

- [ ] **Step 1: Write failing tests**

```typescript
import { resolveCategoriaTap } from './pos-menu-nav';
import {
  asignarUnidades,
  lineaCompleta,
  matrizValida,
  buildAsignaciones
} from './pos-division';

describe('pos-menu-nav', () => {
  it('sin subcategorías va a productos', () => {
    expect(resolveCategoriaTap(0)).toBe('productos');
  });
  it('con subcategorías va a subcategorías', () => {
    expect(resolveCategoriaTap(3)).toBe('subcategorias');
  });
});

describe('pos-division', () => {
  it('asigna y parte línea entre personas', () => {
    let m: Record<number, Record<number, number>> = {};
    m = asignarUnidades(m, 10, 1, 0.5, 1);
    m = asignarUnidades(m, 10, 2, 0.5, 1);
    expect(lineaCompleta(m, 10, 1, 2)).toBe(true);
    expect(matrizValida([{ id: 10, cantidad: 1 }], m, 2)).toBe(true);
    expect(buildAsignaciones([{ id: 10, cantidad: 1 }], m, 2)).toEqual([
      { orden_detalle_id: 10, pagador_index: 1, cantidad: 0.5 },
      { orden_detalle_id: 10, pagador_index: 2, cantidad: 0.5 }
    ]);
  });

  it('no permite sumar más que la línea', () => {
    let m = asignarUnidades({}, 10, 1, 1, 1);
    m = asignarUnidades(m, 10, 2, 1, 1);
    expect(lineaCompleta(m, 10, 1, 2)).toBe(true);
    expect(m[10][2]).toBe(0);
  });
});
```

- [ ] **Step 2: Run tests — expect FAIL (modules missing)**

Run: `cd Frontend && npx ng test --include='**/cuenta-mesa/pos/*.spec.ts' --browsers=ChromeHeadless --watch=false`

Expected: FAIL (cannot find module / no provider) or compile error for missing files.

- [ ] **Step 3: Implement helpers**

```typescript
// pos-menu-nav.ts
export function resolveCategoriaTap(subcategoriasCount: number): 'subcategorias' | 'productos' {
  return subcategoriasCount > 0 ? 'subcategorias' : 'productos';
}
```

```typescript
// pos-division.ts
const EPS = 0.0001;

function round4(n: number): number {
  return Math.round(n * 10000) / 10000;
}

export function sumaPersonaLinea(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  numPersonas: number
): number {
  const row = matriz[ordenDetalleId] || {};
  let s = 0;
  for (let p = 1; p <= numPersonas; p++) {
    s += Number(row[p] || 0);
  }
  return round4(s);
}

export function asignarUnidades(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  persona: number,
  cantidad: number,
  maxLinea: number
): Record<number, Record<number, number>> {
  const next: Record<number, Record<number, number>> = { ...matriz, [ordenDetalleId]: { ...(matriz[ordenDetalleId] || {}) } };
  const row = next[ordenDetalleId];
  const otros = sumaPersonaLinea(next, ordenDetalleId, 20) - Number(row[persona] || 0);
  const maxParaPersona = Math.max(0, round4(maxLinea - otros));
  row[persona] = round4(Math.min(Math.max(0, cantidad), maxParaPersona));
  return next;
}

export function lineaCompleta(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  cantidadLinea: number,
  numPersonas: number
): boolean {
  return Math.abs(sumaPersonaLinea(matriz, ordenDetalleId, numPersonas) - Number(cantidadLinea)) < EPS;
}

export function matrizValida(
  items: { id: number; cantidad: number }[],
  matriz: Record<number, Record<number, number>>,
  numPersonas: number
): boolean {
  return items.every((it) => lineaCompleta(matriz, it.id, it.cantidad, numPersonas));
}

export function buildAsignaciones(
  items: { id: number; cantidad: number }[],
  matriz: Record<number, Record<number, number>>,
  numPersonas: number
): { orden_detalle_id: number; pagador_index: number; cantidad: number }[] {
  const out: { orden_detalle_id: number; pagador_index: number; cantidad: number }[] = [];
  for (const it of items) {
    const row = matriz[it.id] || {};
    for (let p = 1; p <= numPersonas; p++) {
      const q = round4(Number(row[p] || 0));
      if (q > 0) {
        out.push({ orden_detalle_id: it.id, pagador_index: p, cantidad: q });
      }
    }
  }
  return out;
}
```

- [ ] **Step 4: Run tests — expect PASS**

Run: `cd Frontend && npx ng test --include='**/cuenta-mesa/pos/*.spec.ts' --browsers=ChromeHeadless --watch=false`

Expected: PASS (all specs green).

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/views/restaurante/cuenta-mesa/pos/
git commit -m "$(cat <<'EOF'
test: helpers de navegación y división para POS mesa v2

EOF
)"
```

---

### Task 2: API `pos-menu` (catálogo restaurante)

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php`
- Modify: `Backend/routes/modulos/restaurante.php` (añadir rutas e import)
- Create: `Backend/tests/Feature/Restaurante/PosMenuTest.php` (o Unit con query builder mockeado si Feature es pesado; preferir Feature autenticado si el proyecto ya tiene patrón)

**Interfaces:**
- `GET restaurante/pos-menu/categorias` → `[{ id, nombre, img?, subcategorias_count }]` solo `enable=true`, empresa del user, orden nombre.
  - `subcategorias_count` = count de `categoria_subcategorias` con `categoria_id`.
- `GET restaurante/pos-menu/categorias/{id}/contenido` →  
  `{ modo: 'subcategorias'|'productos', items: [...] }`  
  - Si `subcategorias_count > 0`: modo subcategorias, items `{ id, nombre, img? }`.  
  - Si no: modo productos (ver shape abajo), filtrados `id_categoria = id`, `enable = true`.
- `GET restaurante/pos-menu/subcategorias/{id}/productos` → lista productos `subcategoria_id = id`, `enable = true`.
- `GET restaurante/pos-menu/buscar?q=` → productos/servicios `enable`, match nombre/código (like), max 30.
- Shape producto: `{ id, nombre, precio, img, tipo, genera_comanda }`  
  - `img`: accessor `img` del modelo (default `productos/default.jpg` cuenta como “tiene placeholder”).
- **No** filtrar por `genera_comanda`.

- [ ] **Step 1: Write failing Feature test skeleton**

```php
<?php

namespace Tests\Feature\Restaurante;

use Tests\TestCase;
// Usar factories/usuarios del proyecto si existen; si no, marcar test como integración manual
// y preferir Unit del mapeo. Mínimo: assert rutas registradas.

final class PosMenuTest extends TestCase
{
    public function test_rutas_pos_menu_existen(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($r) => $r->uri())
            ->all();
        $this->assertTrue(collect($routes)->contains(fn ($u) => str_contains($u, 'restaurante/pos-menu/categorias')));
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `cd Backend && php artisan test --filter=PosMenuTest`

Expected: FAIL (ruta no existe).

- [ ] **Step 3: Implement controller + routes**

Añadir en `restaurante.php`:

```php
use App\Http\Controllers\Api\Restaurante\PosMenuController;
// dentro del group:
Route::get('/pos-menu/categorias', [PosMenuController::class, 'categorias']);
Route::get('/pos-menu/categorias/{id}/contenido', [PosMenuController::class, 'contenidoCategoria']);
Route::get('/pos-menu/subcategorias/{id}/productos', [PosMenuController::class, 'productosSubcategoria']);
Route::get('/pos-menu/buscar', [PosMenuController::class, 'buscar']);
```

Implementar `PosMenuController` con scopes de empresa (`id_empresa` del auth user), `Producto::with('imagenes')` o append `img`, sin paginación pesada (listados acotados). Productos: `where('enable', true)` e incluir tipo Servicio.

- [ ] **Step 4: Run test — expect PASS**

Run: `cd Backend && php artisan test --filter=PosMenuTest`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php Backend/routes/modulos/restaurante.php Backend/tests/Feature/Restaurante/PosMenuTest.php
git commit -m "$(cat <<'EOF'
feat: API pos-menu para catálogo táctil de restaurante

EOF
)"
```

---

### Task 3: Métodos de servicio + shell layout A

**Files:**
- Modify: `Frontend/src/app/services/restaurante.service.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.css`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.ts` (solo wrappers si hace falta)

**Interfaces:**
- Produces on `RestauranteService`:
  - `posMenuCategorias(): Observable<PosMenuCategoria[]>`
  - `posMenuContenidoCategoria(id: number): Observable<PosMenuContenido>`
  - `posMenuProductosSubcategoria(id: number): Observable<PosMenuProducto[]>`
  - `posMenuBuscar(q: string): Observable<PosMenuProducto[]>`
- Types mínimos en el mismo service file (o `pos-menu.types.ts` si crece).

- [ ] **Step 1: Add service methods**

```typescript
posMenuCategorias(): Observable<any[]> {
  return this.api.getAll(BASE + 'pos-menu/categorias');
}
posMenuContenidoCategoria(id: number): Observable<any> {
  return this.api.read(BASE + 'pos-menu/categorias/', id + '/contenido');
  // Si ApiService.read no soporta sufijo, usar getAll/get con URL completa:
  // return this.api.getAll(BASE + `pos-menu/categorias/${id}/contenido`);
}
```

Verificar el helper real de `ApiService` (`getAll`, `get`, `getAsText`) y usar el que ya usan otras rutas con path compuesto (ej. `putToUrl` pattern → preferir `getAll`/`store` con path string completo).

- [ ] **Step 2: Restructure HTML to layout A**

Estructura objetivo (mantener funcionalidad actual del buscador temporalmente dentro de la columna izquierda):

```html
<div class="pos-shell" *ngIf="!loading && sesion">
  <div class="pos-catalog">
    <!-- header mesa corto + buscador actual temporal -->
    <app-buscador-productos *ngIf="puedeOperarOrden" (selectProducto)="onProductoSelect($event)"></app-buscador-productos>
  </div>
  <aside class="pos-orden">
    <!-- lista ítems + totales + Enviar + Solicitar cuenta (mover desde col-lg-4) -->
  </aside>
</div>
```

CSS mínimo:

```css
.pos-shell {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr);
  gap: 1rem;
  min-height: calc(100vh - 120px);
  align-items: stretch;
}
.pos-orden {
  position: sticky;
  top: 0.5rem;
  max-height: calc(100vh - 100px);
  overflow: auto;
}
@media (max-width: 767.98px) {
  .pos-shell {
    grid-template-columns: 1fr;
  }
  .pos-orden {
    position: static;
    max-height: none;
  }
}
```

- [ ] **Step 3: Manual smoke**

Run frontend (`ng serve` según flujo del repo). Abrir una mesa: ver dos columnas en desktop; apilado en viewport estrecho. Agregar por buscador sigue funcionando.

- [ ] **Step 4: Commit**

```bash
git add Frontend/src/app/services/restaurante.service.ts Frontend/src/app/views/restaurante/cuenta-mesa/
git commit -m "$(cat <<'EOF'
feat: layout POS A en cuenta-mesa y client pos-menu

EOF
)"
```

---

### Task 4: Componente catálogo táctil + sheet agregar

**Files:**
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/pos-catalogo.component.ts`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/pos-catalogo.component.html`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/pos-catalogo.component.css`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/pos-sheet-agregar.component.ts`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/pos-sheet-agregar.component.html`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/pos-sheet-agregar.component.css`
- Modify: `Frontend/src/app/views/restaurante/restaurante.module.ts`
- Modify: `cuenta-mesa.component.{ts,html}` — reemplazar buscador principal por catálogo; buscador queda secundario dentro del catálogo

**Interfaces:**
- `PosCatalogoComponent`
  - `@Output() productoElegido = new EventEmitter<any>()`
  - Niveles: `raiz | subcategorias | productos`
  - Usa `resolveCategoriaTap(cat.subcategorias_count)`
  - Breadcrumb + atrás; input búsqueda con debounce ~300ms → `posMenuBuscar`
  - Tile producto: `img` (si es default.jpg o vacío → clase placeholder), nombre, precio
- `PosSheetAgregarComponent`
  - Inputs: `producto`, `visible`
  - Outputs: `confirmar: { producto_id, cantidad, notas }`, `cancelar`
  - UI: +/- cantidad (min 0.01), textarea/input nota, botones

- [ ] **Step 1: Scaffold components + declare in module**

Selectors: `app-pos-catalogo`, `app-pos-sheet-agregar`. `standalone: false`.

- [ ] **Step 2: Wire catalog navigation**

Al init: `posMenuCategorias()`.  
Tap categoría → `posMenuContenidoCategoria(id)` → si modo subcategorías mostrar grilla; si productos, mostrar productos.  
Tap subcategoría → `posMenuProductosSubcategoria(id)`.  
Tap producto → emitir `productoElegido` (sheet lo abre el padre).

- [ ] **Step 3: Wire sheet in shell**

```typescript
// cuenta-mesa
productoSheet: any = null;
mostrarSheetAgregar = false;

onProductoCatalogo(p: any): void {
  this.productoSheet = p;
  this.mostrarSheetAgregar = true;
}

onConfirmarAgregar(payload: { producto_id: number; cantidad: number; notas: string }): void {
  this.restauranteService.agregarItem(this.sesionId, payload).subscribe({
    next: () => {
      this.mostrarSheetAgregar = false;
      this.cargarSesion();
    },
    error: (err) => this.alertService.error(err)
  });
}
```

Quitar el auto-add de cantidad 1 del buscador como camino principal; el buscador del catálogo también abre el sheet (no agregar directo).

- [ ] **Step 4: Manual smoke**

Categoría sin subcats → productos. Con subcats → subcats → productos. Agregar con qty/nota. Imagen default se ve como placeholder.

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/views/restaurante/
git commit -m "$(cat <<'EOF'
feat: catálogo táctil y sheet de agregar en POS mesa

EOF
)"
```

---

### Task 5: Panel orden (acciones primarias) + flujo cuenta táctil

**Files:**
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.ts`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.html`
- Create: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.css`
- Modify: `restaurante.module.ts`
- Modify: `cuenta-mesa.component.{ts,html,css}` — extraer modal solicitar cuenta al nuevo componente; usar `pos-division` helpers

**Interfaces:**
- `PosFlujoCuentaComponent`
  - `@Input() sesion`
  - `@Input() visible`
  - `@Output() cerrado`, `@Output() confirmado` (padre recarga sesión / imprime)
  - Modos: completo | dividir(equitativa|por_items)
  - Por ítems: tabs Persona 1…N; tap ítem → si `cantidad === 1` asigna 1 a persona activa (y 0 a otras si era reasignación total); si `cantidad > 1` o partial, abrir mini-prompt de unidades → `asignarUnidades`
  - Badges: sin asignar / parcial / completo vía `lineaCompleta` / suma
  - Confirmar: `matrizValida` + `buildAsignaciones` → emitir body `{ dividir?: {...} }` o vacío para completo
- Padre: `confirmarSolicitarCuenta` usa body del hijo o mantiene lógica actual llamando `solicitarCuenta`
- Conservar: imprimir precuenta, `irAFacturar` / `prepararFactura` como hoy
- Botones grandes en panel: Enviar, Solicitar cuenta

- [ ] **Step 1: Move modal UI into `pos-flujo-cuenta`**

Copiar markup del modal actual; reemplazar tabla numérica por lista táctil + tabs.

- [ ] **Step 2: Hook helpers**

```typescript
onTapItem(item: any): void {
  if (this.tipoDivision !== 'por_items') return;
  const max = Number(item.cantidad);
  if (max === 1) {
    // limpia otras personas en esa línea y pone 1 en activa
    let m = { ...this.matriz };
    for (let p = 1; p <= this.numPagadores; p++) {
      m = asignarUnidades(m, item.id, p, p === this.personaActiva ? 1 : 0, max);
    }
    this.matriz = m;
    return;
  }
  // abrir prompt cantidad para personaActiva
  this.itemPartir = item;
}
```

- [ ] **Step 3: Keep equitativa + completo**

Mismos radios/inputs N; equitativa no usa matriz.

- [ ] **Step 4: Manual smoke**

Cobro completo → precuenta. Equitativa N=2. Por ítems: partir 1 pizza 0.5/0.5; facturar precuenta pendiente.

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/views/restaurante/
git commit -m "$(cat <<'EOF'
feat: división táctil y flujo precuenta/facturación en POS mesa

EOF
)"
```

---

### Task 6: Pulido móvil + verificación final

**Files:**
- Modify: CSS de `cuenta-mesa`, `pos-catalogo`, `pos-flujo-cuenta` (targets táctiles ≥44px, grillas `minmax`)
- Modify: spec status doc if needed (no code)

- [ ] **Step 1: Mobile CSS**

En &lt;768px: catálogo primero; orden con botones sticky bottom o bloque final visible. Grilla productos 2–3 columnas.

- [ ] **Step 2: Re-run helper tests**

Run: `cd Frontend && npx ng test --include='**/cuenta-mesa/pos/*.spec.ts' --browsers=ChromeHeadless --watch=false`  
Run: `cd Backend && php artisan test --filter=PosMenuTest`

Expected: PASS.

- [ ] **Step 3: Checklist manual vs spec**

- [ ] Layout A desktop / apilado móvil  
- [ ] Cat → subcat → producto; cat sin subcat → producto  
- [ ] Imagen / placeholder  
- [ ] Sheet qty + nota  
- [ ] Buscador secundario  
- [ ] Enviar comanda sin cambiar reglas  
- [ ] Completo / equitativa / por ítems + partir línea  
- [ ] Precuenta + facturar  

- [ ] **Step 4: Commit**

```bash
git add Frontend/src/app/views/restaurante/
git commit -m "$(cat <<'EOF'
style: pulido móvil del POS mesa v2

EOF
)"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Layout A + móvil | 3, 6 |
| Catálogo cat/subcat/productos + imagen | 2, 4 |
| Sheet qty + nota | 4 |
| Catálogo único sin filtro comanda | 2 |
| Panel orden + Enviar + Solicitar cuenta | 3, 5 |
| Completo / equitativa / táctil / partir línea | 1, 5 |
| Precuenta → facturar | 5 |
| Helpers + check runnable | 1, 2 |
| Fuera de alcance mapa/cocina/modificadores | respetado |

## Placeholder scan

Sin TBD en pasos; rutas y firmas definidas; commits por task.
