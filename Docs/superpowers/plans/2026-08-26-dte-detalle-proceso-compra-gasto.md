# Procesar DTE en detalle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** En el detalle DTE, procesar compra/gasto con forma de pago, crédito, retaceo y (en compra) productos vinculados, reutilizando las APIs de importación JSON.

**Architecture:** Un value object `DteProcesoOpciones` valida el payload de Procesar. `DteToIvaService` lo aplica al crear compra/gasto y solo omite `pendiente_clasificacion` cuando no hay `line_mappings`. El detalle Angular agrega los controles y el match de productos.

**Tech Stack:** Laravel (PHPUnit unit), Angular standalone `DteDetailComponent` (Jasmine sin TestBed, mismo patrón que `gasto.component.spec.ts`).

## Global Constraints

- No columnas nuevas en `dte_documents`.
- No extraer componente compartido del modal de conciliación.
- Job automático sin mappings sigue omitiendo compras en `pendiente_clasificacion`.
- No consigna, recurrente, crear producto nuevo desde el detalle.
- No commit salvo que el usuario lo pida.

---

### Task 1: DteProcesoOpciones

**Files:**
- Create: `Backend/app/Services/Dte/DteProcesoOpciones.php`
- Test: `Backend/tests/Unit/Services/Dte/DteProcesoOpcionesTest.php`

**Interfaces:**
- Consumes: array del request
- Produces: `DteProcesoOpciones::fromArray(array $input): self` con `formaPago`, `credito`, `fechaPago`, `detalleBanco`, `esRetaceo`, `lineMappings`; `omitirCompraPendienteClasificacion(string $status): bool`; `estadoCompra(): string`; `estadoGasto(): string`; `mappingPorIndex(): array`; `validarPago(): void`; `validarLineasCompra(int $totalLineas): void`

- [ ] **Step 1: Write the failing test**
- [ ] **Step 2: Run to verify it fails**
- [ ] **Step 3: Implement `DteProcesoOpciones`**
- [ ] **Step 4: Run tests to pass**

Rules:
- `omitirCompraPendienteClasificacion`: true solo si status es `pendiente_clasificacion` y `lineMappings` está vacío.
- `estadoCompra`: crédito → `Pendiente`, si no `Pagada`.
- `estadoGasto`: crédito → `Pendiente`, si no `Confirmado`.
- `validarPago`: crédito exige `fechaPago`; forma de pago distinta de Efectivo/Wompi exige `detalleBanco`.
- `validarLineasCompra`: cada índice `0..n-1` tiene `id_producto` entero y `cantidad > 0`.

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Dte/DteProcesoOpcionesTest.php`

---

### Task 2: DteToIvaService + controller

**Files:**
- Modify: `Backend/app/Services/Dte/DteToIvaService.php`
- Modify: `Backend/app/Http/Controllers/Api/DteManagement/DteDocumentController.php`
- Test: `Backend/tests/Unit/Services/Dte/DteToIvaServiceProcesoOpcionesTest.php` (reflection o unit del skip + atributos en modelos new Compra/Gasto si se extrae método protegido)

**Interfaces:**
- Consumes: `DteProcesoOpciones`
- Produces: `insertFromDteDocument(DteDocument $document, ?DteProcesoOpciones $opciones = null)`

- [ ] Skip `pendiente_clasificacion` solo si `$opciones?->omitirCompraPendienteClasificacion(...)` es true (null opciones = skip como hoy).
- [ ] `createCompra`/`createGasto`: si opciones traen `formaPago`, usarla; aplicar estado, fecha_pago, detalle_banco, es_retaceo.
- [ ] Compra con mappings: no exigir `all_matched`; resolver producto por `id_producto` del mapping y cantidad del mapping.
- [ ] Compra con mappings incompletos: `InvalidArgumentException` → 422 en `procesar`.
- [ ] `procesar()` construye opciones con `fromArray` y valida pago + líneas si destino compra.
- [ ] `show()` agrega `pago_sugerido` desde JSON (`pagos[0].codigo` + `condicionOperacion`).

Mapa forma pago (igual que `DteToIvaService::$formaPagoMap`): 01 Efectivo, 02 Tarjeta de Crédito, 03 Tarjeta de Débito, 04 Cheque, 05 Transferencia, 06 Crédito (implica credito true), 07 Tarjeta de regalo, 08 Dinero electrónico, 99 Otros.

---

### Task 3: Frontend payload y reglas

**Files:**
- Modify: `Frontend/src/app/services/dte-management/dte-document.service.ts`
- Modify: `Frontend/src/app/views/dte-management/dte-detail/dte-detail.component.ts`
- Test: `Frontend/src/app/views/dte-management/dte-detail/dte-detail.component.spec.ts`

- [ ] Extender `DteProcesarPayload` y `DteLineItem` (`id_producto`, `producto`, `cantidad` editable).
- [ ] Spec sin TestBed: `buildProcesarPayload`, `requiereBanco`, `setCredito`, `compraListaParaProcesar`.
- [ ] Cargar formas de pago (`SharedDataService.getFormasDePago`) y bancos (`banco/cuentas/list`).
- [ ] Precargar `pago_sugerido` / forma_pago Efectivo.

Run: `cd Frontend && npx ng test --include=src/app/views/dte-management/dte-detail/dte-detail.component.spec.ts --browsers=ChromeHeadless --watch=false`

---

### Task 4: UI detalle + match productos

**Files:**
- Modify: `Frontend/src/app/views/dte-management/dte-detail/dte-detail.component.html`
- Modify: `Frontend/src/app/views/dte-management/dte-detail/dte-detail.component.ts`

- [ ] Controles: forma de pago, crédito, fecha de pago, banco, retaceo.
- [ ] Destino compra: columna producto (`ng-select` typeahead `productos/buscar-modal`) y cantidad; al cargar o cambiar a compra llamar `productos/resolver-importacion-dte`.
- [ ] Destino gasto: tabla descriptiva.
- [ ] Procesar envía mappings; frontend no llama si `!compraListaParaProcesar`.

---

### Task 5: Verificar

- [ ] PHPUnit Task 1 + 2
- [ ] Jasmine detalle
- [ ] `graphify update .`
