# FASE 6 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §8 (Realtime / Reverb)  
**Predecesoras:** Fase 1–5 cerradas y validadas  
**Estado:** Fase 6 — **COMPLETADA DENTRO DEL ALCANCE**  
**Siguiente:** **DETENERSE** — no iniciar Fase 7+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen

Capa **realtime de UI** para mapa y cocina, sin convertir WebSocket en dependencia de integridad:

1. Eventos `MapaMesasChanged` / `CocinaComandasChanged` con **`$afterCommit = true`**
2. Canal privado por tenant: `restaurante.empresa.{idEmpresa}` (auth JWT + misma empresa)
3. Emisión best-effort vía `RestauranteRealtimePublisher` (fallo de broadcast ≠ falla de negocio)
4. FE: Echo/Reverb **opcional** (`restauranteRealtime.enabled=false` por defecto); hints → **debounce + GET**
5. Reconexión / `visibilitychange` / `online` → **GET refresh** (SoT HTTP)
6. Sin Reverb instalado en servidor: APIs HTTP siguen funcionando igual

**MariaDB = SoT.** Realtime = hint para refrescar.

---

## 2. Arquitectura

```
[Mutación HTTP + TX MariaDB]
        │ commit OK
        ▼
RestauranteRealtimePublisher ──event(afterCommit)──► Broadcast (null|log|reverb|pusher)
        │
        ▼
PrivateChannel restaurante.empresa.{id}
        │
        ▼
Angular Echo (si enabled) ──debounce──► GET /mesas | GET /comandas
        │
        └─ si WS caído / disabled → UI manual + post-mutación (ya existente)
```

### No SoT / no críticos por WS

- Abrir mesa, ítems, comanda, facturar, inventario: **siguen HTTP + locks/idempotencia F1–5**
- Payload mínimo: `mesa_id`, `estado`, `sesion_id` / `comanda_id`, `destino`, `estado`, `reason`

---

## 3. Eventos y canales

| Evento | `broadcastAs` | Canal | Cuándo |
|--------|---------------|-------|--------|
| `MapaMesasChanged` | `.mapa.updated` | `private-restaurante.empresa.{id}` | abrir/cerrar/reactivar/trasladar, precuenta, reserva, mesa CRUD, marcar facturada, enviar comanda |
| `CocinaComandasChanged` | `.cocina.updated` | igual | enviar comanda (mesa/pedido), estado comanda, eliminar ítem |

Auth canal (`routes/channels.php`):

```php
(int) $user->id_empresa === (int) $idEmpresa
```

Broadcast auth route: `POST /api/broadcasting/auth` con middleware `jwt.auth`.

---

## 4. Frontend

| Pieza | Rol |
|-------|-----|
| `RestauranteRealtimeService` | Echo opcional; dedupe corto + debounce 400ms; leave/disconnect |
| `RestauranteComponent` | `watch('mapa')` + `onRecover` → `cargarMesas()` |
| `CocinaComponent` | `watch('cocina')` + `onRecover` → `cargarComandas()` |
| `environment*.restauranteRealtime` | `enabled: false` por defecto |

Deps: `laravel-echo`, `pusher-js` (protocolo compatible Reverb).

---

## 5. Archivos creados / modificados

### Creados

| Archivo | Rol |
|---------|-----|
| `Backend/app/Events/Restaurante/MapaMesasChanged.php` | Evento mapa |
| `Backend/app/Events/Restaurante/CocinaComandasChanged.php` | Evento cocina |
| `Backend/app/Services/Restaurante/RestauranteRealtimePublisher.php` | Publisher seguro |
| `Backend/tests/Feature/Restaurante/Phase6RealtimeTest.php` | Tests Fase 6 |
| `Frontend/src/app/services/restaurante-realtime.service.ts` | Cliente WS opcional |
| `FASE6_REPORT.md` | Este reporte |

### Modificados (principales)

| Archivo | Cambio |
|---------|--------|
| `BroadcastServiceProvider` | Rutas JWT `/api/broadcasting/auth` |
| `config/app.php` | Provider habilitado |
| `config/broadcasting.php` | Conexión `reverb` documentada |
| `config/restaurante.php` | `realtime_enabled` |
| `routes/channels.php` | Canal empresa |
| Controllers Sesion/Comanda/Orden/Pedido/PreCuenta/Reserva/Mesa | publish post-éxito |
| `environment.ts` / `prod` | flags realtime |
| `restaurante.component.ts` / `cocina.component.ts` | subscribe + recover |
| `.env.example` | vars Reverb documentadas |
| `Frontend/package.json` | echo + pusher-js |

