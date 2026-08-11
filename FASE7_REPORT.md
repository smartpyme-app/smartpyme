# FASE 7 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §9 (Histórico y crecimiento)  
**Documento analítico:** `RESTAURANTE_DATA_GROWTH.md`  
**Predecesoras:** Fase 1–6 cerradas y validadas  
**Estado:** Fase 7 — **COMPLETADA DENTRO DEL ALCANCE** (análisis / documentación)  
**Siguiente:** **DETENERSE** — no iniciar Fase 8+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen

Fase 7 inspeccionó el **estado real** del schema y consultas de Restaurante frente al crecimiento de datos. Entregable principal: `RESTAURANTE_DATA_GROWTH.md` (estimación, hot tables, índices, retención, outbox, archive, métricas).

**No se implementó código ni migraciones nuevas.** El plan §9 pide documento; no hay cambio de aplicación que sea estrictamente necesario hoy sin evidencia de presión en prod.

Baseline suite Restaurante: **45/45** antes y después (sin cambios de código).

Clasificación global:

| Etiqueta | Contenido |
|----------|-----------|
| **IMPLEMENTADO** | `RESTAURANTE_DATA_GROWTH.md`, este reporte |
| **ANALIZADO** | Tablas, índices, EXPLAIN, conteos local, side_effects, consultas |
| **RECOMENDADO** | Acotar cocina / `servido`, cleanup outbox, soft-delete purge, métricas |
| **PENDIENTE** | Ejecutar recomendaciones |
| **FUERA DE ALCANCE** | Load/Peak/k6, Fase 8+, partición, archive jobs, UX Angular |

---

## 2. Estado inicial

| Ítem | Estado |
|------|--------|
| `RESTAURANTE_DATA_GROWTH.md` | **No existía** → creado |
| `FASE7_REPORT.md` | **No existía** → creado |
| Suite Restaurante | 45 passed (baseline pre-análisis) |
| Código app | Sin cambios requeridos por §9 |
| Load tests | No ejecutados (correcto) |
| DB local | MySQL 9.5.0 / `smartpyme_prod` (conteos reales bajos) |
| Prod SoT | MariaDB 10.11.x (considerado en decisiones) |

Distribución local (muestra):

| Tabla | Filas |
|-------|------:|
| `restaurante_mesas` | 44 |
| `restaurante_sesiones_mesa` | 71 (18 abierta / 53 cerrada) |
| `orden_detalle_restaurante` | 380 (**354** soft-deleted) |
| `comandas_restaurante` | 43 |
| `pre_cuentas_restaurante` | 75 |
| `restaurante_pedidos` | 909 |
| `restaurante_pedido_detalles` | 1 413 |
| `restaurante_side_effects` | 16 (todas `done`) |
| `kardexs` | ~2.6M |

---

## 3. Tablas analizadas

Cubiertas en detalle en `RESTAURANTE_DATA_GROWTH.md` §2:

- `restaurante_mesas`, `restaurante_zonas`
- `restaurante_sesiones_mesa` (incl. UNIQUE funcional sesión activa)
- `orden_detalle_restaurante` (SoftDeletes)
- `comandas_restaurante`, `comanda_detalle_restaurante`
- `division_cuenta_restaurante`
- `pre_cuentas_restaurante`, `pre_cuenta_orden_detalle`
- `restaurante_pedidos`, `restaurante_pedido_detalles`
- `reservas_restaurante`
- `restaurante_side_effects`, `restaurante_idempotency_keys`
- Relacionado: `kardexs` (inventario plataforma)

Nombres reales confirmados: `pre_cuentas_restaurante`, `reservas_restaurante`, `division_cuenta_restaurante` (no los alias del brief).

---

## 4. Análisis de crecimiento

- Tablas **estáticas:** mesas/zonas.
- Tablas **históricas acumulativas:** sesiones, detalles, comandas, precuentas, pedidos.
- **Soft-delete** en `orden_detalle_restaurante` ya es el 93% de filas locales → crecimiento “invisible” al negocio pero real en InnoDB.
- **Outbox** (`side_effects`): crece 1:1 con tickets; `done` no se limpia.
- **Idempotency:** TTL 24h + purge oportunista `LIMIT 100` (aceptable).
- Estimación Agro-Mall (orden magnitud): cientos de miles de sesiones/año; millones de líneas de detalle a 12–24 meses — ver doc §3.

---

## 5. Hot Tables

