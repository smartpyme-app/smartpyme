# Permisos para módulos operativos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrar Planillas, Consignas, Restaurante y Pedidos al sistema de roles y permisos, con reglas equivalentes en catálogo, navegación y API.

**Architecture:** Spatie seguirá siendo la fuente de autorización por usuario/rol. Restaurante y Pedidos combinarán permiso Spatie con la funcionalidad empresarial existente. Un seeder aditivo actualizará instalaciones existentes sin ejecutar el `PermissionSeeder` destructivo.

**Tech Stack:** Laravel 12, spatie/laravel-permission, PHPUnit, Angular, Angular Router guards.

## Global Constraints

- No ejecutar ni recomendar `PermissionSeeder` en producción.
- No crear flags empresariales para Planillas o Consignas.
- Acceso predeterminado: `super_admin`, `admin`, `usuario_supervisor`, `contador_superior`.
- Sin acceso predeterminado: `supervisor_limitado`, `contador_auxiliar` y demás roles.
- Restaurante y Pedidos son permisos independientes y conservan `modulo-restaurante` como prerrequisito.
- No modificar `Frontend/src/manifest.webmanifest`.
- Hacer un único commit final, según solicitud del usuario.

---

### Task 1: Catálogo y sincronización aditiva de permisos

**Files:**
- Modify: `Backend/config/permissions.php`
- Modify: `Backend/database/seeders/RoleSeeder.php`
- Create: `Backend/database/seeders/ModulosOperativosPermissionSeeder.php`
- Create: `Backend/tests/Unit/Database/Seeders/ModulosOperativosPermissionSeederTest.php`

**Interfaces:**
- Produces: `PERMISSION_CONSIGNAS`, `PERMISSION_RESTAURANTE`, `PERMISSION_PEDIDOS`.
- Produces: seeder idempotente invocable con `php artisan db:seed --class=ModulosOperativosPermissionSeeder`.

- [ ] **Step 1: Escribir la prueba del catálogo**

La prueba debe afirmar que cada módulo nuevo contiene exactamente `ver`, `crear`, `editar`, `eliminar`, que los nombres tienen el prefijo correcto y que Planillas conserva sus 16 permisos.

- [ ] **Step 2: Ejecutar la prueba y comprobar el fallo**

Run: `cd Backend && php artisan test tests/Unit/Database/Seeders/ModulosOperativosPermissionSeederTest.php`

Expected: FAIL porque las claves nuevas no existen.

- [ ] **Step 3: Agregar los bloques mínimos al catálogo**

```php
'PERMISSION_CONSIGNAS' => [
    'ver' => 'consignas.ver',
    'crear' => 'consignas.crear',
    'editar' => 'consignas.editar',
    'eliminar' => 'consignas.eliminar',
],
'PERMISSION_RESTAURANTE' => [
    'ver' => 'restaurante.ver',
    'crear' => 'restaurante.crear',
    'editar' => 'restaurante.editar',
    'eliminar' => 'restaurante.eliminar',
],
'PERMISSION_PEDIDOS' => [
    'ver' => 'pedidos.ver',
    'crear' => 'pedidos.crear',
    'editar' => 'pedidos.editar',
    'eliminar' => 'pedidos.eliminar',
],
```

- [ ] **Step 4: Implementar el seeder aditivo**

El seeder debe recorrer los tres bloques nuevos y `PERMISSION_PLANILLA`, usar `Permission::firstOrCreate`, `Module::firstOrCreate` y `ModulePermission::firstOrCreate`, asignar todos los permisos a los cuatro roles predeterminados que existan, y limpiar `PermissionRegistrar`. No debe lanzar error si un rol opcional no existe.

- [ ] **Step 5: Actualizar defaults para instalaciones nuevas**

Agregar los permisos nuevos y de Planillas al bloque de `usuario_supervisor`; agregar los nuevos a `contador_superior`. `admin` y `super_admin` ya reciben `Permission::all()`.

- [ ] **Step 6: Ejecutar pruebas**

Run: `cd Backend && php artisan test tests/Unit/Database/Seeders/ModulosOperativosPermissionSeederTest.php`

Expected: PASS.

---

### Task 2: Proteger las API

**Files:**
- Modify: `Backend/routes/modulos/planilla/*.php`
- Modify: `Backend/routes/modulos/inventario/productos.php`
- Modify: `Backend/routes/modulos/restaurante.php`
- Create: `Backend/tests/Feature/Auth/ModulosOperativosPermissionsTest.php`

**Interfaces:**
- Consumes: permisos de Task 1.
- Produces: HTTP 403 para usuarios sin permiso; conserva middleware de funcionalidad en Restaurante/Pedidos.

- [ ] **Step 1: Escribir pruebas de acceso**

Cubrir al menos un GET y un write por módulo:

```php
$user->revokePermissionTo('consignas.ver');
$this->actingAs($user, 'api')->getJson('/api/productos/consignas')->assertForbidden();

$user->givePermissionTo('consignas.ver');
$this->actingAs($user, 'api')->getJson('/api/productos/consignas')->assertStatus(200);
```

Repetir el patrón para Planillas, Restaurante y Pedidos; Restaurante/Pedidos deben permanecer prohibidos cuando la funcionalidad empresarial está inactiva.

- [ ] **Step 2: Ejecutar pruebas y comprobar el fallo**

Run: `cd Backend && php artisan test tests/Feature/Auth/ModulosOperativosPermissionsTest.php`

Expected: FAIL porque las rutas solo validan sesión o funcionalidad.

- [ ] **Step 3: Aplicar permisos por verbo**

