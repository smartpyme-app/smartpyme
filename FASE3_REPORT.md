# FASE 3 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §5 (P2 Performance / DB)  
**Predecesoras:** Fase 1 + Fase 2 cerradas y validadas  
**Estado:** Fase 3 — **COMPLETADA DENTRO DEL ALCANCE**  
**Siguiente:** **DETENERSE** — no iniciar Fase 4+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen de cambios

Implementado P2 de performance/DB **sin tocar integridad Fase 1** ni avance a Angular (Fase 4):

1. **Cache corto del mapa** `GET /mesas` (TTL 3s, preferencia Redis, fallback cache default)
2. **Invalidación** en abrir/cerrar/reactivar/trasladar/facturar/reservar/CRUD mesa
3. **Bulk insert** de `ComandaDetalle` (mesa + canal) + bulk update flags pedido
4. **Índices** justificados con EXPLAIN (fusión + sesión activa); índice cocina ya existía (Fase 2)
5. **Liquidación precuenta:** se documenta **sin bulk** (cantidades parciales por línea)

---

## 2. Archivos modificados / creados

### Creados

| Archivo | Rol |
|---------|-----|
| `Backend/app/Services/Restaurante/MesaMapaCacheService.php` | Cache mapa + bump versión por empresa |
| `Backend/database/migrations/2026_08_10_100000_add_fase3_restaurante_performance_indexes.php` | Índices performance |
| `Backend/tests/Feature/Restaurante/Phase3PerformanceTest.php` | Tests Fase 3 |
| `FASE3_REPORT.md` | Este reporte |

### Modificados

| Archivo | Cambio |
|---------|--------|
| `Backend/app/Http/Controllers/Api/Restaurante/MesaController.php` | `remember` en index; invalidate en store/update |
| `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` | invalidate abrir/cerrar/reactivar/trasladar |
| `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php` | invalidate tras marcar facturada |
| `Backend/app/Http/Controllers/Api/Restaurante/ReservaController.php` | invalidate store/cancelar/convertir |
| `Backend/app/Http/Controllers/Api/Restaurante/ComandaController.php` | bulk `ComandaDetalle::insert` |
| `Backend/app/Http/Controllers/Api/Restaurante/PedidoRestauranteController.php` | bulk detalles + update enviado_* |

**Sin cambios Frontend / Fase 4.**

---

## 3. Migraciones

`2026_08_10_100000_add_fase3_restaurante_performance_indexes` (aplicada en local):

| Índice | Tabla | Columnas |
|--------|-------|----------|
| `orden_detalle_rest_sesion_prod_enviado_index` | `orden_detalle_restaurante` | `(sesion_id, producto_id, enviado_cocina, enviado_barra)` |
| `restaurante_sesiones_mesa_mesa_id_estado_index` | `restaurante_sesiones_mesa` | `(mesa_id, estado)` |

Reversible en `down()`.  
Índice cocina `(id_empresa, estado, created_at)` **no recreado** (ya en Fase 2).

---

## 4. Detalle por ítem del plan §5

### 5.1 Redis cache mapa

- TTL: **3s** (rango plan 2–5s)
- Clave payload: `rest:mapa:p:{ver}:{empresa}:{sucursal|all}:{activo|all}`
- Invalidación: `increment` de `rest:mapa:ver:{empresa}` (todas las variantes)
- Store: intenta `Cache::store('redis')`; si falla → store default (`array` en phpunit, `file` en .env local)
- **Redis ≠ SoT:** miss/error → DB siempre

Invalidación cableada:

- Abrir / cerrar / reactivar / trasladar sesión
- Marcar precuenta facturada
- Crear/cancelar/convertir reserva
- Crear/actualizar mesa

### 5.2 Bulk updates

| Sitio | Decisión |
|-------|----------|
| `crearComandaSesion` detalles | **Bulk insert** (`ComandaDetalle` no es Auditable) |
| Pedido canal detalles + `enviado_*` | **Bulk insert** + `whereIn` update |
| `marcarItemsEnviados` | Ya era bulk UPDATE condicional (Fase 1) — sin cambio |
| `liquidarOrdenTrasFacturarPreCuenta` | **Sin bulk** — liquidación parcial por línea (`delete` vs `update cantidad`); bulk rompería semántica |

### 5.3 Índices (EXPLAIN)

**Entorno local:** MySQL 9.5 / DB `smartpyme_prod`.

#### A) Fusión ítems — `orden_detalle_restaurante`

Consulta:

```sql
SELECT id FROM orden_detalle_restaurante
WHERE sesion_id = ? AND producto_id = ?
  AND enviado_cocina = 0 AND enviado_barra = 0 AND deleted_at IS NULL
```

