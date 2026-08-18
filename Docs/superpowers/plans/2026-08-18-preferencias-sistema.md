# Preferencias del sistema — grupos y filas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** La pestaña Preferencias del sistema muestra un grupo a la vez, con menú interno y filas sin cajas, sin cambiar qué se guarda.

**Architecture:** Helper puro `resolverGrupoPreferencias` decide el slug. El componente lee/escribe `?grupo=` junto a `?tab=`. El HTML del tab se parte en seis `*ngIf`; el modelo sigue en `this.empresa`. CSS nuevo reemplaza `.empresa-pref-tile`.

**Tech Stack:** Angular standalone (`EmpresaComponent`), ngx-bootstrap tabs (sin tabs anidados), Bootstrap 5, query params de `ActivatedRoute`/`Router`.

**Spec:** `Docs/superpowers/specs/2026-08-18-preferencias-sistema-design.md`

## Global Constraints

- No endpoint nuevo. No extraer componente hijo. No buscador. No wizard. No iconos en el menú interno.
- No cambiar `onSubmit()`, ni toggles que ya llaman `onSubmit()` al cambiar, ni el payload `empresa`.
- No reescribir copy ni keys i18n `country.tax.*`; solo reubicar.
- Condiciones de plan / rol / funcionalidad iguales a las actuales (`*ngIf` / `@if`).
- Pestañas de Cuenta (Datos, FE, Integraciones, WooCommerce, Shopify, BoxFul) no se tocan.
- No tocar `Frontend/src/environments/environment.ts`.
- Antes de explorar: `graphify query`. Tras editar TS/HTML/CSS: `graphify update .` al cierre de la última task.

## File map

| File | Role |
|------|------|
| `Frontend/src/app/views/admin/empresa/preferencias-grupo.ts` | Helper puro + lista de slugs |
| `Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs` | Asserts del resolver (node) |
| `Frontend/src/app/views/admin/empresa/empresa.component.ts` | Estado, nav, sync URL |
| `Frontend/src/app/views/admin/empresa/empresa.component.css` | Nav, filas, sticky; sin tiles |
| `Frontend/src/app/views/admin/empresa/empresa.component.html` | Tab Preferencias reordenado |

---

### Task 1: Resolver de grupo

**Files:**
- Create: `Frontend/src/app/views/admin/empresa/preferencias-grupo.ts`
- Create: `Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs`

**Interfaces:**
- Produces: `PREFERENCIAS_GRUPOS` — `readonly ['modulos', 'documentos', 'facturacion', 'inventario', 'permisos', 'cuenta']`
- Produces: `export type PreferenciasGrupo = typeof PREFERENCIAS_GRUPOS[number]`
- Produces: `export function resolverGrupoPreferencias(slug: string | null | undefined, puedeVerPermisos: boolean): PreferenciasGrupo`

- [ ] **Step 1: Write the failing check**

Create `Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs`:

```js
/**
 * Smoke: resolver de grupo en Preferencias del sistema.
 * Run: node Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const tsPath = path.join(dir, 'preferencias-grupo.ts');
assert.equal(fs.existsSync(tsPath), true, 'falta preferencias-grupo.ts');

const ts = fs.readFileSync(tsPath, 'utf8');
assert.match(ts, /export function resolverGrupoPreferencias/);
assert.match(ts, /modulos/);
assert.match(ts, /documentos/);
assert.match(ts, /facturacion/);
assert.match(ts, /inventario/);
assert.match(ts, /permisos/);
assert.match(ts, /cuenta/);

const require = createRequire(import.meta.url);
let mod;
try {
  mod = require('./preferencias-grupo.ts');
} catch {
  const js = ts
    .replace(/export type[^\n]+\n/g, '')
    .replace(/: PreferenciasGrupo/g, '')
    .replace(/ as const/g, '')
    .replace(/export /g, '');
  const fn = new Function(`${js}; return { resolverGrupoPreferencias, PREFERENCIAS_GRUPOS };`);
  mod = fn();
}

const { resolverGrupoPreferencias } = mod;
assert.equal(resolverGrupoPreferencias('facturacion', true), 'facturacion');
assert.equal(resolverGrupoPreferencias('permisos', false), 'modulos');
assert.equal(resolverGrupoPreferencias(null, true), 'modulos');
assert.equal(resolverGrupoPreferencias('foo', true), 'modulos');
assert.equal(resolverGrupoPreferencias('permisos', true), 'permisos');
assert.equal(resolverGrupoPreferencias(undefined, false), 'modulos');

console.log('preferencias-grupo.check: ok');
```

