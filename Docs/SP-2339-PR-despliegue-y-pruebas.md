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
| Buscador usuarios | `buscarUsuarios()` no usa `loadAll()` (no borra `buscador`). |
| Crear venta (vendedor) | `/vendedor/ventas`: botón solo con `ventas.crear`. |
| Solicitudes | Permisos `ventas.solicitudes.{ver,crear,editar,eliminar}`; menú + ruta; ventas limitado excluido; seeder asigna desde cotizaciones. |

### Hecho (M — segunda tanda)

| Ítem | Cambio |
|------|--------|
| Precios por usuario | FE: `precios-usuario-venta.util` + selector v1/v2 + mapper v2; BE: filtro en `searchByCode` / solicitud. |
| Menú ⋮ ventas | Listado `/ventas` y `/vendedor/ventas`: pagar (`abonos.crear`/editar), devolución, anular, emitir FE según permiso. |
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

### Frontend — clave

| Área | Archivos |
|------|----------|
| Solicitudes | `solicitudes-compra-acceso.ts`, sidebars, routing, `solicitudes-compra.component.html` |
| Usuarios | `usuarios.component.ts/html` |
| Ventas | `ventas.component.*`, `caja-ventas.component.*` |
| Precios | `precios-usuario-venta.util.ts`, `venta-detalles*.ts`, `producto-detalle-v2.mapper.ts` |
| Cotizaciones | `cotizaciones.component.*`, `cotizacion-form` estado `Pendiente` |
| Organización | `organizacion-empresas.component.*` |
| Compras OC | `orden-compra-form.component.ts` |

### Checks runnable

```bash
node Frontend/src/app/shared/utils/permisos-roles-ui.check.mjs
node Frontend/src/app/views/ventas/solicitudes-compra/solicitudes-compra-acceso.check.mjs
node Frontend/src/app/utils/precios-usuario-venta.util.check.mjs
cd Backend && ./vendor/bin/phpunit tests/Unit/Ventas/Cotizaciones/CotizacionVentasLimitadoAccionesTest.php
```

---

## Pasos para probar (QA / staging)

### 0. Preparación

1. Desplegar backend + frontend de la rama.
2. Ejecutar seeder (obligatorio).
3. Re-login.

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

### 3. Usuarios — buscador

- [ ] Admin → Usuarios: buscar por nombre/email + Enter filtra sin vaciar el campo.

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
