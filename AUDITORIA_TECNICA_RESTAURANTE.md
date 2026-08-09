# AUDITORÍA TÉCNICA — SMARTPYME RESTAURANTE

**Alcance:** solo lectura del código actual en working tree. Sin cambios, migraciones ni scripts.  
**Fecha:** 2026-08-08  
**Base API:** `routes/api.php` → middleware `jwt.auth` → `routes/modulos/restaurante.php` (prefix `restaurante` + `verificar.funcionalidad:modulo-restaurante`).

---

## 1. Resumen ejecutivo

El módulo es una API REST Laravel centrada en **controllers** (sin Jobs, Events, Policies, Cache ni Redis propios) y un frontend Angular lazy-loaded con **actualización por acción / botón manual**, sin polling ni WebSockets.

Para Agro-Mall (139 mesas, 20–25 meseros, cajas y cocina), el riesgo principal **no** es un “storm de polling” (hoy no existe), sino:

1. **Race conditions** en abrir mesa, fusionar ítems, enviar comanda y marcar pre-cuenta facturada (sin `lockForUpdate` ni unique de sesión activa).
2. **Mapa stale** entre meseros (cada uno ve estado hasta que recarga/actúa).
3. **Picos síncronos** en `GET /mesas` (139 + relaciones), `GET /comandas` (`whereHas` + eager deep), `GET /sesiones-mesa/{id}` y facturación vía módulo Ventas.
4. **Doble click** en agregar producto (sin flag de envío en el sheet).

La arquitectura operativa (permisos, soft-delete con motivo, división en una sola request, POS menu paginado por categoría) está razonablemente bien para un MVP; **no está endurecida para concurrencia multi-mesero**.

---

## 2. Arquitectura actual

```
Angular (RestauranteModule lazy)
  → HttpService / ApiService (JWT)
    → Laravel api.php [jwt.auth]
      → restaurante.php [modulo-restaurante + permission:*]
        → Controllers Api/Restaurante/*
          → Models Eloquent + Services (stock/authz/inventario canal)
          → MariaDB (tablas restaurante_*)
          → Blade HTML (tickets comanda/pre-cuenta)
          → Ventas (facturación real fuera del módulo)
```

| Capa | Hallazgo |
|------|----------|
| Controllers | Lógica de negocio embebida (esp. `PreCuentaController`, `PedidoRestauranteController`) |
| Services | 4: authz, stock mesa (solo valida), inventario canal (activo), inventario pedido (muerto) |
| Queues / Redis / Broadcast | **No usados** en código Restaurante |
| Realtime FE | **No** (ni `setInterval` ni Echo/Pusher/SSE) |
| Multi-tenant | Filtro manual `auth()->user()->id_empresa` (sin global scopes en modelos Restaurante) |

---

## 3. Mapa de archivos

### Árbol lógico (nombres reales)

```
Restaurante
├── Mesas (restaurante_mesas) — mapa + CRUD
├── Zonas (restaurante_zonas)
├── Sesiones de mesa (restaurante_sesiones_mesa) — abrir / cerrar / reactivar / traslado
├── Orden / ítems (orden_detalle_restaurante)
├── Comandas cocina/barra (comandas_restaurante + comanda_detalle_*)
├── Pre-cuentas + división (pre_cuentas_*, division_cuenta_*, pivot)
├── Reservas (reservas_restaurante)
├── POS menú (categorías/productos inventario)
├── Pedidos canal Spoties/manual (restaurante_pedidos*) — permisos pedidos.*
├── Inventario canal (PedidoCanalInventarioService)
├── Log eliminaciones (rest_item_eliminaciones_log)
├── Import mesas (Command + MesasImportPlanner)
└── Facturación (handoff a Ventas vía prepararFactura / marcarFacturada)
```

### Backend

| Tipo | Ruta |
|------|------|
| Controllers | `Backend/app/Http/Controllers/Api/Restaurante/{Mesa,SesionMesa,OrdenDetalle,Comanda,PreCuenta,ZonaRestaurante,PosMenu,PedidoRestaurante,Reserva}Controller.php` |
| Models | `Backend/app/Models/Restaurante/*` (14) |
| Services | `Backend/app/Services/Restaurante/{RestauranteAutorizacion,RestauranteStock,PedidoCanalInventario,PedidoRestauranteInventario}Service.php` |
| Support | `Backend/app/Support/Restaurante/MesasImportPlanner.php` |
| Routes | `Backend/routes/modulos/restaurante.php` |
| Migrations | `2026_03_18_100001` … `2026_08_04_120000_*` (ver §6) |
| Command | `ImportarMesasRestaurante` |
| Seeder | `RestauranteFuncionalidadSeeder` (solo funcionalidad `modulo-restaurante`) |
| Jobs/Events/Policies/Observers/FormRequests/Resources | **Ninguno** |

