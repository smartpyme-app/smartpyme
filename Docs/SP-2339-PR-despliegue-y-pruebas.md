# SP-2339 — Roles y permisos (PR / despliegue / pruebas)

**Rama:** `dc.SP-2339-roles-permisos`  
**Base:** `bk-main`  
**Jira:** SP-2339 (anular + UI roles), SP-2340 (emitir FE + ver costos), SP-2341 (partidas por origen), SP-2343 (matriz roles / seeder)  
**QA cliente (post-SP-2339):** solicitudes, precios por usuario, menú ventas, cotizaciones legacy, OC/documentos, usuarios, licencias org.

---

## Resumen

- Nuevos permisos **`*.anular`** conviven con **`*.eliminar`** (deprecado en UI, no se muestra en roles).
- **`canDeleteTest('…eliminar')`** en frontend resuelve **`.anular` primero**, luego `.eliminar`.
- Catálogo ampliado: emitir FE, ver costos inventario, partidas `generar_*`, **`ventas.solicitudes.*`**.
- API: DELETE operativos exigen **`eliminar|anular`** (Spatie).
- Seeder idempotente post-deploy: permisos, copia eliminar→anular, matriz SP-2343, solicitudes cross-empresa.

---

## Fase QA cliente — incluido en esta rama

### Hecho (XS/S — primera tanda)

| Ítem | Cambio |
|------|--------|
| Buscador usuarios | `buscarUsuarios()` no usa `loadAll()` (no borra `buscador`). API: buscador agrupado `(name OR email)` **dentro** del filtro `id_empresa` / licencia (evita filtrar usuarios de otras empresas). Org: `admin-usuarios` y `licencias/usuarios` limitados a empresas de la licencia (`AdminUsuariosAlcance`); CRUD 403 fuera de alcance. |
| Crear venta (vendedor) | `/vendedor/ventas`: botón solo con `ventas.crear`. |
| Solicitudes | Permisos `ventas.solicitudes.{ver,crear,editar,eliminar}`; menú + ruta; ventas limitado excluido; seeder asigna desde cotizaciones. |

### Hecho (M — segunda tanda)

| Ítem | Cambio |
|------|--------|
| Precios por usuario | FE: `precios-usuario-venta.util` + selector v1/v2 + mapper v2; BE: filtro en `searchByCode` / solicitud. |
| Menú ⋮ ventas | Listado `/ventas` y `/vendedor/ventas`: pagar (`abonos.crear`/editar), devolución, anular, emitir FE según permiso. Util compartido `ventas-acciones-menu.util.ts`; abonos, devoluciones, cotizaciones, solicitudes, órdenes producción y clientes alineados a permisos granulares. |
| Rendimiento listado ventas | `GET /ventas?listado=1`: columnas mínimas (sin JSON `dte` ni `Schema::getColumnListing` por request). **Admin** `/ventas` y **vendedor** `/vendedor/ventas` envían `listado=1`. Modo ligero incluye totales abonos/devoluciones, NC/ND y paquetes Boxful vía `withListadoRelations()`. Paginación API: máx. 50 filas. Índice compuesto opcional: migración `2026_10_28_120000_add_ventas_listado_usuario_index.php`. |
| Cotizaciones estados | Alineado a **legacy `main`**: `Pendiente`, `Facturada`, `Anulada` (UI + `CotizacionConstants` + validación API). |
| OC / documento | Form OC: preselecciona documento «Orden de compra» y correlativo si existe en catálogo. |
| Licencias org | `GET licencias/empresas/select` (sin super_admin); POST con `organizacion.empresas.crear`; botón con permiso. |

---

## Cambios por área (SP-2339 core)

### Backend — configuración y datos

| Archivo | Qué hace |
|---------|----------|
| `Backend/config/permissions.php` | FE emitir, ver costos, partidas `generar_*`, **`ventas.solicitudes.*`** |
| `Backend/database/seeders/Sp2339RolesPermisosSeeder.php` | Permisos, anular, matriz SP-2343, **solicitudes desde cotizaciones**, revoca solicitudes en `ventas_limitado` |
| `Backend/database/seeders/VentasLimitadoRoleSeeder.php` | Excluye `ventas.solicitudes.*` |
| `Backend/app/Constants/CotizacionConstants.php` | Estados legacy PascalCase + `estadosPermitidos()` |
| `Backend/app/Http/Controllers/Api/Licencias/EmpresasController.php` | `selectParaLicencia()` |
| `Backend/routes/modulos/admin/licencias.php` | Middleware permisos organización |