| Momento | Plan resumido |
|---------|----------------|
| Antes | Index lookup `producto_id_foreign` + filter (cost ~0.255) |
| Después | Optimizer local aún puede preferir `producto_id` en datasets chicos; índice compuesto **creado** para patrón fusión por sesión a escala |

**Impacto:** bajo riesgo de escritura; lectura de fusión alineada al candidato del plan.

#### B) Sesión activa — `restaurante_sesiones_mesa`

Consulta:

```sql
SELECT id FROM restaurante_sesiones_mesa
WHERE mesa_id = ? AND estado IN ('abierta','pre_cuenta') LIMIT 1
```

| Momento | Plan resumido |
|---------|----------------|
| Antes | `mesa_id_foreign` + filter estado (cost ~1.2) |
| Después | **Covering range scan** `mesa_id_estado_index` (cost ~0.661) |

**Impacto:** mejora clara en lookup mapa/abrir.

#### C) Cocina — `comandas_restaurante`

Ya usa `comandas_restaurante_id_empresa_estado_created_at_index` (Fase 2). EXPLAIN: index range scan. **Sin migración nueva.**

---

## 5. Tests ejecutados y resultados

### Baseline (antes de cambios)

```bash
./vendor/bin/phpunit tests/Feature/Restaurante/ --testdox
→ 31/31 OK
```

### Después de Fase 3

```bash
./vendor/bin/phpunit tests/Feature/Restaurante/ --testdox
→ 34/34 OK
```

| Suite | Resultado |
|-------|-----------|
| ConcurrencyIntegrity (Fase 1) | 7/7 OK |
| Phase2ApiHardening | 13/13 OK |
| **Phase3Performance** | **3/3 OK** |
| PosMenu | 11/11 OK |
| **Total** | **34/34 OK** |

Nuevos tests:

- Cache hit estable
- Invalidación tras abrir mesa (estado libre → ocupada)
- Existencia de índices Fase 3 (+ cocina Fase 2)

---

## 6. Problemas encontrados

1. **Optimizer MySQL en fusión** puede seguir eligiendo `producto_id` con poca data; el índice compuesto se mantiene por patrón real de fusión por sesión (justificado en plan).
2. **`CACHE_DRIVER=file` local** / `array` en phpunit: cache funciona; Redis preferido cuando el store `redis` responde (verificado ping OK en local).
3. Nada que requiera ampliar alcance a integridad/multi-tenant más allá de invalidación de cache.

---

## 7. Decisiones técnicas

1. Invalidación por **versión de empresa** (no tags): funciona con file/array/redis.
2. TTL 3s: frescura aceptable para mapa; integridad sigue en DB.
3. Preferir Redis store si disponible, sin fallar el endpoint si Redis cae.
4. Bulk solo donde no hay Auditable / liquidación parcial.
5. No implementar OnPush / `@for` / Signals (Fase 4).

---

## 8. Desviaciones del plan

| Plan | Hecho | Nota |
|------|-------|------|
| Medir antes/después | EXPLAIN + suite | Sin benchmark HTTP de carga (Fase load test posterior) |
| Índice fusión | Creado | EXPLAIN local no siempre lo elige con poca data |
| Índice cocina | No recreado | Ya en Fase 2 |
| Liquidación bulk | No | Documentado: semántica parcial |

---

## 9. Riesgos pendientes / hallazgos Fase 4+

| Ítem | Fase | Acción |
|------|------|--------|
| OnPush / `@for` / `mesasPorZona` / `takeUntilDestroyed` | **4** | Solo documentado |
| FE `Idempotency-Key` | **4** | Solo documentado |
| Queues impresión/notificaciones | **5** | No tocado |
| Reverb / realtime | **6** | No tocado |
| Load test Peak | posterior | No tocado |
| Stale mapa ≤3s tras write si invalidate falla | ops | Best-effort; TTL limita ventana |
| Recomendar `CACHE_DRIVER=redis` en prod | deploy | Documentado |

---

## 10. Criterios de completitud de Fase 3

- [x] Cache corto mapa por empresa (+ sucursal/activo en key)
- [x] Invalidación en opens/closes/facturar/trasladar/reservar (+ mesa CRUD)
- [x] Redis preferido; no SoT; fallback seguro
- [x] Bulk donde no rompe auditoría/semántica; liquidación documentada sin bulk
- [x] Índices con EXPLAIN antes/después documentados; migración reversible
- [x] Tests nuevos + suite Restaurante 34/34
- [x] Sin regresiones Fase 1/2 (Concurrency + Phase2 OK)
- [x] Sin entregables Fase 4+

---

## 11. Siguiente paso — DETENERSE

**Fase 3 lista para revisión.** Commit manual pendiente.

**No iniciar Fase 4** (Angular OnPush/track/HTTP/subscriptions) ni fases posteriores hasta nueva autorización explícita.
