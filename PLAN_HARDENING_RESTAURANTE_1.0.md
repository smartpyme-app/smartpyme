# PLAN DE IMPLEMENTACIÓN — SMARTPYME RESTAURANTE 1.0

# Proyecto de Hardening, Estabilización y Preparación para Producción

**Fecha del plan:** 2026-08-09  
**Rama de trabajo observada:** `dc.SP-2104`  
**Documento relacionado:** `AUDITORIA_TECNICA_RESTAURANTE.md`  
**Principio:** Minimal change, maximum reliability.

---

## Contexto operativo (Agro-Mall)

- 139 mesas, 17 zonas
- 20–25 meseros concurrentes
- ~10 cajas/locales
- Cocina/barra + 1–3 admins
- Otros tenants Smartpyme en el mismo VPS
- Los ~1,000 visitantes **no** son usuarios concurrentes del sistema

### Capacidad objetivo

| Escenario | Concurrentes | Req/s | Req/min |
|-----------|--------------|-------|----------|
| Peak Agro-Mall | 40–50 | 8–20 | 500–1,200 |
| Peak + Smartpyme | 50+ | 15–40+ | 900–2,400+ |

### Objetivos de calidad

1. Integridad de datos bajo concurrencia
2. Idempotencia de operaciones críticas
3. Seguridad multi-tenant
4. Consistencia mesas/órdenes/comandas/precuentas/facturación
5. Buen rendimiento
6. Uso eficiente de MariaDB
7. Uso eficiente de Redis cuando aporte valor
8. Frontend Angular eficiente
9. Capacidad de escalar a más clientes
10. Observabilidad
11. Pruebas automatizadas
12. Load testing
13. Preparación para realtime
14. Mantenibilidad
15. No afectar negativamente los demás módulos de Smartpyme

---

## Reglas absolutas

### Antes de modificar código

1. Analizar nuevamente el código actual.
2. Confirmar que los hallazgos de la auditoría siguen siendo válidos.
3. No asumir que las líneas de la auditoría son exactas.
4. Buscar implementaciones reales.
5. Identificar dependencias de cada cambio.
6. Identificar efectos secundarios.
7. Si una recomendación no aplica al código actual, no implementarla a ciegas; documentar por qué.
8. Si aparece un problema crítico adicional, documentarlo e incluirlo.

### No hacer

- No cambiar lógica funcional sin necesidad.
- No cambiar UX salvo performance/concurrencia/seguridad.
- No refactor masivo estético.
- No capas innecesarias / Repository Pattern sin necesidad real.
- No reemplazar Eloquent indiscriminadamente.
- No cambiar arquitectura completa del módulo.
- No instalar paquetes sin justificar.
- No cambiar versiones Laravel/Angular.
- No modificar módulos no relacionados salvo integración estricta.
- No tocar facturación general sin analizar contratos.
- No eliminar funcionalidad existente.
- No breaking changes de endpoints sin compatibilidad.
- No polling agresivo (5s) como “solución rápida” de stale map.

### Stack

- Backend: Laravel 12, PHP, MariaDB 10.11, Redis disponible, Queue disponible
- Frontend: Angular 22
- Infra: Nginx, PHP-FPM, MariaDB, Redis, VPS multi-tenant

---

## 0. Revalidación del código actual (pre-implementación)

| Hallazgo auditoría | ¿Sigue válido? | Evidencia actual |
|---|---|---|
| Race abrir mesa | **Sí** | `SesionMesaController@store`: check → create sin tx/lock |
| Race agregar/fusionar | **Sí** | `OrdenDetalleController@store`: sin tx/lock |
| Race enviar comanda | **Sí** | `ComandaController@store`: tx sin `lockForUpdate` ni update condicional |
| Race marcar facturada | **Sí** | `PreCuentaController@marcarFacturada`: check estado sin lock de fila |
| Liquidación N+1 | **Sí** | `liquidarOrdenTrasFacturarPreCuenta`: `first()` por línea |
| Doble submit agregar FE | **Sí** | `pos-sheet-agregar` sin flag; cuenta-mesa sin `enviandoAgregar` |
| Inventario canal no idempotente | **Sí** | `PedidoCanalInventarioService` **no** usa `inventario_descontado_at`; el servicio legacy sí, pero no se invoca |
| Sin polling/WS | **Sí** | Sin `setInterval`/Echo en views restaurante |
| Eager mapa OK | **Sí** | `MesaController@index` con `with([...])` — no romper |
| Tests Restaurante | Escasos | Solo `PosMenuTest` (sin DB) + `MesasImportPlannerTest` |

