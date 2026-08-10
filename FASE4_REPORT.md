# FASE 4 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §6 (Angular 22) + FE `Idempotency-Key` autorizado  
**Predecesoras:** Fase 1 + Fase 2 + Fase 3 cerradas y validadas  
**Estado:** Fase 4 — **COMPLETADA DENTRO DEL ALCANCE**  
**Siguiente:** **DETENERSE** — no iniciar Fase 5+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen

Implementada la capa Angular de performance/higiene de subscriptions y el cableado FE de `Idempotency-Key` hacia los endpoints ya endurecidos en Fase 2. **Sin** queues, Reverb, load tests ni cambios de integridad P0/P1 salvo el transporte HTTP necesario para no borrar headers custom.

Entregables §6:

1. **OnPush** en mapa, cocina, zonas, cuenta-mesa y POS hijos
2. **`*ngFor` → `@for (...; track …)`** en vistas Restaurante (0 `*ngFor` restantes bajo `views/restaurante`)
3. **`mesasPorZona` preparado** (no getter en cada CD) + helper puro testeable
4. **GET `/zonas` una sola vez** (`zonasLoadStarted` evita duplicado in-flight)
5. **`takeUntilDestroyed`** + `markForCheck` en flujos async OnPush
6. **FE Idempotency-Key** (autorizado) en abrir mesa / agregar ítem / enviar comanda / solicitar cuenta / marcar facturada / confirmar pedido

---

## 2. Cambios realizados

### 2.1 Angular OnPush + CD

| Componente | Cambio |
|------------|--------|
| `RestauranteComponent` | OnPush, `markForCheck`, `takeUntilDestroyed` |
| `CocinaComponent` | OnPush; listas `comandasPendientes` / `comandasListas` preparadas |
| `ZonasRestauranteComponent` | OnPush + subscriptions acotadas |
| `CuentaMesaComponent` | OnPush + `markForCheck` en mutaciones async/UI |
| `PosCatalogoComponent` | OnPush; search debounce con `takeUntilDestroyed` |
| `PosFlujoCuentaComponent` | OnPush |
| `PosSheetAgregarComponent` | OnPush |

### 2.2 `@for` / track

Migrados loops de mesas/zonas/comandas/ítems/precuentas/traslado/catálogo/división de cuenta a `@for` con `track` por `id` (o `nombre`/`p` donde aplica).

### 2.3 `mesasPorZona`

- Helper puro: `Frontend/src/app/views/restaurante/mesas-por-zona.ts` → `buildMesasPorZona`
- Estado `mesasPorZona: MesaZonaGrupo[]` reconstruido al cargar mesas / cambiar filtro
- Spec Jasmine + check Node runnable

### 2.4 HTTP zonas

- `cargarZonas()` early-return si `zonasLoadStarted`
- Modal de mesa solo pide zonas si aún no cargaron (`!zonasCargadas`)

### 2.5 Idempotency-Key (FE)

- `JwtInterceptor`: `setHeaders` (fusiona; no pisa `Idempotency-Key`)
- `HttpService.store` / `putToUrl` + `ApiService`: `extraHeaders` opcional
- `RestauranteService.withIdempotency(scope)`: reusa key in-flight/error; **limpia solo en éxito** (retry/timeout conserva key)
- `agregarItem`: key **nueva por clic** (altas intencionales no deben colisionar)

Scopes:

- `abrir_mesa:{mesaId}`
- `enviar_comanda:{sesionId}`
- `solicitar_cuenta:{sesionId}`
- `marcar_facturada:{preCuentaId}:{facturaId}`
- `confirmar_pedido:{id}`

---

## 3. Archivos modificados / creados

### Creados

| Archivo | Rol |
|---------|-----|
| `Frontend/src/app/views/restaurante/mesas-por-zona.ts` | Agrupación pura mapa |
| `Frontend/src/app/views/restaurante/mesas-por-zona.spec.ts` | Jasmine (suite karma global rota por specs legacy) |
| `Frontend/src/app/views/restaurante/mesas-por-zona.check.mjs` | Self-check Node runnable |
| `FASE4_REPORT.md` | Este reporte |

### Modificados

| Archivo | Cambio |
|---------|--------|
| `Frontend/src/app/services/JwtInterceptor.ts` | `setHeaders` |
| `Frontend/src/app/services/http.service.ts` | `extraHeaders` en store/putToUrl |
| `Frontend/src/app/services/api.service.ts` | Propaga `extraHeaders` |
| `Frontend/src/app/services/restaurante.service.ts` | Idempotency FE |
| `Frontend/.../restaurante.component.{ts,html}` | OnPush, mesasPorZona, @for, zonas once |
| `Frontend/.../cocina/cocina.component.{ts,html}` | OnPush, listas, @for |
| `Frontend/.../zonas/zonas-restaurante.component.{ts,html}` | OnPush, @for |
| `Frontend/.../cuenta-mesa/cuenta-mesa.component.{ts,html}` | OnPush, @for, CD |
| `Frontend/.../pos-catalogo/*` | OnPush, @for |
| `Frontend/.../pos-flujo-cuenta/*` | OnPush, @for |
| `Frontend/.../pos-sheet-agregar/pos-sheet-agregar.component.ts` | OnPush |