### Frontend

| Tipo | Ruta |
|------|------|
| Module/routes | `Frontend/src/app/views/restaurante/restaurante.module.ts`, `restaurante-routing.module.ts` |
| Mapa | `restaurante.component.*` |
| Cuenta/POS | `cuenta-mesa/*` (+ pos-catalogo, pos-sheet-agregar, pos-flujo-cuenta, pos-division) |
| Cocina | `cocina/cocina.component.*` |
| Zonas | `zonas/zonas-restaurante.component.*` |
| Service | `Frontend/src/app/services/restaurante.service.ts` |
| Guards | `FuncionalidadGuard` + `PermissionGuard` (`modulo-restaurante` / `restaurante.ver`) |
| Facturación | `views/ventas/facturacion/*` consume `marcarPreCuentaFacturada` |

---

## 4. Flujo funcional

Convención: `Angular Component → Service → HTTP → Route → Controller → Model/SQL → Response → FE`.

### A. Crear mesa

`RestauranteComponent.guardarMesa` → `crearMesa` → `POST /restaurante/mesas` → `MesaController@store` (~57–81) → `Mesa::create` + sync texto zona → JSON 201 → `cargarMesas()`.

### B. Editar mesa

Mismo componente → `actualizarMesa` → `PUT /mesas/{id}` → `MesaController@update` (~90–108).

### C. Crear zona

`ZonasRestauranteComponent` → `crearZona` → `POST /zonas` → `ZonaRestauranteController@store`.

### D. Abrir mesa

`abrirMesa` → `abrirSesion` → `POST /sesiones-mesa` → `SesionMesaController@store` (~16–54): check sesión activa → `SesionMesa::create` → `mesa.estado=ocupada` → navigate `/restaurante/cuenta/{id}`.  
**Sin transacción ni lock.**

### E–H. Orden (crear implícita / agregar / qty / eliminar)

No hay entidad “Orden” aparte: la sesión **es** la orden.

- Agregar: `onConfirmarAgregar` → `POST .../items` → `OrdenDetalleController@store` (~88–162): fusiona líneas no enviadas o crea → stock solo validación.
- Modificar: `guardarEdicion` → `PUT .../items/{id}` → `@update` (~164–188); **backend no bloquea ítem ya enviado** (FE sí oculta edición).
- Eliminar: `confirmarEliminar` → `POST .../eliminar` → `@eliminar` (~190–247) con tx, log y comanda `DEL-*`.

### I. Enviar orden (comanda)

`enviarACocina` (`enviandoComanda=true`) → `POST .../comandas` → `ComandaController@store` (~116–178): lee pendientes → tx crea cocina/barra → marca `enviado_*` → FE imprime HTML con `setTimeout(400)`.

### J–L. Cocina

`CocinaComponent.cargarComandas` (manual) → `GET /comandas` → `ComandaController@index` (~92–114).  
Estado: `PUT /comandas/{id}/estado` → `@actualizarEstado` (~180–194) **sin máquina de estados**.

### M–P. Solicitar cuenta / precuenta / división

`PosFlujoCuentaComponent` → `solicitarCuenta` → `POST .../pre-cuenta` → `PreCuentaController@generar` (~366–432): anula pendientes → crea PC o división inline (`dividir` en body).  
`dividirCuenta` del service **no se usa** en UI; división va en `generar`.  
Sesión **no** pasa a `pre_cuenta` en este flujo (queda abierta; comentario en `reactivarConsumo`).

### Q. Facturar

`irAFacturar` → `POST /pre-cuentas/{id}/facturar` (`prepararFactura`) → navigate `/venta/crear?pre_cuenta=` → al grabar venta: `marcarPreCuentaFacturada` → `PUT .../marcar-facturada` → liquida líneas → cierra sesión si no quedan PC pendientes.

### R. Trasladar

Admin/gerente → `POST .../trasladar-items` → `SesionMesaController@trasladarItems` (~125–172) con `DB::beginTransaction`, sin locks; fusión en destino.

### S. Cerrar mesa

Cierre “normal”: vía facturación (`marcarFacturada`).  
Forzado: `PUT .../cerrar` → `cerrar` (~85–97) **sin validar** pre-cuentas/ítems pendientes.

### T. Reabrir / modificar

`reactivarConsumo` si estado `pre_cuenta` (legado). Sesiones abiertas siguen editables tras precuenta informativa.

---

## 5. Endpoints

Base efectiva: `/api/restaurante/...` (grupo JWT). Usuario: JWT + permiso indicado. Cache/Queue/Polling: **No** en todos.