### Backend — inventario / cotizaciones

| Archivo | Qué hace |
|---------|----------|
| `ProductosController` | `filtrarPreciosPorUsuarioAutenticado()` en búsqueda por código |
| `CotizacionesController` | Valida estados permitidos al cambiar estado; duplicar → `Pendiente` |
| `VentasController::index` | Query param `listado=1` → `selectListado()` + `withListadoRelations()`; paginate cap 50 |
| `Venta.php` | `scopeSelectListado`, `scopeWithListadoRelations`, `scopeWithAccessorRelations` |
| `IndexVentaRequest` | Valida `listado` booleano |
| Migración `2026_10_28_120000_add_ventas_listado_usuario_index` | Índice `(id_empresa, id_usuario, fecha, id)` para filtro vendedor/caja |

### Frontend — clave

| Área | Archivos |
|------|----------|
| Solicitudes | `solicitudes-compra-acceso.ts`, sidebars, routing, `solicitudes-compra.component.html` |
| Usuarios | `usuarios.component.ts/html` |
| Ventas | `ventas.component.*` (`listado: 1` en filtros/URL), `caja-ventas.component.*` (`listado=1`, `paginate=25`), `ventas-acciones-menu.util.ts`, `ventas-acciones-menu.check.mjs` |
| Footer / versión | `environment.appVersion` (evita fetch lento a `manifest.webmanifest` en cada carga) |
| Precios | `precios-usuario-venta.util.ts`, `venta-detalles*.ts`, `producto-detalle-v2.mapper.ts` |
| Cotizaciones | `cotizaciones.component.*`, `cotizacion-form` estado `Pendiente` |
| Organización | `organizacion-empresas.component.*` |
| Compras OC | `orden-compra-form.component.ts` |

### Checks runnable

```bash
node Frontend/src/app/shared/utils/permisos-roles-ui.check.mjs
node Frontend/src/app/views/ventas/solicitudes-compra/solicitudes-compra-acceso.check.mjs
node Frontend/src/app/utils/precios-usuario-venta.util.check.mjs
node Frontend/src/app/views/ventas/ventas-acciones-menu.check.mjs
cd Backend && ./vendor/bin/phpunit tests/Unit/Ventas/Cotizaciones/CotizacionVentasLimitadoAccionesTest.php
cd Backend && ./vendor/bin/phpunit tests/Unit/Ventas/VentaListadoSelectTest.php
```

---

## Pasos para probar (QA / staging)

### 0. Preparación

1. Desplegar backend + frontend de la rama.
2. Migraciones pendientes (`php artisan migrate`) — incluye índice listado ventas si aplica.
3. Ejecutar seeder (obligatorio).
4. Re-login.

### 1. Seeder

```bash
cd Backend && php artisan db:seed --class=Sp2339RolesPermisosSeeder
```

- [ ] Roles: **Ventas → Solicitudes** (ver/crear/editar/anular).
- [ ] `ventas_limitado` **sin** solicitudes.
- [ ] Roles con cotizaciones reciben solicitudes (excepto ventas limitado).

### 2. Solicitudes + ventas limitado

- [ ] Ventas limitado: **sin** menú Solicitudes; URL `/solicitudes-compra` bloqueada sin permiso.
- [ ] Rol con `ventas.solicitudes.ver`: menú visible (empresa con licencia org).

### 3. Usuarios — buscador y alcance

- [ ] Admin → Usuarios (empresa): buscar «Juan» solo muestra usuarios de **la misma empresa** (no correos de otras empresas).
- [ ] Organización → Usuarios (`/organizacion/…/usuarios`): listado solo usuarios de empresas de **la licencia** (titular + hijas); filtro empresa solo lista licencias/empresas/list.
- [ ] Intentar editar usuario de otra org vía API → 403.
- [ ] Buscador: Enter filtra sin vaciar el campo (`buscarUsuarios` / `aplicarFiltros`).

### 4. Crear venta

- [ ] Solo `ventas.ver` → `/vendedor/ventas` **sin** «Añadir venta».
- [ ] Con `ventas.crear` → botón visible.

### 5. Precios múltiples por usuario

