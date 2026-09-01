# Descuentos con PIN en facturación Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dos permisos de Ventas (`aplicar` / `autorizar`) y PIN de supervisor al facturar si el cajero no tiene `aplicar`, sin cambiar el flujo actual hasta que un admin quite ese permiso.

**Architecture:** Catálogo Spatie + seeder aditivo. La regla vive en `VentaDescuentoAutorizacion` y se llama desde `FacturacionService::assertReglasNegocio` (el cobro real es `POST /facturacion`, no `POST /venta`). El front pide email+código en `onSubmit` de tienda y v2 (POS hereda v2) y lo manda en `descuento_autorizacion`. El id del autorizador se setea en servidor; no es mass-assignable.

**Tech Stack:** Laravel, Spatie permission, PHPUnit, Angular, SweetAlert2.

## Global Constraints

- No ejecutar ni recomendar `PermissionSeeder` (trunca tablas).
- No encender `AuthorizationService` / `HasAutoAuthorization` / `ventas_descuento_alto`.
- No comparar el PIN en el navegador (no copiar el SweetAlert de stock).
- Default: quien tenga `ventas.registros.crear` recibe `aplicar`. `usuario_ventas` no recibe `autorizar`.
- `autorizar` default: `admin`, `super_admin`, `usuario_supervisor`, `gerente_ventas`.
- Cotizaciones, puntos, promociones y consigna fuera de alcance (si `cotizacion == 1`, no exigir PIN).
- Credenciales: `descuento_autorizacion.usuario` = email, `descuento_autorizacion.codigo` = `users.codigo_autorizacion`.
- 403 genérico si email/PIN/`autorizar`/empresa fallan. Mensaje distinto solo si el supervisor no tiene código.
- Un PIN por venta, al facturar. Cualquier descuento de línea > 0 cuenta (`descuento`, `descuento_porcentaje`, `descuento_monto`). No `descuento_puntos`.
- Columna `ventas.id_usuario_autorizo_descuento` nullable, FK users, `nullOnDelete`. Fuera de `$fillable`.

---

### Task 1: Catálogo, RoleSeeder y seeder aditivo

**Files:**
- Modify: `Backend/config/permissions.php` (bloque `PERMISSION_VENTAS`)
- Modify: `Backend/database/seeders/RoleSeeder.php`
- Create: `Backend/database/seeders/VentasDescuentosPermissionSeeder.php`
- Create: `Backend/tests/Unit/Database/Seeders/VentasDescuentosPermissionSeederTest.php`

**Interfaces:**
- Produces: `config('permissions.PERMISSION_VENTAS.descuentos.aplicar')` = `ventas.descuentos.aplicar`
- Produces: `config('permissions.PERMISSION_VENTAS.descuentos.autorizar')` = `ventas.descuentos.autorizar`
- Produces: `php artisan db:seed --class=VentasDescuentosPermissionSeeder`

- [ ] **Step 1: Escribir la prueba del catálogo y del seeder**

Crear `Backend/tests/Unit/Database/Seeders/VentasDescuentosPermissionSeederTest.php` copiando el setup sqlite/tablas de `ModulosOperativosPermissionSeederTest` (`createPermissionTables` + `createModuleTables`). Afirmar:

1. `config('permissions.PERMISSION_VENTAS.descuentos')` es exactamente `['aplicar' => 'ventas.descuentos.aplicar', 'autorizar' => 'ventas.descuentos.autorizar']`.
2. `RoleSeeder.php` (leer fuente) contiene `PERMISSION_VENTAS.descuentos.autorizar` en los bloques de gerente ventas y usuario supervisor, y el recorido de `usuario_ventas` excluye `ventas.descuentos.autorizar`.
3. Tras seedear: permisos y submodule `descuentos` del module `ventas` existen; segunda corrida no cambia counts; `usuario_ventas` (previamente con `ventas.registros.crear`) tiene `aplicar` y no `autorizar`; `usuario_supervisor` tiene `autorizar`.

- [ ] **Step 2: Correr la prueba y ver el fallo**

