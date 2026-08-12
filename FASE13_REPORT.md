# FASE 13 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-11  
**Plan:** Peak / Capacity — medición controlada  
**Predecesora:** Fase 12 (baseline light ≤10 VUs)  
**Estado:** Fase 13 — **COMPLETADA DENTRO DEL ALCANCE**  
**Siguiente:** **DETENERSE** — no iniciar Fase 14 ni optimizaciones  
**Commit:** no automático

---

## 1. Resumen ejecutivo

Se midió capacidad observada en entorno **LOCAL** (`php artisan serve` + MySQL 9.5) con perfil `STAGE_PROFILE=peak` hasta **50 VUs**, más probes steady a VU fijos (mapa/mixed).

| Resultado | Valor |
|-----------|-------|
| Entorno | **LOCAL** — `http://127.0.0.1:8000` (no producción) |
| Dataset | emp **813**: 16 mesas, 1 comanda, 0 pedidos, 0 reservas, 1 sesión, 37 side_effects |
| Peak | ejecutado completo 1→50 VUs (+ cool-down) en baseline, mapa, cocina, reservas, pedidos, sesión, mixed |
| Mutaciones | **fuera del Peak principal — NO EJECUTADO** |
| Error rate lecturas (peak) | **0%** en todos los escenarios |
| p50 / p90 / p95 / **p99** / max | **MEDIDOS** (harness `summaryTrendStats`) |
| RPS máximo sostenible observado (aprox.) | **~27–30 RPS** (mapa/mixed plateau ≈30–40 VUs) |
| Saturación | **SÍ — evidencia** de saturación de runtime single-thread (`artisan serve`) ~**30 VUs** (RPS deja de subir; p95/p99 crecen) |
| Código productivo | **sin cambios** |
| Suite Feature Restaurante | **50 passed / 198 assertions** |

**Etiqueta:** capacidad observada = **LOCAL — NO REPRESENTATIVA DE PROD** (no PHP-FPM/Nginx/MariaDB 10.11).

---

## 2. Objetivo

Determinar experimentalmente capacidad máxima observable, degradación de p95/p99, error rate, RPS sostenible, comportamiento 10–50 VUs, mixed vs single-endpoint, y señales de saturación — **sin** optimizar el producto.

---

## 3. Entorno

| Ítem | Valor |
|------|-------|
| `BASE_URL` | `http://127.0.0.1:8000` |
| Runtime | `php artisan serve` (single-process) |
| DB | MySQL **9.5** local (`smartpyme_prod` = nombre local, **no** remota) |
| Auth | JWT efímero `/tmp/sp_rest_k6_token.txt` (no en git); user 2210 / emp 813 |
| k6 | v2.2.0 |
| Producción | **NO usada** |

---

## 4. Seguridad de la prueba

- Verificado: `BASE_URL` = loopback local.
- Bloqueo explícito de hosts productivos (`api.smartpyme.site` y afines).
- **No** se ejecutó carga contra producción.
- Tokens/JWT no commitados; results JSON gitignored.

---

## 5. Dataset

| Recurso | Cardinalidad |
|---------|-------------:|
| mesas | 16 |
| comandas | 1 |
| pedidos | 0 |
| reservas | 0 |
| sesiones | 1 (`SESION_ID=71`) |
| `restaurante_side_effects` | 37 |
| outbox_events | NO_TABLE |

**Igual que Fase 12** (no se generó dataset sintético mayor). La capacidad observada está **limitada por dataset pequeño** para cocina/reservas/pedidos (payloads chicos no representan histórico Fase 7/9).

---

## 6. Herramientas

| Herramienta | Uso |
|-------------|-----|
| k6 scripts | `load-tests/restaurante/*.js` (reuso F12) |
| Docs | `LOAD_TEST_RESTAURANTE.md` |
| Results | `load-tests/restaurante/results/f13-*.json` (gitignored) |
| Harness | `summaryTrendStats` + `STAGE_PROFILE=peak\|fixed` |