### Restricciones del repo relevantes

- Feature tests existentes de Restaurante evitan `RefreshDatabase` (esquema incompleto en testing).
- `phpunit.xml` tiene SQLite `:memory:` comentado.
- Los tests de concurrencia P0 requieren **estrategia explícita de DB de prueba**.
- Patrones reutilizables ya en Smartpyme:
  - `lockForUpdate()` en Ventas/Compras/Fidelización
  - Idempotencia por clave en `ReversionPuntosService` (DB + lock); no hay middleware genérico aún
- `.env.example`: Redis configurado; `CACHE_DRIVER=file`, `QUEUE_CONNECTION=sync` por defecto

### Frontend mapa — campos usados hoy

- `sesion_activa.id`
- `zona_restaurante.nombre` / `orden`
- `capacidad`
- `tiempo_abierta`
- `estado`, `numero`, `zona_id` / `zona`

Cualquier DTO liviano (Fase 2) debe preservar compatibilidad con estos usos.

---

## 1. Orden exacto de ejecución

```
FASE 0  Baseline (solo diagnóstico + BASELINE_REPORT.md)
   ↓ (reporte; parar si baseline roto)
FASE 1  P0 Integridad (7 bloques) + tests + reporte fase
   ↓ (esperar confirmación explícita para Fase 2)
FASE 2  P1 API/seguridad/FE (DTO mesas, update ítem, cerrar, multi-tenant, Idempotency-Key)
   ↓
FASE 3  P2 Índices + Redis cache mapa + bulk updates
   ↓
FASE 4  Angular (OnPush/track/mesasPorZona/HTTP/subscriptions)
   ↓
FASE 5  Queues candidatos (solo análisis + cambios seguros)
   ↓
FASE 6  Realtime diseño (+ stub/evento opcional; Reverb no bloqueante)
   ↓
FASE 7  RESTAURANTE_DATA_GROWTH.md
   ↓
FASE 8  Legacy inventario (documentar; borrar solo si cero refs)
   ↓
FASE 9  Arquitectura reportes futuros (doc)
   ↓
FASE 10 Observabilidad
   ↓
FASE 11 Completar suite tests
   ↓
FASE 12–13 k6 + métricas
   ↓
FASE 14 Noisy neighbor (medir/proponer)
   ↓
FASE 15 RESTAURANTE_PRODUCTION_READINESS.md
```

**Tras confirmación del plan:** ejecutar solo **Fase 0 → Fase 1**, reportar, y **no** avanzar automáticamente.

Después de cada fase:

1. Ejecutar tests
2. Análisis estático disponible
3. Revisar migraciones
4. Revisar queries
5. Revisar endpoints modificados
6. Revisar regresiones
7. Generar reporte de cambios
8. Indicar pendientes

---

## 2. Fase 0 — Baseline y seguridad del cambio

### Objetivo

Diagnóstico sin cambios de negocio. Generar `BASELINE_REPORT.md`.

### Acciones

1. Ejecutar / inventariar tests existentes
2. Identificar PHPUnit (PHPUnit 11; no Pest)
3. Identificar tests Angular (`ng test`, specs existentes)
4. Identificar lint/build
5. Identificar configuración PHP
6. Identificar configuración Laravel
7. Identificar configuración Redis
8. Identificar configuración queues
9. Identificar migraciones pendientes / estado
10. Identificar estado Git

### Contenido de `BASELINE_REPORT.md`

- Tests existentes
- Comandos utilizados
- Resultados
- Build status
- Módulos afectados
- Endpoints Restaurante
- Tablas Restaurante
- Riesgos actuales
- Archivos que serán modificados en Fase 1
- Estrategia de DB para tests (decisión)

### Archivos

| Archivo | Acción |
|---------|--------|
| `BASELINE_REPORT.md` | **Crear** |

Ningún PHP/TS de producción en esta fase.