| Método | URL | Controller@método | Qué hace | Params clave | Queries / tablas | Tx | Riesgo |
|--------|-----|-------------------|----------|--------------|------------------|----|--------|
| GET | `/mesas` | Mesa@index | Mapa 139 | id_sucursal?, activo? | mesas + sesionActiva + reservas + zona | No | **Alto** (payload) |
| POST | `/mesas` | Mesa@store | Crear | numero, zona_id… | mesas, zonas | No | Bajo |
| GET | `/mesas/{id}` | Mesa@show | Una mesa | — | mesas | No | Bajo |
| PUT | `/mesas/{id}` | Mesa@update | Editar | partial | mesas | No | Bajo |
| GET | `/zonas` | Zona@index | Listar | activo?, sucursal? | zonas | No | Bajo |
| POST/PUT/DELETE | `/zonas…` | Zona@* | CRUD | — | zonas, mesas (destroy) | No | Bajo |
| GET | `/pos-menu/categorias` | PosMenu@categorias | Catálogo raíz | — | categorias (+count) | No | Medio |
| GET | `/pos-menu/categorias/{id}/contenido` | PosMenu@contenidoCategoria | Subcats o productos | — | categorias/productos | No | Medio |
| GET | `/pos-menu/subcategorias/{id}/productos` | PosMenu@productosSubcategoria | Productos | — | productos | No | Medio |
| GET | `/pos-menu/buscar` | PosMenu@buscar | LIKE limit 30 | q | productos | No | Medio |
| POST | `/sesiones-mesa` | Sesion@store | Abrir | mesa_id, comensales | sesiones, mesas | No | **Crítico** (race) |
| GET | `/sesiones-mesa/{id}` | Sesion@show | Cuenta completa | — | sesión+items+producto+PC | No | **Alto** |
| PUT | `/sesiones-mesa/{id}` | Sesion@update | Comensales/obs | — | sesiones | No | Bajo |
| PUT | `.../cerrar` | Sesion@cerrar | Cierre forzado | — | sesiones, mesas | No | Alto (integridad) |
| PUT | `.../reactivar-consumo` | Sesion@reactivarConsumo | pre_cuenta→abierta | — | sesiones | No | Bajo |
| POST | `.../trasladar-items` | Sesion@trasladarItems | Mover líneas | mesa_destino, ids | orden_detalle | Sí | **Alto** |
| POST | `.../items` | OrdenDetalle@store | Agregar/fusionar | producto_id, qty | orden_detalle, productos, inventario? | No | **Crítico** |
| PUT | `.../items/{itemId}` | OrdenDetalle@update | Qty/notas | cantidad, notas | orden_detalle | No | Medio |
| POST | `.../items/{id}/eliminar` | OrdenDetalle@eliminar | Soft-del + log | motivo | orden, log, comanda | Sí | Alto |
| DELETE | `.../items/{id}` | OrdenDetalle@destroy | Deprecated 400 | — | — | No | — |
| GET | `/comandas` | Comanda@index | Board cocina | — | comandas + whereHas + nested | No | **Crítico** si spam refresh |
| POST | `.../comandas` | Comanda@store | Enviar cocina/barra | — | orden_detalle, comandas | Sí | **Crítico** (doble envío) |
| PUT | `/comandas/{id}/estado` | Comanda@actualizarEstado | Estado | estado | comandas | No | Medio |
| GET | `/comandas/{id}/imprimir` | Comanda@imprimir | HTML ticket | — | comandas+detalles | No | Medio |
| POST | `.../pre-cuenta` | PreCuenta@generar | PC / dividir | dividir? | PC, pivot, división, items | Sí | **Alto** |
| POST | `/pre-cuentas/{id}/dividir` | PreCuenta@dividir | Re-dividir | tipo, asignaciones | idem | Sí | Alto |
| POST | `/pre-cuentas/{id}/facturar` | PreCuenta@prepararFactura | Payload venta | — | PC+items | No | Medio |
| PUT | `.../marcar-facturada` | PreCuenta@marcarFacturada | Cierre liquidación | factura_id | PC, orden, sesión, mesa | Sí | **Crítico** |
| GET | `/pre-cuentas/{id}` | PreCuenta@show | Detalle | — | PC | No | Bajo |
| GET | `.../imprimir` | PreCuenta@imprimir | HTML | — | PC | No | Medio |
| GET/POST/PUT… | `/reservas…` | Reserva@* | CRUD/cancel/convertir | — | reservas, mesas, sesiones | Parcial | Medio |
| GET/POST… | `/pedidos…` | PedidoRestaurante@* | Canal | varios | pedidos+detalles+inventario | Varios | **Alto** en confirmar |

Complejidad aprox.: lecturas mapa/cocina O(N mesas/comandas); escrituras ítem O(1–pocas queries); confirmar pedido canal O(líneas × inventario/kardex) síncrono.

---

## 6. Base de datos

### Tablas

