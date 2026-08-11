# RESTAURANTE DATA GROWTH — Smartpyme Restaurante 1.0

**Fecha análisis:** 2026-08-10  
**Fase:** 7 (`PLAN_HARDENING_RESTAURANTE_1.0.md` §9)  
**Producción SoT:** MariaDB **10.11.x**  
**DB inspeccionada (local):** MySQL **9.5.0**, schema `smartpyme_prod` (conteos reales; no asumir igualdad exacta con prod)  
**Alcance:** análisis + política conceptual. **Sin** particionado, archivado destructivo, cleanup automático ni load tests.

---

## 1. Contexto operativo (Agro-Mall)

| Parámetro | Valor plan |
|-----------|------------|
| Mesas | ~139 |
| Zonas | ~17 |
| Peak waiters concurrentes | 40–50 |
| Peak cocina/barra | 8–20 |
| Covers / día (orden magnitud Peak) | 500–1,200 |

Local actual (dev/seed, **no** Agro-Mall): 44 mesas, 13 zonas, histórico bajo.

---

## 2. Inventario de tablas de crecimiento continuo

Leyenda crecimiento: **C** = caliente operativa · **H** = histórico acumulativo · **A** = auxiliar (TTL / outbox).

| Tabla | Filas local | Crecimiento | Soft-delete | Tenant | Histórica | Hot risk |
|-------|-------------|-------------|-------------|--------|-----------|----------|
| `restaurante_mesas` | 44 | Estático/lento | No | `id_empresa`, `id_sucursal` | No | Bajo |
| `restaurante_zonas` | 13 | Estático | No | `id_empresa` | No | Bajo |
| `restaurante_sesiones_mesa` | 71 | **H** (1/sesión) | No | `id_empresa`, `id_sucursal` | Sí (cerradas) | **Alto** |
| `orden_detalle_restaurante` | 380 (354 soft-deleted) | **H** + soft-delete | **Sí** | vía sesión | Sí | **Crítico** |
| `comandas_restaurante` | 43 | **H** | No | `id_empresa` | Sí | **Crítico** (payload cocina) |
| `comanda_detalle_restaurante` | 40 | **H** | No | vía comanda | Sí | Alto |
| `division_cuenta_restaurante` | 7 | H bajo | No | vía sesión | Sí | Bajo |
| `pre_cuentas_restaurante` | 75 | **H** | No | vía sesión | Sí | Medio |
| `pre_cuenta_orden_detalle` | 330 | **H** | No | vía precuenta | Sí | Medio |
| `restaurante_pedidos` | 909 | **H** | No | `id_empresa`, `id_sucursal` | Sí | **Alto** |
| `restaurante_pedido_detalles` | 1 413 | **H** | No | vía pedido | Sí | Alto |
| `reservas_restaurante` | 1 | H bajo–medio | No | `id_empresa` | Sí | Medio (lista sin paginar) |
| `restaurante_side_effects` | 16 | **A→H** (done eterno) | No | `id_empresa` | Sí (done) | Medio→Alto |
| `restaurante_idempotency_keys` | 9 | **A** (TTL 24h + purge oportunista) | No | `id_empresa` | No (expira) | Bajo |
| `kardexs` (inventario global) | ~2.6M | **H** masivo | — | empresa vía movimiento | Sí | **Crítico** (módulo inventario; no solo resto) |

### Columnas de timestamp / estado relevantes

| Tabla | Timestamps | Estado / status |
|-------|------------|-----------------|
| sesiones | `opened_at`, `closed_at`, `created_at` | `abierta` / `pre_cuenta` / `cerrada` |
| orden_detalle | `created_at`, `deleted_at` | flags `enviado_*` |
| comandas | `enviado_at`, `created_at` | enum cocina (+ `servido` en código; ver §4) |
| pre_cuentas | `created_at` | `pendiente` / `facturada` / `anulada` |
| pedidos | `fecha`, `created_at`, `inventario_descontado_at` | `borrador`…`facturado`/`anulado` |
| side_effects | `processed_at`, `created_at` | `pending`/`processing`/`done`/`failed` |
| idempotency | `expires_at` | `completed`/… |

---

## 3. Estimación anual (Agro-Mall, orden de magnitud)

Hipótesis conservadoras (documentales, no medidas en Peak):