### Criterio go/no-go hacia Fase 1

**No** arrancar P0 si:

- No hay forma de ejecutar al menos tests de integridad contra MariaDB (ni alternativa acordada), **o**
- Existen sesiones activas duplicadas en ambientes que se migrarán sin plan de cleanup.

---

## 3. Fase 1 — P0: Integridad, concurrencia e idempotencia

Esta fase es obligatoria antes de considerar Restaurante Production Ready.

### 3.1 Abrir mesa

**Archivo:** `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` (`store`)

**Problema:** patrón check → create sin protección concurrente.

**Implementar:**

```
DB::transaction {
  Mesa::where(empresa)->lockForUpdate()->findOrFail
  re-check sesión activa (abierta|pre_cuenta)
  si existe → 422 consistente
  create sesión + mesa.estado = ocupada
}
```

La integridad debe estar en backend; no depender del frontend.

**Migración candidata (MariaDB):**  
No hay unique partial index nativo. Alternativa segura:

```sql
mesa_sesion_activa_id = IF(estado IN ('abierta','pre_cuenta'), mesa_id, NULL)
UNIQUE(mesa_sesion_activa_id)
```

- Múltiples `NULL` = muchas sesiones cerradas OK
- Una sola sesión activa por mesa

**Antes de migrar:**

1. Contar duplicados activos actuales
2. Si hay >0: remediación (cerrar/merge) antes del unique
3. Verificar compatibilidad / NULL / histórico
4. Probar rollback

**Tests:** apertura OK; segunda apertura; dos aperturas concurrentes → 1 sola activa.

**Resultado esperado:** nunca dos sesiones activas simultáneas para la misma mesa.

---

### 3.2 Agregar / fusionar producto

**Archivo:** `OrdenDetalleController@store`

**Implementar:**

```
DB::transaction {
  SesionMesa lockForUpdate (empresa + estado abierta|pre_cuenta)
  buscar línea fusionable
  si existe: lockForUpdate + sumar cantidad (+ limpiar extras)
  si no: create
  validar stock dentro de la tx (después del lock)
}
```

**Tests:**

- Dos requests simultáneos mismo producto
- Dos requests simultáneos productos diferentes
- Retry del mismo request

---

### 3.3 Enviar comanda

**Archivo:** `ComandaController@store` (+ helper `crearComandaSesion`)

**Implementar:**

```
DB::transaction {
  lock sesión
  lock líneas pendientes (lockForUpdate)
  filtrar pendientes cocina/barra
  crear comandas
  UPDATE ... SET enviado_* = 1 WHERE id IN (...) AND enviado_* = 0
  si affected rows != esperados → rollback / conflicto
}
```

Mantener separación cocina/barra y `destino_comanda`.

**Resultado:** imposible generar dos comandas para las mismas líneas pendientes por carrera.

**Tests concurrentes** de doble envío / doble click / retry.

---

### 3.4 Marcar precuenta como facturada

**Archivo:** `PreCuentaController@marcarFacturada`

**Implementar operación atómica:**

```
DB::transaction {
  PreCuenta lockForUpdate
  validar empresa / estado / factura_id / sesión / mesa
  si ya facturada → respuesta idempotente (NO reliquidar, NO doble cierre)
  marcar factura
  liquidar orden
  cerrar sesión si corresponde
  liberar mesa
}
```

**Contrato FE a preservar:** callers en `facturacion*.ts` esperan shape con `sesion_cerrada`.

**Tests:**

- Dos facturaciones simultáneas
- Retry después de timeout
- Factura ya procesada

---

### 3.5 Liquidación

**Método:** `liquidarOrdenTrasFacturarPreCuenta`

- Eliminar N+1 de SELECTs donde sea seguro
- Bulk update / soft-delete cuando no rompa auditoría/timestamps
- Primero correctness, después performance
- Si auditing exige filas individuales: loop corto **dentro** de tx con datos ya cargados (sin N+1)

---

### 3.6 Doble submit frontend (agregar producto)

**Archivos:**

- `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/*`
- `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.*`

**Patrón existente a replicar:** `guardando`, `enviandoComanda`, `solicitandoCuenta`, `trasladando`.

Agregar `enviandoAgregar` (o equivalente):