Run: `cd Backend && php artisan test tests/Unit/Database/Seeders/VentasDescuentosPermissionSeederTest.php`

Expected: FAIL (clave `descuentos` ausente).

- [ ] **Step 3: Catálogo**

En `PERMISSION_VENTAS`, después de `ordenes_produccion`, agregar:

```php
        'descuentos' => [
            'aplicar' => 'ventas.descuentos.aplicar',
            'autorizar' => 'ventas.descuentos.autorizar',
        ]
```

- [ ] **Step 4: RoleSeeder**

En el foreach de `$usuarioVentas`, no agregar el permiso si `$subValue === 'ventas.descuentos.autorizar'`.

En `gerente_ventas` y `usuario_supervisor`, agregar:

```php
            config('permissions.PERMISSION_VENTAS.descuentos.aplicar'),
            config('permissions.PERMISSION_VENTAS.descuentos.autorizar'),
```

En `usuario_citas` (tiene `registros.crear`), agregar solo:

```php
            config('permissions.PERMISSION_VENTAS.descuentos.aplicar'),
```

- [ ] **Step 5: Seeder aditivo**

`VentasDescuentosPermissionSeeder`: `firstOrCreate` module `ventas` (display Ventas), submodule `descuentos` (display Descuentos), ambos permisos `guard_name=web`, `ModulePermission` con `permission_type=base`. `givePermissionTo('ventas.descuentos.aplicar')` a cada Role que ya tenga `ventas.registros.crear`. `givePermissionTo('ventas.descuentos.autorizar')` a `super_admin`, `admin`, `usuario_supervisor`, `gerente_ventas` si existen. `PermissionRegistrar::forgetCachedPermissions()`. No truncar. Idempotente.

- [ ] **Step 6: Correr pruebas**

Run: `cd Backend && php artisan test tests/Unit/Database/Seeders/VentasDescuentosPermissionSeederTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add Backend/config/permissions.php Backend/database/seeders/RoleSeeder.php Backend/database/seeders/VentasDescuentosPermissionSeeder.php Backend/tests/Unit/Database/Seeders/VentasDescuentosPermissionSeederTest.php
git commit -m "$(cat <<'EOF'
feat: agregar permisos de descuentos en Ventas

Aplicar y autorizar salen en Roles y Permisos. El seeder aditivo deja aplicar a quien ya vende para no cambiar caja.
EOF
)"
```

---

### Task 2: Servicio de autorización (TDD)

**Files:**
- Create: `Backend/app/Services/Ventas/VentaDescuentoAutorizacion.php`
- Create: `Backend/tests/Unit/Services/Ventas/VentaDescuentoAutorizacionTest.php`

**Interfaces:**
- Consumes: Spatie `can()`, `User.email`, `User.id_empresa`, `User.codigo_autorizacion`
- Produces: `VentaDescuentoAutorizacion::tieneDescuentoLinea(array $detalles): bool`
- Produces: `resolverIdAutorizador(User $cajero, Request $request): ?int` — `null` si no hace falta; `FacturacionException` 403 si falla

- [ ] **Step 1: Prueba unitaria (sqlite users + permissions como el seeder test)**

Casos:

- `tieneDescuentoLinea`: true si algún `descuento`, `descuento_porcentaje` o `descuento_monto` > 0; false si solo `descuento_puntos` o ceros.
- Cajero con `aplicar` + descuento → `null`.
- `cotizacion=1` + descuento, sin `aplicar` → `null`.
- Sin descuento de línea, sin `aplicar` → `null`.
- Sin `aplicar` + descuento, sin payload → 403 mensaje `No se pudo autorizar el descuento.`
- PIN de usuario misma empresa con `autorizar` y código → id del supervisor.
- PIN malo / otra empresa / sin `autorizar` → mismo 403 genérico.
- Supervisor sin `codigo_autorizacion` → 403 `El supervisor no tiene código de autorización configurado.`

- [ ] **Step 2: Correr y ver FAIL**

Run: `cd Backend && php artisan test tests/Unit/Services/Ventas/VentaDescuentoAutorizacionTest.php`

Expected: FAIL (clase no existe).

