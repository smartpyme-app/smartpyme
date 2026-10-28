# SP-2339 — Roles y permisos (PR / despliegue / pruebas)

**Rama:** `dc.SP-2339-roles-permisos`  
**Base:** `bk-main`  
**Jira:** SP-2339 (anular + UI roles), SP-2340 (emitir FE + ver costos), SP-2341 (partidas por origen), SP-2343 (matriz roles / seeder)  
**Fuera de alcance:** SP-2342 (flujo estados cotizaciones — se mantiene como en `bk-main`)

---

## Resumen

- Nuevos permisos **`*.anular`** conviven con **`*.eliminar`** (deprecado en UI, no se muestra en roles).
- **`canDeleteTest('…eliminar')`** en frontend resuelve **`.anular` primero**, luego `.eliminar`.
- Nuevos permisos de catálogo: emitir FE, ver costos inventario, partidas `generar_*`.
- API: DELETE operativos exigen **`eliminar|anular`** (Spatie).
- Seeder idempotente post-deploy para permisos, copia eliminar→anular y delta de matriz SP-2343.

---

## Cambios por área

### Backend — configuración y datos

| Archivo | Qué hace |
|---------|----------|
| `Backend/config/permissions.php` | `ventas.facturacion_electronica.emitir`, `productos.inventario.ver_costos`, `contabilidad.partidas.generar_{venta,cxc,compra,cxp,gasto}` |
| `Backend/database/seeders/Sp2339RolesPermisosSeeder.php` | Registra permisos en módulos; crea `*.anular` desde `*.eliminar` (prefijos ventas/compras/productos/servicios/gastos); copia roles y permisos directos; asigna `generar_*` a quien tenía `partidas.crear`; matriz SP-2343 (supervisor limitado sin ver costos; contadores sin emitir FE; ventas/ventas_limitado con emitir FE; contador_auxiliar sin `partidas.crear`) |
| `Backend/app/Support/PermisoAnular.php` | Helper middleware `xxx.eliminar\|xxx.anular` |
| `Backend/tests/Unit/Support/PermisoAnularTest.php` | Test unitario del helper |

### Backend — autorización

| Archivo | Qué hace |
|---------|----------|
| `AuthorizesPartidaContable.php` | Autoriza partida manual: permiso `generar_{tipo}` o fallback `contabilidad.partidas.crear` |
| `AuthorizesVerCostosInventario.php` | Permiso ver costos; oculta campos costo en `GET producto/{id}` |
| `Api/Contabilidad/ApiController.php` | Usa trait en venta, compra, gasto, cxc, cxp |
| `Api/Inventario/ProductosController.php` | Historial precio/costo exige permiso; read oculta costos sin permiso |

### Backend — rutas DELETE (middleware `permission:eliminar\|anular`)

- **Ventas:** `venta`, `venta/abono`, `venta/detalle`, devolución venta (+ detalle), cotización (+ detalle, `cotizacionVentas`, detalle cotización venta)
- **Compras:** `compra`, `compra/abono`, devolución compra (+ detalle)
- **Gastos:** `gasto`, `gasto/abono`, categoría gasto
- **Inventario:** `producto`, `servicio`

> **Nota:** Antes muchos DELETE no validaban permiso en ruta; tras el merge, hace falta **`anular` o `eliminar`** (post-seeder).

### Frontend — servicios

| Archivo | Qué hace |
|---------|----------|
| `permission.service.ts` | `canDeleteTest` → `.anular` antes que `.eliminar` |
| `api.service.ts` | `canGenerarPartidaContable`, `canEmitirFacturaElectronica`, `canVerCostosInventario` |
| `partida-venta-flow.service.ts` | Guard antes de POST partida venta |
| `permisos-roles-ui.util.ts` | Ocultar `.eliminar`, etiquetas, herencia padre↔hijo *(incluir en commit si aún no está en la rama)* |
| `permisos-roles-ui.check.mjs` | Check runnable: `node Frontend/src/app/shared/utils/permisos-roles-ui.check.mjs` |

### Frontend — admin roles

| Archivo | Qué hace |
|---------|----------|
| `roles-permisos.component.ts` | Filtra eliminar en listados; herencia al marcar padre/hijo; “seleccionar todo” ignora eliminar |

### Frontend — partidas (SP-2341)

Menú “Generar partida contable” solo si `canGenerarPartidaContable(tipo, contabilidadHabilitada)`:

- Ventas listado (`venta`), abonos ventas (`cxc`), abonos compras (`cxp`), compras, gastos

### Frontend — emitir FE (SP-2340)

Guard en `emitirDTE()`:

- `ventas.component.ts`, `facturacion.component.ts`, `facturacion-v2.component.ts`

### Frontend — ver costos (SP-2340)

`canVerCostosInventario()` en fichas/listados inventario y compras (producto, combo, kardex, historial, materias prima, consignas, facturación/detalle compra, etc.).

### Tests / regresión

- `abonos-acciones.regression.spec.ts` — asserts HTML partidas con permiso granular

---

## Estado del repositorio (revisar antes del PR)

El commit **`Actualizar roles y permisos`** incluye sobre todo **backend** (~20 archivos).  
Si en local aún aparecen **cambios sin commit en Frontend** o archivos sin trackear (`permisos-roles-ui.util.ts`, `.check.mjs`), **hace falta un segundo commit** con el frontend; sin eso el despliegue no aplica la UI de roles, costos, partidas ni emitir FE.

Comprobar:

```bash
git status
git diff bk-main --stat
```

---

## Pasos para probar (QA / staging)

### 0. Preparación

1. Desplegar backend + frontend de la rama.
2. Ejecutar seeder (ver sección producción).
3. Cerrar sesión y volver a entrar (permisos en caché del navegador / JWT).

