# FASE 5 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §7 (Operaciones síncronas / queues)  
**Predecesoras:** Fase 1–4 cerradas y validadas  
**Estado:** Fase 5 — **COMPLETADA DENTRO DEL ALCANCE**  
**Siguiente:** **DETENERSE** — no iniciar Fase 6+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen

Revisión de impresión HTML / notificaciones y **separación post-commit** de trabajo no crítico:

1. **GET imprimir** (comanda / pre-cuenta) sigue **síncrono** (el browser necesita HTML en la respuesta).
2. Tras crear comanda / pre-cuenta, se encola un **side-effect** (`afterCommit`) que calienta cache del ticket + emite notificación/log.
3. **Outbox en MariaDB** (`restaurante_side_effects`) = SoT de “ya procesado”; queue/Redis no son fuente de verdad de integridad.
4. Jobs **idempotentes** ante retries (`status=done` + `Cache::add` de notify + `ShouldBeUnique`).
5. Operaciones de negocio (abrir mesa, ítems, enviar comanda, facturar, inventario) **no** se movieron a queue.

---

## 2. Análisis previo (estado actual)

| Superficie | Antes de Fase 5 | Decisión |
|------------|-----------------|----------|
| `GET .../comandas/{id}/imprimir` | Blade sync → HTML | **Mantener sync**; render vía `RestauranteTicketHtmlService` (+ cache opcional) |
| `GET .../pre-cuentas/{id}/imprimir` | Blade sync → HTML | Igual |
| `GET .../pedidos/{id}/imprimir` | Blade sync → HTML | **Sin queue** (ticket canal; documentado) |
| Enviar comanda / eliminar ítem / solicitar cuenta | Solo DB en transacción; FE imprime después con GET | Encolar side-effect **después** del commit |
| Notificaciones restaurante | **No existían** | Canal `RestauranteNotifier` (log hoy; extensible) |
| Abrir mesa / agregar ítem / facturar / inventario | Crítico | **No encolar** |

---

## 3. Cambios realizados

### 3.1 Outbox + dispatcher

- Tabla `restaurante_side_effects` (unique `type+resource_type+resource_id`)
- `RestauranteSideEffectDispatcher::enqueue*` → `firstOrCreate` + `Job::dispatch()->afterCommit()`
- Fallo de enqueue **no** rompe la respuesta de negocio (try/catch + log)

### 3.2 Job

- `ProcesarSideEffectRestauranteJob` (`ShouldQueue`, `ShouldBeUnique`, retries 3, backoff)
- Tipos: `comanda_ticket_notify`, `precuenta_ticket_notify`
- Trabajo: `remember*Html` (cache) + `Cache::add` + `RestauranteNotifier::notify`
- Marca outbox `done` / `failed`

### 3.3 Cableado post-commit

| Evento | Encola |
|--------|--------|
| `ComandaController::store` | por cada comanda creada |
| `PedidoRestauranteController::enviarComanda` | por cada comanda |
| `OrdenDetalleController::eliminar` | comanda eliminación |
| `PreCuentaController::generar` / `dividir` | por cada pre-cuenta |

### 3.4 Config

- `config/restaurante.php` + vars opcionales en `.env.example`
- Default `QUEUE_CONNECTION=sync` sigue válido (job corre tras commit en el mismo request)

---

## 4. Archivos creados / modificados

### Creados

| Archivo | Rol |
|---------|-----|
| `Backend/database/migrations/2026_08_10_150000_create_restaurante_side_effects_table.php` | Outbox SoT |
| `Backend/app/Models/Restaurante/RestauranteSideEffect.php` | Modelo |
| `Backend/app/Jobs/Restaurante/ProcesarSideEffectRestauranteJob.php` | Job idempotente |
| `Backend/app/Services/Restaurante/RestauranteSideEffectDispatcher.php` | Encolado afterCommit |
| `Backend/app/Services/Restaurante/RestauranteTicketHtmlService.php` | Render + cache ticket |
| `Backend/app/Services/Restaurante/RestauranteNotifier.php` | Notif/log no crítica |
| `Backend/config/restaurante.php` | Flags TTL/queue |
| `Backend/tests/Feature/Restaurante/Phase5SideEffectsTest.php` | Tests Fase 5 |
| `FASE5_REPORT.md` | Este reporte |

### Modificados

| Archivo | Cambio |
|---------|--------|
| `ComandaController` | enqueue + imprimir vía service |
| `PreCuentaController` | enqueue + imprimir vía service |
| `OrdenDetalleController` | enqueue tras eliminar |
| `PedidoRestauranteController` | enqueue tras enviar comanda |
| `Backend/.env.example` | comentarios vars Fase 5 |