- [ ] **Step 3: Implementar**

```php
namespace App\Services\Ventas;

final class VentaDescuentoAutorizacion
{
    public const PERMISO_APLICAR = 'ventas.descuentos.aplicar';
    public const PERMISO_AUTORIZAR = 'ventas.descuentos.autorizar';
    public const MSG_GENERICO = 'No se pudo autorizar el descuento.';
    public const MSG_SIN_CODIGO = 'El supervisor no tiene código de autorización configurado.';

    public static function tieneDescuentoLinea(array $detalles): bool
    {
        foreach ($detalles as $det) {
            $det = (array) $det;
            foreach (['descuento', 'descuento_porcentaje', 'descuento_monto'] as $campo) {
                if ((float) ($det[$campo] ?? 0) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public function resolverIdAutorizador(User $cajero, Request $request): ?int
    {
        if ((int) $request->input('cotizacion') === 1) {
            return null;
        }
        if ($cajero->can(self::PERMISO_APLICAR)) {
            return null;
        }
        if (! self::tieneDescuentoLinea($request->input('detalles') ?? [])) {
            return null;
        }
        $email = (string) $request->input('descuento_autorizacion.usuario', '');
        $codigo = (string) $request->input('descuento_autorizacion.codigo', '');
        if ($email === '' || $codigo === '') {
            throw new FacturacionException(self::MSG_GENERICO, 403);
        }
        $supervisor = User::query()
            ->where('email', $email)
            ->where('id_empresa', $cajero->id_empresa)
            ->first();
        if (! $supervisor || ! $supervisor->can(self::PERMISO_AUTORIZAR)) {
            throw new FacturacionException(self::MSG_GENERICO, 403);
        }
        if ($supervisor->codigo_autorizacion === null || $supervisor->codigo_autorizacion === '') {
            throw new FacturacionException(self::MSG_SIN_CODIGO, 403);
        }
        if (! hash_equals((string) $supervisor->codigo_autorizacion, $codigo)) {
            throw new FacturacionException(self::MSG_GENERICO, 403);
        }
        return (int) $supervisor->id;
    }
}
```

- [ ] **Step 4: Correr pruebas — PASS**

Run: `cd Backend && php artisan test tests/Unit/Services/Ventas/VentaDescuentoAutorizacionTest.php`

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/Ventas/VentaDescuentoAutorizacion.php Backend/tests/Unit/Services/Ventas/VentaDescuentoAutorizacionTest.php
git commit -m "$(cat <<'EOF'
feat: validar PIN de descuento en servidor

Sin permiso aplicar, cualquier descuento de línea exige email y código de un usuario con autorizar de la misma empresa.
EOF
)"
```

---

### Task 3: Columna y hook en FacturacionService

**Files:**
- Create: `Backend/database/migrations/2026_09_01_140000_add_id_usuario_autorizo_descuento_to_ventas_table.php`
- Modify: `Backend/app/Services/Ventas/FacturacionService.php` (`assertReglasNegocio` y `procesar` justo después de `fill`)
- Modify: `Backend/app/Models/Ventas/Venta.php` — no agregar a `$fillable`; opcional `belongsTo` autorizador

**Interfaces:**
- Consumes: `resolverIdAutorizador`
- Produces: `request->attributes['id_usuario_autorizo_descuento']`; columna persistida

- [ ] **Step 1: Migración**

```php
Schema::table('ventas', function (Blueprint $table) {
    $table->unsignedBigInteger('id_usuario_autorizo_descuento')->nullable()->after('id_usuario');
    $table->foreign('id_usuario_autorizo_descuento')->references('id')->on('users')->nullOnDelete();
});
```

- [ ] **Step 2: En `assertReglasNegocio`, al final**

```php
$idAutorizador = app(VentaDescuentoAutorizacion::class)->resolverIdAutorizador($user, $request);
$request->attributes->set('id_usuario_autorizo_descuento', $idAutorizador);
```

- [ ] **Step 3: En `procesar`, después de `$venta->fill($request->all());`**

```php
$venta->id_usuario_autorizo_descuento = $request->attributes->get('id_usuario_autorizo_descuento');
```

No meter el campo en `$fillable`.

- [ ] **Step 4: Re-correr las pruebas del servicio + seeder**

Run: `cd Backend && php artisan test tests/Unit/Services/Ventas/VentaDescuentoAutorizacionTest.php tests/Unit/Database/Seeders/VentasDescuentosPermissionSeederTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Backend/database/migrations/2026_09_01_140000_add_id_usuario_autorizo_descuento_to_ventas_table.php Backend/app/Services/Ventas/FacturacionService.php Backend/app/Models/Ventas/Venta.php
git commit -m "$(cat <<'EOF'
feat: exigir autorización de descuento al facturar

