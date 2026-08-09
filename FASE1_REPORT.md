# FASE 1 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-09  
**Rama:** `dc.SP-2104`  
**Baseline:** `BASELINE_REPORT.md` → **GO CONDICIONAL**  
**Estado:** Fase 1 + validaciones P0 post-revisión — **DETENERSE** (no Fase 2+)  
**Actualización:** 2026-08-09 (validación MariaDB 10.11 + test concurrente inventario)

---

## 1. Resumen

Se implementó P0 de integridad/concurrencia:

- Locks + transacciones en abrir mesa, agregar/fusionar ítems, enviar comanda, marcar precuenta facturada
- Unique de sesión activa en DB (**solución final: GENERATED STORED + UNIQUE en MariaDB 10.11**)
- Idempotencia de inventario canal vía `inventario_descontado_at`
- Anti doble-submit FE al agregar producto
- Tests de concurrencia con **procesos OS independientes** (no tx envolvente), **incluyendo confirmar pedido**

**Suite ConcurrencyIntegrity:** 7/7 — **OK**  
**Validación MariaDB 10.11.18 (Docker):** GENERATED STORED + UNIQUE — **OK**; functional index MySQL — **INCOMPATIBLE**

---

## 2. Cambios realizados

### 2.1 Abrir mesa — `SesionMesaController@store`

- `DB::transaction` + `Mesa::lockForUpdate()` + re-check sesión activa con lock
- Captura `UniqueConstraintViolationException` → 422 consistente
- Primera línea de defensa: locks; segunda: índice unique

### 2.2 Agregar / fusionar — `OrdenDetalleController@store`

- Transacción + `SesionMesa::lockForUpdate()` + `OrdenDetalle::lockForUpdate()` sobre fusionables
- Stock check dentro de la tx

### 2.3 Enviar comanda — `ComandaController@store`

- Transacción + lock sesión + lock líneas
- `crearComandaSesion` ya no marca `enviado_*` por ítem
- Nuevo `marcarItemsEnviados()`: `UPDATE … WHERE enviado_*=0`; si `affected !== count` → 409 + rollback
- Mantiene split cocina/barra

### 2.4 Marcar precuenta facturada — `PreCuentaController@marcarFacturada`

- `lockForUpdate` de precuenta
- Si ya `facturada` → respuesta **idempotente** (mismo shape `{pre_cuenta, sesion_cerrada}`), sin reliquidar
- Liquidación: 1 SELECT con `whereIn` + `lockForUpdate` (elimina N+1 de SELECTs)
- Lock de sesión al cerrar

### 2.5 Inventario canal — `PedidoCanalInventarioService` + `PedidoRestauranteController@confirmar`

- `aplicarSalidasAlConfirmar`: early-return si `inventario_descontado_at`; al final setea timestamp + `id_bodega_inventario`
- `revertirSalidasPedido`: no-op si no hay flag; limpia flag al terminar
- `confirmar`: lock pedido; retry si ya `pendiente_facturar` + flag → sin segundo descuento
- Cubre también callers BoxFul (mismo service)

### 2.6 Frontend anti doble-submit

- `enviandoAgregar` en `cuenta-mesa.component`
- `@Input() enviando` en `pos-sheet-agregar` (botón/inputs disabled)

### 2.7 Constraint DB sesión activa — SOLUCIÓN FINAL (post-validación MariaDB 10.11)

Ver **§14** para evidencia completa.

| Motor | Functional unique `((CASE…))` | GENERATED STORED + UNIQUE |
|-------|-------------------------------|---------------------------|
| MySQL 9.5 (dev local) | ✅ funciona | ❌ Error 1215 con FK `mesa_id` |
| **MariaDB 10.11.18 (prod target)** | ❌ ERROR 1064 sintaxis | ✅ **funciona con FK** |

**Solución final para producción (MariaDB 10.11):**

```sql
ALTER TABLE restaurante_sesiones_mesa
  ADD COLUMN mesa_sesion_activa_id BIGINT UNSIGNED
  GENERATED ALWAYS AS (
    IF(estado IN ('abierta','pre_cuenta'), mesa_id, NULL)
  ) STORED;

CREATE UNIQUE INDEX uq_restaurante_mesa_sesion_activa
  ON restaurante_sesiones_mesa (mesa_sesion_activa_id);
```

La migración Laravel detecta el motor:

- **MariaDB** → columna GENERATED STORED + UNIQUE (camino producción)
- **MySQL** → fallback functional index (solo dev local)

