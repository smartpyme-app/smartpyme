# FASE 10 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §12 (Observabilidad) + umbrales §14 (referencia futura Peak)  
**Predecesoras:** Fase 1–9 cerradas y validadas  
**Estado:** Fase 10 — **COMPLETADA DENTRO DEL ALCANCE** (medir + documentar; **sin optimizaciones**)  
**Siguiente:** **DETENERSE** — no iniciar Fase 11+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen ejecutivo

Fase 10 midió lo que el entorno **local** permite y marcó el resto como **NO MEDIDO**.

| Resultado | Detalle |
|-----------|---------|
| APM / p95 HTTP Restaurante | **NO MEDIDO** — sin Telescope/Sentry/Datadog; sin tráfico Peak; sin access-log Restaurante agregado |
| SQL planes (EXPLAIN) | **MEDIDO (local MySQL 9.5)** — índices Fase 2/3 usados en mesas/comandas/pedidos/side_effects |
| Latencia HTTP p50/p95/p99 | **NO MEDIDO** |
| Microbench DB local (ms) | **MEDIDO** a volumen bajo — **no** sustituye p95 HTTP ni prod MariaDB 10.11 |
| Redis | Ping OK; cache store redis usable; `CACHE_STORE=file` default; mapa prefiere redis si probe OK |
| Queue | Local `sync`; outbox 28 `done` / 0 `pending`; `failed_jobs=0` |
| Realtime | BE `BROADCAST_CONNECTION=log`; FE `restauranteRealtime.enabled=false`; sin Reverb |
| Optimizaciones | **Ninguna** (regla crítica) |
| Código | **Sin cambios** |
| Suite | **45/45** antes y después |

Hallazgo ops **CONFIRMADO:** `bootstrap/cache/config.php` **no incluye** `config/restaurante.php` → `config('restaurante')` inexistente; el código usa defaults del 2º argumento (`true` / `default` / `300`). Env `RESTAURANTE_*` **no se aplican** hasta `config:clear` / rebuild cache.

---

## 2. Estado de observabilidad existente

| Mecanismo | ¿Existe? | Uso Restaurante |
|-----------|----------|-----------------|
| Laravel `Log` daily (`storage/logs/laravel-YYYY-MM-DD.log`) | Sí | Side effects (`restaurante.side_effect.*`), realtime fail warnings, job failures |
| Canales payments_* | Sí | No Restaurante |
| Telescope / Horizon / Sentry / Datadog / New Relic | **No** en `composer.json` | — |
| Request timing middleware dedicado Restaurante | **No** | — |
| `DB::listen` / query log app | **No** dedicado | — |
| MariaDB/MySQL `slow_query_log` | Local **OFF** (`long_query_time=10`) | Sin captura |
| PHP-FPM / Nginx metrics | **NO MEDIDO** (no accesibles en esta sesión) | — |
| Redis metrics (hit rate, memoria) | **NO MEDIDO** (solo ping) | — |
| Queue metrics | Local sync; sin tabla `jobs` | Outbox en MariaDB |
| APM correlation / request id | **NO MEDIDO** / no estándar Restaurante | — |

Logs Restaurante observados (muestra 2026-08-10): contexto mínimo `id_empresa` + `comanda_id` — **sin** JWT, payloads ni DTE (alineado a privacidad).

---

## 3. Métricas disponibles (evidencia real)

| Métrica | Evidencia | Entorno |
|---------|-----------|---------|
| EXPLAIN mesas / comandas / pedidos / reservas / side_effects | Index lookup / range scan (ver §6) | Local MySQL 9.5 |
| Filas tablas resto (snapshot) | mesas 44, comandas 43, pedidos 909, sesiones 71, SE 28, reservas 1 | Local DB |
| Comandas por estado | pendiente=35, listo=8 | Local |
| Side effects status | done=28, pending=0 | Local |
| Microbench SQL (p50 ms, n=7) | mesas 0.29; cocina 0.38; reservas 0.21; pedidos LIMIT10 0.27; SE pending 0.13 | Local — **no HTTP** |
| Cache mapa hit local | 1ª ~6.11 ms (put); 2ª ~1.53 ms mismo payload | Redis store OK |
| Redis PING | true | Local |
| Config queue/cache/broadcast | sync / file / log | Local `.env` |
| Log side_effect.comanda_ticket_ready | Presente en laravel-2026-08-10.log | Local |
| Suite Feature Restaurante | 45 passed | Local |