---

## 7. Perfil de carga

`STAGE_PROFILE=peak`:

| Stage | Duración | Target VUs |
|-------|----------|------------:|
| ramp | 30s | 1 |
| | 30s | 5 |
| | 60s | 10 |
| | 60s | 20 |
| | 60s | 30 |
| | 60s | 40 |
| | 60s | 50 |
| stabilize | 60s | 50 |
| cool-down | 30s | 30 |
| | 30s | 10 |
| | 30s | 0 |

Adicional (medición): `STAGE_PROFILE=fixed` — 45s steady @10/20/30/40/50 VUs para **mapa** y **mixed** (latencia por nivel de VU).

Baseline: 1 VU / 30s.

---

## 8. Escenarios

| Escenario | Estado |
|-----------|--------|
| A baseline | EJECUTADO |
| B mapa `GET /mesas` | EJECUTADO (peak + steady) |
| C cocina `GET /comandas` | EJECUTADO (peak) |
| D reservas `GET /reservas` | EJECUTADO (peak) |
| E pedidos `GET /pedidos?paginate=10` | EJECUTADO (peak) |
| F sesión `GET /sesiones-mesa/{id}` | EJECUTADO (peak) |
| G mixed (40/25/15/10/10) | EJECUTADO (peak + steady) |
| Mutaciones | **NO EJECUTADO** (fuera del Peak principal) |

Stop criteria (>5% errores, 5xx masivos, etc.): **no disparados** (error rate 0%). Peak completó hasta 50 VUs.

---

## 9. Resultados globales (peak completo, agregado del run)

| Escenario | VUs máx | Requests | RPS medio | p50 ms | p90 ms | p95 ms | p99 ms | max ms | Error rate | Checks fail |
|-----------|--------:|---------:|----------:|-------:|-------:|-------:|-------:|-------:|-----------:|------------:|
| baseline | 1 | 53 | 1.75 | 69.5 | 81.6 | 84.3 | **95.9** | 98.5 | 0% | 0 |
| mapa | 50 | 10117 | 19.82 | 762 | 1551 | 2088 | **4223** | 7469 | 0% | 0 |
| cocina | 50 | 7932 | 15.54 | 1101 | 2360 | 2969 | **5579** | 8865 | 0% | 0 |
| reservas | 50 | 7205 | 14.12 | 375 | 3701 | 5720 | **16140** | 27336 | 0% | 0 |
| pedidos | 50 | 6961 | 13.64 | 944 | 3263 | 4877 | **7290** | 8863 | 0% | 0 |
| sesión | 50 | 10739 | 21.05 | 744 | 1458 | 1606 | **2279** | 3206 | 0% | 0 |
| mixed | 50 | 11984 | 23.49 | 569 | 1185 | 1254 | **1441** | 1659 | 0% | 0 |

HTTP status: **200** en checks (fails=0). Distribución 4xx/5xx: **no observada** en métricas k6.

---

## 10. Resultados por endpoint

Fuente: escenario dedicado peak (agregado). Mixed no exportó breakdown por tag en summary (metric custom global).

| Endpoint | Escenario | Requests | RPS | p50 | p95 | p99 | max | Error | Payload (bytes med) |
|----------|-----------|---------:|----:|----:|----:|----:|----:|------:|--------------------:|
| GET `/mesas` | mapa | 10117 | 19.82 | 762 | 2088 | 4223 | 7469 | 0% | 4286 |
| GET `/comandas` | cocina | 7932 | 15.54 | 1101 | 2969 | 5579 | 8865 | 0% | 2688 |
| GET `/reservas` | reservas | 7205 | 14.12 | 375 | 5720 | 16140 | 27336 | 0% | 2 |
| GET `/pedidos?paginate=10` | pedidos | 6961 | 13.64 | 944 | 4877 | 7290 | 8863 | 0% | 591 |
| GET `/sesiones-mesa/{id}` | sesión | 10739 | 21.05 | 744 | 1606 | 2279 | 3206 | 0% | 2886 |

