# FASE 9 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §11 (Reportes)  
**Predecesoras:** Fase 1–8 cerradas y validadas  
**Estado:** Fase 9 — **COMPLETADA DENTRO DEL ALCANCE** (auditoría + diseño; sin implementación de reporting)  
**Siguiente:** **DETENERSE** — no iniciar Fase 10+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen ejecutivo

**No existen reportes administrativos dedicados del módulo Restaurante** en el código actual (`routes/modulos/restaurante.php`, controllers `Api/Restaurante/*`, frontend `restaurante-routing`).

Lo que hay son endpoints **operativos POS** (mapa, cocina, cuenta, pedidos canal, reservas, tickets HTML). Las agregaciones SQL pesadas **no** viven en el path mesero; las únicas `groupBy` del módulo son en memoria (Collection) sobre payloads de una sesión/pedido.

El plan §11 pide **no inventar reportes** y documentar arquitectura futura: cumplido.

| Etiqueta | Contenido |
|----------|-----------|
| **IMPLEMENTADO** | Este `FASE9_REPORT.md` |
| **ANALIZADO** | Inventario de endpoints, clasificación, riesgos, índices, colateral Ventas/Contabilidad |
| **RECOMENDADO** | Arquitectura E (A ligera + D async) cuando existan reportes; acotar cocina/reservas (ya Fase 7) |
| **PENDIENTE** | Implementación de cualquier reporte nuevo |
| **FUERA DE ALCANCE** | ETL, MV, réplica, archivado, load tests, Fase 10 métricas |

**Cambios de código Fase 9:** ninguno.  
**Suite:** 45/45 antes y después.  
**Fase 8:** eliminación de `PedidoRestauranteInventarioService` intacta (no revertida).

---

## 2. Inventario de reportes

### 2.1 Reportes Restaurante dedicados

| Tipo | Hallazgo |
|------|----------|
| Controllers `*Reporte*Restaurante*` | **0** |
| Routes `/restaurante/.../reporte*` | **0** |
| Services reporting del módulo | **0** |
| Jobs/Commands analytics resto | **0** (solo `ImportarMesasRestaurante` = import Excel mesas, no reporte) |
| FE pantallas reporte/dashboard resto | **0** (rutas: mapa, cuenta, cocina, zonas) |

Conclusión alineada con `AUDITORIA_TECNICA_RESTAURANTE.md` §13 y verificada contra código 2026-08-10: **N/A — no hay reportes del módulo que inventariar como analytics.**

### 2.2 Sustitutos operativos (no son reportes)

Ver §4. Tickets HTML / listados POS.

### 2.3 Reportes colaterales plataforma (mismo VPS/MariaDB)

Existen reportes de **Ventas, Contabilidad, Inventario, Admin reportes-automáticos**. No consultan tablas `restaurante_*` de forma dedicada (p. ej. `ReportesController` / `Services/Ventas/ReporteService` **sin** referencias a modelos Restaurante).  
Las ventas originadas en precuenta/pedido canal **sí** entran en `ventas` vía facturación → aparecen en reportes de ventas genéricos. Eso **no** es un “reporte restaurante”; es colateral de plataforma.

---

## 3. Clasificación POS vs administrativos

| Clase | Qué entra | Acción Fase 9 |
|-------|-----------|---------------|
| **A. OPERACIÓN/POS** | Todo `routes/modulos/restaurante.php` + FE mapa/cuenta/cocina/zonas/pedidos | **No** convertir en reportes pesados |
| **B. REPORTES ADMINISTRATIVOS** | *Ninguno en módulo Restaurante* | Diseñar solo si el negocio los pide después |
| **C. EXPORTACIONES** | Tickets HTML sync; import mesas Excel (entrada, no export analytics) | No ETL |
| **D. POTENCIALMENTE PELIGROSOS** | `GET /comandas` sin límite; `GET /reservas` sin paginar; listados que crecen con histórico (Fase 7) | Documentado; **no** refactor grande aquí |

---

## 4. Endpoints encontrados (`/api/restaurante/*`)