---

## 5. Migraciones / infra

| Cambio | ¿Necesario? | Nota |
|--------|-------------|------|
| Migración `restaurante_side_effects` | **Sí** | Idempotencia durable sin hacer de Redis el SoT |
| Cambio Redis/queue drivers | **No** | Sigue `QUEUE_CONNECTION=sync` por defecto |
| Worker dedicado prod | Recomendado ops | Con `redis`/`database` + worker; no bloquea Fase 5 |

Migración aplicada en entorno local de pruebas.

---

## 6. Tests y resultados

### Suite Restaurante

```text
php artisan test tests/Feature/Restaurante
Tests: 39 passed (164 assertions)
```

Incluye: Concurrency (F1) + Phase2 + Phase3 + PosMenu + **Phase5 (5)**.

### Phase5SideEffectsTest

| Test | Verifica |
|------|----------|
| `enviar_comanda_dispatches...` | Outbox + job tras enviar comanda |
| `side_effect_job_is_idempotent_on_retry` | 2× handle → 1 notify; Cache::add evita 2ª |
| `failed_job_marks_failed_and_retry_can_succeed` | failed → retry → done |
| `rollback_does_not_persist_outbox_row` | rollback de TX revierte outbox |
| `duplicate_enqueue_does_not_create_second_outbox_row` | unique outbox |

### Deuda FE (no tocada)

Karma / specs legacy de Ventas (`async` de `@angular/core/testing`) sigue rompiendo `ng test` — **deuda existente**, fuera de Fase 5 (igual que Fase 4).

---

## 7. Problemas encontrados

1. Con `QUEUE_CONNECTION=sync`, el job puede ejecutarse al encolar si no hay TX abierta; los tests usan `Queue::fake()` o invocan `handle()` manualmente.
2. `Queue::fake()` + `afterCommit` en un rollback puede registrar push en algunos casos; el criterio de integridad usado es **ausencia de fila outbox** tras rollback.
3. No hay impresora/servidor de print ni webhooks de restaurante aún: la “notif” es log estructurado (extensible).

---

## 8. Decisiones técnicas

1. **No encolar GET imprimir** ni operaciones P0 de negocio.
2. **MariaDB outbox** como SoT de idempotencia del side-effect.
3. Cache de HTML = aceleración; miss → re-render sync en GET.
4. Enqueue best-effort: no degrada el 201 de comanda/pre-cuenta.
5. **Sin Reverb / realtime** (Fase 6).

---

## 9. Desviaciones del plan

| Plan | Hecho | Nota |
|------|-------|------|
| “Solo análisis + cambios seguros” | Análisis + implementación mínima de jobs | Autorización GO pedía jobs retry-safe + tests |
| Notificaciones reales (push/email) | Log/notifier stub | No había canal restaurante previo |
| Pedido canal ticket a queue | No | Sigue GET sync; reportado |

---

## 10. Riesgos pendientes / hallazgos Fase 6+

| Ítem | Fase | Acción |
|------|------|--------|
| Reverb / realtime mapa-cocina | **6** | No tocado |
| Worker + `QUEUE_CONNECTION=redis` en prod | ops | Documentar en readiness |
| Webhook/impresora de cocina | futuro | Extender `RestauranteNotifier` |
| Load test / k6 | 12–13 | No tocado |
| Karma legacy Ventas | deuda FE | No corregido |
| Pedido canal `imprimir` sin side-effect | menor | Opcional si se unifica |

---

## 11. Criterios de completitud de Fase 5

- [x] Revisar impresión HTML y notificaciones actuales
- [x] Separar TX de negocio de procesamiento asíncrono no crítico
- [x] Jobs retry-safe / sin duplicar notificaciones
- [x] Dispatch `afterCommit` (o equivalentemente tras commit explícito)
- [x] Redis/queue ≠ SoT de integridad (outbox MariaDB)
- [x] Tests de jobs / retries / fallo / rollback
- [x] Suite Restaurante completa verde (39/39)
- [x] Sin Fase 6+ (Reverb, k6, etc.)
- [x] Karma Ventas no modificado (deuda documentada)

---

## 12. Siguiente paso — DETENERSE

**Fase 5 lista para revisión.** Commit manual pendiente.

**No iniciar Fase 6** (Realtime / Reverb) ni fases posteriores hasta nueva autorización explícita.