---

## 4. Métricas NO MEDIDAS

| Métrica | Motivo |
|---------|--------|
| HTTP p50 / p95 / p99 por endpoint | Sin APM; sin carga representativa; sin parseo access log prod |
| Error rate / 422 / 5xx Restaurante | Sin agregación de access/error logs |
| RPS Peak Agro-Mall | Fase 12/13 |
| DB time dentro del request PHP | Sin instrumentación request |
| Filas examinadas vs rows en prod MariaDB 10.11 | Solo local MySQL 9.5; cardinality distinta |
| Lock wait time / deadlocks | Sin `performance_schema` dump ni tráfico |
| Idempotency hit rate | Sin métrica dedicada |
| Cache hit rate mapa (prod) | Sin contadores; solo probe local |
| Redis latency/p95 | Solo ping |
| PHP-FPM active/idle, Nginx `request_time` | No accesible aquí |
| Contención MariaDB por reportes otros módulos | **NO MEDIDO** (Fase 9 potencial; no afirmado) |
| Reverb WS latency / reconnect | Reverb no instalado; FE disabled |
| Umbrales plan §14 (mapa p95&lt;500ms, etc.) | Requieren Peak instrumentado |

---

## 5. Matriz HTTP

Leyenda estado: **NO MEDIDO** = sin evidencia de latencia/errores HTTP reales.

| Endpoint | p50 | p95 | p99 | requests | errores | DB time | filas (local) | estado |
|----------|-----|-----|-----|----------|---------|---------|---------------|--------|
| GET /mesas | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | ~44 mesas | Observado: cache TTL 3s + DTO |
| GET /comandas | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | 4 cocina / 43 total; 35+8 estados | Sin LIMIT — riesgo Fase 7/9 |
| GET /reservas | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | 0–1 | Sin paginar |
| GET /pedidos | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | page≤100; 909 total | Con detalles |
| GET /sesiones-mesa/{id} | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | 1 + ítems | — |
| GET /pre-cuentas/{id} | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | 1 | — |
| abrir mesa POST | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | Integridad F1 intacta |
| agregar ítem | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | — |
| enviar comanda | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | + outbox/realtime |
| solicitar cuenta | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | — |
| marcar precuenta facturada | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | — |
| confirmar pedido | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | Canal inventario F1 |
| anular pedido | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | NO MEDIDO | — | — |

---

## 6. Matriz DB

Entorno: **local MySQL 9.5.0** (prod SoT = MariaDB **10.11** — planes a revalidar).  
Tiempo = microbench SQL p50 local (n=7), **no** latencia endpoint.

| Query | Endpoint | Índice | EXPLAIN (resumen) | Filas ret. local | Tiempo p50 local | Riesgo |
|-------|----------|--------|-------------------|------------------|------------------|--------|
| mesas por empresa | GET /mesas | `(id_empresa,id_sucursal)` | Index lookup | 6 (empresa 1) | 0.29 ms | Bajo a N≈mesas |
| comandas cocina | GET /comandas | `(id_empresa,estado,created_at)` | Range scan + sort | 4 | 0.38 ms | **POTENCIAL** sin LIMIT al crecer `listo` |
| reservas empresa | GET /reservas | FK `id_empresa` | Index lookup + sort | 0 | 0.21 ms | **POTENCIAL** sin paginar |
| pedidos page | GET /pedidos | `(id_empresa,fecha)` | Index reverse + LIMIT 10 | 1 | 0.27 ms | Medio payload (detalles Eloquent) |
| sesión activa | mapa/apertura | `mesa_id` (+ índice estado Fase 3 en otros paths) | Lookup mesa_id | 0 | 0.32 ms | Bajo |
| SE pending | jobs outbox | `(id_empresa,status)` | Index lookup | 0 | 0.13 ms | Bajo hoy |
| SE count done | ops | covering status idx | Aggregate | 28 | 0.09 ms | Crecimiento UNIQUE (Fase 7) |