Prefijo: `verificar.funcionalidad:modulo-restaurante`. Consumidor: Angular Restaurante / Pedidos.

### A — Operación POS (lectura frecuente)

| Endpoint | Controller | Consumidor | Paginación | Rango fechas | Sync |
|----------|------------|------------|------------|--------------|------|
| `GET /mesas` | `MesaController@index` | Mapa mesero | N≈mesas (+ cache 3s) | No | Sí |
| `GET /mesas/{id}` | `show` | Detalle | 1 | No | Sí |
| `GET /zonas` | `ZonaRestauranteController@index` | Admin zonas / mapa | N≈zonas | No | Sí |
| `GET /pos-menu/*` | `PosMenuController` | Cuenta POS | Catálogo scoped | No | Sí |
| `GET /comandas` | `ComandaController@index` | Cocina | **No** — `->get()` | No | Sí |
| `GET /sesiones-mesa/{id}` | `SesionMesaController@show` | Cuenta | 1 sesión + ítems | No | Sí |
| `GET /pre-cuentas/{id}` | `PreCuentaController@show` | Cuenta/caja | 1 | No | Sí |
| `GET /reservas` | `ReservaController@index` | Admin/mapa | **No** — `->get()` | Filtro `fecha` opcional | Sí |
| `GET /pedidos` | `PedidoRestauranteController@index` | Lista canal | **Sí** ≤100 | Filtros estado/canal/sucursal; orden fecha | Sí |
| `GET /pedidos/{id}` | `show` | Form canal | 1 | No | Sí |

### A — Mutaciones (fuera de “reportes”; no tocadas)

Abrir/cerrar sesión, ítems, comanda, precuenta, facturar, confirmar/anular pedido, reservas CRUD, zonas/mesas CRUD — **integridad F1–5 intacta; Fase 9 no las modifica.**

### C — Tickets / “export” operativo

| Endpoint | Formato | Async | Notas |
|----------|---------|-------|-------|
| `GET /comandas/{id}/imprimir` | HTML (`RestauranteTicketHtmlService` cache) | Sync (+ side-effect Fase 5) | 1 comanda |
| `GET /pre-cuentas/{id}/imprimir` | HTML | Sync | 1 precuenta |
| `GET /pedidos/{id}/imprimir` | HTML blade | Sync | 1 pedido + detalles |

No hay CSV/Excel/PDF analytics de Restaurante ni job de “generar reporte y descargar después”.

### Import (no reporte)

| Comando | Rol |
|---------|-----|
| `ImportarMesasRestaurante` | Carga mesas desde Excel → escritura; `--dry-run` valida |

---

## 5. Queries principales (lectura)

### `GET /mesas`

- Tablas: `restaurante_mesas` + `sesionActiva` + `reservasActivas` + zona  
- Joins: Eloquent relations (activo / fechas reserva)  
- GROUP BY SQL: no  
- Índices: `(id_empresa, id_sucursal)`; sesión `(mesa_id, estado)`  
- SELECT: columnas limitadas + DTO mapa (Fase 2)  
- Recorrido: O(mesas empresa) — **operativo, no histórico**  
- p95: **NO MEDIDO**

### `GET /comandas` (cocina)

- Tablas: `comandas_restaurante` + sesión/mesa + pedido + detalles + orden_detalle (+ soft-deleted) + producto  
- Filtro: `id_empresa` + `estado IN (pendiente,preparando,listo)`  
- ORDER BY: `created_at DESC`  
- Índice: `(id_empresa, estado, created_at)` — EXPLAIN Fase 7 usó range scan  
- **Sin LIMIT** → cardinalidad = todas comandas no terminales  
- N+1: mitigado con `with([...])`  
- p95 / filas Peak: **NO MEDIDO**  
- Clasificación D: **riesgo potencial** (Fase 7), no benchmark de capacidad

### `GET /pedidos`