- Bloquear botón / submit múltiple
- Recuperar en success / error / `finalize`

**Importante:** mejora UX; **no** reemplaza protección backend.

---

### 3.7 Inventario / confirmar pedido canal

**Archivos:**

- `PedidoRestauranteController@confirmar` (+ `anular` mínimo si hace falta limpiar flag)
- `PedidoCanalInventarioService`

**Campo existente:** `inventario_descontado_at` (migración `2026_03_31_120000_add_inventario_pedido_restaurante.php`).

**No inventar columnas nuevas** si el modelo ya las tiene.

**Implementar en el service (cubre también BoxFul):**

```
lock pedido
si ya inventario_descontado_at → skip salidas
aplicar salidas + set inventario_descontado_at
```

En `confirmar`: lock + estado `borrador`; respuesta idempotente si ya confirmado cuando sea seguro.

En `anular`: revertir salidas y limpiar `inventario_descontado_at`.

**Garantía:** una operación lógica = un descuento de inventario.

---

### 3.8 Qué NO va en Fase 1

- Idempotency-Key header (Fase 2)
- DTO `/mesas`, Redis, índices nuevos, OnPush, Reverb
- Policies / global scopes
- Cambios a facturación Ventas salvo preservar contrato de `marcarFacturada`

---

## 4. Fase 2 — P1: API, seguridad y frontend

### 4.1 DTO liviano `GET /mesas`

- Respuesta mínima para mapa: id, numero, capacidad, estado, zona_id, zona, sesion_id, opened_at, badges
- Verificar frontend antes de quitar campos
- Preferir `select()` + relaciones limitadas
- Mantener eager loading correcto actual

### 4.2 `id_empresa` en `comandas_restaurante`

Antes:

1. Estructura actual
2. Todas las escrituras
3. Relaciones
4. Datos existentes / backfill
5. Índice `(id_empresa, estado, created_at)` solo si EXPLAIN lo justifica

Actualizar creación de comandas y query de cocina. Test multi-tenant.

### 4.3 Bloquear update de ítem enviado

`OrdenDetalleController@update`: rechazar si ya enviado cocina/barra según reglas. Backend autoridad. Test HTTP directo.

### 4.4 Endurecer cierre de sesión

`SesionMesaController@cerrar`: validar pre-cuentas / ítems / comandas / facturación. Documentar estados que permiten cierre forzado. No cambiar reglas de negocio sin documentarlas.

### 4.5 Idempotency-Key

Middleware/service reutilizable, pequeño y testeable.

Header: `Idempotency-Key`

Primera ola de endpoints:

- abrir mesa
- agregar producto
- enviar comanda
- solicitar cuenta
- marcar facturada
- confirmar pedido (si aplica)

Clave lógica: empresa + usuario + operación + key.

Reglas: no re-ejecutar; devolver resultado original; manejar “en progreso”; TTL; no guardar secretos.

Redis opcional para storage; **integridad crítica sigue apoyada en DB locks/constraints**.

### 4.6 Multi-tenancy

Revisar `exists:` sin scope de empresa (`zona_id`, `mesa_id`, etc.).  
Un usuario de empresa A jamás manipula recursos de B.  
Evaluar Policies si aportan valor.  
**No** introducir global scopes automáticamente.  
Tests cross-tenant.

---

## 5. Fase 3 — P2: Performance y base de datos

### 5.1 Redis cache mapa

- Cache corto 2–5s por empresa + sucursal
- Invalidación en abrir/cerrar/facturar/trasladar/reservar relevante
- Usar infra Redis existente; medir antes/después

### 5.2 Bulk updates

- `crearComandaSesion`, liquidación, loops UPDATE
- No bulk si se pierden eventos/auditoría necesarios; documentar

### 5.3 Índices (solo con EXPLAIN)

Candidatos:

| Índice | Tabla | Consulta |
|--------|-------|----------|
| `(sesion_id, producto_id, enviado_cocina, enviado_barra)` | `orden_detalle_restaurante` | fusión items |
| `(id_empresa, estado, created_at)` | `comandas_restaurante` | cocina (tras columna empresa) |
| `(mesa_id, estado)` | `restaurante_sesiones_mesa` | sesión activa / abrir |