Script reproducible: `Backend/scripts/validate_mariadb_1011_sesion_unique.sh`  
Contenedor usado: `docker run … mariadb:10.11` → `10.11.18-MariaDB-ubu2204`.

---

## 3. Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` | locks abrir mesa |
| `Backend/app/Http/Controllers/Api/Restaurante/OrdenDetalleController.php` | locks agregar/fusionar |
| `Backend/app/Http/Controllers/Api/Restaurante/ComandaController.php` | locks + update condicional |
| `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php` | lock facturar + liquidación |
| `Backend/app/Http/Controllers/Api/Restaurante/PedidoRestauranteController.php` | lock confirmar idempotente |
| `Backend/app/Services/Restaurante/PedidoCanalInventarioService.php` | flag inventario |
| `Frontend/.../cuenta-mesa.component.ts/html` | `enviandoAgregar` |
| `Frontend/.../pos-sheet-agregar.component.ts/html` | disable durante envío |
| `PLAN_HARDENING_RESTAURANTE_1.0.md` | §25 instrucciones obligatorias |
| `BASELINE_REPORT.md` | Fase 0 |

### Creados

| Archivo |
|---------|
| `Backend/database/migrations/2026_08_09_120000_add_unique_active_sesion_mesa.php` |
| `Backend/tests/Support/Restaurante/ConcurrentActorRunner.php` |
| `Backend/tests/Support/Restaurante/concurrent_actor.php` |
| `Backend/tests/Feature/Restaurante/ConcurrencyIntegrityTest.php` |
| `FASE1_REPORT.md` (este) |

---

## 4. Migraciones

| Migración | Estado local | Rollback |
|-----------|--------------|----------|
| `2026_08_09_120000_add_unique_active_sesion_mesa` | **Ran** | Probado OK |
| `2026_08_04_120000_add_servido_…` | Pending (preexistente) | No tocada en Fase 1 |

---

## 5. Tests creados y resultados

### Comando

```bash
cd Backend && ./vendor/bin/phpunit --filter 'Restaurante|MesasImportPlanner|PosMenu|ConcurrencyIntegrity' --testdox
```

### Resultado

| Grupo | Tests | Resultado |
|-------|------:|-----------|
| ConcurrencyIntegrity | **7** | OK |
| PosMenu + permissions + import | 14 | OK |

### Pruebas de concurrencia (reales)

Infra: `ConcurrentActorRunner` → N procesos `php concurrent_actor.php` con barrera de sincronización + PDO propio por proceso.

| Escenario | Resultado |
|-----------|-----------|
| 2 opens concurrentes misma mesa | 1×201 + 1×422; **1 sesión activa** |
| Retry open tras éxito | 422; sigue 1 sesión |
| 2 adds concurrentes mismo producto/notas | 2 OK; **1 línea qty=2** |
| 2 envíos comanda concurrentes | 1×201 + 1×422/409; **1 comanda** |
| **2 confirms concurrentes mismo pedido** | **ambos OK (idempotente); 1 kardex salida; stock −1; 1 `inventario_descontado_at`** |
| Retry marcar facturada | 200 idempotente; sin doble liquidación |

### No falso verde

No se usó una sola `DatabaseTransactions` envolvente para simular carrera. Los escenarios concurrentes usan procesos independientes.

---

## 6. Problemas encontrados

1. **Functional unique index MySQL → INCOMPATIBLE en MariaDB 10.11 (1064)** — descubierto en validación Docker; migración corregida a GENERATED STORED para MariaDB.
2. **Generated STORED + FK → 1215 en MySQL 9.5 local** — se mantiene fallback functional solo para dev MySQL.
3. **895 pedidos** sin `inventario_descontado_at` (baseline): no backfill; idempotencia forward + check `estado` en confirmar.
4. Test inventario falló primero por tabla faltante `empresa_configuracion` en DB local (migración pendiente `2026_07_31_210000_…`); se aplicó como **fixture de entorno**, no cambio de módulo. Documentado abajo.
5. Migración `servido` en comandas sigue pending (preexistente).

---

## 7. Decisiones técnicas

| Decisión | Razón |
|----------|-------|
| Functional unique index vs generated column | Generated falló con FK en MySQL 9; functional index cumple UNIQUE + NULL OK |
| Idempotencia facturar = 200 con mismo body (no 422) | Retry post-timeout no debe fallar ni reliquidar |
| Conflicto comanda = 409 | Distingue “nada pendiente” (422) de carrera (409) |
| Guard update ítem enviado **no** incluido | Explicitamente Fase 2 (ítem 10); no adelantar scope |
| Idempotency-Key header **no** incluido | Fase 2; Fase 1 usa locks/constraints/estado |
| Redis no usado en Fase 1 | Integridad solo MariaDB/locks |