**Backend / migraciones:** sin cambios en Fase 4.

---

## 4. Detalle por ítem del plan §6

| # | Ítem plan | Estado |
|---|-----------|--------|
| 1 | OnPush Restaurante (+ relacionados) + modales/estados | Hecho |
| 2 | `*ngFor` → `@for` + track | Hecho (módulo restaurante) |
| 3 | Eliminar recálculo `mesasPorZona` | Hecho (estado + helper) |
| 4 | Eliminar HTTP duplicado `getZonas` | Hecho (`zonasLoadStarted`) |
| 5 | `takeUntilDestroyed` larga vida | Hecho (+ one-shots con DestroyRef por higiene OnPush) |
| — | FE Idempotency-Key (autorización GO) | Hecho |

Signals: **no** introducidos (plan: solo si aportan; estado preparado alcanza).

---

## 5. Tests y resultados

### Backend (regresión Fase 1–3)

```text
php artisan test tests/Feature/Restaurante
Tests: 34 passed
```

Suite Restaurante **sin regresiones** tras cambios FE.

### Frontend

| Check | Resultado |
|-------|-----------|
| `node .../mesas-por-zona.check.mjs` | **OK** |
| `mesas-por-zona.spec.ts` | Presente; alineado al check |
| `ng test --include='**/mesas-por-zona.spec.ts'` | **No ejecutable** en este entorno: Karma falla al cargar specs legacy de Ventas (`async` removido de `@angular/core/testing`). **Preexistente**, fuera de alcance Fase 4 |

No se “arreglaron” specs legacy de otras áreas (fuera de alcance).

---

## 6. Problemas encontrados

1. **Karma / specs legacy:** `async` de Angular testing en múltiples `*.spec.ts` de Ventas rompe el bootstrap de `ng test`. Documentado; no corregido en Fase 4.
2. **`ng build` local:** el primer intento en sandbox fue inestable; un reintento con más heap **completó OK** (`exit 0`, ~32 min). Verificación también: tsc limpio + self-check + suite BE.
3. Nada de integridad multi-tenant/seguridad que requiera detenerse más allá de confirmar que el interceptor ya no borra headers.

---

## 7. Decisiones técnicas

1. **OnPush + `markForCheck`** explícito en callbacks HTTP (ngx-bootstrap / modales no siempre disparan CD del host).
2. **`mesasPorZona` como array**, no Signal: menor superficie, mismo efecto.
3. **Idempotency client:** key estable por scope hasta **éxito**; en error se reusa (retry/timeout). `agregarItem` es excepción (key fresca por alta).
4. **Interceptor con `setHeaders`:** requisito para que `Idempotency-Key` llegue al backend.
5. **No Signals / no Reverb / no queues.**

---

## 8. Desviaciones del plan

| Plan | Hecho | Nota |
|------|-------|------|
| §6 no lista Idempotency FE | Incluido | Autorización explícita del GO |
| Signals opcionales | No | Estado preparado suficiente |
| Suite `ng test` limpia | No | Bloqueo legacy Ventas; check Node + BE suite |

---

## 9. Riesgos pendientes / hallazgos Fase 5+

| Ítem | Fase | Acción |
|------|------|--------|
| Impresión HTML / notificaciones a queue | **5** | No tocado |
| Reverb / realtime mapa-cocina | **6** | No tocado (sigue refresh manual) |
| Load test Peak / k6 | 12–13 | No tocado |
| Arreglar Karma legacy (`async`) | deuda FE | Documentado |
| Stale UI OnPush si falta `markForCheck` en path raro | ops/QA | Revisar modales en smoke manual |
| `marcarPreCuentaFacturada` desde facturación | OK vía service | Ya envía header |

---

## 10. Criterios de completitud de Fase 4

- [x] OnPush en `RestauranteComponent` y relacionados (cocina, zonas, cuenta, POS)
- [x] `@for` + track en listas del módulo
- [x] `mesasPorZona` sin recálculo por getter en cada CD
- [x] Sin GET `/zonas` duplicado in-flight / modal
- [x] `takeUntilDestroyed` en subscriptions del módulo
- [x] FE Idempotency-Key en ola de endpoints del plan (vía service)
- [x] Tests/checks: BE 34/34 + self-check `mesasPorZona`
- [x] Sin entregables Fase 5+
- [x] Sin commit (manual)

---

## 11. Siguiente paso — DETENERSE

**Fase 4 lista para revisión.** Commit manual pendiente.

**No iniciar Fase 5** (queues / impresión / notificaciones) ni fases posteriores hasta nueva autorización explícita.
