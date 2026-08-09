# BASELINE REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-09  
**Rama:** `dc.SP-2104`  
**Fase:** 0  
**Veredicto:** **GO CONDICIONAL** para Fase 1

---

## 1. Estado Git

| Item | Valor |
|------|-------|
| Branch | `dc.SP-2104` |
| Working tree | Limpio de código; untracked: `AUDITORIA_TECNICA_RESTAURANTE.md`, `PLAN_HARDENING_RESTAURANTE_1.0.md` (+ este reporte) |
| Últimos commits | docs/plantillas (no relacionados a Restaurante) |

---

## 2. Stack local observado

| Componente | Valor local | Target producción (plan) |
|------------|-------------|---------------------------|
| PHP | 8.4.18 | PHP 8.x |
| Composer | 2.10.1 | — |
| Laravel | 12 (composer.lock) | 12 |
| DB engine local | **MySQL 9.5.0** | **MariaDB 10.11** |
| Database | `smartpyme_prod` (local) | — |
| CACHE_DRIVER | file | Redis disponible |
| QUEUE_CONNECTION | sync | Queue disponible |
| Redis | OK vía ext-redis (`PING` = 1); `redis-cli` no instalado en PATH | Redis |
| Node / npm | v22.23.1 / 8.19.4 | Angular 22 |
| PHPUnit | 11.5.55 | — |
| pcntl | **sí** | útil para actores concurrentes |
| pdo_mysql | sí | — |

**Nota crítica:** el entorno local de desarrollo **no es MariaDB 10.11**. La sintaxis de columna generada STORED + UNIQUE se validó en MySQL 9.5 (bloquea duplicados; permite múltiples NULL). MariaDB 10.11 documenta el mismo patrón (`GENERATED ALWAYS AS … STORED/PERSISTENT` + UNIQUE; NULLs no colisionan). La migración debe probarse también en un staging MariaDB 10.11 antes de producción.

---

## 3. Tests existentes

### Backend (comandos)

```bash
cd Backend && ./vendor/bin/phpunit --filter 'Restaurante|MesasImportPlanner|PosMenu' --testdox
```

### Resultado

| Suite | Tests | Resultado |
|-------|-------|-----------|
| `MesasImportPlanner` | 2 | OK |
| `PosMenu` | 11 | OK |
| `ModulosOperativosPermissions` (restaurante/pedidos middleware) | 1 relevante | OK |
| **Total filtrado** | **14** | **OK** (8 PHPUnit deprecations) |

### Características de los tests actuales

- `PosMenuTest` y permissions: **sin DB** (inspección de query builders / middleware).
- **No existen** tests de concurrencia, abrir mesa, comanda, precuenta ni inventario.
- `phpunit.xml`: SQLite `:memory:` **comentado** → Feature con DB usa el MySQL del `.env`.
- **No hay** `actingAs` / JWT helpers en `tests/` hoy.

### Frontend

```bash
# package.json
"test": "ng test"
"build": "ng build"
```

Specs Restaurante:

- `cuenta-mesa/pos/pos-division.spec.ts`
- `cuenta-mesa/pos/pos-menu-nav.spec.ts`
- `mesa-zona-label.check.mjs`

No se ejecutó `ng test`/`ng build` completo en Fase 0 (costoso / browser); inventariados.

---

## 4. Migraciones Restaurante

Todas las migraciones core **Ran**, excepto:

| Migración | Estado |
|-----------|--------|
| `2026_08_04_120000_add_servido_to_comandas_restaurante_estado` | **Pending** |

El código de `ComandaController@actualizarEstado` ya acepta `servido`. Gap schema↔código documentado; **no** se aplica en Fase 0. Evaluar en Fase 1 solo si un test de estado lo requiere (no es P0 de races).

---

## 5. Volumen de datos local (referencia)

| Tabla | Count |
|-------|------:|
| restaurante_mesas | 38 |
| restaurante_zonas | 13 |
| restaurante_sesiones_mesa | 67 |
| orden_detalle_restaurante | 376 |
| comandas_restaurante | 39 |
| pre_cuentas_restaurante | 75 |
| restaurante_pedidos | 908 |

### Auditoría sesión activa (pre-constraint)

| Métrica | Valor |
|---------|------:|
| Sesiones `abierta` | 14 |
| Sesiones `pre_cuenta` | 0 |
| Sesiones `cerrada` | 53 |
| **Duplicados activos (mesa_id con >1 abierta/pre_cuenta)** | **0** |

→ Seguro evaluar migración de unique generada en este ambiente.

### Inventario canal / `inventario_descontado_at`