FacturacionService aplica la regla antes de guardar y persiste quién autorizó.
EOF
)"
```

---

### Task 4: Modal al facturar (tienda, v2, POS)

**Files:**
- Create: `Frontend/src/app/views/ventas/facturacion/venta-descuento-autorizacion.util.ts`
- Create: `Frontend/src/app/views/ventas/facturacion/venta-descuento-autorizacion.util.spec.ts`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts` (`onSubmit`)
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts` (`onSubmit`)

POS extiende v2: un cambio en v2 cubre POS.

**Interfaces:**
- Consumes: `apiService.hasPermission('ventas.descuentos.aplicar')`
- Produces: `ventaTieneDescuentoLinea(venta)`, `pedirPinDescuentoSiAplica(api, venta): Promise<false | { usuario: string, codigo: string } | null>`
  - `null` = no hace falta (tiene aplicar o no hay descuento)
  - `false` = canceló el modal
  - objeto = credenciales a poner en `venta.descuento_autorizacion`

- [ ] **Step 1: Spec del util** (Jasmine, sin TestBed)

- `ventaTieneDescuentoLinea` true/false como el PHP.
- Un helper puro `debePedirPinDescuento(hasAplicar, venta)`: false si aplicar o cotizacion==1 o sin descuento.

- [ ] **Step 2: Correr FAIL**

Run: `cd Frontend && npx ng test --no-watch --browsers=ChromeHeadless --include='**/venta-descuento-autorizacion.util.spec.ts'`

Si el include de ng test no filtra, correr el archivo con el patrón que use el repo. Expected: FAIL.

- [ ] **Step 3: Implementar util**

`pedirPinDescuentoSiAplica`: si `debePedirPinDescuento` es false, `null`. Si sí, `Swal.fire` con dos campos (email, password), título tipo “Autorización de descuento”. Cancelar → `false`. Confirmar → `{ usuario, codigo }`. No validar el código en el cliente.

- [ ] **Step 4: `onSubmit` v1 y v2**

Antes de `apiService.store(endpointSave, this.venta)`:

```typescript
const pin = await pedirPinDescuentoSiAplica(this.apiService, this.venta);
if (pin === false) {
  this.saving = false;
  return;
}
if (pin) {
  this.venta.descuento_autorizacion = pin;
} else {
  delete this.venta.descuento_autorizacion;
}
```

Hacer `onSubmit` `async`. El 403 ya lo muestra `alertService.error`.

- [ ] **Step 5: Correr spec del util — PASS**

- [ ] **Step 6: Commit**

```bash
git add Frontend/src/app/views/ventas/facturacion/venta-descuento-autorizacion.util.ts Frontend/src/app/views/ventas/facturacion/venta-descuento-autorizacion.util.spec.ts Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts
git commit -m "$(cat <<'EOF'
feat: pedir PIN de descuento al facturar en caja

Si el cajero no puede aplicar descuentos, un modal pide el email y código del supervisor antes de POST /facturacion.
EOF
)"
```

---

## Verificación manual

1. Seeder aditivo en un ambiente con datos. Cajero con `aplicar` factura con descuento: igual que hoy.
2. Quitar `aplicar` al rol de ventas. Supervisor con `autorizar` y código. Al facturar con descuento: modal; PIN correcto guarda; incorrecto 403 y no hay venta.
3. Roles y Permisos → Ventas → Descuentos muestra `aplicar` y `autorizar`.