| Severidad | Tabla | Motivo breve |
|-----------|-------|--------------|
| **CRÍTICO** | `orden_detalle_restaurante` | Volumen ítems + soft-delete |
| **CRÍTICO** | `comandas_restaurante` | `GET /comandas` sin límite + estados no drenados |
| **CRÍTICO** | `kardexs` | ~2.6M ya; escritura inventario |
| **ALTO** | `restaurante_sesiones_mesa` | Histórico cerradas |
| **ALTO** | `restaurante_pedidos` + detalles | Volumen canal |
| **MEDIO→ALTO** | `restaurante_side_effects` | UNIQUE eterna |
| **MEDIO** | precuentas / reservas | Histórico / listado sin paginar |
| **BAJO** | mesas, zonas, idempotency | |

---

## 6. Índices analizados

- Fase 2/3 índices presentes y usados en EXPLAIN local (cocina, sesión activa, side_effects pending).
- UNIQUE funcional sesiones activas: compatible con enfoque MariaDB 10.11; **validar en prod** al desplegar.
- Pedidos: `(id_empresa, fecha)` / `(id_empresa, estado)` adecuados con paginación.
- Pre-cuentas: solo `(sesion_id, estado)` — sin `id_empresa` denormalizado.
- **Ningún índice nuevo añadido en Fase 7** (sin evidencia de necesidad inmediata; evitar costo escritura especulativo).

---

## 7. Consultas analizadas

| Consulta | Hallazgo |
|----------|----------|
| `GET /mesas` | Cache 3s; N≈mesas; relaciones solo activas → **bien para crecimiento** |
| `GET /comandas` | `whereIn` estados + `orderBy created_at` + **`get()` sin límite** → **riesgo CRÍTICO** |
| `GET /pedidos` | Paginado ≤100 + scope empresa → **aceptable** |
| `GET /reservas` | Scope empresa, **sin paginar** → riesgo medio futuro |
| Sesión activa / fusión ítems | Índices Fase 3 → OK |
| Migración `servido` | En código/validación; **no aplicada** en DB local → cocina no puede drenar a terminal |

EXPLAIN cocina local: range scan en `comandas_restaurante_id_empresa_estado_created_at_index` + sort — índice OK; el problema es **cardinalidad del resultado**, no el plan.

---

## 8. Retención de datos

Política **conceptual** (A/B/C/D) en `RESTAURANTE_DATA_GROWTH.md` §7.

- No se borró ni archivó nada.
- Candidatos a retención indefinida sin política: sesiones cerradas, soft-deletes, comandas, pedidos facturados, side_effects `done`, kardex.

---

## 9. Side effects / Outbox

| Tema | Estado |
|------|--------|
| Crecimiento | 1 fila / recurso ticket |
| `done` | Persistente; 16/16 local |
| Cleanup | **Ausente** |
| UNIQUE | Crece indefinidamente |
| Cleanup futuro | Documentado (criterio 30d `done`, batches, riesgos) — **no implementado** |

Idempotency keys: cleanup parcial ya existente (Fase 2) — fuera de cambio Fase 7.

---

## 10. Estrategia de archivado

Documentada en doc §9. Recomendación Smartpyme:

1. Operativo: drenar cocina (`servido` / ventana) + cleanup outbox/soft-deletes.
2. Medio plazo: tablas `*_archive` por empresa+fecha.
3. Partición: **solo con evidencia** en MariaDB 10.11.
4. No cold-storage ni delete destructivo ahora.

**Estado:** ANALIZADO / RECOMENDADO — **no IMPLEMENTADO**.

---

## 11. Métricas recomendadas

Lista en doc §11 (filas/día, por empresa, outbox backlog, p95 mesas/comandas, ratio soft-delete, edad `listo`).  
Implementación de observabilidad → **Fase 10** (FUERA DE ALCANCE aquí).

---

## 12. Cambios implementados

| Cambio | Tipo |
|--------|------|
| `RESTAURANTE_DATA_GROWTH.md` | **IMPLEMENTADO** (doc) |
| `FASE7_REPORT.md` | **IMPLEMENTADO** (doc) |
| Código PHP / Angular | **Ninguno** |
| Migraciones nuevas | **Ninguna** |

Decisión A/B/C del brief:

