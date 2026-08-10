# FASE 2 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-09  
**Cierre técnico:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §4 (P1)  
**Baseline / Fase 1:** `BASELINE_REPORT.md`, `FASE1_REPORT.md` (aprobada; commit manual)  
**Estado:** Fase 2 — **CIERRE TÉCNICO COMPLETO** (criterios OK)  
**Siguiente:** **DETENERSE** — no iniciar Fase 3+ hasta nueva aprobación explícita.  
**Commit:** pendiente (manual por el usuario).

---

## 1. Resumen de cambios

Se implementó P1 de API/seguridad sin tocar las protecciones de concurrencia de Fase 1:

1. **DTO liviano** para `GET /mesas` (claves FE preservadas)
2. **`id_empresa` en `comandas_restaurante`** + backfill + query cocina sin `whereHas`
3. **Bloqueo de update** de ítems ya enviados a cocina/barra
4. **`SesionMesaController@cerrar` endurecido** con reglas documentadas
5. **`Idempotency-Key`** reutilizable (DB + unique + lock; Redis no es SoT)
6. **Hardening multi-tenant** en `exists` críticos + tests cross-tenant

**Suite Restaurante (cierre técnico 2026-08-10):** 31/31 — **OK**  
(Phase2 13/13, Concurrency 7/7, PosMenu 11/11)

---

## 2. Archivos modificados / creados

### Creados

| Archivo | Rol |
|---------|-----|
| `Backend/database/migrations/2026_08_09_140000_add_id_empresa_to_comandas_restaurante.php` | Columna + backfill + índice |
| `Backend/database/migrations/2026_08_09_140001_create_restaurante_idempotency_keys_table.php` | Tabla idempotencia |
| `Backend/app/Models/Restaurante/RestauranteIdempotencyKey.php` | Modelo |
| `Backend/app/Services/Restaurante/RestauranteIdempotencyService.php` | Servicio header `Idempotency-Key` |
| `Backend/app/Http/Resources/Restaurante/MesaMapaDto.php` | DTO mapa mesas |
| `Backend/tests/Feature/Restaurante/Phase2ApiHardeningTest.php` | Tests Fase 2 |
| `FASE2_REPORT.md` | Este reporte |

### Modificados

| Archivo | Cambio |
|---------|--------|
| `Backend/app/Http/Controllers/Api/Restaurante/MesaController.php` | DTO index; `exists` scoped zona/sucursal |
| `Backend/app/Http/Controllers/Api/Restaurante/ComandaController.php` | `id_empresa` en create; cocina por columna; idempotency enviar |
| `Backend/app/Http/Controllers/Api/Restaurante/OrdenDetalleController.php` | `id_empresa` en comanda eliminación; block update enviado; idempotency agregar; `exists` producto scoped |
| `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` | Idempotency abrir; `exists` mesa scoped; `cerrar` endurecido |
| `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php` | Idempotency solicitar cuenta / marcar facturada; `factura_id` scoped |
| `Backend/app/Http/Controllers/Api/Restaurante/PedidoRestauranteController.php` | `id_empresa` en comandas canal; idempotency confirmar; `exists` scoped venta/cliente/sucursal |
| `Backend/app/Http/Controllers/Api/Restaurante/ReservaController.php` | `exists` scoped mesa/cliente (cierre técnico) |
| `Backend/app/Http/Controllers/Api/Restaurante/ZonaRestauranteController.php` | `exists` scoped sucursal (cierre técnico) |
| `Backend/app/Models/Restaurante/Comanda.php` | `id_empresa` fillable + audit |

**No se modificó frontend Angular** (DTO compatible; header Idempotency-Key es opt-in backend).

---

## 3. Migraciones

Aplicadas en entorno local de pruebas (`smartpyme_prod`):

1. `2026_08_09_140000_add_id_empresa_to_comandas_restaurante`
   - Columna `id_empresa` (UNSIGNED)
   - Backfill: `COALESCE(sesion.id_empresa, pedido.id_empresa)`
   - Huérfanos locales: **0** → columna quedó `NOT NULL`
   - Índice: `comandas_restaurante_id_empresa_estado_created_at_index` `(id_empresa, estado, created_at)`

2. `2026_08_09_140001_create_restaurante_idempotency_keys_table`
   - Unique `(id_empresa, user_id, operation, idempotency_key)`
   - TTL vía `expires_at` (purge best-effort en servicio; keys vencidas reutilizables)

---

## 4. Detalle por ítem de alcance

### 4.1 DTO `GET /mesas`

- `select` limitado + eager load parcial (`sesionActiva`, `reservasActivas`, `zonaRestaurante`)
- Respuesta vía `MesaMapaDto`: mantiene `sesion_activa`, `zona_restaurante`, `reservas_activas`, `tiempo_abierta`, `activo`, `orden`, etc.
- Lógica de estado (`ocupada` / `reservada` / `libre`) sin cambios

