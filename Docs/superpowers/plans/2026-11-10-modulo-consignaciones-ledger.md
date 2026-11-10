# Módulo consignaciones v2 (ledger aparte) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ledger de consignación en tablas aparte, activable por Super Admin + Mi cuenta, sin afectar empresas que no lo usan; Fase 1 en paralelo al kardex actual.

**Architecture:** `consigna_movimientos` append-only; `ConsignaMovimientoService` centraliza escrituras tras `Empresa::isModuloConsignacionesLedgerActivo()`; hooks en compra/venta/liquidación; UI kardex consigna; funcionalidad `modulo-consignaciones` + `consignaciones_ledger_activo` en custom_config.

**Tech Stack:** Laravel (PHPUnit), Angular 15, permisos Spatie, Funcionalidades existentes, Maatwebsite Excel (export opcional).

**Spec:** `Docs/superpowers/specs/2026-11-10-modulo-consignaciones-ledger-design.md`

## Global Constraints

- Ledger **off** → comportamiento byte-for-byte igual al release anterior en kardex/stock (regresión obligatoria).
- Liquidación consigna compra/venta **nunca** incrementa stock ni escribe entrada "Compra" en `kardexs`.
- Una sola salida física por unidad por evento de entrega/venta.
- Sin nueva dependencia npm/composer salvo necesidad demostrada.
- Comentarios `ponytail:` en atajos con techo conocido (idempotencia, backfill O(n)).
- Al **cerrar el epic:** completar paquete docs en `Docs/consignaciones-v2/` (ver Task 20).

## Documentación entregable al finalizar (promesa al usuario)

| Archivo | Contenido |
|---------|-----------|
| `Docs/consignaciones-v2/CHANGELOG.md` | Qué se hizo, archivos clave, breaking changes (ninguno con ledger off) |
| `Docs/consignaciones-v2/MIGRATIONS_AND_SEEDERS.md` | Cada migración, orden, rollback, seeders |
| `Docs/consignaciones-v2/QA_MANUAL.md` | Pruebas paso a paso (legacy off + ledger on + 4 escenarios venta) |
| `Docs/consignaciones-v2/DEPLOY_PRODUCTION.md` | Deploy, flags, piloto, rollback |
| `Docs/consignaciones-v2/MANUAL_USUARIO.md` | Soporte/ventas: activación, operación, FAQs |

Task 20 rellena estos archivos; durante desarrollo, anotar en CHANGELOG al cerrar cada epic.

---

## File map (resumen)

| Área | Archivos |
|------|----------|
| DB | `Backend/database/migrations/2026_11_*_create_consigna_movimientos_table.php` |
| Constantes | `Backend/app/Constants/ConsignaMovimientoTipoConstants.php` |
| Modelo | `Backend/app/Models/Inventario/ConsignaMovimiento.php` |
| Servicio | `Backend/app/Services/Inventario/ConsignaMovimientoService.php` |
| Empresa gate | `Backend/app/Models/Admin/Empresa.php` |
| Funcionalidad | `Backend/database/seeders/FuncionalidadesSeeder.php` |
| Permisos | `Backend/config/permissions.php`, migración permisos si aplica |
| Hooks compra | `Backend/app/Http/Controllers/Api/Compras/ComprasController.php` |
| Hooks venta | `Backend/app/Services/Ventas/FacturacionService.php` |
| Hooks liquidación | `ComprasController::facturacionConsigna`, `VentasController` + `FacturacionConsignaService` |
| API | `ConsignaKardexController.php`, rutas `productos.php` o `inventario` |
| Tests | `ConsignaMovimientoServiceTest`, `ConsignaLedgerRegressionTest`, feature gate |
| Front preferencias | `empresa.component.ts/html` |
| Front kardex | `consigna-kardex.component.ts/html`, routing inventario |
| Front guard | `functionalities.service`, sidebar |

---

### Task 1: Constantes y contrato del servicio

**Files:**
- Create: `Backend/app/Constants/ConsignaMovimientoTipoConstants.php`
- Create: `Backend/tests/Unit/Constants/ConsignaMovimientoTipoConstantsTest.php`

**Interfaces:**
- Produces: `ConsignaMovimientoTipoConstants::ENTRADA_PROVEEDOR` etc. + `todos(): array` + `esValido(string $tipo): bool`