| Tabla | Propósito | PK | Índices relevantes | FKs / notas |
|-------|-----------|-----|-------------------|-------------|
| `restaurante_mesas` | Mesas | id | `(id_empresa, id_sucursal)` | empresa; `zona_id` |
| `restaurante_zonas` | Zonas | id | `(id_empresa, activo)` | empresa |
| `restaurante_sesiones_mesa` | Sesión/cuenta | id | `(id_empresa, estado)`; FK `mesa_id` | **sin unique sesión activa** |
| `orden_detalle_restaurante` | Líneas | id | `sesion_id`; soft deletes | sesión, producto |
| `comandas_restaurante` | Tickets cocina | id | `(sesion_id, estado)`; FK `pedido_id` | sesión/pedido |
| `comanda_detalle_restaurante` | Detalle comanda | id | unique `(comanda_id, orden_detalle_id)` | |
| `division_cuenta_restaurante` | Meta división | id | `sesion_id` | |
| `pre_cuentas_restaurante` | Pre-cuentas | id | `(sesion_id, estado)` | |
| `pre_cuenta_orden_detalle` | Pivot + cantidad | id | unique pair | |
| `reservas_restaurante` | Reservas | id | `(mesa_id, fecha_reserva, estado)` | |
| `restaurante_pedidos` | Canal | id | `(id_empresa, fecha/estado)` | |
| `restaurante_pedido_detalles` | Detalles canal | id | `producto_id` | |
| `pedido_detalle_lotes` | Lotes | id | `(pedido_detalle_id, lote_id)` | |
| `rest_item_eliminaciones_log` | Auditoría voids | id | `(sesion_id, created_at)` | |

### Migraciones relacionadas

| Migration | Purpose |
|-----------|---------|
| `2026_03_18_100001_create_mesas_table.php` | `restaurante_mesas` |
| `2026_03_18_100002_create_sesiones_mesa_table.php` | `restaurante_sesiones_mesa` |
| `2026_03_18_100003_create_orden_detalle_restaurante_table.php` | `orden_detalle_restaurante` |
| `2026_03_18_100004_create_comandas_restaurante_table.php` | `comandas_restaurante` |
| `2026_03_18_100005_create_comanda_detalle_restaurante_table.php` | `comanda_detalle_restaurante` |
| `2026_03_18_100006_create_division_cuenta_restaurante_table.php` | `division_cuenta_restaurante` |
| `2026_03_18_100007_create_pre_cuentas_restaurante_table.php` | `pre_cuentas_restaurante` |
| `2026_03_18_100008_create_pre_cuenta_orden_detalle_table.php` | `pre_cuenta_orden_detalle` |
| `2026_03_18_100009_create_reservas_restaurante_table.php` | `reservas_restaurante` |
| `2026_03_18_100010_add_genera_comanda_to_productos_table.php` | `productos.genera_comanda` |
| `2026_03_24_100000_create_restaurante_pedidos_table.php` | `restaurante_pedidos` |
| `2026_03_24_100001_create_restaurante_pedido_detalles_table.php` | `restaurante_pedido_detalles` |
| `2026_03_24_120000_add_propina_to_pre_cuentas_restaurante_table.php` | propina cols |
| `2026_03_31_120000_add_inventario_pedido_restaurante.php` | `inventario_descontado_at`, `lote_id` |
| `2026_05_14_120000_restaurante_mejoras_operacion.php` | destino_comanda, soft deletes, elim log |
| `2026_05_14_120000_add_cantidad_to_pre_cuenta_orden_detalle_table.php` | pivot `cantidad` |
| `2026_05_14_130000_add_eliminacion_motivo_to_comandas_restaurante.php` | elim motive cols |
| `2026_06_02_120000_restaurante_zonas_y_comandas_pedido.php` | zonas + pedido comandas |
| `2026_06_21_111722_add_id_paquete_to_restaurante_pedido_detalles_table.php` | `id_paquete` FK |
| `2026_07_06_120002_create_pedido_detalle_lotes_table.php` | `pedido_detalle_lotes` |
| `2026_08_04_120000_add_servido_to_comandas_restaurante_estado.php` | enum `servido` |

### Índices faltantes (justificados por consultas reales)

1. **Sesión activa por mesa** — `SesionMesaController@store` filtra `mesa_id` + `estado IN (abierta, pre_cuenta)` (~31–33). Hoy solo indexa `mesa_id` (FK). Ideal: unique parcial / aplicación + `lockForUpdate`. Un índice `(mesa_id, estado)` ayuda lecturas del mapa vía `sesionActiva`.
2. **Comandas por empresa/estado** — `Comanda@index` usa `whereHas(sesion|pedido)` + `estado IN (...)`. Un índice solo en `(sesion_id, estado)` no evita el `whereHas`. Mejor denormalizar `id_empresa` en `comandas_restaurante` + index `(id_empresa, estado, created_at)`.
3. **Orden detalle fusion** — `store` filtra `sesion_id, producto_id, enviado_*, notas`. Índice `(sesion_id, producto_id, enviado_cocina, enviado_barra)` reduciría scans al fusionar bajo carga.
4. **No proponer** índice en `created_at` solo: el `orderBy created_at` de cocina es secundario al filtro empresa/estado.