### 4.2 `id_empresa` en comandas

- Escrituras actualizadas en: `ComandaController`, `OrdenDetalleController` (eliminación), `PedidoRestauranteController` (canal)
- Cocina (`index` / `actualizarEstado` / `imprimir`): filtro `where('id_empresa', …)` (sin `whereHas`)

### 4.3 Update ítem enviado

- Si `enviado_cocina || enviado_barra` → **422**  
  `"No se puede modificar un ítem ya enviado a cocina/barra"`

### 4.4 Endurecer `cerrar`

Documentado en PHPDoc del método. Reglas:

| Condición | Resultado |
|-----------|-----------|
| Ya `cerrada` | 422 |
| Estado ≠ `abierta`/`pre_cuenta` | 422 |
| PreCuenta `pendiente` | 422 |
| OrdenDetalle vivos | 422 |
| Comanda cocina/barra en pendiente/preparando/listo | 422 |
| Sesión vacía sin pendientes | 200 + mesa `libre` |

No hay “cierre forzado” en esta fase. El cierre operativo tras facturación sigue en `marcarFacturada`.

### 4.5 Idempotency-Key

- Header opcional `Idempotency-Key` (max 128, charset `[A-Za-z0-9._:-]`)
- Clave lógica: empresa + usuario + operación + key
- Persistencia **DB** (unique + `lockForUpdate` al replay)
- Estados: `processing` → 409 si conflicto concurrente; `completed` → replay status/body
- Key **vencida** → se elimina y permite reutilizar la misma key
- Excepción en callback → borra key (permite retry)
- TTL 24h

Operaciones cableadas:

| Operación | Endpoint |
|-----------|----------|
| `abrir_mesa` | `SesionMesaController@store` |
| `agregar_item` | `OrdenDetalleController@store` |
| `enviar_comanda` | `ComandaController@store` |
| `solicitar_cuenta` | `PreCuentaController@generar` |
| `marcar_facturada` | `PreCuentaController@marcarFacturada` |
| `confirmar_pedido` | `PedidoRestauranteController@confirmar` |

### 4.6 Multi-tenant

- `mesa_id`, `zona_id`, `id_sucursal`, `producto_id`, `factura_id`/`venta_id`, `cliente_id`, `mesa_destino_id` con `Rule::exists(…).where('id_empresa', …)`
- Tabla sucursales: `sucursales` (no `empresa_sucursales`)
- Controllers cubiertos: Mesa, Sesion, OrdenDetalle, PreCuenta, Pedido, Reserva, Zona
- Tests: empresa A no abre/reserva mesa de B; B no lee sesión de A; cocina B no ve comanda de A

---

## 5. Tests ejecutados y resultados

Comando (cierre técnico):

```bash
./vendor/bin/phpunit tests/Feature/Restaurante/ --testdox
```

| Suite | Resultado |
|-------|-----------|
| `Phase2ApiHardeningTest` | **13/13 OK** |
| `ConcurrencyIntegrityTest` (Fase 1) | **7/7 OK** |
| `PosMenuTest` | **11/11 OK** |
| **Total** | **31/31 OK** |

### Escenarios Phase2 cubiertos

- DTO keys de `/mesas`
- Comanda guarda `id_empresa` + aislamiento cocina cross-tenant
- Update ítem enviado → 422
- Cerrar con ítems / precuenta pendiente / comanda activa → 422; sesión vacía → OK
- Idempotency replay abrir mesa
- Idempotency `processing` → 409
- Idempotency key vencida reutilizable
- Cross-tenant open / read / reservar bloqueados

### No ejercitado / documentado como pendiente (no bloqueante)

- Stress concurrente OS de la misma `Idempotency-Key` en dos procesos (path 409 implementado; test unitario de fila `processing` sí)
- Frontend enviando `Idempotency-Key` → **pendiente Fase 4 / ola FE** (no Fase 3)
- EXPLAIN formal en MariaDB 10.11 prod del índice de comandas → **pendiente Fase 3**

---

## 6. Decisiones técnicas

1. **Integridad > optimización:** locks/constraints Fase 1 intactos; idempotencia HTTP es capa adicional, no reemplazo.
2. **Idempotencia en DB**, no Redis: cumple regla “Redis ≠ fuente de verdad para integridad”.
3. **Header opcional:** clientes actuales siguen funcionando; FE puede adoptar el header después.
4. **DTO sin romper FE:** se reduce payload/eager load, no se cambian nombres de campos críticos Angular.
5. **Cerrar conservador:** prioriza no dejar precuentas/ítems/comandas inconsistentes; sin force-close.
6. **Sin Policies nuevas / sin global scopes** (plan: no introducirlos automáticamente).
7. **TTL idempotency:** keys vencidas se borran al conflicto unique y permiten reuso (corregido en cierre técnico).

---

## 7. Desviaciones respecto al plan