Full scan histórico Restaurante en estas queries: **no observado** en EXPLAIN local.  
N+1 HTTP: no medido con profiler; código cocina/mesas usa `with()` (Fase 9).

**No se crearon índices.**

---

## 7. Redis

| Ítem | Resultado | Evidencia |
|------|-----------|-----------|
| Redis reachable | Sí | `Redis::ping()` → true |
| `CACHE_STORE` default | `file` | config |
| Mapa usa | Prefiere `Cache::store('redis')` si probe OK | `MesaMapaCacheService` |
| Probe redis store | OK | Local |
| TTL mapa | 3 s | constante `TTL_SECONDS` |
| Invalidación | bump `rest:mapa:ver:{empresa}` | código |
| Hit/miss rate prod | **NO MEDIDO** | — |
| Fallback sin Redis | catch → DB / cache default | código |
| Queue Redis | Local queue=`sync` (no redis queue) | config |
| Diferencia hit vs miss | Local: ~1.5 ms vs ~6 ms put path (probe artificial) | no prod |

**Sin cambio de estrategia cache.**

---

## 8. Queues / outbox

| Ítem | Resultado | Evidencia |
|------|-----------|-----------|
| `QUEUE_CONNECTION` | `sync` | local |
| Tabla `jobs` | no existe | Schema |
| `failed_jobs` | 0 | count |
| `restaurante_side_effects` | 28 total, **todas done**, 0 pending | SQL |
| Retries job | `$tries=3`, backoff 5/30/60, `ShouldBeUnique` 120s | código |
| `afterCommit` dispatch | Sí | `RestauranteSideEffectDispatcher` |
| Prod esperado | Redis queue + worker (doc Fase 5) | **NO MEDIDO** en prod |
| Acumulación pending | No en local | CONFIRMADO local; prod **NO MEDIDO** |
| Config `restaurante.side_effects_*` | Defaults por 2º arg (cache config stale) | ver §12 |

Privacidad logs: `restaurante.side_effect.comanda_ticket_ready` con `id_empresa`, `comanda_id` únicamente.

---

## 9. Realtime

| Ítem | Resultado |
|------|-----------|
| Eventos | `MapaMesasChanged`, `CocinaComandasChanged` (`$afterCommit=true`) |
| `BROADCAST_CONNECTION` | `log` (local) |
| `config('restaurante.realtime_enabled', true)` | default true (archivo no en cache) |
| FE `environment.restauranteRealtime.enabled` | **false** |
| Reverb package | No instalado (Fase 6) |
| SoT | HTTP GET refresh |
| Errores WS | N/A (disabled); publish failures → `Log::warning` si fallan |
| Latencia realtime | **NO MEDIDO** |

**No se instaló Reverb.**

---

## 10. Nginx / PHP-FPM / MariaDB

| Componente | Métrica | Resultado | Evidencia | Riesgo |
|------------|---------|-----------|-----------|--------|
| Nginx | request_time / RPS | **NO MEDIDO** | — | — |
| PHP-FPM | active workers | **NO MEDIDO** | — | — |
| MariaDB 10.11 prod | slow log / QPS | **NO MEDIDO** | Entorno = MySQL 9.5 local | Revalidar EXPLAIN en prod |
| MySQL local | slow_query_log | OFF | SHOW VARIABLES | Sin captura lenta |
| Contención multi-módulo | — | **NO MEDIDO** | — | POTENCIAL (Fase 9) |

---

## 11. Slow queries

