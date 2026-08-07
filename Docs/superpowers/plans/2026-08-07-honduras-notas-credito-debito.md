# Notas de Crédito/Débito Honduras — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Habilitar NC/ND sobre Factura en empresas Honduras, reflejarlas bien en Libro de Ventas, e imprimir plantillas SAR para Inversiones André (420).

**Architecture:** Reutilizar devoluciones de venta. Ampliar menú UI para HN+Factura. Clasificar NC/ND en `LibroVentasExport`. Nuevos Blade NC/ND 420 hardcodeados + switch en `generarDoc`. Sin FE MH en HN (`facturacion_electronica` ya lo evita).

**Tech Stack:** Angular (Frontend), Laravel + DomPDF + Maatwebsite Excel (Backend), Blade.

**Spec:** `Docs/superpowers/specs/2026-08-07-honduras-notas-credito-debito-design.md`

## Global Constraints

- Fechas plantillas 420: autorización `02/03/2026`, límite `02/03/2027`.
- CAI: `4C127A-574649-D93CE0-63BE03-0909A0-30`.
- Rango NC: `000-002-06-00000201 / 000-002-06-00000220`; prefijo número `000-002-06-`.
- Rango ND: `000-002-07-00000201 / 000-002-07-00000220`; prefijo número `000-002-07-`.
- Emisión NC/ND: todas las empresas Honduras sobre documento `Factura` (+ CCF existente).
- Plantillas SAR: solo `id_empresa == 420`.
- CAI/rango en UI Documentos: fuera de alcance (hardcode en Blade).
- Sin seed de documentos; sin FE Honduras.

---

### Task 1: Menú ventas — NC/ND para Honduras + Factura

**Files:**
- Modify: `Frontend/src/app/views/ventas/ventas.component.html` (~305–316)
- Modify: `Frontend/src/app/views/ventas/ventas.component.ts` (helper opcional)
- Modify: `Frontend/src/app/views/ventas/devoluciones/devolucion-nueva/devolucion-nueva.component.ts` (`cargarDocumentos` filter)

- [x] **Step 1:** Agregar método `puedeCrearNotaCreditoDebito(venta): boolean` en `ventas.component.ts`:
- [x] **Step 2:** En el HTML, reemplazar las dos condiciones `venta.nombre_documento == 'Crédito fiscal'` de NC/ND por `puedeCrearNotaCreditoDebito(venta)`.
- [x] **Step 3:** Corregir filtro de documentos (precedencia `&&`/`||`):

---

### Task 2: Libro de Ventas HN — distinguir NC vs ND

**Files:**
- Modify: `Backend/app/Exports/Contabilidad/Honduras/LibroVentasExport.php` (`filasDevoluciones` en `buildDetailRows`)

- [x] **Step 1:** Eager-load `documento` en la query de devoluciones: `with(['cliente', 'venta', 'documento'])`.
- [x] **Step 2:** En el map de devoluciones, clasificar NC vs ND y aplicar signo.
- [x] **Step 3:** PDF/UI consumen `rowsForApi()` — sin cambios de columnas.

---

### Task 3: Plantillas NC/ND Inversiones André + cableado

**Files:**
- Create: `Backend/resources/views/reportes/facturacion/formatos_empresas/NC-Inversiones-Andre.blade.php`
- Create: `Backend/resources/views/reportes/facturacion/formatos_empresas/ND-Inversiones-Andre.blade.php`
- Modify: `Backend/app/Http/Controllers/Api/Ventas/Devoluciones/DevolucionVentasController.php` (`generarDoc`)

- [x] **Step 1:** Plantillas NC/ND basadas en Factura-Inversiones-Andre con CAI/rango/fechas y factura relacionada.
- [x] **Step 2:** Ramas `id_empresa == 420` en `generarDoc` para NC y ND.
- [x] **Step 3:** Eager load `detalles.producto`, `cliente`, `venta`, `documento`.

---

### Task 4: Spec status + verificación estática

**Files:**
- Modify: `Docs/superpowers/specs/2026-08-07-honduras-notas-credito-debito-design.md` (Estado → Aprobado)

- [x] **Step 1:** Marcar estado Aprobado.
- [x] **Step 2:** Grep verificación: menú helper, LibroVentasExport ND, blades NC/ND, generarDoc 420.
- [x] **Step 3:** No tocar `Frontend/src/manifest.webmanifest` (cambio ajeno).

---

## Self-review

- Spec § emisión UI → Task 1
- Spec § libro → Task 2 (PDF/UI vía mismo export)
- Spec § plantillas 420 → Task 3
- Spec § FE HN → ya cubierto por `facturacion_electronica` (sin task)
- Spec § Documentos CAI UI → fuera de alcance
- Sin placeholders