Mixed payload med ≈ 2886 B (mezcla); p95 bytes 4286 (= mesas).

---

## 11. p50 / p95 / p99

### Peak agregado (incluye ramp + cool-down)

p99 **real** exportado (no sustituido por max). Bajo peak, p95/p99 están dominados por stages altos de VU.

### Steady fijo (mapa / mixed) — latencia por VU

| Escenario | VUs | RPS | p50 | p95 | p99 | max | Notas |
|-----------|----:|----:|----:|----:|----:|----:|-------|
| mapa | 10 | 0.89* | 59 | 240 | 527 | **991047*** | *contaminado: duración efectiva ≫45s / outlier extremo |
| mapa | 20 | 29.94 | 253 | 382 | 636 | 716 | usable |
| mapa | 30 | 23.51 | 729 | 1741 | 2492 | 2591 | degradación clara |
| mapa | 40 | 29.76 | 906 | 1082 | 1175 | 1406 | RPS flat; cola alta |
| mapa | 50 | 29.98 | 1240 | 1474 | 1564 | 1618 | RPS flat; p50↑ |
| mixed | 10 | 0.90* | 52 | 133 | 332 | **921112*** | *mismo tipo de contaminación |
| mixed | 20 | 2.93* | 493 | 1814 | 3975 | 230267* | *parcialmente contaminado |
| mixed | 30 | 26.20 | 570 | 1376 | 2522 | 3113 | usable |
| mixed | 40 | 29.52 | 872 | 1063 | 1236 | 1400 | plateau RPS |
| mixed | 50 | 27.30 | 1204 | 2588 | 3376 | 3641 | p95/p99 peores que @40 |

\*Steady @10 (y parcialmente @20 mixed) no son confiables para comparar con F12: outliers ~15 min tras la batería peak (posible cola/`artisan serve` recuperándose). Preferir F12@10 y peak-stage RPS.

---

## 12. RPS / throughput

### Capacidad observada

- **RPS máximo sostenible observado** (plateau): ~**27–30 RPS** en mapa/mixed cuando VUs ≥ ~30.
- Añadir VUs de 30→50 **no** aumenta throughput de forma proporcional → cola/latencia.

### RPS aproximado por stage (progreso k6, 1 req/iter)

| Target VU | mapa | cocina | reservas | pedidos | sesión | mixed |
|----------:|-----:|-------:|---------:|--------:|-------:|------:|
| 10 | 13.8 | 14.6 | 15.6 | 9.8 | 15.2 | 13.5 |
| 20 | 24.4 | 14.4 | 29.3 | 8.9 | 20.9 | 25.9 |
| 30 | 27.8 | 12.9 | 21.3 | 17.9 | 27.2 | 30.6 |
| 40 | 27.5 | 22.9 | **8.7** | 10.0 | 26.8 | 30.6 |
| 50 | 27.3→20.8† | 18.6→17.6† | 3.4→11.7† | 15.0→17.3† | 25.9→26.7† | 30.2→30.2† |

† segundo minuto a 50 VUs (stabilize) cuando aplica.

**Reservas:** colapso de RPS a 40–50 VUs (hasta ~3.4) pese a payload vacío (2 B) → evidencia fuerte de contención en runtime, no de payload.

---

## 13. Error rate

| Escenario peak | Error rate |
|----------------|------------|
| todos | **0.00%** |

No se alcanzó criterio de stop por errores (>5%).

---

## 14. Payload

| Endpoint | Bytes (med / p95) | Enviado |
|----------|-------------------|---------|
| mesas | 4286 / 4286 | GET (headers auth) |
| comandas | 2688 / 2688 | GET |
| reservas | 2 / 2 | GET |
| pedidos | 591 / 591 | GET |
| sesión | 2886 / 2886 | GET |

