# FASE 12 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-11  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §14 (Load test k6 + métricas) — **solo medición controlada**  
**Predecesoras:** Fase 1–11 cerradas y validadas  
**Estado:** Fase 12 — **COMPLETADA DENTRO DEL ALCANCE** (baseline load medido en local)  
**Siguiente:** **DETENERSE** — no iniciar Fase 13 (Peak/Capacity)  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen ejecutivo

Se crearon scripts k6 reutilizables y se ejecutó una carga **controlada ligera** (`STAGE_PROFILE=light`, máx. **10 VUs**) contra API local `php artisan serve` + DB local MySQL 9.5.

| Resultado | Valor |
|-----------|-------|
| Entorno | **LOCAL** — `http://127.0.0.1:8000` |
| Dataset | empresa **813**: 16 mesas, 1 comanda, 0 pedidos, 0 reservas |
| Escenarios | baseline, mapa, cocina, reservas, pedidos, sesión, mixed |
| Mutaciones | **NO EJECUTADO** |
| Peak / Capacity | **NO EJECUTADO** (Fase 13) |
| Error rate HTTP | **0%** en todos los escenarios ejecutados |
| p50 / p95 | **MEDIDOS** (local) |
| p99 | **NO MEDIDO** (summary k6 v2 sin `p(99)` por defecto) |
| Código productivo | **sin cambios** |
| Optimizaciones | **ninguna** |

**Etiqueta obligatoria:** resultados = **LOCAL — NO REPRESENTATIVO DE PROD** (MySQL 9.5 ≠ MariaDB 10.11; `artisan serve` ≠ PHP-FPM/Nginx; dataset pequeño).

---

## 2. Objetivo

Establecer **baseline de load** (throughput, latencias, errores, payload) sin buscar el límite del sistema.

---

## 3. Entorno de prueba

| Ítem | Valor |
|------|-------|
| Prioridad usada | **local** (staging/QA no disponible / `api.smartpyme.test` no resolvía) |
| `BASE_URL` | `http://127.0.0.1:8000` |
| Runtime | `php artisan serve` |
| DB | MySQL **9.5.0**, schema local `smartpyme_prod` (nombre confuso; **no** es prod remota) |
| Auth | JWT efímero en `/tmp` (no commit); usuario con módulo + permisos |
| Producción remota | **NO usada** |

---

## 4. Dataset utilizado

Empresa bajo prueba (IDs no sensibles de negocio más allá de conteos):

| Recurso | Cardinalidad |
|---------|-------------:|
| mesas | 16 |
| comandas (filtro cocina) | 1 |
| pedidos | 0 |
| reservas | 0 |
| sesión activa usada | id=71 (GET) |

**Importante:** cocina/reservas/pedidos con pocas filas → latencias **no** extrapolan a histórico Agro-Mall (Fase 7/9).

---

## 5. Infraestructura disponible

| Componente | Estado en esta corrida |
|------------|------------------------|
| PHP-FPM | **NO MEDIDO** (artisan serve) |
| Nginx | **NO MEDIDO** / no en path |
| MariaDB 10.11 | **NO MEDIDO** |
| Redis hit rate | **NO MEDIDO** (mapa puede usar redis local; sin métricas) |
| Queue/outbox bajo load | **NO MEDIDO** (solo lecturas) |

---

## 6. Herramientas

| Herramienta | Detalle |
|-------------|---------|
| k6 | **v2.2.0** (instalado vía Homebrew para esta fase) |
| Scripts | `load-tests/restaurante/*.js` |
| Docs | `LOAD_TEST_RESTAURANTE.md` |
| Resultados | `load-tests/restaurante/results/*.json` (gitignored; tokens redactados) |

No existía k6/load-tests previo en el repo (auditoría previa).

---

## 7. Escenarios ejecutados