- [x] **Step 1:** Test que todos los tipos del diseño están en `todos()` y `esValido` rechaza basura.
- [x] **Step 2:** Implementar constantes (strings, sin enum PHP 8.1 si el proyecto no usa enums en otros lados).
- [x] **Step 3:** `php vendor/bin/phpunit tests/Unit/Constants/ConsignaMovimientoTipoConstantsTest.php`

---

### Task 2: Migración `consigna_movimientos`

**Files:**
- Create: `Backend/database/migrations/2026_11_11_100000_create_consigna_movimientos_table.php`
- Create: `Backend/app/Models/Inventario/ConsignaMovimiento.php`

**Interfaces:**
- Produces: modelo con `$fillable`, casts decimales, relaciones `producto`, `bodega`, `proveedor`, `cliente`, `compra`, `venta`, `usuario`
- Índices: `(id_empresa, fecha)`, `(id_empresa, id_producto, id_bodega)`, unique `(id_empresa, idempotency_key)` where not null

- [ ] **Step 1:** Escribir migración `up`/`down` (tabla vacía en prod, no toca kardex).
- [ ] **Step 2:** Modelo + scope `empresa`.
- [ ] **Step 3:** `php artisan migrate --path=database/migrations/2026_11_11_100000_create_consigna_movimientos_table.php` en local.
- [ ] **Step 4:** Anotar en `Docs/consignaciones-v2/MIGRATIONS_AND_SEEDERS.md` (sección stub).

---

### Task 3: Feature gate en `Empresa`

**Files:**
- Modify: `Backend/app/Models/Admin/Empresa.php`
- Create: `Backend/tests/Unit/Models/EmpresaConsignacionesLedgerTest.php`

**Interfaces:**
- Produces: `tieneFuncionalidadModuloConsignaciones(): bool` (slug `modulo-consignaciones`)
- Produces: `isModuloConsignacionesLedgerActivo(): bool` = funcionalidad AND `getCustomConfigValue('configuraciones', 'consignaciones_ledger_activo', false)`

- [ ] **Step 1:** Tests con `Empresa` mock/factory: sin funcionalidad → false; con funcionalidad sin config → false; ambos true → true.
- [ ] **Step 2:** Implementar métodos siguiendo patrón `isModuloPresentaciones()`.
- [ ] **Step 3:** PHPUnit pass.

---

### Task 4: Seeder funcionalidad Super Admin

**Files:**
- Modify: `Backend/database/seeders/FuncionalidadesSeeder.php`
- Modify: `Backend/tests/Unit/Services/Funcionalidades/FuncionalidadesSeederSlugTest.php`

- [ ] **Step 1:** Agregar entrada orden ~25: `modulo-consignaciones`, descripción clara.
- [ ] **Step 2:** Test `assertStringContainsString("'slug' => 'modulo-consignaciones'"`.
- [ ] **Step 3:** Documentar: en prod ejecutar `php artisan db:seed --class=FuncionalidadesSeeder` (idempotente updateOrCreate).

---

### Task 5: Permiso kardex consigna

**Files:**
- Modify: `Backend/config/permissions.php`
- Create migración: `Backend/database/migrations/2026_11_11_110000_add_inventario_consigna_kardex_permissions.php` (siguiendo patrón `2026_11_06_143000_extend_otras_entradas_salidas_permissions...`)

**Interfaces:**
- Produces: `inventario.consigna_kardex.ver` (y `exportar` si aplica)

- [ ] **Step 1:** Migración que inserta permisos y asigna a rol Admin empresa si el seeder del proyecto lo hace así (copiar patrón archivo existente).
- [ ] **Step 2:** `Backend/tests/Feature/Auth/ModulosOperativosPermissionsTest.php` — assert ruta nueva con middleware permission.
- [ ] **Step 3:** Actualizar `MIGRATIONS_AND_SEEDERS.md`.

---

### Task 6: `ConsignaMovimientoService` (núcleo)

**Files:**
- Create: `Backend/app/Services/Inventario/ConsignaMovimientoService.php`
- Create: `Backend/tests/Unit/Services/Inventario/ConsignaMovimientoServiceTest.php`