- [ ] **Step 2: Run check to verify it fails**

Run: `node Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs`

Expected: FAIL (`falta preferencias-grupo.ts` o `MODULE_NOT_FOUND`).

- [ ] **Step 3: Write minimal implementation**

Create `Frontend/src/app/views/admin/empresa/preferencias-grupo.ts`:

```ts
export const PREFERENCIAS_GRUPOS = [
    'modulos',
    'documentos',
    'facturacion',
    'inventario',
    'permisos',
    'cuenta',
] as const;

export type PreferenciasGrupo = typeof PREFERENCIAS_GRUPOS[number];

export function resolverGrupoPreferencias(
    slug: string | null | undefined,
    puedeVerPermisos: boolean,
): PreferenciasGrupo {
    if (slug === 'permisos' && !puedeVerPermisos) {
        return 'modulos';
    }
    if (slug && (PREFERENCIAS_GRUPOS as readonly string[]).includes(slug)) {
        return slug as PreferenciasGrupo;
    }
    return 'modulos';
}
```

- [ ] **Step 4: Run check to verify it passes**

Run: `node Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs`

Expected: `preferencias-grupo.check: ok`

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/views/admin/empresa/preferencias-grupo.ts Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs
git commit -m "$(cat <<'EOF'
feat: resolver de grupo para preferencias del sistema

EOF
)"
```

---

### Task 2: Estado y URL en EmpresaComponent

**Files:**
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.ts`

**Interfaces:**
- Consumes: `resolverGrupoPreferencias`, `PREFERENCIAS_GRUPOS`, `PreferenciasGrupo` from `./preferencias-grupo`
- Produces: `preferenciasGrupo: PreferenciasGrupo`
- Produces: `preferenciasNav: { slug: PreferenciasGrupo; label: string }[]`
- Produces: `puedeVerPermisosPreferencias(): boolean`
- Produces: `preferenciasNavLabel(): string`

- [ ] **Step 1: Add import and state**

After the existing import of `impuestos-default-por-pais`, add:

```ts
import {
    PREFERENCIAS_GRUPOS,
    PreferenciasGrupo,
    resolverGrupoPreferencias,
} from './preferencias-grupo';
```

Next to `public activeTabSlug = 'datos';` add:

```ts
    public preferenciasGrupo: PreferenciasGrupo = 'modulos';
    public readonly preferenciasNav: { slug: PreferenciasGrupo; label: string }[] = [
        { slug: 'modulos', label: 'Módulos' },
        { slug: 'documentos', label: 'Documentos e impresión' },
        { slug: 'facturacion', label: 'Facturación' },
        { slug: 'inventario', label: 'Inventario y productos' },
        { slug: 'permisos', label: 'Permisos' },
        { slug: 'cuenta', label: 'Cuenta' },
    ];
```

- [ ] **Step 2: Helpers + URL sync**

Add these methods on the class (near `onTabSelect`):

```ts
    public puedeVerPermisosPreferencias(): boolean {
        const tipo = this.apiService.auth_user()?.tipo;
        return tipo === 'Administrador' || tipo === 'Super Admin';
    }

    public preferenciasNavLabel(): string {
        return this.preferenciasNav.find((i) => i.slug === this.preferenciasGrupo)?.label ?? 'Módulos';
    }

    public setPreferenciasGrupo(slug: string): void {
        const next = resolverGrupoPreferencias(slug, this.puedeVerPermisosPreferencias());
        const current = this.route.snapshot.queryParamMap.get('grupo');
        this.preferenciasGrupo = next;
        if (current === next) {
            this.cdr.markForCheck();
            return;
        }
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: { grupo: next },
            queryParamsHandling: 'merge',
            replaceUrl: true,
        });
        this.cdr.markForCheck();
    }

    private syncPreferenciasGrupoFromUrl(grupoRaw: string | null): void {
        const next = resolverGrupoPreferencias(grupoRaw, this.puedeVerPermisosPreferencias());
        this.preferenciasGrupo = next;
        const raw = grupoRaw ?? 'modulos';
        if (raw !== next) {
            this.router.navigate([], {
                relativeTo: this.route,
                queryParams: { grupo: next },
                queryParamsHandling: 'merge',
                replaceUrl: true,
            });
        }
    }
```