| Plan | Hecho | Nota |
|------|-------|------|
| Índice comandas “solo si EXPLAIN justifica” | Índice agregado | Justificado por query cocina; EXPLAIN prod → **pendiente Fase 3** |
| Redis opcional para storage idempotency | No usado | Solo DB; alineado a regla de integridad |
| Policies si aportan valor | No | `exists` scoped + queries por empresa bastan |
| `exists:empresa_sucursales` original en Mesa | Corregido a `sucursales` | Tabla real del dominio |
| FE Idempotency-Key | No en esta fase | Backend listo; adopción FE → **pendiente Fase 4** |

Ninguna desviación requiere ampliar alcance a Fase 3 en este cierre.

---

## 8. Riesgos pendientes

| Riesgo | Severidad | Mitigación / siguiente |
|--------|-----------|------------------------|
| FE aún no envía `Idempotency-Key` | Medio | Retries de red pueden seguir creando operaciones lógicas distintas; locks Fase 1 cubren lo crítico → **pendiente FE (Fase 4)** |
| Cerrar más estricto puede sorprender flujos manuales legacy | Bajo | Documentado; cierre real post-factura intacto |
| Purge idempotency best-effort (`limit 100`) | Bajo | Reuso por key vencida al conflicto; cron dedicado si crece volumen (**Fase 3+ ops**, no implementado) |
| Índice comandas sin EXPLAIN prod | Bajo | Validar en **Fase 3** índices |
| Comandas históricas huérfanas en otros entornos | Medio | Migración deja `id_empresa` nullable si hay huérfanos; revisar en deploy |

---

## 9. Criterios de completitud de Fase 2

- [x] DTO liviano `GET /mesas` compatible con FE
- [x] `id_empresa` en comandas + backfill + cocina por columna
- [x] Update ítem enviado bloqueado (backend autoridad)
- [x] `cerrar` endurecido y documentado
- [x] `Idempotency-Key` reutilizable en ola 1 de endpoints
- [x] Tests multi-tenant + tests por cambio relevante
- [x] Suite Restaurante ejecutada y documentada (31/31)
- [x] Protecciones Fase 1 no deshechas (Concurrency 7/7 OK)
- [x] Sin Redis como SoT de integridad
- [x] Sin avance a Fase 3+

---

## 10. Cierre técnico de Fase 2 (2026-08-10)

### Hallazgos corregidos (dentro de Fase 2)

1. **Idempotency TTL incompleto:** una key vencida seguía bloqueando por unique y devolvía el response viejo.  
   **Fix:** al conflicto, si `expires_at` pasó → delete + reintento create (test `test_idempotency_expired_key_can_be_reused`).

2. **Multi-tenant parcial en `exists`:**  
   - `ReservaController`: `mesa_id` / `cliente_id` sin scope empresa  
   - `ZonaRestauranteController`: `id_sucursal` sin exists scoped  
   - `PedidoRestauranteController`: `venta_id` / `cliente_id` / `id_sucursal` sin Rule scoped (venta ya tenía check secundario; unificado)  
   **Fix:** `Rule::exists(...)->where('id_empresa', …)` + test reserva cross-tenant.

3. **Tests incompletos documentados en reporte original:**  
   - Cerrar con comanda activa sin ítems  
   - Idempotency `processing` → 409  
   Añadidos y verdes.

4. **Import muerto:** `VentaModel` en Pedido tras unificar validación de venta — eliminado.

### Hallazgos NO implementados (pertenecen a Fase 3+)

| Hallazgo | Fase sugerida | Acción tomada |
|----------|---------------|---------------|
| EXPLAIN / validación índice comandas en MariaDB 10.11 prod | Fase 3 | Solo documentado |
| Redis cache mapa + invalidación | Fase 3 | No tocado |
| Bulk updates / más índices | Fase 3 | No tocado |
| Cablear `Idempotency-Key` en Angular | Fase 4 | Solo documentado |
| Cron purge dedicado de idempotency | Ops / Fase 3+ | Solo documentado |

### Verificación final

```text
./vendor/bin/phpunit tests/Feature/Restaurante/ --testdox
→ 31/31 OK (Phase2 13, Concurrency 7, PosMenu 11)
```

### Veredicto de cierre

**Fase 2 técnicamente cerrada.** Criterios de completitud §9 en OK.  
Sin commit (manual). **DETENERSE** — esperar aprobación explícita para Fase 3.

---

## 11. Siguiente paso — DETENERSE

**Fase 2 está lista para revisión/aprobación del commit manual.**

**No iniciar Fase 3** (Redis cache mapa, bulk updates, índices EXPLAIN, etc.) ni fases posteriores hasta nueva autorización explícita del usuario.

Cuando se apruebe:

1. Commit de Fase 2 (manual)
2. Deploy migraciones (`id_empresa` comandas + `restaurante_idempotency_keys`) en entornos destino
3. Solo entonces evaluar alcance de **Fase 3 — P2 Performance/DB**