**Interfaces:**
- Consumes: `Empresa::isModuloConsignacionesLedgerActivo()` en cada `registrar*`
- Produces:
  - `registrar(int $idEmpresa, array $payload): ConsignaMovimiento` — valida tipo, idempotency
  - `entradaProveedorDesdeCompra(Compra $compra, Detalle $detalle): ?ConsignaMovimiento`
  - `desdeVentaDetalle(Venta $venta, DetalleVenta $detalle): void` — ramifica por `venta.estado` y `origen_stock`
  - `liquidacionProveedorDesdeCompra(Compra $compra, Detalle $detalle, float $cantidad): ?ConsignaMovimiento`
  - `liquidacionClienteDesdeVenta(Venta $venta, DetalleVenta $detalle, float $cantidad): ?ConsignaMovimiento`
  - `listar(Request $request): LengthAwarePaginator|Collection`

- [ ] **Step 1:** Tests: si ledger off, `registrar` no inserta (mock empresa).
- [ ] **Step 2:** Tests: entrada proveedor crea fila tipo `entrada_proveedor` con costo del detalle.
- [ ] **Step 3:** Tests: liquidación proveedor no llama Inventario (mock; assert no interaction).
- [ ] **Step 4:** Implementar servicio mínimo.
- [ ] **Step 5:** PHPUnit.

---

### Task 7: Hook compra consigna (entrada ledger)

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Compras/ComprasController.php` (bloque guardar detalles + inventario, ~300-460)
- Create: `Backend/tests/Feature/Compras/ConsignaLedgerEntradaTest.php`

- [ ] **Step 1:** Feature test: empresa ledger on, compra Consigna guardada → N filas `consigna_movimientos` = N detalles; `kardexs` sigue como antes.
- [ ] **Step 2:** Feature test: ledger off → 0 filas consigna.
- [ ] **Step 3:** Inyectar `ConsignaMovimientoService` y llamar `entradaProveedorDesdeCompra` **después** de aplicar inventario existente (no cambiar orden stock).
- [ ] **Step 4:** Tests green.

---

### Task 8: Hook venta (4 escenarios en ledger)

**Files:**
- Modify: `Backend/app/Services/Ventas/FacturacionService.php` (~340-436 post inventario)
- Create: `Backend/tests/Feature/Ventas/ConsignaLedgerVentaTest.php`

**Matrices a cubrir en tests:**

| venta.estado | origen_stock | tipo movimiento esperado |
|--------------|--------------|--------------------------|
| Pagada | normal | `venta_regular_propio` o skip si solo rastreamos consigna |
| Pagada | consigna_compra | `venta_regular_consigna_proveedor` |
| Consigna | normal | `entrega_consigna_propio` |
| Consigna | consigna_compra | `entrega_consigna_proveedor` + efecto proveedor |

- [ ] **Step 1:** Tests failing para las 4 filas (ledger on).
- [ ] **Step 2:** Llamada `ConsignaMovimientoService::desdeVentaDetalle` tras kardex venta actual.
- [ ] **Step 3:** Assert stock delta idéntico con ledger on/off.
- [ ] **Step 4:** PHPUnit.

---

### Task 9: Hook liquidación compra

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Compras/ComprasController.php::facturacionConsigna`
- Create: `Backend/tests/Feature/Compras/ConsignaLedgerLiquidacionTest.php`

- [ ] **Step 1:** Escenario 10→vende 4→paga 4: stock antes = después; movimiento `liquidacion_proveedor` cantidad 4; sin nueva fila kardex entrada.
- [ ] **Step 2:** Implementar loop detalles pagados → `liquidacionProveedorDesdeCompra`.
- [ ] **Step 3:** Tests.

---

### Task 10: Hook liquidación venta (consigna salida)

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/VentasController.php` + `FacturacionConsignaService.php`
- Create: `Backend/tests/Feature/Ventas/ConsignaLedgerLiquidacionVentaTest.php`

- [ ] **Step 1:** Venta consigna parcial facturada → `liquidacion_cliente`; stock sin cambio en cierre.
- [ ] **Step 2:** Implementar.
- [ ] **Step 3:** Tests.

---

### Task 11: API listado kardex consigna

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Inventario/ConsignaKardexController.php`
- Modify: `Backend/routes/modulos/inventario/productos.php` (o nuevo archivo rutas)
- Create: `Backend/tests/Feature/Inventario/ConsignaKardexApiTest.php`