| Escenario | Script | Estado |
|-----------|--------|--------|
| A Health/Baseline | `restaurante-baseline.js` | **EJECUTADO** |
| B Mapa | `restaurante-mapa.js` | **EJECUTADO** |
| C Cocina | `restaurante-cocina.js` | **EJECUTADO** |
| D Reservas | `restaurante-reservas.js` | **EJECUTADO** |
| E Pedidos | `restaurante-pedidos.js` | **EJECUTADO** |
| F Sesión | `restaurante-sesion.js` | **EJECUTADO** (SESION_ID=71) |
| Mixed reads | `restaurante-mixed.js` | **EJECUTADO** |
| G Mutaciones | `restaurante-mutations.js` | **NO EJECUTADO** (`ENABLE_MUTATIONS` unset) |

---

## 8. Modelo de carga

`STAGE_PROFILE=light` (recomendado para artisan local):

| Stage | Duración | Target VUs |
|-------|----------|------------:|
| 0 | 20s | 1 |
| 1 | 40s | 5 |
| 2 | 40s | 10 |
| 3 | 30s | 5 |
| 4 | 20s | 0 |

Baseline: 1 VU / 30s.

**No** se ejecutó el profile full hasta 30 VUs (evitar stress/self-load sobre artisan). Full stages quedan documentados en scripts para staging.

Think time: 0.2–0.7s aleatorio entre requests.

---

## 9. Distribución de tráfico (mixed)

- 40% `GET /mesas`
- 25% `GET /comandas`
- 15% `GET /pedidos?paginate=10`
- 10% `GET /reservas`
- 10% `GET /sesiones-mesa/{id}` (o fallback mesas)

---

## 10. Thresholds del plan

Del plan §14 (referencia; **no** usados como PASS/FAIL de capacidad):

| Métrica plan | Objetivo plan | Esta corrida |
|--------------|---------------|--------------|
| GET mapa/sesión p95 | &lt; 500ms | mapa p95 ≈ **98 ms** (local, dataset chico) |
| items/comandas p95 | &lt; 1s | cocina p95 ≈ **107 ms** (1 comanda) |
| facturación p99 | &lt; 3s | **NO EJECUTADO** (mutaciones off) |
| error rate | &lt; 1% | **0%** en lecturas |

Otros endpoints: **threshold no definido** en el plan.

---

## 11. Resultados globales

| Escenario | VUs máx | Requests | RPS | p50 (med) ms | p90 ms | p95 ms | p99 | Max ms | Error rate |
|-----------|--------:|---------:|----:|-------------:|-------:|-------:|-----|-------:|-----------:|
| baseline | 1 | 57 | 1.87 | 61.5 | 77.7 | 81.1 | NO MEDIDO | 97.4 | 0.00% |
| mapa | 10 | 1547 | 10.31 | 46.6 | 82.4 | 98.3 | NO MEDIDO | 347.9 | 0.00% |
| cocina | 10 | 1527 | 10.16 | 49.1 | 89.2 | 107.0 | NO MEDIDO | 250.3 | 0.00% |
| reservas | 10 | 1570 | 10.44 | 42.5 | 74.5 | 89.0 | NO MEDIDO | 261.4 | 0.00% |
| pedidos | 10 | 1568 | 10.44 | 41.4 | 72.8 | 86.8 | NO MEDIDO | 287.2 | 0.00% |
| sesion | 10 | 1514 | 10.07 | 50.4 | 93.7 | 109.5 | NO MEDIDO | 418.7 | 0.00% |
| mixed | 10 | 1389 | 9.24 | 44.4 | 81.3 | 100.0 | NO MEDIDO | 292.1 | 0.00% |

Checks funcionales (`status 200` + body non-empty): **100%** en escenarios ejecutados.

---

## 12. Resultados por endpoint

| Endpoint | Escenario principal | Requests (aprox) | p50 | p95 | Errors | Payload medio approx |
|----------|---------------------|-----------------:|----:|----:|-------:|---------------------:|
| GET `/api/restaurante/mesas` | mapa / baseline | 1547 / 57 | 46.6 / 61.5 | 98.3 / 81.1 | 0% | ~4507 B |
| GET `/api/restaurante/comandas` | cocina | 1527 | 49.1 | 107.0 | 0% | ~2909 B |
| GET `/api/restaurante/reservas` | reservas | 1570 | 42.5 | 89.0 | 0% | ~223 B (lista vacía) |
| GET `/api/restaurante/pedidos?paginate=10` | pedidos | 1568 | 41.4 | 86.8 | 0% | ~812 B (0 filas) |
| GET `/api/restaurante/sesiones-mesa/71` | sesion | ~1514 | 50.4 | 109.5 | 0% | ~3107 B |