Dataset chico → payloads no estresan serialización/histórico.

---

## 15. Infraestructura

| Métrica | Estado |
|---------|--------|
| Load average (pre) | ~7.1 / 5.1 / 4.1 (máquina ya cargada) |
| Load average (post) | ~2.0 / 3.0 / 4.5 |
| CPU detallado app | **NO MEDIDO** (sin sample continuo) |
| RAM detallada | **NO MEDIDO** (memory_pressure parcial / no series) |

---

## 16. PHP-FPM / Nginx

| Componente | Estado |
|------------|--------|
| PHP-FPM active/idle/max/backlog | **NO MEDIDO** / **NO DISPONIBLE** (`artisan serve`) |
| Nginx request time / connections | **NO MEDIDO** / **NO DISPONIBLE** |

---

## 17. MariaDB / MySQL

| Métrica | Estado |
|---------|--------|
| Versión local | MySQL 9.5 (no MariaDB 10.11) |
| Threads / Questions / slow / locks | **NO MEDIDO** (cliente `mysql` CLI no disponible en path de prueba) |
| Deadlocks bajo peak | **NO MEDIDO** en DB; HTTP sin errores |

---

## 18. Redis

| Métrica | Estado |
|---------|--------|
| ping / memoria / hit-miss / conexiones | **NO MEDIDO** (`redis-cli` no disponible en path) |

Mapa usa cache TTL 3s (conocido F12); hit rate bajo peak = **NO MEDIDO**.

---

## 19. Logs / errores

- Laravel log: sin ráfaga de ERROR/SQLSTATE/502/503/504 atribuible a la ventana peak en el muestreo post-prueba.
- k6: 0 failed HTTP; checks fails = 0.
- Suite tras load: primer intento `artisan test` abortó por **memory exhausted 128M** al cargar `routes-v7.php` (interferencia/post-carga). Reintento con `php -d memory_limit=512M vendor/bin/phpunit` → **50/198 OK**.

No se registraron JWT ni payloads sensibles.

---

## 20. Punto de saturación

### SATURATION SIGNAL — CONFIRMADO (entorno local / artisan serve)

**Evidencia:**

1. **Throughput plateau:** mapa/mixed ~27–30 RPS desde ~30 VUs; 40–50 VUs no aumentan RPS proporcionalmente.
2. **Latencia:** steady mapa p50 253→729→906→1240 ms (20→50 VUs); p95 sube a >1s desde ~30 VUs.
3. **Reservas:** RPS cae de ~29 (@20) a ~3.4 (@50 ramp) con payload vacío → cola/runtime, no dataset.
4. Runtime = **single-threaded** `php artisan serve` → saturación de aplicación/proceso HTTP es la explicación más directa.

**Último stage “estable” relativo:** ~**20–30 VUs** aún con RPS creciente o cerca del plateau; a partir de **~30 VUs** la señal de saturación es clara.

**NO decir:** “soporta 50 usuarios”.  
**Sí decir:** capacidad observada bajo estas condiciones; saturación del runtime local alrededor de ~30 VUs concurrentes de k6.

---

## 21. Thresholds del plan (referencia)

| Threshold plan | Resultado bajo peak local |
|----------------|---------------------------|
| mapa/sesión p95 &lt; 500 ms | **EXCEDIDO** en peak agregado y steady ≥30 VUs; F12@10 cumplía |
| items/comandas p95 &lt; 1 s | **EXCEDIDO** en peak cocina (p95 ≈ 2.97 s) |
| facturación p99 &lt; 3 s | **NO MEDIDO** (sin mutaciones/facturación en Peak) |
| error rate &lt; 1% | **CUMPLIDO** (0%) |

**Capacidad aprobada:** **NO DECLARADA**.  
Umbrales = referencia; resultado = capacidad observada / saturación local.

---