| Flujo | /día | /año (~300 d) | Multiplicador filas hijas |
|-------|------|---------------|---------------------------|
| Sesiones mesa | 200–600 | 60k–180k | — |
| Ítems orden (detalle) | 1.5k–6k | 0.5M–1.8M | soft-delete acumula más |
| Comandas | 400–1.2k | 120k–360k | ×2–8 detalles |
| Pre-cuentas | 150–500 | 45k–150k | ×N líneas |
| Pedidos canal | 50–200 | 15k–60k | ×2–10 detalles |
| Side effects (ticket) | ~1 por comanda/precuenta | ~igual a tickets | UNIQUE 1 fila/recurso |
| Idempotency keys | burst Peak | techo ~TTL×ops | purge parcial |

**Conclusión:** a 12–24 meses, tablas de detalle (`orden_detalle_*`, `*_pedido_detalles`, `comanda_detalle_*`) dominan filas; `kardexs` ya es Hot Table global (~2.6M local) y crecerá con cada descuento de inventario restaurante.

---

## 4. Hot Tables / Hot Indexes

| # | Tabla | Severidad | INSERT | UPDATE | Por qué |
|---|-------|-----------|--------|--------|---------|
| 1 | `orden_detalle_restaurante` | **CRÍTICO** | Alto | Alto (flags, soft-delete) | Ítem = unidad de trabajo; soft-delete **no libera** espacio lógico |
| 2 | `comandas_restaurante` | **CRÍTICO** | Alto | Medio | `GET /comandas` sin `LIMIT` y estados no terminales |
| 3 | `kardexs` | **CRÍTICO** | Muy alto | Bajo | Inventario plataforma; restaurante escribe vía pedidos |
| 4 | `restaurante_sesiones_mesa` | **ALTO** | Medio | Medio (cierre) | Histórico cerradas crece; lookup activa indexado |
| 5 | `restaurante_pedidos` + detalles | **ALTO** | Medio–alto | Medio | Lista paginada OK; histórico facturado crece |
| 6 | `restaurante_side_effects` | **MEDIO→ALTO** | 1/ticket | status→done | UNIQUE eterna; sin cleanup |
| 7 | `pre_cuentas_*` | **MEDIO** | Medio | Medio | Acceso por sesión; histórico facturado |
| 8 | `reservas_restaurante` | **MEDIO** | Bajo | Bajo | `index` sin paginar |
| 9 | `restaurante_idempotency_keys` | **BAJO** | Burst | — | TTL + `delete` limit 100 |

### Índices actuales (resumen)

| Tabla | Índices clave |
|-------|---------------|
| sesiones | UNIQUE funcional activa `(CASE WHEN estado IN (abierta,pre_cuenta) THEN mesa_id END)`; `(id_empresa,estado)`; `(mesa_id,estado)` |
| orden_detalle | `(sesion_id)`; `(sesion_id,producto_id,enviado_cocina,enviado_barra)` Fase 3 |
| comandas | `(sesion_id,estado)`; `(id_empresa,estado,created_at)` Fase 2 |
| pedidos | `(id_empresa,fecha)`; `(id_empresa,estado)` |
| side_effects | UNIQUE `(type,resource_type,resource_id)`; `(id_empresa,status)` |
| idempotency | UNIQUE tenant+op+key; `(expires_at)` |
| pre_cuentas | `(sesion_id,estado)` — **sin** `id_empresa` denormalizado |
| reservas | `(mesa_id,fecha_reserva,estado)`; FK `id_empresa` |

**MariaDB 10.11:** el índice funcional de sesión activa es válido (expresión); validar en prod con `SHOW CREATE TABLE` / `EXPLAIN` al desplegar — local MySQL 9.5 ya lo usa.

### Índices propuestos (solo justificados — **NO implementar en Fase 7**)

Ningún índice nuevo obligatorio hoy: EXPLAIN local usa los índices Fase 2/3 en cocina, sesión activa y side_effects pending.

| Propuesta futura | Consulta | Problema | Costo escritura | Riesgo |
|------------------|----------|----------|----------------|--------|
| `(id_empresa, fecha_reserva, estado)` en reservas | listados por día/empresa | hoy FK empresa + orden fecha sin índice compuesto empresa | Bajo | duplicar parcialmente mesa+fecha |
| `(sesion_id, deleted_at)` o purge soft-deletes | reportes/histórico ítems | soft-deletes dominan filas | Medio | reportes con `withTrashed` |
| Índice `(status, processed_at)` side_effects | cleanup done | cleanup futuro por antigüedad | Bajo | solo si hay job cleanup |

---

## 5. Consultas históricas / degradables