Mixed: mezcla ponderada; p95 global ~100 ms.

---

## 13. p50 / p95 / p99

| | p50 | p95 | p99 |
|--|-----|-----|-----|
| Estado | **MEDIDO** (local) | **MEDIDO** (local) | **NO MEDIDO** (k6 summary sin `p(99)`) |
| Observación | Ver tabla §11 | Ver tabla §11 | Usar `max` solo como cota superior observada, no p99 |

---

## 14. Error rate

**0.00%** HTTP failed en todos los escenarios de lectura ejecutados.  
Checks failed: **0**.

---

## 15. Payload sizes

Medidos vía `data_received / requests` (aprox. por respuesta):

| Endpoint | Approx bytes |
|----------|-------------:|
| mesas (16) | ~4507 |
| comandas (1) | ~2909 |
| reservas (0) | ~223 |
| pedidos page vacía | ~812 |
| sesión | ~3107 |

Con histórico grande (Fase 7/9), cocina/pedidos pueden crecer — **POTENCIAL**, no confirmado aquí.

---

## 16. PHP-FPM / Nginx

**NO MEDIDO**

---

## 17. MariaDB

Prod MariaDB 10.11: **NO MEDIDO**.  
Local MySQL 9.5 bajo load de lectura: sin errores DB visibles en respuestas HTTP. Slow log: **NO MEDIDO**.

---

## 18. Redis / Cache

Hit rate: **NO MEDIDO**.  
Mapa sigue con TTL 3s (Fase 3); no se modificó estrategia.

---

## 19. Outbox / Queues

**NO MEDIDO** / no ejercitado (solo GET).

---

## 20. Integridad funcional bajo carga

- Solo lecturas → sin mutaciones masivas.
- Suite Feature Restaurante post-corrida: **50 passed / 198 assertions**.
- Mutaciones concurrentes: **NO EJECUTADO** en k6 (cubiertas en Fase 11 Feature).

---

## 21. Hallazgos

### H1 — Baseline HTTP local medido (antes Fase 10 = NO MEDIDO)
- **TIPO:** MEDIDO / CONFIRMADO (solo local)
- **EVIDENCIA:** tablas §11–12
- **IMPACTO:** cierra gap de evidencia p50/p95 en entorno local
- **RECOMENDACIÓN:** repetir en staging MariaDB 10.11 + PHP-FPM antes de Peak

### H2 — Dataset cocina/reservas/pedidos demasiado pequeño para riesgo Fase 9
- **TIPO:** POTENCIAL
- **EVIDENCIA:** 1 comanda / 0 reservas / 0 pedidos en empresa 813
- **IMPACTO:** p95 cocina ~107 ms **no** prueba crecimiento histórico
- **RECOMENDACIÓN:** dataset sintético o tenant con histórico; medir payload vs filas en Fase 13 prep

### H3 — Permisos Spatie `restaurante.*` / `pedidos.*` ausentes en DB local
- **TIPO:** CONFIRMADO (entorno)
- **EVIDENCIA:** routes referencian `permission:restaurante.ver` pero filas no existían → 403
- **ACCIÓN HARNESS:** se crearon permisos faltantes + cache Spatie clear para poder medir HTTP (no es optimización de performance)
- **RECOMENDACIÓN:** alinear seeds/migraciones de permisos en entornos de prueba

### H4 — `api.smartpyme.test` / staging no alcanzable desde el agente
- **TIPO:** NO DISPONIBLE
- **EVIDENCIA:** DNS timeout
- **IMPACTO:** solo local

### H5 — p99 no exportado por default en k6 v2
- **TIPO:** NO MEDIDO
- **RECOMENDACIÓN:** `summaryTrendStats` incl. `p(99)` en próxima corrida staging

---

## 22. Riesgos confirmados