Por cada índice: consulta, EXPLAIN antes/después, impacto, migración reversible.

---

## 6. Fase 4 — Angular 22

1. Evaluar `OnPush` en `RestauranteComponent` (y relacionados) con verificación de modales/estados
2. Migrar `*ngFor` → `@for (...; track mesa.id)` donde corresponda
3. Eliminar recálculo excesivo de `mesasPorZona` (computed/memo/estado preparado; Signals solo si aportan)
4. Eliminar HTTP duplicado de `getZonas`
5. `takeUntilDestroyed` en subscriptions de larga vida

---

## 7. Fase 5 — Operaciones síncronas / queues

Revisar impresión HTML, notificaciones, documentos no críticos.

**No** mover a queue operaciones que necesiten respuesta transaccional inmediata (abrir mesa, agregar ítem, enviar comanda, facturar, descontar inventario).

---

## 8. Fase 6 — Realtime (diseño primero)

- **No** polling cada 5s
- Evaluar Laravel Reverb
- Canales por tenant; payloads mínimos (`mesa_id`, `estado`, `sesion_id`)
- Mapa / cocina / barra / admin
- Si Reverb no es necesario para primera salida productiva: diseño + stub; no bloquear fases anteriores

---

## 9. Fase 7 — Histórico y crecimiento

Documento: `RESTAURANTE_DATA_GROWTH.md`

- Estimación anual
- Índices
- Consultas históricas
- Política de archivo / retención
- Cuándo particionar (no implementar particionado automáticamente)

---

## 10. Fase 8 — Servicios legacy

`PedidoRestauranteInventarioService`:

- Buscar referencias / imports / uso indirecto / tests
- Documentar
- Eliminar solo si cero dependencias y tests pasan

---

## 11. Fase 9 — Reportes

No inventar reportes. Documentar arquitectura futura: queue, cache, tablas agregadas, réplica; **nunca** agregaciones pesadas en el request path de meseros.

---

## 12. Fase 10 — Observabilidad

Detectar:

- duplicate session / command attempts
- idempotency hits
- lock waits
- slow endpoints/queries
- facturación duplicada
- inventory double confirm
- 422 mesa ocupada
- 5xx / 502 / 504

Contexto de log: empresa, sucursal, usuario, mesa, sesión, endpoint, request id, idempotency key.  
Sin datos sensibles. Integrar con logging existente.

---

## 13. Fase 11 — Tests (suite completa)

Mínimo:

1. Abrir mesa
2. Abrir misma mesa simultáneamente
3. Agregar producto
4. Agregar mismo producto simultáneamente
5. Enviar comanda
6. Enviar comanda simultáneamente
7. Solicitar cuenta
8. Facturar
9. Facturar simultáneamente
10. Retry de POST
11. Idempotency-Key repetida
12. Modificar ítem enviado
13. Cerrar mesa con cuenta pendiente
14–17. Cross-tenant mesa/sesión/comanda/precuenta

Integridad:

- 0 sesiones activas duplicadas por mesa
- 0 doble comanda de mismas líneas
- 0 doble liquidación
- 0 doble descuento inventario

---

## 14. Fases 12–13 — Load test k6 + métricas

**No** crear load test antes de estabilizar P0.

### Perfiles

| Perfil | % | Acciones |
|--------|---|----------|
| WAITER | 55% | login → mesas → abrir ocasional → sesión → menú → 3–8 items → modificar ocasional → comanda → sesión → precuenta ocasional |
| CASHIER | 25% | preparar factura → ventas real → marcar → mesas ocasional |
| KITCHEN | 15% | GET comandas → estado → imprimir ocasional |
| ADMIN | 5% | mesas / zonas / reservas |

### Escenarios

1. NORMAL 3–5
2. MEDIO 13–15
3. ALTO 35–40
4. PEAK AGRO-MALL 40–50
5. PEAK + SMARTPYME

Ramp ≥15 min por nivel. 5% retries en POST críticos.

### Thresholds iniciales

| Métrica | Objetivo |
|---------|----------|
| GET mapa/sesión p95 | < 500ms |
| items/comandas p95 | < 1s |
| facturación p99 | < 3s |
| error rate | < 1% |
| integridad | 0 dup sesiones/comandas/liquidaciones/descuentos |