In `ngOnInit`, after reading `tabFromUrl`:

```ts
        this.syncPreferenciasGrupoFromUrl(this.route.snapshot.queryParamMap.get('grupo'));
```

Replace `ngAfterViewInit` so it also lee `grupo` (no solo `tab`). Quitar el `map`+`distinctUntilChanged` de tab-only:

```ts
    ngAfterViewInit() {
        this.route.queryParamMap.pipe(
            this.untilDestroyed()
        ).subscribe((m) => {
            const slug = (m.get('tab') ?? 'datos').toLowerCase();
            if (slug !== this.activeTabSlug) {
                this.activeTabSlug = slug;
            }
            this.syncPreferenciasGrupoFromUrl(m.get('grupo'));
            this.cdr.markForCheck();
        });
    }
```

Do **not** change `onTabSelect`: `queryParamsHandling: 'merge'` already keeps `grupo`.

- [ ] **Step 3: Re-run resolver check**

Run: `node Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs`

Expected: `preferencias-grupo.check: ok`

- [ ] **Step 4: Commit**

```bash
git add Frontend/src/app/views/admin/empresa/empresa.component.ts
git commit -m "$(cat <<'EOF'
feat: recordar grupo de preferencias en la URL

EOF
)"
```

---

### Task 3: CSS — nav, filas, sticky