| Endpoint / patrón | Límite | Tenant | Índice usado (EXPLAIN local) | Riesgo crecimiento |
|-------------------|--------|--------|------------------------------|--------------------|
| `GET /mesas` | mesas empresa (cache 3s) | `id_empresa` (+ sucursal) | mesas + relación activa | **Bajo** (N≈mesas; no histórico) |
| `GET /comandas` (cocina) | **ninguno** — `->get()` | `id_empresa` | `(id_empresa,estado,created_at)` | **CRÍTICO** si `listo` acumula sin terminal |
| `GET /pedidos` | paginate ≤100 | `id_empresa` | `(id_empresa,fecha)` / estado | Medio (OK con paginación) |
| `GET /reservas` | **ninguno** — `->get()` | `id_empresa` | parcial | Medio a 12m+ |
| Lookup sesión activa | 1 mesa | — | `(mesa_id,estado)` | Bajo |
| Side effects pending | por job | `id_empresa,status` | empresa+status | Bajo mientras pending≪done |

### Hallazgo crítico: cocina + estado `servido`

- Código valida `estado ∈ {pendiente,preparando,listo,servido}` al actualizar.
- Migración `2026_08_04_120000_add_servido_to_comandas_restaurante_estado` **existe** pero **no está aplicada** en la DB local inspeccionada (`migrations` sin esa fila; enum DB = 3 valores).
- `ComandaController@index` filtra `pendiente|preparando|listo` → sin estado terminal efectivo fuera del filtro, **toda comanda “listo” permanece en el payload cocina para siempre**.

**Recomendación (futura, no Fase 7):** aplicar migración `servido` en MariaDB 10.11; excluir `servido` (y opcionalmente `listo` > N horas) del listado cocina; o ventana temporal. Pertenecería a hardening operativo / Fase posterior, no a load test.

---

## 6. Multi-tenant y crecimiento

| Área | Estado | Nota crecimiento |
|------|--------|------------------|
| Mesas / zonas / sesiones / pedidos / comandas / reservas | Scoped `id_empresa` | OK |
| Pre-cuentas | Tenant vía `whereHas(sesion.id_empresa)` | Correcto; a escala, joins a histórico sesión pueden costar más que denormalizar `id_empresa` (Fase 2 ya lo hizo en comandas) |
| Orden detalle | vía sesión | Soft-delete multi-tenant: no hay filtro empresa directo en tabla |
| Side effects | `id_empresa` + unique global por recurso | Unique no es por empresa (IDs de recurso globales) — OK si IDs no colisionan entre tablas |

No reabrir auditoría Fase 2: solo anotar que **pre_cuentas sin `id_empresa` denormalizado** es el candidato más claro a degradación multi-tenant al crecer histórico.

---

## 7. Retención conceptual (sin borrar)

| Clase | Qué | Retención sugerida (política) |
|-------|-----|-------------------------------|
| **A Operativa caliente** | Sesiones abierta/pre_cuenta; comandas no servidas; precuentas pendiente; pedidos borrador/pendiente_facturar; side_effects pending/failed; mesas/zonas | Indefinida mientras viva la operación |
| **B Histórica reciente** | Sesiones/comandas/precuentas/pedidos ≤ 90 días; idempotency ≤ 24h | Online en tablas calientes; reportes del día a día |
| **C Histórica antigua** | > 90–365 días facturado/cerrado/anulado | Online o réplica lectura; candidatos archive |
| **D Archivado futuro** | Sesiones cerradas + hijos; comandas `servido`/antiguas; precuentas facturadas; pedidos facturados; side_effects `done` > 30–90d; soft-deletes orden > 30–90d | Tablas `*_archive` o cold storage; **nunca** borrar sin política legal/fiscal |

Crecen indefinidamente hoy sin política: sesiones cerradas, orden_detalle (incl. soft-deleted), comandas, detalles, precuentas, pedidos, side_effects done, kardex.

---

## 8. `restaurante_side_effects` (outbox)

| Pregunta | Hallazgo |
|----------|----------|
| Cómo crece | 1 fila por `(type, resource_type, resource_id)` al encolar ticket |
| Paso a `done` | Job `ProcesarSideEffectRestauranteJob` tras procesar; status durable en MariaDB |
| Limpieza | **No existe** |
| Índices | UNIQUE recurso; `(id_empresa, status)` |
| UNIQUE indefinida | **Sí** — cada ticket histórico ocupa slot UNIQUE para siempre |
| ¿Retener done? | Útil corto plazo (reintentos/auditoría); no hace falta años en caliente |
| Cleanup futuro seguro | Sí: `DELETE … WHERE status='done' AND processed_at < NOW()-INTERVAL N DAY LIMIT K` en job; índice opcional `(status, processed_at)`; rollback = no borrar / restore from backup |