| Métrica | Valor |
|---------|------:|
| Pedidos no-borrador | 897 |
| Con `inventario_descontado_at` | 2 |
| Sin flag | 895 |

→ **No** hacer backfill masivo ciego en Fase 1 (riesgo de anular/revert incorrecto). Idempotencia forward: lock + `estado` + set/check del flag al descontar.

---

## 6. Probe columna generada (local MySQL 9.5)

```sql
mesa_sesion_activa_id INT
  GENERATED ALWAYS AS (IF(estado IN ('abierta','pre_cuenta'), mesa_id, NULL)) STORED
UNIQUE (mesa_sesion_activa_id)
```

Resultados:

- Segunda `abierta` misma mesa → **1062 Duplicate entry** ✅
- Múltiples `cerrada` misma mesa → **OK** (NULL múltiples) ✅
- Rollback de probe: tabla temporal

---

## 7. Endpoints Restaurante (sin cambio)

Prefijo: `api/restaurante/*` + `jwt.auth` + `verificar.funcionalidad:modulo-restaurante` + `permission:*`.

Ver listado completo en `AUDITORIA_TECNICA_RESTAURANTE.md` §5 / `routes/modulos/restaurante.php`.

---

## 8. Riesgos actuales (baseline)

1. Races P0 sin locks (abrir / items / comanda / facturar / inventario).
2. Sin unique de sesión activa en DB.
3. Sin tests de concurrencia reales.
4. Motor local ≠ MariaDB 10.11.
5. Migración `servido` pendiente.
6. 895 pedidos sin `inventario_descontado_at`.
7. Redis no es fuente de verdad (correcto); Fase 1 no depende de Redis para integridad.

---

## 9. Gap: tests de concurrencia en PHPUnit

### Problema

Una sola conexión + `DatabaseTransactions` envolvente **no** reproduce carreras reales con `lockForUpdate()`.

### Estrategia aprobada para Fase 1 (no falso verde)

1. **Actores = procesos OS independientes** (`Symfony\Component\Process\Process` o `pcntl_fork` + `DB::purge`/`reconnect`).
2. Cada actor abre **su propia conexión PDO** a MariaDB/MySQL.
3. Cada actor ejecuta el flujo real (controller o HTTP interno) en su proceso.
4. El padre sincroniza (barrera/archivo) y espera; luego aserta invariantes SQL.
5. Tests de **retry** = segundo actor tras commit del primero (no “simultáneo”, pero valida idempotencia de facto).
6. Si un escenario no puede reproducirse: marcar `skipped` con razón + documentar en `FASE1_REPORT.md` — **nunca assert green falso**.

Hot Table / Hot Product / k6 quedan para Fase 12+ (anotados en plan §25).

---

## 10. Archivos previstos Fase 1

Ver `PLAN_HARDENING_RESTAURANTE_1.0.md` §18.

---

## 11. Instrucciones obligatorias incorporadas

Actualizado `PLAN_HARDENING_RESTAURANTE_1.0.md` §25:

- Concurrencia dual-connection
- Constraint con audit previo
- Hot Table / Hot Product
- Retry real
- Integridad absoluta vs performance
- Redis ≠ fuente de verdad
- Load test realista / no prod destructivo
- Observabilidad por request ID
- Orden: Fase 0 → GO → Fase 1 → stop

---

## 12. Veredicto GO / NO-GO

| Criterio | Estado |
|----------|--------|
| Tests existentes Restaurante pasan | ✅ |
| DB accesible + tablas presentes | ✅ |
| 0 duplicados activos de sesión | ✅ |
| Probe generated unique OK (motor local) | ✅ |
| Redis disponible (no requerido P0) | ✅ |
| Estrategia concurrencia definida (no falso verde) | ✅ |
| Motor local = MariaDB 10.11 | ⚠️ No (MySQL 9.5) — documentado |
| Migración `servido` al día | ⚠️ Pending — no bloquea P0 races |
| Suite JWT Feature helpers | ⚠️ Ausente — se usarán actores CLI + Auth::loginUsingId |

### **GO CONDICIONAL** — ejecutado

Fase 1 se ejecutó tras este GO. Ver `FASE1_REPORT.md`.

Condiciones del GO:

1. Migración unique solo tras re-check de duplicados = 0 en el momento del migrate.
2. Tests concurrentes con procesos/conexiones independientes; skip documentado si falla infra.
3. No backfill masivo de `inventario_descontado_at`.
4. Validar sintaxis de migración también contra MariaDB 10.11 en staging antes de prod.
5. Al terminar Fase 1: `FASE1_REPORT.md` y **detenerse**.