| Fuente | Estado |
|--------|--------|
| App slow query log | No existe canal dedicado |
| DB `slow_query_log` | OFF local |
| Queries Restaurante &gt;1s observadas | **NO MEDIDO** / no capturadas |
| Candidatos a vigilar (código, no tiempo) | `GET /comandas` unbounded; `GET /reservas` unbounded; joins cocina con `withTrashed` |

---

## 12. Riesgos confirmados

| # | Hallazgo | Evidencia |
|---|----------|-----------|
| 1 | Observabilidad HTTP Restaurante insuficiente para Peak | Sin APM; matrices HTTP = NO MEDIDO |
| 2 | `config` cacheado sin clave `restaurante` | `config:show restaurante` error; `bootstrap/cache/config.php` sin key |
| 3 | `GET /comandas` sin LIMIT (diseño) | Código + filas `listo`/`pendiente` locales |
| 4 | Slow query log DB apagado en entorno medido | SHOW VARIABLES |

---

## 13. Riesgos potenciales

| # | Riesgo | Evidencia | ¿Afirmar capacidad? |
|---|--------|-----------|---------------------|
| 1 | Cocina degrada al crecer histórico `listo` | Código + Fase 7 | No — latencia **NO MEDIDO** |
| 2 | Reservas sin paginar | Código | No a volumen actual |
| 3 | Pedidos list + detalles | Código | No |
| 4 | Contención MariaDB por otros módulos | Arquitectura compartida | **NO MEDIDO** |
| 5 | Outbox `done` crece sin cleanup | 28 done; UNIQUE eterna | Ops futuro |
| 6 | Env `RESTAURANTE_*` ignorados con config cache | Cache stale | Ops deploy |

---

## 14. Hallazgos (formato obligatorio)

### H1 — Sin telemetría HTTP Restaurante
- **EVIDENCIA:** no APM; no middleware timing; access logs no analizados  
- **IMPACTO:** no se puede validar umbrales §14 ni regresiones p95  
- **RECOMENDACIÓN:** instrumentación mínima (propuesta §15) **o** Nginx `request_time` + dashboard  
- **FASE PROPUESTA:** post-aprobación / soporte a Fase 12  

### H2 — Config cache sin `restaurante.php`
- **EVIDENCIA:** `Configuration file or key restaurante does not exist`; defaults vía 2º arg  
- **IMPACTO:** no se pueden apagar side_effects/realtime por env sin rebuild config  
- **RECOMENDACIÓN:** `php artisan config:clear` (o `config:cache` tras deploy que incluya el archivo)  
- **FASE PROPUESTA:** ops inmediata (no es optimización de query)  

### H3 — GET /comandas sin límite (reafirmado)
- **EVIDENCIA:** `ComandaController@index` → `->get()`; estados no terminales  
- **IMPACTO:** payload cocina crece con histórico  
- **RECOMENDACIÓN:** estado `servido` + filtro / ventana / límite — **no implementar en F10**  
- **FASE PROPUESTA:** hardening operativo autorizado aparte  

### H4 — GET /reservas sin paginación (reafirmado)
- **EVIDENCIA:** código  
- **IMPACTO:** bajo hoy (1 fila); medio a 12m+  
- **RECOMENDACIÓN:** paginar o filtrar fecha por defecto  
- **FASE PROPUESTA:** cuando se autorice cambio de contrato/UX  

### H5 — Medición solo local ≠ prod MariaDB 10.11
- **EVIDENCIA:** VERSION local 9.5.0  
- **IMPACTO:** EXPLAIN/cardinality pueden diferir  
- **RECOMENDACIÓN:** repetir EXPLAIN + slow log en staging/prod  
- **FASE PROPUESTA:** antes de Peak (12/13)  

---

## 15. Instrumentación realizada / propuesta

### Realizada en Fase 10
**Ninguna** (sin cambios de código). Medición vía EXPLAIN, conteos SQL, ping Redis, lectura logs/config.

### Propuesta mínima reversible (NO implementada — requiere autorización)

1. Middleware `RestauranteRequestMetrics` en prefijo `api/restaurante`:  
   - log `INFO` con `endpoint`, `method`, `status`, `duration_ms`, `id_empresa` (si auth), `request_id`  
   - **sin** body, Authorization, JWT, detalles pedido  