**Fase 7:** documentar únicamente. **No** implementar cleanup automático.

Criterio documentado para fase posterior:

- Antigüedad: `done` ≥ 30 días (ajuste por auditoría)
- Frecuencia: diaria off-peak, batches `LIMIT 1000`
- Riesgo: perder evidencia de “ya notificado”; mitigar log/métrica antes de borrar
- No tocar `pending`/`failed`/`processing`

---

## 9. Estrategia futura de archivado (no implementar)

| Alternativa | Ventajas | Desventajas | Complejidad | Consultas | Auditoría | Multi-tenant | Recuperación |
|-------------|----------|-------------|-------------|-----------|-----------|--------------|--------------|
| Tablas `*_archive` misma DB | SQL simple; mismo tenant | Doble schema; migraciones | Media | App debe elegir hot/cold | Buena | Fácil filtrar empresa | Restaurar INSERT reverse |
| Particionamiento por fecha | Queries por rango | MariaDB ops; PK/UNIQUE constraints | **Alta** | Transparente si bien diseñado | Media | Por partición global | Difícil |
| Cold storage (S3/export) | Barato | No SQL ad-hoc | Media | Solo hot en app | Export + hash | Por archivo empresa | Reimport |
| Eliminación controlada | Reduce tamaño | Irreversible; legal | Baja–media | Más rápidas | Peor | Por empresa/fecha | Backup only |
| Retención por empresa | Flex comercial | N políticas | Media | Jobs parametrizados | Variable | Nativo | Variable |

### Recomendación concreta Smartpyme

1. **Corto plazo (post–Fase 7, sin Peak):** aplicar `servido` + acotar `GET /comandas`; purge soft-deletes antiguos de `orden_detalle` tras ventana; cleanup `side_effects` done; cleanup idempotency ya parcial.
2. **Medio plazo:** tablas archive por año para `restaurante_sesiones_mesa` + cascada lógica de hijos (orden/comandas/precuentas) **por `id_empresa` + `closed_at`/`created_at`**, sin FK cross-hot si se rompe cascade.
3. **No particionar** hasta evidencia (filas > umbral + slow query log en MariaDB 10.11).
4. **Kardex:** política de inventario/plataforma (fuera del silo solo-restaurante); no “arreglar” en Fase 7.

---

## 10. Cuándo particionar (criterio — no auto)

Considerar partición **solo si** en prod MariaDB 10.11:

- Tabla > ~20–50M filas **o** > ~20–30 GB, **y**
- Slow queries muestran range scans por fecha dominantes, **y**
- Archive/purge ya no basta.

Candidatos teóricos: `orden_detalle_restaurante`, `kardexs`, eventualmente `restaurante_pedido_detalles`.  
**Hoy:** no.

---

## 11. Métricas recomendadas (Fase 10 observará; aquí solo lista)

| Métrica | Por qué |
|---------|---------|
| Filas / tamaño data+index por tabla resto | Anticipar Hot Table |
| Filas nuevas / día (sesiones, comandas, pedidos, side_effects) | Tendencia |
| Filas nuevas / empresa / día | Multi-tenant skew |
| Side effects: pending vs done vs failed | Backlog outbox |
| Soft-deletes `orden_detalle` ratio | Inflado tabla |
| p95 `GET /mesas`, `GET /comandas` | Degradación UX cocina/mapa |
| Slow query log: scans sin `id_empresa` / sin límite | Regresiones |
| Edad máxima comanda en estado `listo` | Detectar cocina sin drenaje |

Sin sistema complejo en Fase 7.

---

## 12. Decisiones Fase 7

| Tipo | Ítem |
|------|------|
| **IMPLEMENTADO** | Este documento + `FASE7_REPORT.md` |
| **ANALIZADO** | Schema, índices, EXPLAIN, conteos, side_effects, retención, archive |
| **RECOMENDADO (no código)** | `servido` + acotar cocina; cleanup done; soft-delete purge; métricas |
| **PENDIENTE** | Implementación de recomendaciones |
| **FUERA DE ALCANCE** | Load/Peak tests; Fase 8+; partición; archive jobs; UX |

---

*Fin RESTAURANTE_DATA_GROWTH.md*