- GET/list/show/download/print: `permission:<modulo>.ver`
- POST/store/generate: `permission:<modulo>.crear`
- PUT/PATCH/update/state transitions: `permission:<modulo>.editar`
- DELETE/destroy: `permission:<modulo>.eliminar`

En Planillas usar los permisos específicos `planilla.empleados.*`, `planilla.registros.*` y `planilla.configuracion.*` según cada archivo; aguinaldos y préstamos usarán `planilla.registros.*`.

En `restaurante.php`, las rutas de `PedidoRestauranteController` usarán `pedidos.*`; las demás usarán `restaurante.*`. Todo el archivo conserva:

```php
->middleware(['verificar.funcionalidad:modulo-restaurante'])
```

- [ ] **Step 4: Ejecutar pruebas**

Run: `cd Backend && php artisan test tests/Feature/Auth/ModulosOperativosPermissionsTest.php`

Expected: PASS.

---

### Task 3: Alinear sidebar y rutas Angular

**Files:**
- Modify: `Frontend/src/app/layout/sidebar/sidebar.component.html`
- Modify: `Frontend/src/app/views/planillas/planillas.routing.module.ts`
- Modify: `Frontend/src/app/views/inventario/inventario.routing.module.ts`
- Modify: `Frontend/src/app/views/restaurante/restaurante-routing.module.ts`
- Modify: `Frontend/src/app/views/pedidos/pedidos-routing.module.ts`
- Modify tests adjacent to the changed components/routing files, or create focused specs if absent.

**Interfaces:**
- Consumes: `ApiService.hasPermission(string)` y `PermissionGuard`.
- Produces: menús y navegación coherentes con los permisos efectivos.

- [ ] **Step 1: Escribir/actualizar pruebas frontend**

Casos mínimos:

- `planilla.ver=false` oculta Planillas.
- `planilla.ver=true` y `planilla.empleados.ver=false` oculta solo Empleados.
- Restaurante y Pedidos consultan permisos independientes además de sus flags de visibilidad.
- Rutas directas declaran `PermissionGuard` y `data.permission`.
- Configuraciones filtra Usuarios, Sucursales y Suscripción igual que el header; Mi cuenta siempre aparece.

- [ ] **Step 2: Ejecutar pruebas y comprobar el fallo**

Run: `cd Frontend && npm test -- --watch=false`

Expected: FAIL en las nuevas expectativas.

- [ ] **Step 3: Corregir sidebar**

Cambiar los checks invertidos de Planilla:

```html
@if (apiService.hasPermission('planilla.ver')) { ... }
@if (apiService.hasPermission('planilla.empleados.ver')) { ... }
@if (apiService.hasPermission('planilla.registros.ver')) { ... }
@if (apiService.hasPermission('planilla.configuracion.ver')) { ... }
```

Aplicar:

```html
*ngIf="mostrarMenuRestaurante && apiService.hasPermission('restaurante.ver')"
*ngIf="mostrarMenuPedidos && apiService.hasPermission('pedidos.ver')"
```

El grupo Configuraciones queda visible porque Mi cuenta siempre está disponible. Sus hijos:

```html
@if (apiService.hasPermission('organizacion.usuarios.ver')) { ...Usuarios... }
@if (apiService.hasPermission('administracion.sucursales.ver')) { ...Sucursales... }
@if (apiService.validateRole('admin', true)) { ...Mi suscripción... }
...Mi cuenta...
```

Eliminar del grupo las opciones que no existen en el header (`Reportes automáticos` y `Auditoría`), sin eliminar sus rutas.

- [ ] **Step 4: Agregar guards a rutas**

Agregar `PermissionGuard` y `data: { permission: '...' }` en las rutas de cada feature. Mantener `InventarioOperacionesAdminGuard` en Consignas y los guards existentes de Planillas. Restaurante/Pedidos mantienen su control de funcionalidad y añaden el permiso independiente.

- [ ] **Step 5: Ejecutar pruebas y build**

Run: `cd Frontend && npm test -- --watch=false`

Expected: PASS.

Run: `cd Frontend && npm run build`

Expected: build exitoso.

---

### Task 4: Verificación, documentación operativa y commit

**Files:**
- Verify: todos los archivos anteriores.
- Include: `Docs/superpowers/specs/2026-08-05-permisos-modulos-operativos-design.md`
- Include: `Docs/superpowers/plans/2026-08-05-permisos-modulos-operativos.md`

- [ ] **Step 1: Ejecutar pruebas backend enfocadas**

Run:

```bash
cd Backend
php artisan test tests/Unit/Database/Seeders/ModulosOperativosPermissionSeederTest.php
php artisan test tests/Feature/Auth/ModulosOperativosPermissionsTest.php
```

Expected: PASS.

- [ ] **Step 2: Ejecutar lint/compilación frontend**

Run: `cd Frontend && npm run build`

Expected: build exitoso.

- [ ] **Step 3: Revisar diff y excluir cambios ajenos**

No incluir cambios del usuario en `Frontend/src/manifest.webmanifest`. Incluir solo el trabajo solicitado en esta conversación y los cambios previos que el usuario pidió incluir en “todo”, después de revisar que no contengan secretos.

- [ ] **Step 4: Crear un único commit**

Mensaje sugerido:

```text
feat: enforce permissions for operational modules

Add role-controlled access for payroll, consignments, restaurant and orders across navigation and APIs, while safely preserving existing production assignments.
```

- [ ] **Step 5: Verificar estado posterior**

Run: `git status --short`

Expected: solo quedan sin commit cambios ajenos explícitamente excluidos.