- **A (necesario y seguro en Fase 7):** solo documentación.
- **B (recomendado, esperar):** `servido` + filtro cocina; cleanup outbox; soft-delete purge; índice reservas; denormalizar `id_empresa` en precuentas si duele.
- **C (fases posteriores):** Fase 8 legacy service; Fase 9 reportes; Fase 10 métricas cableadas; Peak/k6; Reverb ops; partición.

---

## 13. Migraciones

| Migración | Nota Fase 7 |
|-----------|-------------|
| Existentes F1–F5 | Revisadas; índices OK |
| `2026_08_04_120000_add_servido_…` | **Pendiente de aplicar** en DB local; impacto negocio cocina — **no ejecutada** en esta fase (cambio de enum + comportamiento) |
| Nuevas Fase 7 | Ninguna |

Prod: cualquier ALTER enum en MariaDB 10.11 sobre tablas grandes debe planificarse (lock/copy); hoy `comandas_restaurante` es pequeña.

---

## 14. Tests antes/después

| Momento | Comando | Resultado |
|---------|---------|-----------|
| Antes | `php artisan test tests/Feature/Restaurante` | **45 passed** (176 assertions) |
| Después | mismo (sin cambios código) | **45 passed** — sin regresión esperada |

Deuda documentada previa (no tocada): Karma/Ventas `async` — fuera de alcance.

---

## 15. Problemas encontrados

1. **CRÍTICO (diseño):** `GET /comandas` sin límite + estados que no salen del filtro.
2. **ALTO:** soft-deletes masivos en `orden_detalle_restaurante`.
3. **ALTO (plataforma):** `kardexs` ~2.6M ya Hot Table.
4. **MEDIO:** migración `servido` no aplicada vs validación que ya la acepta.
5. **MEDIO:** `GET /reservas` sin paginación.
6. **MEDIO:** side_effects `done` sin retención/cleanup.
7. **INFO:** entorno local MySQL 9.5 ≠ prod MariaDB 10.11 — EXPLAIN/cardinality pueden diferir; revalidar en prod.

---

## 16. Riesgos

| Riesgo | Mitigación futura |
|--------|-------------------|
| Cocina payload crece con histórico `listo` | Estado terminal + filtro / ventana |
| Tabla orden inflada por SoftDeletes | Purge/archive tras ventana legal |
| Outbox UNIQUE interminable | Cleanup `done` antiguo |
| Particionar prematuro | Esperar métricas + slow log |
| Asumir MySQL local = MariaDB prod | Checklist deploy MariaDB 10.11 |

---

## 17. Hallazgos para fases posteriores

| Hallazgo | Fase sugerida |
|----------|---------------|
| Eliminar/documentar `PedidoRestauranteInventarioService` legacy | **8** |
| Reportes pesados fuera del path mesero | **9** |
| Métricas/logs de crecimiento y slow endpoints | **10** |
| Load/Peak k6 | **12/13** |
| Aplicar `servido` + acotar cocina | Hardening operativo (post-7; no Peak) |
| Cleanup side_effects / soft-deletes | Post-7 job ops |
| Reverb instalación/ops | Solo si se habilita realtime (Fase 6 ya dejó stub) |

---

## 18. Desviaciones del plan

- Plan §9 pide el documento de crecimiento: **cumplido**.
- No se “estimó” con datos Peak reales (no hay prod Agro-Mall en esta DB): se usó orden de magnitud del plan + conteos locales.
- No se añadieron índices “por si acaso” (alineado a YAGNI / justificación obligatoria).

---

## 19. Criterios de completitud

- [x] `RESTAURANTE_DATA_GROWTH.md` revisado/creado
- [x] Tablas de crecimiento identificadas
- [x] Hot Tables identificadas
- [x] Índices revisados
- [x] Consultas de crecimiento analizadas
- [x] Retención documentada
- [x] Side effects/outbox analizado
- [x] Estrategia futura de archivado documentada
- [x] Métricas recomendadas documentadas
- [x] MariaDB 10.11 considerado
- [x] No load tests
- [x] No Fase 8+
- [x] Suite Restaurante verde (45/45)
- [x] `FASE7_REPORT.md` creado
- [x] Sin cambios fuera de alcance

---

## 20. Siguiente paso

**DETENERSE.**

Esperar aprobación explícita del usuario para **Fase 8 — Servicios legacy** (`PedidoRestauranteInventarioService`).

No commit automático.

---

**FASE 7 COMPLETADA — DETENERSE — ESPERANDO APROBACIÓN PARA FASE 8**
