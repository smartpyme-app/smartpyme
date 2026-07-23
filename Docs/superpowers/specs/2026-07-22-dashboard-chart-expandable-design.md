# Diseño: Expandir gráficos “apretados” del dashboard (modal overlay)

**Fecha:** 2026-07-22  
**Estado:** Pendiente revisión de usuario  
**Tipo:** Mejora UX  
**Alcance v1:** Resultados — cuentas por cobrar / cuentas por pagar

---

## 1. Contexto y problema

En el dashboard de Resultados, gráficos half-width (p. ej. `app-accounts-list` de cuentas por cobrar y por pagar) quedan en columnas `col-md-6` con altura fija (~400px). Son difíciles de leer cuando hay muchas barras o etiquetas largas.

No existe hoy un patrón de expandir/pantalla completa en el dashboard.

---

## 2. Objetivos

1. Permitir ver un gráfico “apretado” en un panel grande sobre el dashboard.
2. Animación clara de apertura y cierre (scale + fade).
3. Reutilizar el mismo DOM del gráfico (no clonar) para no romper instancias de echarts.
4. Dejar un wrapper reutilizable para aplicar después a otros half-width (Ventas, Gastos, etc.) sin reescribir la lógica.

---

## 3. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Alcance | Solo gráficos “apretados” (half-width), no full-width ni tablas |
| v1 UI | Solo cuentas por cobrar y por pagar en Resultados |
| Presentación | Overlay modal propio (no pantallacompleta del browser, no expand in-place en el grid) |
| Implementación modal | Overlay + panel con `role="dialog"` / `aria-modal="true"` — **no** `<dialog>` nativo ni `BsModalService` |
| Animación | ~280ms fade + scale (abre ~0.92→1; cierra inverso) |
| Contenido | Misma instancia proyectada (`ng-content`); el contenedor pasa a `position: fixed` |
| Cerrar | Botón X, Escape, clic en backdrop |
| Un expandido a la vez | Estado local por instancia (cada wrapper gestiona el suyo; no hay servicio global en v1) |

**Por qué no `<dialog>`:** el diseño mueve el mismo contenedor del chart a overlay. Un `<dialog>` con `showModal()` oculta o teleporta contenido y complica echarts/Angular. Un div con roles ARIA cubre accesibilidad básica sin esa fricción.

**Por qué no ngx-bootstrap modal:** ya hay patrón en la app, pero limita la animación de expansión y el ciclo de vida choca con “mover” el mismo chart.

---

## 4. Arquitectura

### 4.1 Componente `app-chart-expandable`

**Ruta:** `Frontend/src/app/views/dashboard/components/chart-expandable/`

**API**

- `@Input() title?: string` — título opcional en el header del panel expandido / tooltip del botón.
- Contenido: `<ng-content></ng-content>` (cualquier chart half-width).

**Estructura (conceptual)**

```html
<div class="chart-expandable" [class.is-expanded]="expanded">
  <!-- placeholder mantiene altura en el flujo cuando el panel es fixed -->
  <div class="chart-expandable__placeholder" *ngIf="expanded" [style.height.px]="placeholderHeight"></div>

  <div *ngIf="expanded" class="chart-expandable__backdrop" (click)="close()"></div>

  <div
    class="chart-expandable__panel"
    role="dialog"
    aria-modal="true"
    [attr.aria-label]="title || 'Gráfico ampliado'">
    <div class="chart-expandable__toolbar">
      <span class="chart-expandable__title">{{ title }}</span>
      <button type="button" (click)="toggle()" [attr.aria-label]="expanded ? 'Cerrar' : 'Expandir'">
        <!-- icono expand / close -->
      </button>
    </div>
    <div class="chart-expandable__body">
      <ng-content></ng-content>
    </div>
  </div>
</div>
```

**Comportamiento**

1. Cerrado: panel en flujo normal; botón expandir visible.
2. Abrir: medir altura del panel → mostrar placeholder → añadir clase de animación de entrada → backdrop → `document.body` overflow hidden → listener Escape.
3. Tras `transitionend` (o timeout fallback ~300ms): disparar resize (`window.dispatchEvent(new Event('resize'))` o resize del chart hijo si está expuesto) para que echarts redibuje.
4. Cerrar: clase de salida → al terminar transición, quitar fixed/backdrop, restaurar scroll, quitar Escape, resize de nuevo al tamaño original.
5. `ngOnDestroy`: cleanup de listeners y overflow del body.

**CSS**

- Backdrop: `position: fixed; inset: 0; z-index` alto; fade.
- Panel expandido: centrado, ~90vw × ~85vh, fondo blanco, sombra suave; animación scale + opacity.
- Body expandido: el contenedor del chart hijo debe poder crecer (p. ej. `:host-context(.is-expanded) .chart-container { height: ... }` o altura en el wrapper) — ajuste mínimo en CSS del wrapper o de `accounts-list` solo si el chart no llena el panel.

### 4.2 Integración v1 — Resultados

En `resultados.component.html` (bloque ~140–161), envolver cada `app-accounts-list` con `app-chart-expandable` y pasar el mismo título.

Registrar `ChartExpandableComponent` en `dashboard.module.ts` (`declarations`; export opcional si hace falta).

### 4.3 Sin cambios de API en charts

`AccountsListComponent` no cambia su contrato. Solo se toca CSS si hace falta que el chart ocupe la altura del panel expandido.

---

## 5. Fuera de alcance (v1)

- Bar chart full-width de ventas/gastos, métricas, tablas, flujo de efectivo.
- Aplicar el wrapper en Ventas / Gastos / Inventario / Control de cuentas (queda listo para después).
- Fullscreen API del navegador (F11).
- Morph geométrico exacto desde la bounding box de la card (FLIP).
- Servicio global de “solo un expandido en toda la página”.

---

## 6. Verificación manual

- [ ] Abrir cuentas por cobrar: animación de entrada, panel grande, chart legible y redibujado.
- [ ] Cerrar con X, Escape y backdrop: animación de salida; layout de la card vuelve sin salto raro.
- [ ] Abrir cuentas por pagar: mismo comportamiento.
- [ ] Con lista vacía (“No hay datos…”): el expandir sigue disponible y el panel muestra el empty state.
- [ ] Tras cerrar, scroll del body funciona; no quedan listeners huérfanos al navegar fuera de Resultados.

---

## 7. Archivos tocados (v1)

| Archivo | Acción |
|---------|--------|
| `Frontend/.../chart-expandable/chart-expandable.component.{ts,html,css}` | Crear |
| `Frontend/.../dashboard.module.ts` | Declarar componente |
| `Frontend/.../resultados/resultados.component.html` | Envolver 2 lists |
| `Frontend/.../accounts-list/accounts-list.component.css` | Solo si hace falta altura en modo expandido |