2. Alternativa cero-código: habilitar en Nginx `log_format` con `$request_time` y filtrar `/api/restaurante`  
3. MariaDB 10.11: `slow_query_log=ON`, `long_query_time` razonable (p.ej. 0.5–1s) en staging  

Overhead esperado: bajo si sampling o solo rutas resto; no toca integridad.

---

## 16. Cambios de código

| Cambio | Estado |
|--------|--------|
| PHP / Angular / migraciones / índices | **Ninguno** |
| Optimizaciones | **Ninguna** |
| `FASE10_REPORT.md` | **Creado** |
| Garantías F1–9 | **Intactas** |

---

## 17. Tests antes/después

| Momento | Resultado |
|---------|-----------|
| Antes | **45 passed** (176 assertions) |
| Después | **45 passed** (sin cambios código) |

`git status` app: solo eliminación Fase 8 del legacy (sin churn F10).

---

## 18. Recomendaciones

1. **Ops:** refrescar config cache para cargar `restaurante.php`.  
2. **Antes de Peak:** Nginx/FPM métricas + slow log MariaDB 10.11 + (opcional) middleware timing.  
3. **No optimizar** cocina/reservas/índices hasta tener p95 o evidencia slow log.  
4. Mantener logs side_effect con IDs únicamente (ya correcto).  
5. En prod: queue Redis + worker; monitorear `pending`/`failed` outbox (hoy local sync+done).

---

## 19. Qué debe medirse en Fase 11 / 12 / 13

| Fase | Medición |
|------|----------|
| **11** (suite) | Cobertura funcional/concurrencia — no sustituye p95 |
| **12** Load k6 | p50/p95/p99 HTTP endpoints críticos; error rate; RPS |
| **13** Peak / capacity | Umbrales §14; PHP-FPM; MariaDB; Redis; noisy neighbor reportes |
| Pre-12 | EXPLAIN en MariaDB 10.11; slow log; opcional middleware |

---

## 20. Límites de la medición

- Entorno: **local**, `APP_ENV=local`, volumen bajo, MySQL **9.5** ≠ MariaDB **10.11** prod.  
- Sin usuarios concurrentes Agro-Mall.  
- Microbench SQL ≠ latencia end-to-end (PHP, auth, JSON, red, FE).  
- Cache probe artificial (payload stub).  
- Queue `sync` no representa workers Redis prod.  
- Realtime deshabilitado en FE.  
- Hipótesis de contención multi-módulo **no** elevadas a CONFIRMADO.

---

## Correlación Fases 1–9

| Fase | ¿Observabilidad degrada garantías? |
|------|-------------------------------------|
| 1 locks/idempotencia/inventario | No tocado |
| 2 API/índices | Índices verificados vía EXPLAIN; no alterados |
| 3 cache mapa | Auditado; no modificado |
| 4 FE OnPush/Idempotency-Key | No tocado |
| 5 outbox | Contado/log; no cleanup |
| 6 realtime hint | Auditado; no Reverb |
| 7 crecimiento | Riesgos cocina/reservas reafirmados sin “fix” |
| 8 legacy | Eliminación intacta |
| 9 POS vs reportes | Sin agregar reportes pesados |

---

## Criterios de completitud

- [x] Observabilidad existente auditada  
- [x] Métricas disponibles vs NO MEDIDO diferenciadas  
- [x] Medido lo posible en el entorno  
- [x] HTTP / SQL / Redis / queues / outbox / realtime / infra revisados  
- [x] Riesgos Fase 9 verificados (sin optimizar)  
- [x] Sin optimizaciones no autorizadas  
- [x] Integridad F1–9 intacta  
- [x] Tests 45/45  
- [x] `FASE10_REPORT.md` creado  
- [x] No Fase 11+  

---

**FASE 10 COMPLETADA — DETENERSE — ESPERANDO APROBACIÓN PARA FASE 11**
