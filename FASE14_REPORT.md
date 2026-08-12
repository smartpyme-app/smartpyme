# FASE 14 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-12  
**Alcance:** Correcciones controladas + preparación PR + procedimiento de deploy (sin ejecutar en producción)  
**Estado:** **COMPLETADA DENTRO DEL ALCANCE**  
**Producción:** **NO MODIFICADA**  
**Commit / push / merge / deploy:** **NO automáticos**

---

## 1. Resumen ejecutivo

Se auditaron los riesgos de Fase 13 contra el código y consumidores Angular.

| Decisión | Acción |
|----------|--------|
| Saturación ~30 VUs local | **No corregir** (runtime `artisan serve`) |
| `GET /comandas` | **No paginar** (FE cocina espera array; ya filtra estados activos). Regresión + migración `servido` en plan de deploy |
| `GET /reservas` | **Sí:** default `fecha=hoy` si no hay `fecha`; escape `?todas=1` |
| `GET /pedidos` | **Sin cambio** (paginación + cap 100 ya existe) |
| Índices / cache / FPM / Nginx | **Sin cambio especulativo** |

Suite Restaurante:

| | Tests | Assertions |
|--|------:|-----------:|
| Antes (F11–13) | 50 | 198 |
| Después (F14) | **57** | **210** |

(+7 tests / +12 assertions: `Phase14ControlledFixesTest`)

---

## 2. Objetivo

Corregir solo lo justificado; preparar PR y procedimiento seguro de despliegue; **no** tocar producción en esta fase.

---

## 3. Auditoría de riesgos F13

| Riesgo F13 | Clasificación | Notas |
|------------|---------------|-------|
| Saturación artisan ~30 VUs | **NO REPRODUCIBLE como bug de producto** | Limitación de entorno local |
| `/comandas` histórico ilimitado | **POTENCIAL** (board activa sin LIMIT) | Código ya excluye `servido`; crece si `listo` no se drena |
| `/reservas` sin límite | **CONFIRMADO** (código) / impacto **POTENCIAL** | FE no llama `getReservas()`; API sí hacía `->get()` |
| `/pedidos` crecimiento | **MITIGADO** | `paginate` 1–100 |
| Thresholds latencia F13 | **NO CORREGIR AQUÍ** | No evidencia de PHP-FPM/Nginx/MariaDB |

---

## 4. Correcciones realizadas

### 4.1 `ReservaController::index` — default fecha hoy

**Archivo:** `Backend/app/Http/Controllers/Api/Restaurante/ReservaController.php`

- Sin `fecha` y sin `todas=1` → filtra `fecha_reserva = hoy`.
- Con `fecha=YYYY-MM-DD` → filtro explícito.
- Con `todas=1` → sin filtro de fecha (histórico; escape documentado).
- `estado` sigue opcional.
- Tenant `id_empresa` intacto.

**Breaking change (API):** clientes que listaban **todas** las reservas sin params ahora reciben solo el día actual. UI Angular del mapa **no** usa este listado (usa `reservas_activas` en mesas).

### 4.2 Comentario tipado en Angular service

**Archivo:** `Frontend/src/app/services/restaurante.service.ts`  
Documenta default + `todas` (sin cambio de flujos UI activos).

### 4.3 Tests Fase 14

**Archivo:** `Backend/tests/Feature/Restaurante/Phase14ControlledFixesTest.php`

- Default hoy / `todas` / fecha explícita  
- Cross-tenant reservas  
- Cocina excluye `servido` (si enum lo permite)  
- Cross-tenant comandas  
- Cap `paginate` pedidos = 100  

### 4.4 Migración `servido` en DB **local** de desarrollo

Aplicada **solo** en MySQL local `127.0.0.1` / `smartpyme_prod` (nombre local):

`2026_08_04_120000_add_servido_to_comandas_restaurante_estado`

**No** se ejecutó en producción.

---

## 5. Problemas encontrados / corregidos / aplazados

### Corregidos
1. Listado `/reservas` sin acotar fecha (default hoy + escape `todas`).
2. Gap de cobertura de tests para reservas default, cocina `servido`, cap pedidos, tenant.

### No corregir todavía
1. Paginación de `/comandas` (rompería cocina sin cambio FE mayor).
2. Índices nuevos (ya hay índices F2/F3; sin EXPLAIN prod).
3. TTL cache mapa / Redis.
4. Config PHP-FPM / Nginx / MariaDB.
5. Cleanup histórico `listo` por edad (comportamiento operativo; requiere producto).
6. Load test adicional.

---

## 6. Tests

```text
php -d memory_limit=512M ./vendor/bin/phpunit tests/Feature/Restaurante
→ Tests: 57, Assertions: 210  (OK; 1 PHPUnit deprecation preexistente)
```

---