**Interfaces:**
- `GET /productos/consigna-kardex` — filtros: `fecha_ini`, `fecha_fin`, `id_producto`, `id_bodega`, `id_proveedor`, `id_cliente`, `tipo`
- Middleware: `permission:inventario.consigna_kardex.ver` + policy ledger activo (403 si funcionalidad off)

- [ ] **Step 1:** Feature tests auth + filtros.
- [ ] **Step 2:** Controller delega a `ConsignaMovimientoService::listar`.
- [ ] **Step 3:** Tests.

---

### Task 12: Export Excel (opcional Fase 1)

**Files:**
- Create: `Backend/app/Exports/ConsignaKardexExport.php`
- Modify: `ConsignaKardexController.php`

- [ ] **Step 1:** Export mismos filtros que index.
- [ ] **Step 2:** Ruta `GET .../exportar`.

---

### Task 13: Mi cuenta — preferencias

**Files:**
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.ts`
- Modify: `Frontend/src/app/views/admin/empresa/empresa.component.html`
- Modify: defaults en `defaultConfig` (`consignaciones_ledger_activo`, `compra_consigna_habilitada` opcional)

- [ ] **Step 1:** `verificarAcceso('modulo-consignaciones')` → mostrar switches.
- [ ] **Step 2:** `addCustomConfig('configuraciones', 'consignaciones_ledger_activo', bool)` + help text.
- [ ] **Step 3:** Mantener `venta_consigna` column; ayuda que indica requisito funcionalidad para ledger.
- [ ] **Step 4:** Probar manual en local.

---

### Task 14: Frontend — pantalla Kardex consigna

**Files:**
- Create: `Frontend/src/app/views/inventario/consigna-kardex/consigna-kardex.component.ts/html`
- Modify: `Frontend/src/app/views/inventario/inventario.routing.module.ts`
- Modify: `Frontend/src/app/layout/sidebar/sidebar.component.html` (condición funcionalidad + permiso)

- [ ] **Step 1:** Tabla con filtros, paginación, llamada API Task 11.
- [ ] **Step 2:** Export si Task 12.
- [ ] **Step 3:** Ocultar ruta si no `modulo-consignaciones` en sesión (slugs activos).

---

### Task 15: Modal origen stock (sin cambio visual grave)

**Files:**
- Modify: `Frontend/src/app/utils/venta-consigna.util.ts` (solo si hace falta guardar metadata para ledger — backend ya lee `origen_stock`)
- Modify: textos si `consignaciones_ledger_activo` (opcional)

- [ ] **Step 1:** Confirmar que con ledger on el backend clasifica bien sin cambio front.
- [ ] **Step 2:** Si venta consigna salida: documentar que `estado=Consigna` basta para modo operación.

---

### Task 16: Regresión empresa sin módulo

**Files:**
- Create: `Backend/tests/Feature/Inventario/ConsignaLedgerRegressionLegacyTest.php`

- [ ] **Step 1:** Snapshot: compra normal + venta normal → count `kardexs` y stock; comparar con ledger off.
- [ ] **Step 2:** Ejecutar suite `php vendor/bin/phpunit --filter ConsignaLedger`.
- [ ] **Step 3:** CI verde.

---

### Task 17: Comando backfill opcional

**Files:**
- Create: `Backend/app/Console/Commands/ConsignaLedgerBackfillCommand.php`

- [ ] **Step 1:** `php artisan consigna:ledger-backfill {--empresa=} {--dry-run}` — compras `es_consigna`, ventas con `origen_stock`, **no** modifica kardex.
- [ ] **Step 2:** Documentar en `MIGRATIONS_AND_SEEDERS.md` como opcional post-go-live.

---

### Task 18: Integrar `ConsignaDisponibleService` (validación cruzada)

**Files:**
- Modify: `Backend/app/Services/Inventario/ConsignaDisponibleService.php`
- Create: test comparación pool vs suma ledger (solo ledger on)

- [ ] **Step 1:** Método debug `assertConsistenteConLedger` detrás de `config('app.debug')` o comando artisan — ponytail: O(n) scan.
- [ ] **Step 2:** No cambiar fórmula producción en Fase 1; solo alerta en logs si diverge.

---

### Task 19: `graphify update` + linter

- [ ] **Step 1:** Tras merges de código: `graphify update .` desde raíz repo.
- [ ] **Step 2:** `ReadLints` en archivos TS/PHP tocados.

---

### Task 20: Paquete documentación final (obligatorio antes de cerrar SP-2344)

**Files (rellenar, no dejar plantillas vacías):**
- `Docs/consignaciones-v2/CHANGELOG.md`
- `Docs/consignaciones-v2/MIGRATIONS_AND_SEEDERS.md`
- `Docs/consignaciones-v2/QA_MANUAL.md`
- `Docs/consignaciones-v2/DEPLOY_PRODUCTION.md`
- `Docs/consignaciones-v2/MANUAL_USUARIO.md`

**Contenido mínimo QA_MANUAL:**
1. Empresa A: sin funcionalidad — compra/venta/kardex producto (baseline).
2. Super Admin activa `modulo-consignaciones` empresa B.
3. Mi cuenta activa ledger.
4. Entrada consigna proveedor → movimiento + kardex clásico.
5. Venta regular desde pool → modal + movimiento.
6. Liquidación proveedor → movimiento, stock igual.
7. Venta consigna salida + liquidación cliente.
8. Permisos usuario sin `inventario.consigna_kardex.ver` → 403.

**Contenido mínimo DEPLOY:**
1. Backup BD.
2. `php artisan migrate` (listar archivos exactos al momento del release).
3. `php artisan db:seed --class=FuncionalidadesSeeder`.
4. Migración permisos si aplica.
5. Deploy front/back.
6. Activar solo empresa piloto.
7. Rollback: desactivar flags; migración `down` solo si tabla vacía en otras empresas.

**Contenido mínimo MANUAL_USUARIO:**
- Qué es consigna entrada/salida.
- Cómo activar (Super Admin + Mi cuenta).
- Operación diaria con capturas pendientes.
- FAQs: “¿Por qué no duplica inventario al pagar proveedor?”
- Contacto soporte.

- [ ] **Step 1:** Crear stubs al inicio del epic (Task 2 anota migraciones).
- [ ] **Step 2:** Al cerrar Task 19, completar todos los manuales.
- [ ] **Step 3:** Revisión con usuario (David / soporte).

---

## Orden de ejecución recomendado

```
Task 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → (12) → 13 → 14 → 15 → 16 → 17 → 18 → 19 → 20
```

Epics paralelizables tras Task 6: **7-10** (hooks) con cuidado de conflictos en mismos archivos → secuencial preferido.

## Fase 2 (ticket SP-2344 — plan separado después de Fase 1 en prod)

- Bodega lógica cliente (`consigna_custodias`)
- UI conteo periódico
- Traslados con etiqueta consigna
- Dejar de escribir “Compra a consigna” en `kardexs` (solo empresas ledger on)

Crear `Docs/superpowers/plans/2026-XX-consignaciones-fase-2.md` cuando Fase 1 esté estable.

---

## Spec coverage (self-review)

| Requerimiento diseño | Task |
|----------------------|------|
| Tabla aparte | 2, 6 |
| Activación Super Admin + Mi cuenta | 3, 4, 13 |
| No afectar sin módulo | 16 |
| Liquidación sin stock | 9, 10 |
| 4 escenarios venta | 8 |
| Kardex UI | 11, 14 |
| Permisos | 5 |
| Docs deploy/QA/usuario | 20 |
| Trazabilidad SP-2344 entrada/salida Fase 1 | 7-10 |

---

## Execution Handoff

**Plan guardado en:** `Docs/superpowers/plans/2026-11-10-modulo-consignaciones-ledger.md`  
**Spec:** `Docs/superpowers/specs/2026-11-10-modulo-consignaciones-ledger-design.md`

**Al terminar el desarrollo:** Task 20 produce los 5 documentos en `Docs/consignaciones-v2/` para soporte, ventas, deploy y tu presentación.

**Opciones de ejecución:**

1. **Subagent-Driven (recomendado)** — una tarea por sesión/subagent con review entre tareas.
2. **Inline** — implementar Tasks 1–6 en esta u otra sesión con checkpoints.

¿Con cuál quieres empezar (p. ej. Task 1–6 fundación)?