- Tablas: `restaurante_pedidos` + cliente/usuario/**detalles**  
- Filtros: empresa, estado, canal LIKE, sucursal, búsqueda  
- ORDER BY: fecha/id/total/estado + id  
- Índices: `(id_empresa, fecha)`, `(id_empresa, estado)`  
- Paginación: sí  
- Payload: incluye **líneas de detalle** por página (no solo agregados) — ver §10  
- p95: **NO MEDIDO**

### `GET /reservas`

- `id_empresa` + optional fecha/estado; `with(mesa,usuario)`; **sin paginar**  
- Índice parcial `(mesa_id, fecha_reserva, estado)`  
- Riesgo crecimiento: **potencial** (Fase 7)

### Tickets imprimir

- Lookup por id + tenant; HTML; no GROUP BY histórico  
- Riesgo reporte: **bajo** (1 documento)

### Agregaciones in-process (no SQL report)

| Ubicación | Tipo | Scope |
|-----------|------|-------|
| `PreCuentaController` | `groupBy` Collection al dividir | Asignaciones de **una** sesión |
| `RestauranteTicketHtmlService` | `groupBy` líneas ticket | 1 comanda |
| `PedidoCanalInventarioService::ventaCoincideConPedido` | `groupBy` cantidades | 1 pedido vs payload venta |

**No** son reportes administrativos.

---

## 6. Tablas involucradas

| Tabla | Uso lectura POS | Uso reporte admin módulo |
|-------|-----------------|--------------------------|
| `restaurante_mesas` / `_zonas` | Mapa/CRUD | N/A |
| `restaurante_sesiones_mesa` | Cuenta/mapa | N/A |
| `orden_detalle_restaurante` | Cuenta/comanda | N/A |
| `comandas_restaurante` / `comanda_detalle_*` | Cocina/ticket | N/A |
| `pre_cuentas_*` | Cuenta/caja | N/A |
| `restaurante_pedidos` / `_detalles` | Canal | N/A |
| `reservas_restaurante` | Reservas | N/A |
| `ventas` / `kardexs` | Vía facturación / inventario (otros módulos) | Reportes plataforma (colateral) |

Filas locales (Fase 7, **no** prod Agro-Mall): pedidos ~900; comandas ~40; kardexs ~2.6M (inventario global).

---

## 7. Índices existentes (relevantes lectura)

| Tabla | Índice | Cubre |
|-------|--------|-------|
| comandas | `(id_empresa, estado, created_at)` | Cocina |
| sesiones | `(mesa_id, estado)`, `(id_empresa, estado)`, UNIQUE funcional activa | Mapa/apertura |
| orden_detalle | `(sesion_id, producto_id, enviado_*)` | Fusión ítems |
| pedidos | `(id_empresa, fecha)`, `(id_empresa, estado)` | Lista canal |
| mesas | `(id_empresa, id_sucursal)` | Mapa |
| reservas | `(mesa_id, fecha_reserva, estado)` | Reservas por mesa |
| pre_cuentas | `(sesion_id, estado)` | Cuenta |

### Candidatos futuros (NO crear en Fase 9)

| Candidato | Query | Cubierto hoy | Evidencia | Escritura |
|-----------|-------|--------------|-----------|-----------|
| `(id_empresa, fecha_reserva, estado)` reservas | listado admin por día | Parcial | Sin slow query prod | Baja |
| Índices reporting nuevos | *no hay queries reporting* | N/A | N/A | — |

**Beneficio medido de índices nuevos:** **NO MEDIDO** → no se crean.

---

## 8. Riesgos confirmados

| # | Riesgo | Evidencia |
|---|--------|-----------|
| 1 | No hay capa reporting Restaurante | Inventario de routes/controllers/FE = vacío analytics |
| 2 | `GET /comandas` sin límite de filas | Código: `->get()` + estados no terminales (Fase 7) |

No se confirma degradación de capacidad Peak ni p95 alto: **NO MEDIDO**.

---

## 9. Riesgos potenciales

| # | Riesgo | Relación sistemas | Evidencia |
|---|--------|-------------------|-----------|
| 1 | Cocina payload crece con `listo` histórico | API + PHP-FPM + FE cocina | Código + schema; latency **NO MEDIDO** |
| 2 | `GET /reservas` sin paginar | API admin | Código; volumen local ~1 fila |
| 3 | `GET /pedidos` carga detalles por página | API canal | Código; paginado mitiga |
| 4 | Reportes Ventas/Contabilidad/Inventario en mismo MariaDB | MariaDB compartido → posible contención con POS | Arquitectura multi-módulo; impacto Peak **NO MEDIDO** |
| 5 | `kardexs` grande si reportes inventario coinciden con Peak | MariaDB + inventario | ~2.6M filas local; no es query Restaurante |
| 6 | Redis mapa (TTL 3s) | Redis; miss → DB | Fallo Redis no rompe integridad (Fase 3) |

Distinción: **potencial ≠ confirmado de capacidad.**

---

## 10. Problemas de payload

| Endpoint | Observación | Severidad |
|----------|-------------|-----------|
| `GET /comandas` | Eager load completo de detalles/productos para todas las abiertas | Media→Alta al crecer |
| `GET /pedidos` | `with(['detalles'])` en listado paginado | Media (útil UI; no es agregado) |
| `GET /mesas` | DTO liviano Fase 2 | Bajo |
| Tickets | HTML de 1 recurso | Bajo |
| DTE/JSON venta completo | No en listados resto; facturación es otro módulo | N/A aquí |

Sin cambio de contrato en Fase 9 (requeriría decisión de producto).

---

## 11. Problemas de N+1

| Endpoint | Estado |
|----------|--------|
| Mesas / comandas / pedidos / reservas / precuenta show | Eager `with` presente |
| Pos-menu | Queries scoped + `with('imagenes')` donde aplica |

N+1 masivo en reportes: **N/A** (no hay reportes).  
N+1 residual no demostrado con profiler: **NO MEDIDO**.

---

## 12. Riesgos por crecimiento de datos

Relación con `RESTAURANTE_DATA_GROWTH.md`:

- Historial sesiones/detalles/comandas/pedidos crecerá; **hoy** impacta más listados POS sin límite que “reportes”.
- Soft-deletes en `orden_detalle` inflan joins de cocina (`withTrashed`).
- Side_effects/outbox: no usados para reporting.
- **No** implementar archivado/partición/purge en Fase 9.

Cuando existan reportes por rango de fechas sobre tablas calientes, el riesgo de scan histórico será real → entonces Opción B/D (ver §15).

---

## 13. Evaluación de arquitectura actual

| Criterio | Evaluación |
|----------|------------|
| Separación POS vs reportes | **De facto buena**: no hay reportes en path mesero |
| Madurez reporting Restaurante | **Inexistente** (gap de producto, no bug) |
| Facturación | Ventas genéricas absorben ingresos resto |
| Tickets | Sync HTML + cache/outbox Fase 5 — adecuado |
| Cumple plan §11 “nunca agregaciones pesadas en path mesero” | **Sí** (vacío de agregaciones SQL admin) |

No se afirma que la arquitectura esté “optimizada” para analytics: **no hay analytics que optimizar**.

---

## 14. Opciones arquitectónicas (sin implementar)

| Opción | Ventajas | Desventajas | Complejidad | Costo | Impacto POS | Cuándo |
|--------|----------|-------------|-------------|-------|-------------|--------|
| **A** Queries optimizadas sobre tablas TX | Simple; datos frescos | Compite con POS en MariaDB | Baja | Bajo | Medio si mal acotado | Reportes ligeros ≤90d, filtros empresa+fecha+índice |
| **B** Read models / tablas agregadas | Lecturas baratas | ETL, consistencia eventual | Media–alta | Medio | Bajo si job off-peak | KPIs diarios (ocupación, ticket medio) con alto volumen |
| **C** ETL / reporting DB | Aísla MariaDB TX | Ops, sync lag | Alta | Alto | Mínimo en TX | Multi-tenant Peak + reportes pesados concurrentes |
| **D** Jobs async + descarga | No bloquea PHP-FPM request | UX espera; cola | Media | Medio | Bajo | Excel/PDF/CSV grandes |
| **E** Combinación A+D (+B después) | Pragmática | Dos caminos | Media | Medio | Controlado | Estado real actual → futuro |

Volumen local actual **no** justifica B/C ahora.

---

## 15. Arquitectura recomendada

**Hoy (Restaurante 1.0 sin reportes del módulo):**

1. Mantener **cero agregaciones pesadas** en path mesero/cocina/mapa.  
2. Seguir usando **Ventas** para ingresos facturados (canal/precuenta → `ventas`).  
3. Tratar `GET /comandas` / `GET /reservas` como deuda operativa (Fase 7), no como “reporte”.

**Cuando el negocio pida reportes Restaurante (ocupación, tiempos cocina, mix productos mesa, etc.):**

1. Empezar con **Opción E = A + D**:  
   - A: endpoints admin **separados** (`/restaurante/reportes/...` o módulo admin), siempre `id_empresa` + rango fechas obligatorio + límites.  
   - D: exportaciones Excel/CSV/PDF vía job + notificación/descarga.  
2. Subir a **B** solo si A demuestra contención (slow log MariaDB 10.11 / Fase 10–12 evidencia).  
3. **C** solo si multi-tenant Peak + reportes concurrentes lo exigen (Fase 12/13).  
4. Nunca encolar reportes en los mismos workers críticos de side-effects de cocina sin aislamiento de cola.

**No** crear tablas agregadas / MV / ETL en esta fase.

---

## 16. Cambios realizados

| Cambio | Estado |
|--------|--------|
| Código PHP / Angular / migraciones | **Ninguno** |
| `FASE9_REPORT.md` | **Creado** |
| `PedidoRestauranteInventarioService` (Fase 8) | **No revertido** |

---

## 17. Cambios deliberadamente NO realizados

- Nuevos endpoints de reportes  
- Tablas agregadas / materialized views / ETL / réplica  
- Índices nuevos  
- Refactor `GET /comandas` / reservas (documentado Fase 7; cambio de producto)  
- Cleanup/archivado/partición  
- Observabilidad Fase 10  
- Load/Peak tests  
- Modificar confirmar/anular/inventario/comanda/facturar  

---

## 18. Tests

| Momento | Comando | Resultado |
|---------|---------|-----------|
| Baseline pre-Fase 9 | `php artisan test tests/Feature/Restaurante` | **45 passed** (176 assertions) |
| Post (sin cambios código) | mismo | **45 passed** |

`git diff` app/routes: solo la eliminación Fase 8 del legacy (esperado); **sin** churn adicional de Fase 9.

---

## 19. Riesgos pendientes

1. Producto pedirá reportes sin path aislado → riesgo de meter agregaciones en controllers POS.  
2. Contención MariaDB por reportes **otros módulos** durante Peak — medir en Fase 12/13, no afirmar ahora.  
3. Cocina sin drenaje de estados (Fase 7) sigue siendo el peor “falso reporte” actual.

---

## 20. Recomendaciones para fases posteriores

| Ítem | Fase |
|------|------|
| Logs/métricas p95 mesas/comandas, slow queries | **10** |
| Suite E2E ampliada | **11** |
| Load/Peak; medir colateral reportes Ventas | **12 / 13** |
| Si se implementan reportes: A+D, índices justificados con EXPLAIN prod | Post-aprobación producto |
| Acotar cocina (`servido` / ventana) | Ops / hardening post-7 |

---

## 21. Criterios de completitud

- [x] Inventario real de endpoints/reportes  
- [x] Clasificación A/B/C/D  
- [x] Queries/tablas/índices documentados  
- [x] Riesgos confirmados vs potenciales diferenciados  
- [x] Opciones A–E evaluadas + recomendación  
- [x] Sin agregaciones pesadas añadidas al path mesero  
- [x] Sin ETL/MV/archivado/load tests/Fase 10  
- [x] Sin alterar flujos transaccionales F1–8  
- [x] Suite 45/45  
- [x] `FASE9_REPORT.md` creado  
- [x] Sin commit automático  

---

**FASE 9 COMPLETADA — DETENERSE — ESPERANDO APROBACIÓN PARA FASE 10**