## 7. Migrations (Restaurante) — inventario y seguridad

| Archivo | Propósito | Tablas | up() | down() | Lock riesgo | Prod |
|---------|-----------|--------|------|--------|-------------|------|
| `2026_03_18_100003_create_orden_detalle_restaurante_table` | create | orden_detalle | create | drop | bajo si ya aplicada | ya en prod típico |
| `2026_03_18_100004_create_comandas_restaurante_table` | create | comandas | create | drop | bajo | ya |
| `2026_03_18_100005_create_comanda_detalle_restaurante_table` | create | comanda_detalle | create | drop | bajo | ya |
| `2026_03_18_100006_create_division_cuenta_restaurante_table` | create | division | create | drop | bajo | ya |
| `2026_03_18_100007_create_pre_cuentas_restaurante_table` | create | pre_cuentas | create | drop | bajo | ya |
| `2026_03_18_100009_create_reservas_restaurante_table` | create | reservas | create | drop | bajo | ya |
| `2026_03_24_100000_create_restaurante_pedidos_table` | create | pedidos | create | drop | bajo | ya |
| `2026_03_24_100001_create_restaurante_pedido_detalles_table` | create | detalles | create | drop | bajo | ya |
| `2026_03_24_120000_add_propina_to_pre_cuentas…` | add column | pre_cuentas | alter | drop col | bajo–medio | ya |
| `2026_03_31_120000_add_inventario_pedido…` | inventario | pedidos | alter | reverse | medio | ya |
| `2026_05_14_120000_restaurante_mejoras_operacion` | mejoras | varias | alter | reverse | medio | ya |
| `2026_05_14_130000_add_eliminacion_motivo…` | motivo | comandas | alter | drop | bajo | ya |
| `2026_06_02_120000_restaurante_zonas_y_comandas_pedido` | zonas/pedido | varias | create/alter | reverse | medio | ya |
| `2026_06_21_111722_add_id_paquete…` | paquete | detalles | alter | drop | bajo | ya |
| `2026_08_04_120000_add_servido_to_comandas…` | **enum +servido** | comandas | `MODIFY ENUM` | reduce enum (data→listo) | **MEDIO** (ALTER TABLE) | **SAFE CON CONDICIONES** — verificar si falta en prod |
| `2026_08_09_140000_add_id_empresa_to_comandas` | tenant denorm | comandas | add+backfill | drop | medio | F2 — verificar |
| `2026_08_09_140001_create_restaurante_idempotency_keys` | idempotency | keys | create | drop | bajo | F2 — verificar |
| `2026_08_10_100000_add_fase3_restaurante_performance_indexes` | índices | orden_detalle, sesiones | add index if missing | drop | bajo–medio (online DDL) | F3 — verificar |
| `2026_08_10_150000_create_restaurante_side_effects` | outbox | side_effects | create | drop | bajo | F5 — verificar |

### Migrations relevantes para el próximo deploy (verificar `migrate:status`)

Priorizar si **pending** en producción:

1. `2026_08_04_120000_add_servido_to_comandas_restaurante_estado` — **SAFE CON CONDICIONES**  
2. `2026_08_09_140000_add_id_empresa_to_comandas_restaurante` — **SAFE CON CONDICIONES** (backfill)  
3. `2026_08_09_140001_create_restaurante_idempotency_keys_table` — **SAFE** (create)  
4. `2026_08_10_100000_add_fase3_restaurante_performance_indexes` — **SAFE CON CONDICIONES**  
5. `2026_08_10_150000_create_restaurante_side_effects_table` — **SAFE** (create)

**Fase 14 no añade migrations nuevas.** Solo código + tests + docs.

Antes de `migrate --force` en prod: `php artisan migrate:status` y confirmar solo pendientes esperadas.  
Si aparece una migración ajena/desconocida: **REQUIERE REVISIÓN MANUAL — DETENER**.

---

## 8. Seeders Restaurante

| Seeder | Qué hace | Idempotente | Clasificación | Prod |
|--------|----------|-------------|---------------|------|
| `RestauranteFuncionalidadSeeder` | `updateOrCreate` slug `modulo-restaurante` | Sí | **SAFE WITH CONDITIONS** | Solo si falta el módulo; no borra datos |
| `PermissionSeeder` / `ModulosOperativosPermissionSeeder` / `RoleSeeder` | permisos/roles globales (incl. módulo Restaurante) | Parcial | **NOT SAFE** / revisar | **NO EJECUTAR EN PRODUCCIÓN** sin auditoría (puede reasignar roles) |
| `DatabaseSeeder` | orquesta varios | — | **NOT SAFE** | **NO EJECUTAR** (`db:seed` completo) |