---

## 6. Configuración (dev / prod) — sin cambios destructivos

### Sin Reverb (default recomendado hasta desplegar WS)

```env
BROADCAST_DRIVER=null
RESTAURANTE_REALTIME_ENABLED=true   # emite events (no-op sink con null)
```

FE: `restauranteRealtime.enabled = false` → solo HTTP.

### Con Reverb (opcional)

```bash
cd Backend && composer require laravel/reverb
php artisan reverb:install
php artisan reverb:start
```

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

FE:

```ts
restauranteRealtime: {
  enabled: true,
  key: '<REVERB_APP_KEY>',
  wsHost: 'localhost',
  wsPort: 8080,
  forceTLS: false,
}
```

**No** se instaló `laravel/reverb` en este repo (paquete opcional; plan: stub si no hace falta para salida productiva). La abstracción de broadcasting ya está lista.

---

## 7. Tests y resultados

```text
php artisan test tests/Feature/Restaurante
Tests: 45 passed (176 assertions)
```

Incluye **Phase6RealtimeTest (6)**:

| Test | Verifica |
|------|----------|
| afterCommit flag + push fuera de TX | Evento marca `afterCommit` y encola `BroadcastEvent` |
| rollback probe | TX revertida no deja filas |
| cocina payload/canal | `broadcastAs` + canal empresa |
| publisher disabled | No despacha si `realtime_enabled=false` |
| channel auth | Solo misma empresa |
| payload mínimo mapa | Shape estable |

### Deuda FE (no tocada)

Karma / specs legacy Ventas (`async`) — **sin cambios** (igual Fases 4–5).

---

## 8. Problemas / limitaciones

1. `Queue::fake()` registra push aun dentro de TX abierta en este Laravel; el contrato `afterCommit=true` está en el evento/job; los controllers publican **después** del commit de negocio.
2. Reverb no viene en `composer.json` aún: hay que instalarlo para WS real.
3. FE `enabled=false` por defecto: sin config no hay sockets (deseado).

---

## 9. Decisiones técnicas

1. Realtime = **hint + GET**, nunca estado authoritative en el cliente solo por evento.
2. Un canal por empresa (mapa+cocina); filtrado por `broadcastAs`.
3. Publisher try/catch: WS/broadcast roto no rompe 201/200 de negocio.
4. Dedupe FE (Set 2s + debounce): tolera eventos duplicados.
5. Sin polling 5s.

---

## 10. Desviaciones del plan

| Plan | Hecho | Nota |
|------|-------|------|
| Diseño primero / stub si Reverb no necesario | Stub + wiring completo; Reverb package opcional | Autorización GO pedía eventos/canales/tests/reconexión |
| Barra / admin channels separados | Mismo canal empresa | Suficiente; destino en payload cocina |
| Instalar Reverb en CI/prod | No | Documentado; no infra destructiva |

---

## 11. Riesgos pendientes / Fase 7+

| Ítem | Fase | Acción |
|------|------|--------|
| `RESTAURANTE_DATA_GROWTH.md` | **7** | No tocado |
| Desplegar Reverb + TLS + workers | ops | Checklist en §6 |
| Canales por sucursal | futuro | Si multi-sucursal satura |
| Load test Peak | 12–13 | No tocado |
| Karma Ventas | deuda FE | No tocado |

---

## 12. Criterios de completitud de Fase 6

- [x] Revisar arquitectura broadcast/auth/canales existente
- [x] Realtime ≠ SoT; HTTP sigue siendo camino completo
- [x] Eventos afterCommit / publicación post-éxito de negocio
- [x] Canal por tenant + auth JWT
- [x] FE tolera dupes; recover con GET
- [x] No romper F1–5 (suite 45/45)
- [x] Tests nuevos Fase 6
- [x] Config documentada; sin k6 / Fase 7+
- [x] Karma Ventas intacto

---

## 13. Siguiente paso — DETENERSE

**Fase 6 lista para revisión.** Commit manual pendiente.

**No iniciar Fase 7** (histórico/crecimiento) ni posteriores hasta nueva autorización explícita.