**Files:**
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.css`

**Interfaces:**
- Produces classes: `.empresa-pref-nav`, `.empresa-pref-nav-btn`, `.empresa-pref-row`, `.empresa-pref-row-text`, `.empresa-pref-row-label`, `.empresa-pref-row-help`, `.empresa-pref-field`, `.empresa-pref-save`
- Removes usage of `.empresa-pref-tile` (borrar esas reglas)

- [ ] **Step 1: Replace the file contents**

Overwrite `Frontend/src/app/views/admin/empresa/empresa.component.css` with:

```css
.empresa-pref-nav {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.empresa-pref-nav-btn {
  display: block;
  width: 100%;
  text-align: left;
  border: 0;
  background: transparent;
  color: #495057;
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
}

.empresa-pref-nav-btn:hover {
  background: rgba(var(--bs-primary-rgb), 0.08);
  color: var(--bs-primary);
}

.empresa-pref-nav-btn.active {
  background: var(--bs-primary);
  color: #fff;
}

.empresa-pref-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.empresa-pref-row-text {
  min-width: 0;
  flex: 1 1 auto;
}

.empresa-pref-row-label {
  display: block;
  margin: 0;
  font-weight: 600;
}

.empresa-pref-row-help {
  margin: 0.25rem 0 0;
}

.empresa-pref-row .form-switch {
  flex: 0 0 auto;
  padding-left: 2.5em;
}

.empresa-pref-field {
  padding: 0.875rem 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.empresa-pref-field > label {
  font-weight: 600;
}

.empresa-pref-save {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: #fff;
  border-top: 1px solid rgba(0, 0, 0, 0.08);
}

@media (max-width: 991.98px) {
  .empresa-pref-nav {
    flex-direction: row;
    overflow-x: auto;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    margin-bottom: 0.75rem;
  }

  .empresa-pref-nav-btn {
    width: auto;
    white-space: nowrap;
  }
}
```

- [ ] **Step 2: Confirm no leftover tile rules**

Run: `rg "empresa-pref-tile" Frontend/src/app/views/admin/empresa/empresa.component.css`

Expected: no matches.

- [ ] **Step 3: Commit**

```bash
git add Frontend/src/app/views/admin/empresa/empresa.component.css
git commit -m "$(cat <<'EOF'
style: filas y menú interno de preferencias

EOF
)"
```

---

### Task 4: HTML del tab Preferencias

**Files:**
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.html` — only the tab `heading="Preferencias del sistema"` (today ~lines 389–1144). Do not edit other tabs.

**Interfaces:**
- Consumes: `preferenciasNav`, `preferenciasGrupo`, `setPreferenciasGrupo()`, `puedeVerPermisosPreferencias()`
- Produces: six groups behind `*ngIf="preferenciasGrupo === '…'"`; one `<form>` wrapping nav + content + sticky Guardar

**Conversion recipes (apply to every control; do not rewrite bindings or copy):**

Switch row — before: `form-switch bg-light-info rounded` (and in Facturación, also `empresa-pref-tile`). After:

```html
<div class="empresa-pref-row">
  <div class="empresa-pref-row-text">
    <label class="empresa-pref-row-label" for="EXISTING_ID">EXISTING_LABEL</label>
    <p class="empresa-pref-row-help text-muted small mb-0">EXISTING_HELP</p>
  </div>
  <div class="form-switch m-0">
    <input class="form-check-input" type="checkbox" role="switch" id="EXISTING_ID" ...existing bindings...>
  </div>
</div>
```

If there is no help text, omit the `<p>`. Keep `id`, `name`, `[(ngModel)]`, `[checked]`, `(change)` exactly. Drop `float-end` and `bg-light-info`.

Select/input field:

```html
<div class="empresa-pref-field">
  <label for="EXISTING_ID">EXISTING_LABEL</label>
  <p class="text-muted small">EXISTING_HELP</p>
  <select or input ...existing bindings...></select>
</div>
```

- [ ] **Step 1: Replace the tab shell**

Keep `<tab heading="Preferencias del sistema" ...>` and `</tab>`. Replace the inner `<form>…</form>` with this shell, then fill groups by **cutting** existing controls from the old markup (do not retype bindings).

```html
        <form name="form" (ngSubmit)="onSubmit()">
            <section class="bg-white p-4 rounded mb-3">
                <div class="row">
                    <div class="col-12 col-lg-3 mb-3 mb-lg-0">
                        <nav class="empresa-pref-nav" aria-label="Grupos de preferencias">
                            @for (item of preferenciasNav; track item.slug) {
                              @if (item.slug !== 'permisos' || puedeVerPermisosPreferencias()) {
                                <button type="button" class="empresa-pref-nav-btn"
                                    [class.active]="preferenciasGrupo === item.slug"
                                    [attr.aria-current]="preferenciasGrupo === item.slug ? 'page' : null"
                                    (click)="setPreferenciasGrupo(item.slug)">
                                    {{ item.label }}
                                </button>
                              }
                            }
                        </nav>
                    </div>
                    <div class="col-12 col-lg-9">
                        <h4 class="fw-bold mb-3">{{ preferenciasNavLabel() }}</h4>

                        <div *ngIf="preferenciasGrupo === 'modulos'">
                            <!-- Módulos: see mapping below -->
                        </div>
                        <div *ngIf="preferenciasGrupo === 'documentos'">
                            <!-- Documentos e impresión -->
                        </div>
                        <div *ngIf="preferenciasGrupo === 'facturacion'">
                            <!-- Facturación -->
                        </div>
                        <div *ngIf="preferenciasGrupo === 'inventario'">
                            <!-- Inventario y productos -->
                        </div>
                        <div *ngIf="preferenciasGrupo === 'permisos' && puedeVerPermisosPreferencias()">
                            <!-- Permisos -->
                        </div>
                        <div *ngIf="preferenciasGrupo === 'cuenta'">
                            <!-- Cuenta -->
                        </div>
                    </div>
                </div>
            </section>
            <div class="empresa-pref-save bg-white rounded p-3 mb-3">
                <div class="form-group col-sm-12 pt-2 pb-2 mb-0">
                    <button type="submit" [disabled]="saving" class="btn btn-primary float-end tcla-F8" tooltip="Guardar (F8)">
                        @if (!saving) {
                          <span>Guardar <img appLazyImage="assets/icons/arrow-right-stroke.png" class="icon2 ms-2"></span>
                        }
                        @if (saving) {
                          <span>Guardando...</span>
                        }
                    </button>
                </div>
            </div>
        </form>
```

- [ ] **Step 2: Fill Módulos**

Move these controls here, converted to `empresa-pref-row` / `empresa-pref-field`. Keep every `@if` / `*ngIf` that already wraps them:

| Control | name / id |
|---------|-----------|
| Módulo de citas | `modulo_citas` |
| Módulo de proyectos | `modulo_proyectos` (plan Pro) |
| Módulo de paquetes | `modulo_paquetes` (plan Pro) |
| Restaurantes y pedidos | `vista_modulo_rest_ped` (`*ngIf="tieneAccesoModuloRestaurantePedidos"`) — use `empresa-pref-field` for the select; keep the two existing `<p>` |
| Mostrar columna proyecto | `columna_proyecto` |
| Habilitar módulo de bancos | `modulo_bancos` — cut from the old Facturación block; keep its help paragraph as `empresa-pref-row-help` |
| Activar fidelización | `fidelizacion_activa` (`*ngIf="tieneAccesoFidelizacionGlobal"`) |
| Categorías de gastos | `gastos_categorias_personalizadas` |

Complete example for citas (pattern for the other switches):

```html
<div class="empresa-pref-row">
    <div class="empresa-pref-row-text">
        <label class="empresa-pref-row-label" for="modulo_citas">Módulo de citas</label>
    </div>
    <div class="form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch"
            (change)="onSubmit()" [(ngModel)]="empresa.modulo_citas" name="modulo_citas" id="modulo_citas">
    </div>
</div>
```

Restaurantes select stays a `empresa-pref-field` with the existing `getVistaModuloRestaurantePedidos` / `setVistaModuloRestaurantePedidos` bindings.

- [ ] **Step 3: Fill Documentos e impresión**

| Control | name / id |
|---------|-----------|
| Ticket PDF | `mostrar_ticket_pdf` |
| Descripción producto DTE | `dte_mostrar_descripcion_producto` (keep translate pipes) |
| Sello/firma órdenes | `mostrar_sello_firma` |
| Sello/firma cotizaciones | `mostrar_sello_firma_cotizacion` |
| Upload sello + firma | existing block `*ngIf="empresa.mostrar_sello_firma \|\| empresa.mostrar_sello_firma_cotizacion"` — keep as-is inside this group (not a switch row) |
| Descripción en cotizaciones | `cotizacion_mostrar_descripcion` |
| Imágenes en cotizaciones | `cotizacion_mostrar_imagenes_productos` |
| Imprimir en facturación | `impresion_en_facturacion` — cut from Facturación |
| Nota del documento al imprimir | `mostrar_nota_documento_impresion` — cut from Facturación |

- [ ] **Step 4: Fill Facturación**

Only these (no bancos / fidelización / gastos / vendedor_inventario / impresión):

| Control | name / id |
|---------|-----------|
| Cobrar IVA | `cobrar_iva` / `setCobrarIVA()` / `[checked]="empresa.cobra_iva == 'Si'"` — help = `country.tax.defaultTaxCalcDesc` |
| Vender sin stock | `vender_sin_stock` |
| Editar precio | `editar_precio_venta` |
| Agrupar detalles | `agrupar_detalles_venta` |
| Editar descripciones | `editar_descripcion_venta` |
| Versión de facturación | `version_facturacion` — `empresa-pref-field` + existing select |
| Bloquear correlativo | `bloquear_edicion_correlativo` |
| Editar tipo de cambio | `fe_cr_permitir_editar_tipo_cambio` (`@if (tieneMultimoneda)`) |
| Campos contables | `mostrar_campos_contables` |
| Vendedor por detalle | `vendedor_detalle_venta` |
| Ventas pueden cambiar vendedor | `ventas_puede_cambiar_vendedor_facturacion` |
| Cambiar tipo de impuesto | `cambiar_tipo_impuesto_venta` |
| Monto mínimo retención | `monto_minimo_retencion_iva_gc` — `empresa-pref-field` + existing input-group |
| Facturación electrónica | `facturacion_electronica` |
| Venta a consigna | `venta_consigna` |
| Estado de cuenta en facturación | `estado_cuenta_en_facturacion` |

Put the tile **description** as `empresa-pref-row-help` under the label (today it sits above the switch).

Complete IVA example:

```html
<div class="empresa-pref-row">
    <div class="empresa-pref-row-text">
        <label class="empresa-pref-row-label" for="cobrar_iva">{{ 'country.tax.chargeTax' | translate }}</label>
        <p class="empresa-pref-row-help text-muted small mb-0">{{ 'country.tax.defaultTaxCalcDesc' | translate }}</p>
    </div>
    <div class="form-switch m-0">
        <input class="form-check-input" type="checkbox" id="cobrar_iva" (change)="setCobrarIVA()"
            role="switch" [checked]="empresa.cobra_iva == 'Si'">
    </div>
</div>
```

- [ ] **Step 5: Fill Inventario, Permisos, Cuenta**

Inventario:

| Control | name / id |
|---------|-----------|
| Gestión de productos por vendedores | `vendedor_inventario` — cut from Facturación |
| Lotes | `lotes_activo` + `*ngIf="isLotesActivo()"` for `lotes_metodologia`, `lotes_dias_anticipacion`, `<app-activar-lotes-masivo>` — switch as row; the rest as `empresa-pref-field` |
| Componente químico | `componente_quimico_activo` |
| Presentaciones | `modulo_presentaciones` (`*ngIf="tieneAccesoModuloPresentacionesProductos"`) |
| Código de barras correlativo | `barcode_correlativo_automatico` |
| Total stock en búsquedas | `inventario_sumar_stock_busquedas` |
| Transformación | `transformacion_productos_activo` (`*ngIf="tieneAccesoTransformacionProductos"`) |
| Reporte Excel | `inventario_reporte_analisis_ventas_mensual` |
| Valor de inventario | `valor_inventario` — `empresa-pref-field` |

Permisos (admin only; keep the same role `*ngIf` that already wrapped these rows):

| Control | name / id |
|---------|-----------|
| Bloquear cotizaciones vendedores | `bloquear_cotizaciones_vendedores` |
| Supervisor limitado / gastos | `restringir_gastos_supervisor_limitado` |
| Supervisor limitado / compras | `restringir_compras_supervisor_limitado` |

Cuenta — move the two existing paragraphs (`limpiarCacheYLogout`, `routerLink="/eliminar-datos"`) without the `pt-5` spacer.

Drop the commented-out bloques de términos y condiciones; do not copy them into the new markup.

- [ ] **Step 6: Smoke the HTML**

Extend `preferencias-grupo.check.mjs` after the resolver asserts:

```js
const html = fs.readFileSync(path.join(dir, 'empresa.component.html'), 'utf8');
assert.match(html, /empresa-pref-nav/);
assert.match(html, /setPreferenciasGrupo/);
assert.match(html, /preferenciasGrupo === 'modulos'/);
assert.match(html, /preferenciasGrupo === 'documentos'/);
assert.match(html, /preferenciasGrupo === 'facturacion'/);
assert.match(html, /preferenciasGrupo === 'inventario'/);
assert.match(html, /preferenciasGrupo === 'permisos'/);
assert.match(html, /preferenciasGrupo === 'cuenta'/);
assert.match(html, /empresa-pref-save/);
assert.doesNotMatch(html, /empresa-pref-tile/);
assert.doesNotMatch(html, /empresa-pref-facturacion/);
```

Run: `node Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs`

Expected: `preferencias-grupo.check: ok`

Also grep that moved names still exist once:

```bash
rg -c "modulo_bancos|impresion_en_facturacion|vendedor_inventario|bloquear_cotizaciones_vendedores|limpiarCacheYLogout" Frontend/src/app/views/admin/empresa/empresa.component.html
```

Expected: each name appears at least once.

- [ ] **Step 7: graphify + commit**

Run: `graphify update .`

```bash
git add Frontend/src/app/views/admin/empresa/empresa.component.html Frontend/src/app/views/admin/empresa/empresa.component.ts Frontend/src/app/views/admin/empresa/preferencias-grupo.check.mjs
git commit -m "$(cat <<'EOF'
feat: agrupar preferencias del sistema en un menú interno

EOF
)"
```

---

### Manual check (after Task 4)

1. Abrir Cuenta → Preferencias: menú a la izquierda, solo Módulos a la derecha, Guardar visible abajo.
2. Cambiar a Facturación: URL tiene `?tab=preferencias&grupo=facturacion`; refresh restaura el grupo.
3. IVA / un switch que ya auto-guardaba sigue mostrando el toast de éxito.
4. Un select (valor de inventario) solo persiste con Guardar.
5. Con usuario no admin, no aparece Permisos; `?grupo=permisos` cae a Módulos.
6. En viewport angosto, el menú pasa a pills horizontales.
7. Tab Facturación electrónica sigue dependiendo del switch de FE.
8. Subida de sello/firma solo en Documentos, y solo si un switch de sello está on.