### 1. Seeder y permisos nuevos

- [ ] Admin → Roles y permisos: aparecen **Anular**, **Ver costos**, **Emitir**, **Generar partida (…)**.
- [ ] No aparece ningún permiso cuyo nombre termine en **`.eliminar`** en la UI de roles.
- [ ] Rol que tenía `ventas.eliminar` (u otro operativo) queda con **`ventas.anular`** tras seeder.

### 2. Anular en UI

- [ ] Usuario **con** `*.anular`: ve acciones de anular/eliminar en módulos habituales (ventas, compras, productos, etc.).
- [ ] Usuario **sin** anular ni eliminar: no ve esas acciones.
- [ ] API DELETE (ej. anular venta/cotización) responde **403** sin permiso.

### 3. Ver costos inventario

Rol **sin** `productos.inventario.ver_costos` (ej. supervisor limitado tras seeder):

- [ ] Producto editar/ver: sin costo, ganancia, margen.
- [ ] Kardex / historial precio-costo: oculto o sin datos sensibles.
- [ ] Compras: sin columna costo donde aplica el cambio.

Rol **con** permiso: comportamiento anterior visible.

### 4. Emitir factura electrónica

Rol **sin** `ventas.facturacion_electronica.emitir` (ej. contador):

- [ ] Listado ventas y pantallas de facturación: al emitir DTE, mensaje de error / no procede.

Rol **con** permiso (ventas / ventas_limitado tras seeder): emisión normal.

### 5. Partidas contables

Empresa con contabilidad activa:

- [ ] Sin `generar_venta` ni `partidas.crear`: no menú partida en ventas; API partida venta 403.
- [ ] Con solo `generar_cxc`: partida en abonos ventas sí; en venta no (según permiso).
- [ ] Con `partidas.crear`: fallback como antes para todos los `generar_*` asignados por seeder.

### 6. Roles — herencia

- [ ] Marcar permiso padre (ej. `ventas.anular`) marca hijos del mismo verbo en submódulos.
- [ ] “Seleccionar todo” del módulo no deja colgados checkboxes por permisos eliminar ocultos.

### 7. Cotizaciones

- [ ] Flujo de **estados** igual que `bk-main` (pendiente, enviada, aceptada, etc.) — sin cambios SP-2342.
- [ ] Eliminar cotización requiere `ventas.cotizaciones.eliminar` **o** `ventas.cotizaciones.anular`.

### 8. Automatizado (opcional)

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Support/PermisoAnularTest.php
node Frontend/src/app/shared/utils/permisos-roles-ui.check.mjs
```

---

## Producción (después de merge del PR)

Orden recomendado:

### 1. Deploy

- Desplegar **Backend** y **Frontend** del merge a producción (mismo release).

### 2. Seeder (obligatorio)

En el servidor de producción (contenedor/app), **una vez por despliegue**:

```bash
cd /ruta/al/Backend
php artisan db:seed --class=Sp2339RolesPermisosSeeder
```

- **Idempotente:** se puede re-ejecutar; no trunca tablas.
- **No** está registrado en `DatabaseSeeder` a propósito (evita correr en todos los `db:seed` genéricos).

### 3. Caché de permisos

El seeder ya llama `forgetCachedPermissions()`. Si usan Redis/cache para Spatie:

```bash
php artisan cache:clear
php artisan permission:cache-reset
```

*(Solo si existe el comando en su versión de spatie/laravel-permission.)*

### 4. Usuarios

- Pedir **re-login** a usuarios activos para refrescar permisos en el cliente.
- Revisar roles críticos: **supervisor_limitado**, **contador_superior**, **contador_auxiliar**, **usuario_ventas**, **ventas_limitado**.

### 5. Comunicación interna (sugerido)

- **Eliminar** ya no se asigna en UI; usar **Anular**.
- Contadores: sin emitir FE por defecto (matriz seeder).
- Supervisor limitado: sin ver costos inventario por defecto.
- DELETE en API ahora exige permiso; integraciones/custom que borraban sin permiso fallarán con 403 hasta asignar anular.

### 6. Rollback

- Revert del deploy de código.
- **No** hay migración down automática de permisos; permisos creados en BD pueden quedar (inofensivos). Revertir asignaciones manuales solo si hiciera falta.

---

## Known gaps / follow-up (no bloquean merge si se acepta el alcance)

- Emitir DTE: enforcement principal en **frontend**; API MH puede no validar aún `ventas.facturacion_electronica.emitir`.
- Costos: algunos endpoints de búsqueda/listado de productos pueden seguir devolviendo costo en JSON (UI ya oculta).
- Partidas desde inventario (ajustes, entradas/salidas, retaceo): sin permisos granulares nuevos en esta fase.
- SP-2342 / alinear cotizaciones con `main` legacy: ticket aparte.

---

## Referencia rápida — permisos nuevos

| Permiso | Uso |
|---------|-----|
| `{modulo…}.anular` | Sustituto visible de eliminar (módulos operativos) |
| `productos.inventario.ver_costos` | Ver costos en inventario/compras (UI + historial API) |
| `ventas.facturacion_electronica.emitir` | Emitir DTE desde ventas |
| `contabilidad.partidas.generar_venta` | Partida manual desde venta |
| `contabilidad.partidas.generar_cxc` | Partida desde abono venta |
| `contabilidad.partidas.generar_cxp` | Partida desde abono compra |
| `contabilidad.partidas.generar_compra` | Partida desde compra |
| `contabilidad.partidas.generar_gasto` | Partida desde gasto |

Fallback partidas: quien tiene `contabilidad.partidas.crear` sigue pudiendo generar si no tiene el granular (API + lógica FE).