---

## 8. Desviaciones respecto al plan original

| Plan | Real |
|------|------|
| Columna GENERATED STORED + UNIQUE | **Confirmada en MariaDB 10.11** como solución final; fallback functional solo en MySQL local |
| Functional index como atajo Fase 1 | **Descartado para prod** tras prueba real MariaDB |
| Tests Opción A MariaDB | Validación schema en Docker MariaDB 10.11.18; tests PHPUnit de concurrencia siguen en MySQL local (locks InnoDB equivalentes) |
| Backfill `inventario_descontado_at` opcional | **No** aplicado (riesgo anulación) |
| Test concurrente inventario diferido | **Ya no diferido** — implementado y verde |

---

## 9. Riesgos pendientes (post Fase 1)

| Riesgo | Severidad | Fase sugerida |
|--------|-----------|---------------|
| Aplicar migración GENERATED en staging/prod MariaDB 10.11 (no MySQL) | Alto | Deploy |
| Pedidos históricos sin `inventario_descontado_at` | Medio | Ops / Fase 2 doc |
| Sin Idempotency-Key HTTP | Medio | Fase 2 |
| Update API de ítem enviado aún permitido | Medio | Fase 2 |
| Cierre forzado `cerrar` sin validar cuenta | Medio | Fase 2 |
| Mapa stale (sin realtime) | Medio | Fase 6 / política refresh |
| Hot Table / Hot Product load tests | Alto (capacidad) | Fase 12 |
| Payload `GET /mesas` | Medio | Fase 2 |
| Migración `servido` pending | Bajo | Mantener / alinear |
| Dev local en MySQL usa fallback distinto a prod | Medio | Preferir MariaDB 10.11 local/Docker para paridad |

---

## 10. Integridad vs performance (criterios absolutos)

Tras Fase 1 (cubierto por tests):

- [x] 0 sesiones activas duplicadas (locks + unique + test concurrente)
- [x] 0 comandas duplicadas por carrera de mismas líneas (test concurrente)
- [x] 0 doble liquidación en retry facturar (test)
- [x] 0 doble descuento inventario en confirms concurrentes (test dual-process: 1 kardex + stock −qty + 1 flag)
- [ ] Cross-tenant → Fase 2
- [ ] Load test Peak / Hot Table → Fase 12

Performance p95/p99: **no medido en Fase 1** (separado a propósito).

---

## 11. Observabilidad

Aún no hay request-ID / logging estructurado de Restaurante (Fase 10).  
Los códigos HTTP 422/409 ya permiten distinguir ocupación vs conflicto de comanda.

---

## 12. Criterio de completitud Fase 1

| Criterio | Estado |
|----------|--------|
| Código P0 de races implementado | ✅ |
| Migración unique validada en **MariaDB 10.11** | ✅ |
| Tests concurrencia reales (dual process) | ✅ |
| Test concurrente inventario/confirmar | ✅ |
| FE anti doble-submit agregar | ✅ |
| Inventario canal idempotente forward | ✅ |
| Suite concurrency verde (7/7) | ✅ |
| Compila ≠ completo: hay evidencia de tests | ✅ |
| Fase 2+ no iniciada | ✅ |

---

## 13. Siguiente paso (requiere nueva aprobación)

Solo tras OK explícito:

**Fase 2 — P1:** DTO `/mesas`, `id_empresa` en comandas, bloquear update ítem enviado, endurecer `cerrar`, Idempotency-Key, multi-tenant tests.

No ejecutar automáticamente.

---

## 14. Validación MariaDB 10.11 (obligatoria post-revisión)

### Entorno

| Item | Valor |
|------|-------|
| Imagen | `mariadb:10.11` |
| Versión reportada | `10.11.18-MariaDB-ubu2204` |
| Contenedor | `sp-mariadb-1011` (puerto host 3307) |
| Script | `Backend/scripts/validate_mariadb_1011_sesion_unique.sh` |
| Schema probe | DB `sp_rest_probe` con FK `mesa_id` → `restaurante_mesas` (igual que prod) |

### A) Functional unique index (el de Fase 1 inicial)

```sql
CREATE UNIQUE INDEX … ON restaurante_sesiones_mesa (
  (CASE WHEN estado IN ('abierta','pre_cuenta') THEN mesa_id ELSE NULL END)
);
```