Volumen Agro-Mall (orden magnitud): ~139 mesas; sesiones/día cientos; `orden_detalle` miles/día; comandas miles/fin de semana; histórico crece si no se archiva.

---

## 7. N+1 Queries

| Archivo | Método | Problema | Queries pot. | Recomendación |
|---------|--------|----------|--------------|---------------|
| `MesaController.php` | `index` ~34–37 | **No N+1** (eager correcto) | ~4 queries | Mantener; añadir `select` columnas |
| `ComandaController.php` | `index` ~99–111 | `whereHas` ×2 + eager nested; OK no N+1 tras load | 1+subqueries pesadas | Denormalizar empresa / join |
| `ComandaController.php` | `crearComandaSesion` ~75–84 | Update por ítem en loop | 1 + N updates | bulk update flags |
| `PreCuentaController.php` | `liquidarOrdenTrasFacturarPreCuenta` ~100–120 | `OrdenDetalle::...->first()` **por línea** | 1+N | eager + update en memoria |
| `PreCuentaController.php` | `calcularImpuestoItems` ~51–54 | `loadMissing('producto')` si no eager | N | siempre `with('producto')` (ya en `generar`) |
| `SesionMesaController.php` | `trasladarItems` ~157–159 | `findOrFail` por id | 1+N | `whereIn` + map |
| `OrdenDetalleController.php` | `store` | No N+1; fusion en memoria | ~3–6 | tx + lock fila |
| Frontend `cocina.component.html` | template | `getComandasPendientes()` repetido por CD | 0 SQL, CPU FE | pipes/computed |

---

## 8. Concurrencia

**Primitivas en módulo:** solo `DB::transaction` / `beginTransaction` en algunos writes. **Cero** `lockForUpdate`, optimistic version, idempotency keys, unique de sesión activa.

| Escenario | ¿Protegido? | Qué ocurre en código |
|-----------|-------------|----------------------|
| **1** Dos abren mesa 10 | **No** | Ambos pasan check ~31–37; dos sesiones `abierta`; `sesionActiva` hasOne indeterminado; mesa `ocupada` |
| **2** Dos agregan producto | **Parcial / débil** | Sin tx; dos creates o lost update en fusión ~109–141 |
| **3** Dos modifican misma línea | **No** | last-write-wins en `@update` |
| **4** Dos solicitan cuenta | **Parcial** | Cada `generar` anula pendientes en tx (~379) pero sin lock de sesión → carrera puede dejar 2 PC o borrar la del otro |
| **5** Cuenta vs modificar orden | **No** | PC es snapshot; ítems pueden cambiar después; liquidación al facturar usa cantidades actuales |
| **6** Dos trasladan misma mesa | **Débil** | Tx sin lock; posible double-move / qty incorrecta |
| **7** Dos facturan misma PC | **Parcial** | Check `estado === facturada` (~552) sin lock → doble liquidación posible |
| **8** Enviar comanda vs modificar | **Débil** | Tx en store sin lock de líneas; doble comanda posible; update de qty no mira `enviado_*` |
| **9** Red caída post-commit | **No idempotencia** | Cliente puede reintentar → escenarios 1/2/4/8 |
| **10** Doble click | **Parcial FE** | `guardando` / `enviandoComanda` / `solicitando` / `trasladando` sí; **agregar ítem no** (`pos-sheet-agregar` emite libremente) |

`comanda_detalle` unique `(comanda_id, orden_detalle_id)` evita duplicar el mismo par **dentro de una comanda**, no evita **dos comandas** con el mismo ítem.

---

## 9. Polling / WebSockets

**Resultado:** no hay polling automático, WebSocket, Echo, Pusher, Reverb ni SSE en el módulo.

| Pantalla | Mecanismo | Frecuencia | Endpoint |
|----------|-----------|------------|----------|
| Mapa | load + post-mutación | on demand | `GET /mesas`, `GET /zonas` |
| Cuenta | load + post-mutación | on demand | `GET /sesiones-mesa/{id}` |
| Cocina | botón “Actualizar” | manual | `GET /comandas` |
| POS search | `debounceTime(300)+switchMap` | por tecla | `GET /pos-menu/buscar` |

### Cálculo requests/min **solo por polling automático**

| Usuarios | Req/min por polling |
|----------|---------------------|
| 25 meseros | **0** |
| 25 + cocina + cajas | **0** |
| 50 usuarios | **0** |