**Recomendación F14:**  
**NO EJECUTAR SEEDERS EN PRODUCCIÓN** para este deploy, salvo confirmación explícita de que falta `modulo-restaurante` y se corre **solo**:

```bash
php artisan db:seed --class=RestauranteFuncionalidadSeeder --force
```

---

## 9. Archivos modificados / nuevos

```
Backend/app/Http/Controllers/Api/Restaurante/ReservaController.php   (mod)
Frontend/src/app/services/restaurante.service.ts                    (mod, comentario)
Backend/tests/Feature/Restaurante/Phase14ControlledFixesTest.php    (nuevo)
FASE14_REPORT.md                                                    (nuevo)
```

También en working tree (fases previas, no tocados en lógica F14): `load-tests/`, `FASE12/13`, etc.

**No modificados:** Controllers de comandas/pedidos (lógica), Models, cache, Redis, Nginx, FPM, índices nuevos.

---

## 10. Propuesta de PR (no creada automáticamente)

### Título sugerido

`fix(restaurante): acotar GET /reservas a fecha de hoy + tests Fase 14`

### Descripción

```markdown
## Summary
- GET /api/restaurante/reservas sin `fecha` ahora filtra el día actual (escape `?todas=1`).
- Tests de regresión Fase 14 (reservas, cocina/servido, pedidos cap, tenant).
- Sin paginar /comandas (compatibilidad cocina Angular).
- Sin cambios de infraestructura ni índices especulativos.

## Changes
- ReservaController::index default fecha
- Phase14ControlledFixesTest (7 tests)
- Comentario en restaurante.service.ts

## Why
Fases 7/13: riesgo de listado histórico sin límite en /reservas.
Cocina ya filtra estados activos; drenaje depende de enum `servido` (migración existente).

## Tests
```
cd Backend && php -d memory_limit=512M ./vendor/bin/phpunit tests/Feature/Restaurante
# 57 passed / 210 assertions
```

## Database
- No hay migration nueva en este PR.
- Verificar en prod pending: especialmente `2026_08_04_120000_add_servido…` y migraciones F2/F3/F5 si faltan.
- Seeders: NO por defecto.

## Production Risk
- Bajo–medio: cambio de default API /reservas (UI mapa no usa listado).
- ALTER ENUM `servido` si pendiente: planificar ventana / observar lock.

## Rollback
- Código: revertir commit del PR.
- Migration `servido`: ver §13 — no rollback automático si hay filas `servido`.

## Checklist
- [ ] Suite Restaurante verde
- [ ] migrate:status revisado en staging/prod
- [ ] Smoke READ-ONLY post-deploy
- [ ] Sin seeders destructivos
```

---

## 11. Procedimiento de deploy a PRODUCCIÓN

> **NO ejecutar automáticamente.** El usuario lo ejecuta cuando apruebe.

### A. Pre-deploy

1. Branch con el PR mergeado (p. ej. `main` tras merge).
2. PR aprobado + CI verde.
3. Confirmar URL/API productiva y ventana de mantenimiento si hará falta ALTER.
4. Salud: login OK, GET mesas OK, sin 5xx en logs recientes.
5. Espacio disco: `df -h` en servidor app y DB.
6. Versión actual: `git rev-parse HEAD` / tag desplegado.
7. Migraciones: `php artisan migrate:status` (solo lectura).

### B. Backup

1. Backup DB MariaDB **antes** del deploy (mysqldump o snapshot proveedor).  
2. Verificar tamaño/fecha del backup; **no borrar** backups previos.  
3. Si no hay backup verificable: **DETENER**.

### C. Código (adaptar paths reales del servidor)

```bash
# Ejemplo genérico — ajustar user/path
cd /ruta/al/proyecto/Backend   # o monorepo root según layout
git fetch origin
git checkout <tag-o-sha-aprobado>
git pull --ff-only             # o deploy artifact

composer install --no-dev --optimize-autoloader

# Frontend (si el deploy incluye build)
cd ../Frontend
npm ci
npm run build                  # output según angular.json
# publicar dist al path que sirva Nginx
```

Permisos storage (si aplica):

```bash
chmod -R ug+rwx storage bootstrap/cache
```

### D. Migrations

```bash
cd Backend
php artisan migrate:status
# Revisar pendientes. Solo continuar si coinciden con la lista esperada (§7).

php artisan migrate --force
# NUNCA: migrate:fresh | migrate:refresh | db:wipe | migrate:reset
```

Post-check:

```bash
php artisan migrate:status
# Verificar enum:
# SHOW COLUMNS FROM comandas_restaurante LIKE 'estado';
# debe incluir 'servido' tras la migración correspondiente
```

Si una pending es desconocida o riesgosa: **DETENER — REQUIERE REVISIÓN MANUAL**.

### E. Seeders

**NO EJECUTAR SEEDERS EN PRODUCCIÓN** por defecto.