| Check | Resultado |
|-------|-----------|
| Sintaxis aceptada | **NO** — ERROR 1064 |
| Compatible prod MariaDB 10.11 | **NO** |

### B) GENERATED STORED / PERSISTENT / VIRTUAL + UNIQUE

| Variante | ADD COLUMN con FK | UNIQUE | Notas |
|----------|-------------------|--------|-------|
| `GENERATED ALWAYS AS (…) STORED` + UNIQUE INDEX | ✅ | ✅ | **Elegida** |
| `AS (…) PERSISTENT UNIQUE` inline | ✅ | ✅ | Equivalente STORED en MariaDB |
| `AS (…) VIRTUAL` + UNIQUE INDEX | ✅ | ✅ | También OK; preferimos STORED |
| Trailing `NULL` keyword tras STORED | ❌ 1064 | — | No usar |

### Comportamiento validado (GENERATED STORED + UNIQUE)

| Caso | Resultado |
|------|-----------|
| CREATE/ALTER con FK `mesa_id` | OK |
| NULL (sesiones cerradas) | Múltiples cerradas misma mesa OK |
| 2ª sesión `abierta` misma mesa | **1062 Duplicate** |
| `abierta` + `pre_cuenta` misma mesa | **1062 Duplicate** |
| Cerrar y volver a abrir | OK (1 activa) |
| DROP INDEX + DROP COLUMN (rollback) | OK |
| Re-migrate | OK |
| Rollback ×2 + migrate ×3 | OK |

### Solución final elegida

**MariaDB 10.11 (producción):** columna `mesa_sesion_activa_id` GENERATED STORED + `uq_restaurante_mesa_sesion_activa`.  
**MySQL local (dev):** fallback functional index (no usar en prod).

Migración actualizada: `2026_08_09_120000_add_unique_active_sesion_mesa.php` (detecta motor).

---

## 15. Test concurrente de inventario / confirmar pedido

### Test

`ConcurrencyIntegrityTest::test_two_concurrent_confirmar_pedido_single_inventory_exit`

### Mecánica

- 2 procesos OS (`concurrent_actor.php` action `confirmar_pedido`)
- Barrera de sincronización + `DB::purge/reconnect` por proceso
- Mismo `pedido_id` borrador con 1 línea producto (stock≥5, bodega usuario)

### Fixtures requeridas

| Fixture | Valor usado / nota |
|---------|---------------------|
| Usuario con `id_empresa` + `id_bodega` | user id=1, bodega=1 |
| Producto no-Servicio con stock en esa bodega | producto 992, stock≥5 |
| `empresa.vender_sin_stock` | 1 en ambiente local |
| Tabla `empresa_configuracion` | **Requerida** por `Empresa::isLotesActivo()`; en local faltaba → se aplicó migración pendiente `2026_07_31_210000_create_empresa_configuracion_table` como fixture de entorno (sin cambiar código de otros módulos) |
| Tablas | `restaurante_pedidos`, `restaurante_pedido_detalles`, `inventario`, `kardexs` |

### Resultado

| Invariante | Resultado |
|------------|-----------|
| ≥1 confirm OK (ambos pueden OK por idempotencia) | ✅ |
| `estado = pendiente_facturar` | ✅ |
| Un único `inventario_descontado_at` no null | ✅ |
| Kardex `detalle='Pedido pendiente de facturar'` +1 exactamente | ✅ |
| Stock final = stock inicial − cantidad | ✅ |
| No es test secuencial | ✅ dual-process |

### Suite final

```text
ConcurrencyIntegrity: 7/7 OK
```

---

## 16. Cambios realizados en esta actualización (post-revisión)

| Archivo | Cambio |
|---------|--------|
| `database/migrations/2026_08_09_120000_add_unique_active_sesion_mesa.php` | Motor-aware: MariaDB GENERATED STORED; MySQL fallback functional |
| `tests/Support/Restaurante/concurrent_actor.php` | action `confirmar_pedido` |
| `tests/Feature/Restaurante/ConcurrencyIntegrityTest.php` | test concurrente inventario + assert columna en MariaDB |
| `scripts/validate_mariadb_1011_sesion_unique.sh` | script evidencia MariaDB 10.11 |
| `FASE1_REPORT.md` | este update |
| Migración fixture `empresa_configuracion` | aplicada en DB local (schema faltante) |

**No se inició Fase 2.** No Idempotency-Key, no DTO, no Redis/cache.