**Riesgo de polling como cuello de botella hoy: bajo (inexistente).**  
**Riesgo operativo:** mapa/cocina desactualizados entre dispositivos. Si en Agro-Mall se añade poll cada 5–10s en mapa/cocina, con 25 meseros + 5 cocina:  
`25×(60/5) + 5×(60/5) ≈ 300–360 req/min` solo de mapa/cocina → **entonces** sí sería cuello crítico sobre `GET /mesas` y `GET /comandas`.

---

## 10. Laravel / PHP

| Hallazgo | Detalle |
|----------|---------|
| Controllers pesados | `PreCuentaController` (~600 líneas), `PedidoRestauranteController` |
| HTTP bloqueante | Confirmación canal + inventario síncrono; impresión HTML en request |
| JSON grandes | `GET /mesas` sin `select`; sesión con productos/PC anidados; comandas full nest |
| Sin cache | Cada mapa/cocina pega DB |
| Sin queue | Nada diferido (tickets, inventario, notificaciones) |
| PHP-FPM risk | Picos en `comandas@index`, `mesas@index`, `pedido@confirmar`, facturación Ventas |

`RestauranteStockService`: valida stock, **no descuenta** en flujo mesa (descuento real en Ventas).

---

## 11. Redis / Queues

| Uso en Restaurante | Estado |
|--------------------|--------|
| Cache mapa/comandas | No |
| Locks distribuidos | No |
| Queues/Jobs | No |
| Broadcasting | No |

**Recomendaciones (sin implementar):** Redis lock al abrir mesa / enviar comanda / marcar facturada; cache corto del mapa por `(empresa, sucursal)`; cola para impresión/notificación cocina si se agrega realtime.

---

## 12. Angular

| Tema | Hallazgo |
|------|----------|
| CD | Default (no OnPush) |
| Signals | No |
| trackBy / @for | No — `*ngFor` de las 139 mesas |
| Virtualización | No — grid completo |
| `mesasPorZona` getter | Reagrupa en cada CD |
| Subscribes | Casi sin `unsubscribe`/`takeUntilDestroyed` (excepto search POS) |
| Mapa 139 | Sí, render simultáneo; OK DOM-wise (~139 nodos), costo real es JSON + agrupación |
| Memory leaks | Bajo-medio (HTTP complete); peores: getters cocina + reagrupación mapa |
| HTTP duplicado | `getZonas` en init y al abrir modal mesa |
| Doble submit | Falta en agregar producto |

---

## 13. Reportes

**No hay controllers/reportes dedicados de Restaurante** (ni routes de analytics del módulo). Agregaciones son Collection (`groupBy` en pre-cuenta / inventario canal).

| Clasificación | Nota |
|---------------|------|
| Reportes Restaurante | N/A / **Bajo** impacto DB del módulo |
| Riesgo colateral | Reportes **Ventas/Inventario** u otros tenants en el mismo VPS sí pueden degradar meseros |

---

## 14. Seguridad / Multi-tenancy

| Control | Estado |
|---------|--------|
| JWT | Sí (`api.php`) |
| Funcionalidad `modulo-restaurante` | Sí |
| Permissions Spatie | Sí por ruta |
| Filtro `id_empresa` | Sí en la mayoría de finds |
| Policies | No |
| Global scopes modelos Restaurante | No |
| `exists:restaurante_zonas,id` / `exists:restaurante_mesas,id` | Validación global; re-check empresa en lógica (zona_id de otra empresa → `sincronizarZonaTexto` deja zona null sin 403) |
| Authz privilegiada | `user.tipo ∈ {administrador,admin,gerente}` string — no permiso granular |
| Pedidos canal | Permisos `pedidos.*` separados |

Riesgo IDOR residual: bajo-medio en paths que solo hacen `exists:` sin join a empresa en la validación; la mayoría de lecturas posteriores sí filtran empresa.

---

## 15. Capacidad estimada

Modelo: **usuarios operativos**, no 1000 comensales. Sin polling auto; carga = acciones + hábitos de refresh.

| Escenario | Concurrentes | Req/s est. | Req/min est. | Ops negocio/min | Endpoints calientes |
|-----------|--------------|------------|---------------|-----------------|---------------------|
| Normal (3 meseros) | 3–5 | 0.5–1.5 | 30–90 | 20–40 | items, sesión, mesas |
| Medio (10+3 cajas) | 13–15 | 2–5 | 120–300 | 80–150 | items, comandas, precuenta, ventas |
| Alto (20+10+cocina) | 35–40 | 5–12 | 300–700 | 200–400 | mesas, sesión, items, comandas, facturar |
| Peak Agro-Mall | 40–50 | 8–20 | 500–1200 | 300–600 | mismos + Ventas |
| Peak + Smartpyme | 50+otros | 15–40+ | 900–2400+ | — | PHP-FPM/DB compartidos |