Solo si falta módulo:

```bash
php artisan db:seed --class=RestauranteFuncionalidadSeeder --force
```

### F. Cache

Preferir reconstrucción explícita tras deploy de config/rutas:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Spatie (si se usó permission change — normalmente NO en este PR):
# php artisan permission:cache-reset
```

Evitar `cache:clear` global indiscriminado en prod salvo necesidad; invalida Redis app-wide.

`config/restaurante.php` debe estar incluido al hacer `config:cache` (hallazgo F10).

### G. Queue / Supervisor

Si hay workers Laravel:

```bash
sudo supervisorctl status
sudo supervisorctl restart <programa-queue-smartpyme>
# o: php artisan queue:restart
```

No detener todos los workers del host sin necesidad.

### H. Nginx / PHP-FPM

Tras código/config:

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl reload php*-fpm    # versión exacta del servidor
```

Preferir **reload** sobre restart.

### I. Smoke tests READ-ONLY post-deploy

Con JWT de prueba **no productor** / usuario autorizado, **solo GET**:

```bash
BASE=https://<API-PROD>
AUTH="Authorization: Bearer <TOKEN>"

curl -sS -o /tmp/m.json -w "%{http_code} %{time_total}\n" -H "$AUTH" -H "Accept: application/json" "$BASE/api/restaurante/mesas"
curl -sS -o /tmp/c.json -w "%{http_code} %{time_total}\n" -H "$AUTH" -H "Accept: application/json" "$BASE/api/restaurante/comandas"
curl -sS -o /tmp/r.json -w "%{http_code} %{time_total}\n" -H "$AUTH" -H "Accept: application/json" "$BASE/api/restaurante/reservas"
curl -sS -o /tmp/p.json -w "%{http_code} %{time_total}\n" -H "$AUTH" -H "Accept: application/json" "$BASE/api/restaurante/pedidos?paginate=10&page=1"
# sesión: usar ID conocido de la empresa del token, no inventar writes
curl -sS -o /tmp/s.json -w "%{http_code} %{time_total}\n" -H "$AUTH" -H "Accept: application/json" "$BASE/api/restaurante/sesiones-mesa/<ID>"
```

Validar: HTTP 200, JSON válido, datos solo del tenant del token, sin 5xx en logs.  
**NO** crear mesas/pedidos/comandas/reservas/sesiones/facturas en prod para probar.

---

## 12. Rollback plan

### A. Código
```bash
git checkout <sha-previo-estable>
composer install --no-dev --optimize-autoloader
# rebuild FE si aplica
php artisan config:cache && php artisan route:cache
reload nginx/php-fpm
```

### B. Migration
- Creates (idempotency, side_effects): rollback posible con `migrate:rollback --step=1` **solo** si no hay dependencia de datos críticos — preferir dejar tablas.
- `servido` enum: `down()` convierte `servido`→`listo`. Si cocina depende de terminal:  
  **NO HACER ROLLBACK AUTOMÁTICO** sin análisis de filas `estado='servido'`.
- Índices F3: drop index es seguro funcionalmente (pérdida de perf).

### C. Configuración
Restaurar `.env` previo si se cambió; `config:cache`.

### D. Cache
`config:cache` / `route:cache` tras rollback de código; Spatie reset solo si se tocó permisos.

---

## 13. Riesgos pendientes

1. Capacidad real PHP-FPM/Nginx/MariaDB 10.11 / Redis — **NO MEDIDO**.  
2. `/comandas` board grande si `listo` no se marca `servido`.  
3. Cambio API `/reservas` default (clientes externos desconocidos).  
4. ALTER ENUM en tablas grandes en MariaDB — planificar.  
5. Working tree incluye artefactos F12/F13 (load-tests) — decidir qué entra al PR.

---

## 14. Criterios de completitud

- [x] Riesgos F13 revisados y clasificados  
- [x] Solo correcciones justificadas (1+2 aprobados)  
- [x] Sin optimizaciones especulativas  
- [x] Sin modificar producción (excepto N/A; migrate solo local)  
- [x] Sin migrate/seed en producción  
- [x] Sin comandos destructivos  
- [x] Tests agregados; suite 57/210 verde  
- [x] Multi-tenant verificado en tests  
- [x] Migrations/seeders documentados  
- [x] Propuesta de PR  
- [x] Procedimiento deploy + smoke READ-ONLY + rollback  
- [x] Sin commit/push/merge/deploy automático  

---

## 15. Siguiente paso

**DETENERSE.** No Fase 15. No load test. No deploy. Esperar aprobación explícita para PR y/o deploy.

**FASE 14 COMPLETADA — NO SE MODIFICÓ PRODUCCIÓN — ESPERANDO APROBACIÓN PARA PR/DEPLOY**