### Observabilidad durante prueba

App (p50/p95/p99, RPS, errors), PHP-FPM, MariaDB, Redis, Nginx, servidor (CPU/RAM/swap/IO/net).

No ejecutar a ciegas en producción; empresa/sucursal de prueba; cleanup definido.

---

## 15. Fase 14 — Shared VPS / noisy neighbor

Medir impacto de reportes/queues/backups/ETL de otros módulos/tenants durante Peak Restaurante.  
Proponer límites, horarios, queues, prioridades, índices u aislamiento **después** de medir.

---

## 16. Fase 15 — Auditoría final

Documento: `RESTAURANTE_PRODUCTION_READINESS.md`

Comparar ANTES vs DESPUÉS por cada recomendación P0/P1/P2/P3:

- [ ] corregido
- [ ] parcialmente corregido
- [ ] no aplica
- [ ] pendiente

Incluir arquitectura final, migraciones, índices, Redis, idempotencia, concurrencia, seguridad, Angular, realtime, tests, load test, observabilidad, riesgos restantes, recomendaciones futuras.

---

## 17. Migraciones necesarias (resumen)

| # | Migración | Fase | Condición |
|---|-----------|------|-----------|
| M1 | Unique sesión activa vía columna generada `mesa_sesion_activa_id` en `restaurante_sesiones_mesa` | 1 | Solo si audit SQL = 0 duplicados activos |
| M2 | (Opcional data) Backfill `inventario_descontado_at` en pedidos ya no-borrador | 1 | Si hay filas inconsistentes |
| M3 | `id_empresa` + backfill + índice en `comandas_restaurante` | 2 | Tras verificar escrituras/datos |
| M4+ | Índices compuestos justificados por EXPLAIN | 3 | Tras medición |

Rollback: `down()` debe ser reversible y probado.

---

## 18. Archivos a modificar — Fase 0 + Fase 1

### Crear

- `BASELINE_REPORT.md`
- `Backend/database/migrations/XXXX_add_unique_active_sesion_mesa.php` (condicional)
- `Backend/database/migrations/XXXX_backfill_inventario_descontado_at_pedidos.php` (condicional)
- `Backend/tests/Feature/Restaurante/AbrirMesaConcurrencyTest.php` (nombre final TBD)
- `Backend/tests/Feature/Restaurante/OrdenDetalleConcurrencyTest.php`
- `Backend/tests/Feature/Restaurante/ComandaConcurrencyTest.php`
- `Backend/tests/Feature/Restaurante/PreCuentaFacturarIdempotencyTest.php`
- `Backend/tests/Feature/Restaurante/PedidoConfirmarInventarioIdempotencyTest.php`
- Posible `Backend/tests/Support/RestauranteTestHelpers.php`
- `FASE1_REPORT.md` al cerrar Fase 1

### Modificar

- `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php`
- `Backend/app/Http/Controllers/Api/Restaurante/OrdenDetalleController.php`
- `Backend/app/Http/Controllers/Api/Restaurante/ComandaController.php`
- `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php`
- `Backend/app/Http/Controllers/Api/Restaurante/PedidoRestauranteController.php` (confirmar/anular mínimos)
- `Backend/app/Services/Restaurante/PedidoCanalInventarioService.php`
- `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar.component.ts/html`
- `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.ts/html` (si el flag vive en el padre)

### No tocar en Fase 1

- Controllers de Ventas (salvo verificar callers)
- `MesaController` DTO
- Redis/cache
- Routing público / nombres de URL
- BoxFul salvo el efecto colateral positivo del guard en el service compartido

---

## 19. Estrategia de tests (decisión)

| Opción | Pros | Contras |
|--------|------|---------|
| **A. MariaDB testing + `DatabaseTransactions`** | Realista para locks/unique | Requiere DB configurada + seed |
| **B. SQLite `:memory:` + migraciones mínimas** | CI local fácil | Locks/unique generadas pueden diferir de MariaDB |
| **C. Solo secuencial + script stress manual** | Débil para races | No cumple criterio de tests concurrentes |

**Propuesta del plan:** Opción **A** para tests de integridad.

Si Fase 0 detecta que no hay DB testing: reportarlo; incluir tests HTTP secuenciales de retry + assert de unique, más script/artisan de stress concurrente opcional, y acordar cómo cerrar el gap.