## 22. Hallazgos

### H1 — Saturación de `artisan serve` ~30 VUs (CONFIRMADO)

- **Evidencia:** plateau RPS + p95/p99 crecientes; reservas RPS collapse.
- **Impacto:** p95 mapa peak ~2.1 s; no representa PHP-FPM.
- **Riesgo:** extrapolar VUs k6 a “usuarios reales” o a prod.
- **Recomendación:** repetir Peak en staging con Nginx+PHP-FPM+MariaDB 10.11.
- **Fase propuesta:** medición infra real (post-aprobación).

### H2 — p99 ahora medible en harness (CONFIRMADO)

- **Evidencia:** `summaryTrendStats` incluye `p(99)`.
- **Impacto:** cierra gap F12 (p99 NO MEDIDO).

### H3 — Dataset pequeño limita lectura de capacidad de negocio (CONFIRMADO / arrastre F12)

- **Evidencia:** 1 comanda / 0 reservas / 0 pedidos.
- **Impacto:** no valida riesgo `/comandas` sin LIMIT con histórico.
- **Recomendación:** dataset sintético aislado antes de afirmar cuellos de query.
- **Fase propuesta:** prep datos (sin cambiar contratos) bajo aprobación.

### H4 — Steady @10 contaminado post-peak (CONFIRMADO medición)

- **Evidencia:** max ~991s / RPS &lt;1 pese a target 45s.
- **Impacto:** no usar esos puntos para comparar vs F12; usar F12@10.

---

## 23. Riesgos confirmados

1. Runtime local single-thread satura ~30 VUs k6 (esta corrida).  
2. Thresholds plan de latencia **no se cumplen** bajo peak local ≥30 VUs.  
3. Resultados **no** extrapolables a producción.

---

## 24. Riesgos potenciales

1. `/comandas` sin LIMIT / `/reservas` sin paginación con histórico grande (F7/F9) — **POTENCIAL**, dataset no lo demuestra.  
2. PHP-FPM/Nginx/MariaDB/Redis bajo carga real — **POTENCIAL / NO MEDIDO**.  
3. Outbox/queue bajo load — **POTENCIAL / NO MEDIDO** (solo lecturas).  
4. Contención CPU máquina host (load pre ~7) pudo influir — **POTENCIAL**.

---

## 25. Elementos NO MEDIDOS

- PHP-FPM, Nginx, MariaDB 10.11, Redis hit rate  
- p99 de facturación bajo load  
- Mutaciones concurrentes masivas  
- Staging/QA autorizado  
- Capacidad productiva  
- Series temporales de latencia por segundo (solo agregado + steady + RPS por stage)  
- Breakdown HTTP tagged por endpoint dentro de mixed  

---

## 26. Comparación Fase 12 vs Fase 13

| Métrica | F12 @10 VUs (light) | F13 peak @~10* | F13 steady @20 | @30 | @40 | @50 |
|---------|---------------------|----------------|----------------|-----|-----|-----|
| **mapa RPS** | 10.31 | ~13.8 | 29.9 | 23.5 | 29.8 | 30.0 |
| **mapa p50** | 46.6 | (agg peak 762) | 253 | 729 | 906 | 1240 |
| **mapa p95** | 98.3 | (agg 2088) | 382 | 1741 | 1082 | 1474 |
| **mapa p99** | NO MEDIDO | (agg 4223) | 636 | 2492 | 1175 | 1564 |
| **mapa errors** | 0% | 0% | 0% | 0% | 0% | 0% |
| **cocina RPS** | 10.16 | ~14.6 | — | — | — | (peak mean 15.5) |
| **cocina p95** | 107 | (agg 2969) | — | — | — | — |
| **reservas p95** | ~93 (F12) | (agg 5720) | — | — | — | — |
| **pedidos p95** | ~95 (F12) | (agg 4877) | — | — | — | — |
| **sesión p95** | ~99 (F12) | (agg 1606) | — | — | — | — |
| **mixed RPS** | 9.24 | ~13.5 | (contam.) | 26.2 | 29.5 | 27.3 |
| **mixed p95** | 100 | (agg 1254) | — | 1376 | 1063 | 2588 |
| **mixed errors** | 0% | 0% | 0% | 0% | 0% | 0% |