- [ ] Producto con 3 tarifas; una restringida a usuario A: usuario B en venta/cotización **no** ve esa tarifa en el selector.
- [ ] Tarifa sin usuarios asignados: visible para todos.

### 6. Menú acciones ventas

- [ ] Sin `ventas.abonos.crear` ni editar: no «Pagar venta».
- [ ] Sin `ventas.devoluciones.crear`: no devolución.
- [ ] Sin emitir FE: no emitir DTE en ⋮.
- [ ] Solo `ventas.registros.ver`: detalles/imprimir en caja; sin editar, DTE, pagar ni traslado en admin.
- [ ] Abonos: editar con `ventas.abonos.editar`; imprimir con editar o `ventas.abonos.ver`.

### 6b. Rendimiento — listado ventas (admin y vendedor)

- [ ] DevTools → Network: al abrir **`/ventas`** (admin) la petición `GET …/ventas` incluye **`listado=1`** (y `paginate` según UI, típ. 10).
- [ ] Misma comprobación en **`/vendedor/ventas`**: `listado=1` y `paginate=25`.
- [ ] Tiempo de respuesta razonable vs. listado completo (sin `listado`: carga todas las columnas + JSON DTE).
- [ ] Grilla admin sigue mostrando: totales pendientes (abonos/devoluciones en popover), badge FE, menú Boxful si aplica, NC/ND en detalle de fila.
- [ ] Editar venta desde ⋮ sigue abriendo modal con **`GET venta/{id}`** (detalle completo bajo demanda).

### 7. Cotizaciones (legacy)

- [ ] Select solo: **Pendiente**, **Facturada**, **Anulada** (PascalCase).
- [ ] Acciones: editar/detalle/facturar/imprimir como en main (sin flujo enviada/aceptada).
- [ ] Datos viejos en minúsculas: revisar visualmente; nuevos cambios usan PascalCase.

### 8. Orden de compra

- [ ] Existe documento «Orden de compra» en Preferencias → Documentos.
- [ ] Crear OC desde menú: tipo documento y correlativo (referencia) precargados si hay documento activo.

### 9. Licencias — añadir empresa

- [ ] Empresa padre org, admin con `organizacion.empresas.crear`: modal carga lista (`licencias/empresas/select`), guardar OK.
- [ ] Sin permiso: botón oculto / POST 403.

### 10. SP-2339 original

- [ ] Anular, ver costos, emitir FE, partidas granulares (secciones previas del doc siguen vigentes).

---

## Producción (después de merge)

```bash
cd Backend && php artisan migrate
cd Backend && php artisan db:seed --class=Sp2339RolesPermisosSeeder
php artisan cache:clear
# permission:cache-reset si aplica
```

Re-login obligatorio.

---

## Notas / riesgos

- **Cotizaciones en BD** con estados extendidos (`enviada`, `aceptada`, …) no se migran automáticamente; el UI ya no ofrece esos valores. Valorar script SQL de normalización si el cliente lo pide.
- **`licencias/empresas/select`**: lista empresas del sistema no ligadas a la licencia (excluye empresa padre de la licencia); acotar más si negocio lo requiere.
- **Precios**: filtro backend solo en búsqueda por código; otras rutas de producto pueden seguir enviando tarifas completas (UI filtra en selector).
- **Listado ventas (`listado=1`)**: no sustituye el detalle de una venta (`GET venta/{id}`). Filtro por **vendedor** (`id_vendedor`) puede seguir siendo costoso por `orWhereHas('detalles')`; el índice de migración ayuda al patrón empresa + usuario + fecha. `selectListado` solo columnas reales en `ventas` (no existe columna `credito`; crédito/Boxful usa `condicion`).
- **Versión en footer**: usa `environment.appVersion`; no depende de descargar `manifest.webmanifest` en runtime.

---

## Referencia — permisos nuevos / QA

| Permiso | Uso |
|---------|-----|
| `ventas.solicitudes.*` | Menú y pantalla solicitudes cross-empresa |
| `{modulo…}.anular` | Sustituto visible de eliminar |
| `productos.inventario.ver_costos` | Costos inventario/compras |
| `ventas.facturacion_electronica.emitir` | Emitir DTE |
| `contabilidad.partidas.generar_*` | Partidas por origen |
| `organizacion.empresas.crear` | POST licencia/empresa + modal select |