Críticos bajo peak: `POST .../items`, `POST .../comandas`, `POST .../pre-cuenta`, `PUT .../marcar-facturada`, `GET /mesas`, `GET /comandas`, pipeline Ventas.

---

## 16. Load Test propuesto (k6 — diseño, sin script)

### Perfiles

**WAITER (55%)**  
login → `GET /mesas` → `POST /sesiones-mesa` (10%) / entrar sesión existente (90%) → `GET /sesion` → `GET pos-menu/*` → `POST items` (×3–8) → `PUT item` (20%) → `POST comandas` → `GET sesion` → `POST pre-cuenta` (15%) → think 5–20s.

**CASHIER (25%)**  
login → preparar factura PC → flujo ventas (endpoints reales de facturación) → `PUT marcar-facturada` → ocasional `GET /mesas`.

**KITCHEN (15%)**  
login → `GET /comandas` cada 10–30s (simular hábito humano) → `PUT estado` → `GET imprimir` (10%).

**ADMIN (5%)**  
`GET /mesas`, zonas, reservas; **evitar** reportes pesados ajenos en la misma prueba o medirlos aparte.

### Escenarios k6

- Ramp: Normal → Medio → Alto → Peak (15 min c/u).
- Thresholds: p95 `< 500ms` lecturas mapa/sesión; p95 `< 1s` items/comandas; p99 facturar `< 3s`; error rate `< 1%`; **cero** sesiones duplicadas por mesa (check SQL post-test).
- Chaos: 5% retry inmediato tras POST (escenario 9/10).

---

## 17. Observabilidad

Medir durante prueba y en Agro-Mall:

**App:** latency p50/p95/p99 por ruta restaurante; 4xx/5xx; timeouts.  
**Laravel:** slow log (>200ms), exceptions, query count/request (`GET /comandas`, `/mesas`).  
**PHP-FPM:** `active`, `max children reached`, listen queue.  
**MariaDB:** CPU, Threads_running, InnoDB row lock waits, slow queries, rows examined en comandas/mesas.  
**Redis:** baseline (sesión/cache app global); hoy no keys de restaurante.  
**Nginx:** active conn, 502/504.  
**Angular (DevTools/RUM):** tiempo `GET mesas`, reflow mapa, memoria cocina, llamadas duplicadas.

Alertas útiles: lock waits en `restaurante_sesiones_mesa` / `orden_detalle`; tasa 422 “mesa ya tiene sesión”; picos `comandas` index.

---

## 18. Riesgos

| Riesgo | Prob. | Impacto | Sev. | Archivo | Causa | Recomendación |
|--------|-------|---------|------|---------|-------|---------------|
| Doble sesión misma mesa | Alta | Alto | **CRÍTICO** | `SesionMesaController@store` ~31–50 | check-then-act sin lock/unique | `lockForUpdate` mesa + unique activa |
| Doble envío comanda | Media | Alto | **CRÍTICO** | `ComandaController@store` ~124–168 | sin lock líneas | lock ítems + where enviado=false en update |
| Lost update / líneas dup al agregar | Alta | Medio | **ALTO** | `OrdenDetalleController@store` ~109–161 | sin tx/lock | transacción + lock sesión |
| Doble marcar facturada | Media | Alto | **CRÍTICO** | `PreCuentaController@marcarFacturada` ~545–584 | sin lock fila | `lockForUpdate` PC |
| Liquidación N+1 + race | Media | Alto | **ALTO** | `liquidarOrden…` ~92–120 | loop queries | bulk + lock |
| Mapa desactualizado multi-mesero | Alta | Medio | **ALTO** | FE `restaurante.component` | sin realtime/poll | poll/ETag/websocket + invalidación |
| Cocina `whereHas` bajo refresh agresivo | Media | Alto | **ALTO** | `ComandaController@index` ~99–111 | subqueries | denormalizar `id_empresa` |
| Cerrar sesión con cuenta abierta | Baja | Alto | **MEDIO** | `SesionMesaController@cerrar` ~85–97 | sin validaciones | bloquear si PC/ítems |
| Update ítem ya enviado (API) | Media | Medio | **MEDIO** | `OrdenDetalleController@update` ~164–188 | sin guard | rechazar si enviado |
| Doble click agregar | Alta | Bajo-Medio | **MEDIO** | `pos-sheet-agregar` / `onConfirmarAgregar` | sin disabled | flag enviando |
| Payload mapa 139 full models | Alta | Medio | **MEDIO** | `MesaController@index` | sin select | DTO liviano |
| Stock canal double confirm | Media | Alto | **ALTO** | `PedidoRestauranteController@confirmar` | sin lock / flag | usar `inventario_descontado_at` + lock |
| Shared VPS noisy neighbor | Alta | Alto | **ALTO** | infra | multi-tenant | quotas / slow-query isolation |