\*RPS @10 en F13 = aproximación por stage del ramp peak (no run aislado light).

**Degradación significativa:** sí, al subir de 10→30+ VUs en local: p95 pasa de ~100 ms (F12) a multi-segundo en agregados peak / steady ≥30.

---

## 27. Cambios realizados

Solo harness/docs/resultados:

- `load-tests/restaurante/lib.js` — `SUMMARY_TREND_STATS` (avg/min/med/p90/p95/**p99**/max), `STAGE_PROFILE=peak|fixed`, `loadOptions()`
- Scripts escenario — usan `loadOptions()`
- `LOAD_TEST_RESTAURANTE.md` — documenta peak/fixed
- `FASE13_REPORT.md` — este informe
- Results JSON bajo `results/` (gitignored)

---

## 28. Cambios deliberadamente NO realizados

- Controllers / Services / Models / Migrations / queries  
- Índices, cache, Redis, PHP-FPM, Nginx, MariaDB  
- Reverb  
- LIMIT en `/comandas`, paginación en `/reservas`  
- Contratos HTTP / Angular  
- Optimizaciones de cualquier tipo  

---

## 29. Limitaciones

1. Local ≠ prod.  
2. Dataset pequeño.  
3. Peak agregado mezcla todos los stages → p95/p99 globales sesgados al alza.  
4. Steady @10 contaminado post-peak.  
5. Infra DB/Redis/FPM **NO MEDIDO**.  
6. VUs k6 ≠ usuarios humanos concurrentes.

---

## 30. Recomendaciones

1. **No** optimizar código en esta fase (ya cumplido).  
2. Repetir Peak en staging con PHP-FPM+Nginx+MariaDB 10.11 cuando exista URL autorizada.  
3. Preparar dataset histórico sintético aislado antes de atribuir latencia a queries.  
4. Usar `STAGE_PROFILE=fixed` + series por VU para comparar entornos.  
5. Esperar aprobación explícita antes de Fase 14 / cualquier fix.

---

## 31. Relación con Fases 1–12

| Fase | Aporte a F13 |
|------|----------------|
| 1–6 | integridad / cache / outbox — no alterados |
| 7/9 | riesgo crecimiento datos — no demostrado aquí (dataset chico) |
| 10 | observabilidad; F13 aporta p50/p95/**p99**/RPS bajo peak local |
| 11 | suite 50/198 — revalidada |
| 12 | scripts + baseline @10 — reutilizados y extendidos |

---

## 32. Criterios de completitud

- [x] entorno seguro verificado  
- [x] no carga contra producción  
- [x] peak progresivo hasta límite seguro (50 VUs; stop no requerido)  
- [x] p50 / p95 / **p99** medidos  
- [x] RPS / error rate medidos  
- [x] resultados por endpoint  
- [x] mixed medido  
- [x] comparación vs Fase 12  
- [x] thresholds documentados (cumplido/excedido)  
- [x] punto de saturación identificado (señal ~30 VUs local)  
- [x] infraestructura medida cuando posible / NO MEDIDO marcado  
- [x] logs/errores revisados  
- [x] sin optimizaciones de producto  
- [x] suite Restaurante 50 / 198  
- [x] `FASE13_REPORT.md` creado  
- [x] sin commit automático  

---

## 33. Siguiente paso

**DETENERSE.** No iniciar Fase 14. No optimizar. No commit automático. Esperar aprobación del usuario.

**FASE 13 COMPLETADA — DETENERSE — ESPERANDO APROBACIÓN PARA LA SIGUIENTE FASE**