---

## 20. Riesgos por cambio (Fase 1)

| Cambio | Riesgo | Severidad | Mitigación |
|--------|--------|-----------|------------|
| Unique sesión activa | Datos históricos con 2 activas | Crítico | Audit SQL + cleanup antes de migrate |
| Locks abrir/items/comanda | Deadlocks / latencia p99 | Medio | Orden de locks estable; tx cortas |
| Fusion items con lock | Contención en sesión caliente | Medio | Aceptable; medir en load test |
| Update condicional comanda | Segunda request 422 | Bajo | Comportamiento correcto / idempotente de facto |
| Marcar facturada idempotente | Shape response distinto | Alto | Preservar contrato FE |
| Liquidación bulk | Perder auditing | Medio | Preferir load + loop corto si auditing lo exige |
| `inventario_descontado_at` | Pedidos viejos sin flag | Medio | Backfill opcional; guard forward |
| Service inventario | BoxFul path | Alto | Guard en service, no solo controller |
| FE `enviandoAgregar` | Botón stuck | Medio | `finalize()` RxJS |
| Tests sin DB | Falso verde | Alto | Fase 0 diagnostica; no fingir cobertura |

### Riesgos transversales

| Riesgo | Severidad | Manejo |
|--------|-----------|--------|
| Idempotencia HTTP completa aplazada a Fase 2 | Medio | Locks cubren integridad; retries sin key pueden 422 legítimos |
| Contención PHP-FPM por locks largos | Medio | No meter trabajo extra en abrir mesa |
| Breaking response shape | Alto | Mantener JSON actual |

---

## 21. Entregables por parada

| Momento | Entregable |
|---------|------------|
| Fin Fase 0 | `BASELINE_REPORT.md` + verdicto go/no-go |
| Fin Fase 1 | Código P0 + migraciones + tests + `FASE1_REPORT.md` + pendientes |
| Fin cada fase posterior | Reporte corto + checklist |
| Fin proyecto | `RESTAURANTE_PRODUCTION_READINESS.md` |

---

## 22. Criterio final de aceptación (Production Ready)

No declarar Production Ready solo porque compile / tests pasen / load test responda.

Debe cumplirse:

- [ ] No existen races críticas conocidas
- [ ] No existen sesiones activas duplicadas
- [ ] No existen comandas duplicadas por concurrencia
- [ ] No existe doble liquidación
- [ ] No existe doble descuento de inventario
- [ ] POST críticos tienen protección de idempotencia donde corresponda
- [ ] Multi-tenancy validado
- [ ] API no depende del frontend para seguridad
- [ ] GET /mesas tiene payload razonable
- [ ] Queries críticas tienen índices justificados
- [ ] No existen N+1 relevantes
- [ ] Angular no hace trabajo innecesario evidente
- [ ] Load test Peak completado
- [ ] Peak + Smartpyme completado
- [ ] PHP-FPM no alcanza max_children de manera sostenida
- [ ] MariaDB no presenta lock contention crítico
- [ ] Redis estable
- [ ] Nginx estable
- [ ] Error rate dentro del objetivo
- [ ] p95/p99 dentro de objetivos
- [ ] Tests de concurrencia pasan
- [ ] Logs permiten investigar incidentes
- [ ] Rollback de migraciones probado
- [ ] No existen breaking changes no documentados

---

## 23. Confirmación requerida para ejecutar

Para proceder se necesita OK explícito a:

1. Ejecutar **Fase 0** (baseline + `BASELINE_REPORT.md`).
2. Si baseline OK, ejecutar **Fase 1** según este plan.
3. **Parar** al terminar Fase 1 y reportar (sin avanzar a Fase 2).
4. Estrategia de tests: confirmar **Opción A (MariaDB testing)** u otra.

Ejemplo de confirmación:

```text
OK Fase 0+1, tests opción A
```

---

## 24. Mapa de fases vs prioridades auditoría