---

## 19. Recomendaciones P0/P1/P2/P3

### P0 — Antes de Agro-Mall

1. **Unique / lock al abrir mesa** — `SesionMesaController@store` (~31–50): transacción + `Mesa::lockForUpdate()` + re-check; ideal unique parcial una sesión activa/mesa.
2. **Lock al enviar comanda** — `ComandaController@store`: `lockForUpdate` de `OrdenDetalle` pendientes; update condicional `enviado_*=false`.
3. **Lock al marcar facturada** — `PreCuentaController@marcarFacturada` (~548–584) + liquidación atómica.
4. **Tx + lock en `OrdenDetalleController@store`** al fusionar (~109–141).
5. **Disable FE en agregar ítem** — mismo patrón que `enviandoComanda`.
6. **Load test k6** escenarios Peak + verificación SQL de sesiones duplicadas.
7. **Protocolo cocina/mapa** — si usan refresh manual agresivo, acotar; si necesitan live, diseñar poll ≥15–30s o push, no 5s.

### P1

1. DTO liviano `GET /mesas` (id, numero, estado, zona, sesion_id, opened_at).
2. `id_empresa` (+ índice) en `comandas_restaurante` para eliminar `whereHas` en cocina.
3. Guard API: no `update` ítem enviado; endurecer `cerrar`.
4. Idempotency-Key en POST críticos.
5. OnPush + `trackBy` en mapa; memoizar `mesasPorZona`.

### P2

1. Cache Redis mapa 2–5s invalidado en open/close/factura.
2. Bulk updates en comanda/liquidación.
3. Índices compuestos fusion/comanda-empresa.
4. Sustituir authz por `tipo` string por permisos Spatie.

### P3

1. WebSockets/Reverb para mapa y cocina.
2. Archivo/particionado histórico sesiones.
3. Eliminar `PedidoRestauranteInventarioService` muerto o unificar con canal.
4. Reportes restaurante en réplica/cola si se agregan.

---

## 20. Conclusión

El módulo Restaurante está **funcionalmente completo para operación de mesas/comandas/precuentas/facturación**, con buenas piezas (eager en mapa, permisos, soft-delete con motivo, división en una request, POS por categoría).

Para Agro-Mall, el cuello **no** es polling (no hay), sino **integridad concurrente** y **picos síncronos** en mapa, cocina, ítems y cierre de cuenta, sobre un VPS multi-tenant. Sin P0 de locking/idempotencia, 20–25 meseros pueden generar mesas “dobles”, comandas duplicadas o liquidaciones inconsistentes aunque el servidor “aguante” el QPS.

---

# TOP 10 COSAS QUE DEBEMOS CORREGIR ANTES DEL AGRO-MALL

1. Race al abrir mesa — `SesionMesaController::store` (~31–50), sin lock/unique.
2. Race al enviar comanda — `ComandaController::store` (~124–168).
3. Race al facturar/liquidar — `PreCuentaController::marcarFacturada` + `liquidarOrdenTrasFacturarPreCuenta`.
4. Race al agregar/fusionar ítems — `OrdenDetalleController::store` (~109–161).
5. Doble click en agregar producto — FE sin flag (a diferencia de `enviandoComanda`).
6. `GET /comandas` con `whereHas` sin paginación — riesgo si cocina refresca seguido.
7. `GET /mesas` payload completo × 139 — optimizar select/DTO.
8. API permite `update` de ítem ya enviado — `OrdenDetalleController::update`.
9. Ausencia total de realtime → meseros trabajan con mapa stale (definir política de refresh).
10. Prueba de carga real + métricas PHP-FPM/MariaDB en Peak + resto Smartpyme.

---

# TOP 10 COSAS QUE YA ESTÁN BIEN IMPLEMENTADAS

1. Eager loading del mapa — `MesaController::index` `with(['sesionActiva','reservasActivas','zonaRestaurante'])` (~34–37).
2. Middleware funcionalidad + permisos por ruta en `restaurante.php`.
3. Filtro consistente `id_empresa` en la mayoría de controllers.
4. **No hay polling automático** — evita storm de requests con 25 dispositivos.
5. Separación cocina/barra por `destino_comanda` + flags `enviado_*`.
6. Eliminación de ítems enviados con autorización + log + comanda `DEL-*`.
7. División de cuenta (equitativa / por ítems) en una transacción vía `generar`.
8. POS menú por categorías/búsqueda limitada (30), no descarga catálogo entero.
9. Flags FE anti doble-submit en abrir mesa, enviar comanda, solicitar cuenta, traslado.
10. Handoff limpio a Ventas (`prepararFactura` → `marcarFacturada`) en lugar de facturar dentro del controller de mesas.