1. Medición actual **no** valida capacidad productiva.  
2. Sin permisos sembrados, API HTTP Restaurante responde 403 en este DB local.

---

## 23. Riesgos potenciales

1. GET `/comandas` sin LIMIT con histórico grande (Fase 7/9) — no tensionado aquí.  
2. Self-load / artisan single-thread limita RPS observado (~10 rps a 10 VUs).  
3. Contención multi-módulo MariaDB — **NO MEDIDO**.

---

## 24. Elementos NO MEDIDOS

- p99 HTTP  
- Peak / Capacity / 30–50 VUs full profile  
- Mutaciones bajo load  
- PHP-FPM / Nginx / MariaDB 10.11  
- Redis hit rate  
- Outbox bajo load  
- Staging/QA  
- Contención noisy-neighbor  

---

## 25. Cambios realizados

| Cambio | Tipo |
|--------|------|
| `load-tests/restaurante/*.js` | **Creado** (scripts k6) |
| `LOAD_TEST_RESTAURANTE.md` | **Creado** |
| `FASE12_REPORT.md` | **Creado** |
| `.gitignore` | Ignorar results/logs k6 y `.env.load` |
| Código productivo Restaurante | **Ninguno** |
| Harness DB: permisos Spatie faltantes | Insertados para habilitar HTTP (documentado H3) |

---

## 26. Cambios deliberadamente NO realizados

Índices, queries, cache, Redis, PHP-FPM, Nginx, MariaDB config, Reverb, LIMIT en comandas, paginación reservas, mutaciones masivas, Peak/Fase 13, commit automático.

---

## 27. Comparación contra Fase 10

| Métrica Fase 10 | Fase 10 | Fase 12 (local light) |
|-----------------|---------|------------------------|
| HTTP p50 | NO MEDIDO | **MEDIDO** (ver §11) |
| HTTP p95 | NO MEDIDO | **MEDIDO** |
| HTTP p99 | NO MEDIDO | **aún NO MEDIDO** |
| RPS | NO MEDIDO | **MEDIDO** (~9–10 rps @10 VUs) |
| Error rate | NO MEDIDO | **0%** (lecturas) |
| Microbench SQL | MEDIDO | No confundir con HTTP (sigue válido) |

---

## 28. Limitaciones

- Local artisan + MySQL 9.5.  
- Light profile ≤10 VUs.  
- Dataset restaurante pequeño en empresa bajo prueba.  
- p99 ausente en summary.  
- Mutaciones off.  
- No afirmar “soporta N usuarios reales”.

---

## 29. Recomendaciones

1. Re-ejecutar scripts en **staging MariaDB 10.11 + PHP-FPM/Nginx** con `STAGE_PROFILE` full.  
2. Configurar `summaryTrendStats` con `p(99)`.  
3. Preparar dataset histórico sintético para cocina/pedidos antes de Fase 13.  
4. Mantener mutaciones solo en DB aislada.  
5. No optimizar endpoints por esta corrida local.

---

## 30. Relación con Fases 1–11

Integridad F1–5, cache F3, outbox F5, realtime F6, growth F7, legacy F8, reportes F9, observabilidad F10, suite F11: **no alteradas en código**. Suite Feature sigue **50/50**.

---

## 31. Criterios de completitud

- [x] k6 auditado/instalado  
- [x] entorno seguro (local) identificado; prod remota no cargada  
- [x] baseline + endpoints críticos + mixed medidos  
- [x] p50/p95/RPS/error rate/payload observados  
- [x] checks funcionales  
- [x] comparación Fase 10  
- [x] sin optimizaciones de performance en código  
- [x] sin Fase 13  
- [x] `FASE12_REPORT.md` + `LOAD_TEST_RESTAURANTE.md`  
- [x] sin secretos en git (tokens redactados; results json gitignored)  
- [x] sin commit automático  

---

## 32. Siguiente paso

**DETENERSE.**

Esperar aprobación explícita para **Fase 13 — Peak / Capacity**.

---

**FASE 12 COMPLETADA — DETENERSE — ESPERANDO APROBACIÓN PARA FASE 13**