| Prioridad auditoría | Fase plan | Contenido |
|---------------------|-----------|-----------|
| P0 races + doble submit + inventario | Fase 1 | Locks, unique, liquidación, FE, inventario |
| P1 DTO, comandas empresa, update ítem, cerrar, idempotency, multi-tenant | Fase 2 | API/seguridad |
| P2 Redis, bulk, índices | Fase 3 | Performance DB |
| Angular OnPush/track/getter/HTTP | Fase 4 | FE performance |
| Queues / sync heavy | Fase 5 | Arquitectura ops |
| Realtime | Fase 6 | Diseño Reverb |
| Histórico | Fase 7 | Doc crecimiento |
| Legacy service | Fase 8 | Limpieza |
| Reportes futuros | Fase 9 | Doc |
| Observabilidad | Fase 10 | Logs/métricas |
| Tests completos | Fase 11 | Suite |
| Load test | Fases 12–13 | k6 |
| Noisy neighbor | Fase 14 | Infra |
| Auditoría final | Fase 15 | Readiness |

---

## 25. Instrucciones técnicas obligatorias (aprobación 2026-08-09)

Estas reglas forman parte del plan aprobado y prevalecen sobre atajos de implementación.

### 25.1 Tests de concurrencia

- NO depender de una única transacción envolvente del test.
- Cada actor concurrente debe usar conexiones/transacciones independientes.
- NO considerar válido un test que solo ejecute dos llamadas secuenciales para escenarios concurrentes.
- Debe demostrar: requests simultáneos, tx independientes, `lockForUpdate()`, commit/rollback, retry.
- Si PHPUnit no puede reproducirlo: documentar el gap; NO crear falso test verde.

### 25.2 Constraint de sesión activa

Antes de la columna generada/unique:

- Auditar duplicados actuales
- Identificar estados existentes
- Verificar compatibilidad MariaDB 10.11 (`GENERATED ALWAYS AS` / `STORED`)
- Comprobar NULL en UNIQUE
- Comprobar migración Laravel + rollback

No ejecutar la migración si hay inconsistencias sin resolver.  
La DB es segunda línea de defensa; locks siguen siendo la primera.

### 25.3 Hot Table (load test — Fase 12+)

10–20 usuarios concurrentes sobre la **misma** mesa/sesión: agregar, fusionar, enviar comanda, retries.  
Medir: lock waits, deadlocks, p95/p99, 409/422, duplicados, estado final sesión/líneas/comandas.

### 25.4 Hot Product / inventario (load test — Fase 12+)

Muchos usuarios agregando/confirmando el mismo producto.  
Verificar: sin doble descuento, sin lost updates, stock final correcto, `inventario_descontado_at` consistente, sin salidas duplicadas.

### 25.5 Retry real

Simular: POST OK → cliente pierde respuesta → reintento.  
Segundo request NO debe generar segunda operación lógica (completo con Idempotency-Key en Fase 2; en Fase 1 vía locks/constraints/estado).

### 25.6 Separar integridad de performance

Integridad = absoluta (0 duplicados / 0 doble liquidación / 0 doble descuento / 0 cross-tenant).  
Performance = p95/p99, RPS, CPU, etc.

### 25.7 Redis no es fuente de verdad

Redis: cache / idempotency storage / realtime.  
Integridad crítica: MariaDB + transacciones + locks + constraints.

### 25.8 Load test realista

Perfiles WAITER/CASHIER/KITCHEN/ADMIN + Hot Table + Hot Product + Retry/timeout.  
Think times. Ambiente de prueba ≈ producción. NO load test destructivo inicial contra producción.

### 25.9 Observabilidad

Correlacionar: request ID, empresa, sucursal, usuario, mesa, sesión, endpoint, Idempotency-Key.

### 25.10 Orden de ejecución aprobado

```
FASE 0 → BASELINE_REPORT.md → GO/NO-GO
→ (si GO) FASE 1 → FASE1_REPORT.md → DETENERSE
```

No ejecutar Fase 2+ automáticamente.

---

## Regla final

No se busca “código nuevo” por sí mismo.

Se busca un módulo Restaurante:

**ROBUSTO · CONSISTENTE · ESCALABLE · OBSERVABLE · MULTI-TENANT · IDEMPOTENTE · RESISTENTE A CONCURRENCIA · OPTIMIZADO · TESTEADO · PREPARADO PARA PRODUCCIÓN**

El Agro-Mall es el primer benchmark real, no el único caso de uso.
