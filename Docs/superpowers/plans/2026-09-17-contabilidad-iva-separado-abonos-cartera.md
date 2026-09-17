# IVA separado y abonos en cartera — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dos switches en configuración de contabilidad: separar IVA CF vs crédito fiscal (ventas y compras) y mandar abonos a CXC/CXP en vez de ingresos/egresos.

**Architecture:** Helpers puros `ReglaCuentaIva` y `ReglaAbonosCartera`. Columnas nuevas en `contabilidad_configuracion`. Los generadores de partidas resuelven la cuenta de IVA y el merge de abonos solo a través de esos helpers.

**Tech Stack:** Laravel 10, PHPUnit, Angular standalone, Bootstrap form-switch.

## Global Constraints

- Flags default false: sin marcar, comportamiento idéntico al actual.
- `id_cuenta_iva_ventas` / `id_cuenta_iva_compras` se reutilizan como crédito fiscal cuando el split está on.
- Cuenta CF opcional; si falta, fallback a la cuenta general.
- Clasificación por el tipo del propio documento (no factura origen).
- Crédito fiscal = CCF o texto que contiene “crédito fiscal” (sin acentos).
- Un switch de abonos cubre ingresos/CXC y egresos/CXP.
- Partidas ya generadas no se reprocesan.
- Sin dependencias nuevas. Sin pantallas nuevas.
- Prueba: PHPUnit de helpers + check Node de la UI. No frameworks extra.

## File map

- Create: `Backend/app/Services/Contabilidad/Partidas/ReglaCuentaIva.php`
- Create: `Backend/app/Services/Contabilidad/Partidas/ReglaAbonosCartera.php`
- Create: `Backend/tests/Unit/Services/Contabilidad/Partidas/ReglaCuentaIvaTest.php`
- Create: `Backend/tests/Unit/Services/Contabilidad/Partidas/ReglaAbonosCarteraTest.php`
- Create: `Backend/database/migrations/2026_09_17_120000_add_iva_split_and_abonos_cartera_to_contabilidad_configuracion.php`
- Create: `Frontend/src/app/views/contabilidad/configuracion/contabilidad-configuracion.check.mjs`
- Modify: `Backend/app/Models/Contabilidad/Configuracion.php`
- Modify: `Backend/app/Http/Requests/Contabilidad/StoreConfiguracionRequest.php`
- Modify: `Backend/tests/Unit/Http/Requests/Contabilidad/StoreConfiguracionRequestTest.php`
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/Partidas/PartidasController.php`
- Modify: `Backend/app/Services/Contabilidad/Partidas/PartidaIngresosService.php`
- Modify: `Backend/app/Services/Contabilidad/Partidas/PartidaEgresosService.php`
- Modify: `Backend/app/Services/Contabilidad/VentasService.php`
- Modify: `Backend/app/Services/Contabilidad/ComprasService.php`
- Modify: `Backend/app/Services/Contabilidad/GastosService.php`
- Modify: `Frontend/src/app/views/contabilidad/configuracion/contabilidad-configuracion.component.html`
- Modify: `Frontend/src/app/views/contabilidad/configuracion/contabilidad-configuracion.component.ts`

---

### Task 1: Helpers de reglas (TDD)

**Files:**
- Create: `Backend/app/Services/Contabilidad/Partidas/ReglaCuentaIva.php`
- Create: `Backend/app/Services/Contabilidad/Partidas/ReglaAbonosCartera.php`
- Test: `Backend/tests/Unit/Services/Contabilidad/Partidas/ReglaCuentaIvaTest.php`
- Test: `Backend/tests/Unit/Services/Contabilidad/Partidas/ReglaAbonosCarteraTest.php`

**Interfaces:**
- Produces: `ReglaCuentaIva::tipoDe(object $doc): string`
- Produces: `ReglaCuentaIva::esCreditoFiscal(string $tipo): bool`
- Produces: `ReglaCuentaIva::idCuentaVentas(object $config, string $tipo): ?int`
- Produces: `ReglaCuentaIva::idCuentaCompras(object $config, string $tipo): ?int`
- Produces: `ReglaAbonosCartera::incluirEnIngresosEgresos(object $config): bool`

- [x] **Step 1: Tests que fallen** (PHPUnit sin Laravel)
- [x] **Step 2: Implementar helpers**
- [x] **Step 3: `php vendor/bin/phpunit tests/Unit/Services/Contabilidad/Partidas/ReglaCuentaIvaTest.php tests/Unit/Services/Contabilidad/Partidas/ReglaAbonosCarteraTest.php`**

---

### Task 2: Persistencia

**Files:**
- Create: migration `2026_09_17_120000_add_iva_split_and_abonos_cartera_to_contabilidad_configuracion.php`
- Modify: `Configuracion` fillable + casts boolean
- Modify: `StoreConfiguracionRequest` (flags sometimes/boolean; CF nullable; `''` → null)
- Modify: `StoreConfiguracionRequestTest`

- [x] **Step 1: Migration + model + request**
- [x] **Step 2: Tests del request**

---

### Task 3: Generadores de partidas

**Files:**
- Modify: `PartidasController` (`generarIngresos`, `generarCxC`, `generarEgresos`, `generarCxP`)
- Modify: `PartidaIngresosService`, `PartidaEgresosService`
- Modify: `VentasService`, `ComprasService`, `GastosService`

Al preload de cuentas, incluir ids CF. Por cada documento con IVA, resolver id con el helper y tomar la cuenta del cache. Si `!incluirEnIngresosEgresos`, no cargar/mergear abonos.

- [x] **Step 1: Cablear ingresos/egresos/CXC/CXP + servicios individuales**

---

### Task 4: UI

**Files:**
- Modify: `contabilidad-configuracion.component.html` / `.ts`
- Create: `contabilidad-configuracion.check.mjs`

Bloque Reglas con dos `form-switch`. IVA: un campo si split off; crédito fiscal + CF si on. Inicializar flags en `false` si vienen null.

- [x] **Step 1: HTML + defaults**
- [x] **Step 2: `node Frontend/src/app/views/contabilidad/configuracion/contabilidad-configuracion.check.mjs`**
