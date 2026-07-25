# Bloquear edición de correlativo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preferencia de empresa que, al activarse, deja readonly el correlativo al editar ventas y la referencia al editar compras, y el backend conserva el valor original.

**Architecture:** Flag booleano `bloquear_edicion_correlativo` en `custom_empresa.configuraciones`. UI en Preferencias (sección Facturación). Helper FE + helper BE. Enforce en `VentasController::store` y `ComprasController::store` restaurando el valor original antes de `fill`.

**Tech Stack:** Angular (Frontend), Laravel (Backend), JSON `custom_empresa`.

**Spec:** `Docs/superpowers/specs/2026-07-23-bloquear-edicion-correlativo-design.md`

## Global Constraints

- Solo edición (no create / recurrentes / facturación create).
- Clave: `bloquear_edicion_correlativo` (default `false`).
- Backend: restaurar original (no 422).
- Ventas: campo `correlativo`. Compras: campo `referencia`.

---

### Task 1: Preferencia (backend allowlist + Empresa helper)

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Admin/EmpresasController.php` (`$booleanConfigs`)
- Modify: `Backend/app/Models/Admin/Empresa.php` (`initializeCustomConfig` + helper)

- [ ] **Step 1:** Agregar `'bloquear_edicion_correlativo'` a `$booleanConfigs`.
- [ ] **Step 2:** Default `false` en `initializeCustomConfig` y método `bloqueaEdicionCorrelativo(): bool`.

---

### Task 2: Preferencia UI + helper ApiService

**Files:**
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.ts`
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.html` (sección Facturación)
- Modify: `Frontend/src/app/services/api.service.ts`

- [ ] **Step 1:** Default + getters/setters/toggle en `EmpresaComponent` (mismo patrón que `bloquear_cotizaciones_vendedores`).
- [ ] **Step 2:** Tile switch en Facturación.
- [ ] **Step 3:** `empresaBloqueaEdicionCorrelativo()` en `ApiService`.

---

### Task 3: Readonly en modales de edición

**Files:**
- Modify: `Frontend/src/app/views/ventas/ventas.component.html` (`#mventa` correlativo)
- Modify: `Frontend/src/app/views/compras/compras.component.html` (`#mcompra` referencia)

- [ ] **Step 1:** `[readonly]="apiService.empresaBloqueaEdicionCorrelativo()"` en ambos inputs.

---

### Task 4: Enforce backend en store

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/VentasController.php` (`store`, antes de `fill`)
- Modify: `Backend/app/Http/Controllers/Api/Compras/ComprasController.php` (`store`, antes de `fill`)

- [ ] **Step 1:** Si empresa bloquea y hay cambio, mergear request con correlativo/referencia original.
- [ ] **Step 2:** Verificación manual según spec §6.

---

## Self-review

- Spec coverage: preferencia, UI, modales venta/compra, backend store — cubierto.
- Fuera de alcance create/recurrentes — no hay tasks.
- Sin placeholders.
