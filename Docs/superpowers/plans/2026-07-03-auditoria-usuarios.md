# Auditoría de actividad de usuarios — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar y mostrar acciones de usuarios (crear/actualizar/eliminar documentos de negocio) con vistas filtrables para admin de empresa y super admin cross-tenant.

**Architecture:** Laravel Auditing v14 en modelos clave; tabla `audits` extendida con `id_empresa` y `module`; API paginada con scopes de tenant; Angular reutiliza patrón de listados existentes; purge mensual vía Artisan.

**Tech Stack:** Laravel 12, PHP 8.2+, JWT, Spatie Permission, Angular 20, owen-it/laravel-auditing ^14.

**Design spec:** [`Docs/superpowers/specs/2026-07-03-auditoria-usuarios-design.md`](../specs/2026-07-03-auditoria-usuarios-design.md)

---

## Mapa de archivos

### Backend — nuevo

| Archivo | Responsabilidad |
|---------|-----------------|
| `Backend/config/auditing.php` | Config publicada + `purge_months`, resolvers |
| `Backend/database/migrations/2026_07_03_100000_add_empresa_module_to_audits_table.php` | Columnas `id_empresa`, `module`, índices |
| `Backend/app/Models/Audit/Audit.php` | Modelo extendido, scope `empresa`, relaciones |
| `Backend/app/Models/Concerns/AuditableForEmpresa.php` | Trait wrapper: Auditable + `transformAudit` + `getModule()` |
| `Backend/app/Resolvers/JwtUserResolver.php` | Resolver de usuario para JWT (no session) |
| `Backend/app/Services/Audit/AuditQueryService.php` | Listado paginado con filtros |
| `Backend/app/Services/Audit/AuditPresentationService.php` | Texto legible en español |
| `Backend/app/Http/Controllers/Api/Admin/AuditoriaController.php` | GET tenant-scoped |
| `Backend/app/Http/Controllers/Api/SuperAdmin/AuditoriaController.php` | GET cross-tenant |
| `Backend/app/Http/Resources/AuditResource.php` | JSON para frontend |
| `Backend/routes/modulos/admin/auditoria.php` | Rutas tenant |
| `Backend/routes/modulos/super-admin/auditoria.php` | Rutas platform |
| `Backend/app/Console/Commands/PurgeAuditsCommand.php` | `auditoria:purge` |
| `Backend/tests/Feature/Api/Admin/AuditoriaControllerTest.php` | Scoping + filtros |
| `Backend/tests/Unit/Services/Audit/AuditPresentationServiceTest.php` | Mensajes ES |

### Backend — modificar

| Archivo | Cambio |
|---------|--------|
| `Backend/composer.json` | `owen-it/laravel-auditing:^14.0` |
| `Backend/app/Models/Ventas/Venta.php` | `use AuditableForEmpresa` |
| `Backend/app/Models/Compras/Compra.php` | idem |
| `Backend/app/Models/CotizacionVenta.php` | idem |
| `Backend/app/Models/Ventas/Orden_Produccion/OrdenProduccion.php` | idem |
| `Backend/app/Models/OrdenCompra.php` | idem |
| `Backend/app/Models/Compras/Gastos/Gasto.php` | idem |
| `Backend/app/Models/Inventario/Entradas/Entrada.php` | idem |
| `Backend/app/Models/Inventario/Salidas/Salida.php` | idem |
| `Backend/app/Models/Inventario/Ajuste.php` | idem |
| `Backend/app/Models/Inventario/Traslados/Traslado.php` | idem |
| `Backend/app/Models/Inventario/Producto.php` | idem (excluir campos ruidosos) |
| `Backend/config/permissions.php` | `auditoria.ver`, `auditoria.plataforma.ver` |
| `Backend/database/seeders/PermissionSeeder.php` | Registrar permisos |
| `Backend/database/seeders/RoleSeeder.php` | Asignar a `admin` y `super_admin` |
| `Backend/routes/api.php` | `require` rutas auditoría |
| `Backend/app/Console/Kernel.php` | Schedule `auditoria:purge` mensual |

### Frontend — nuevo

| Archivo | Responsabilidad |
|---------|-----------------|
| `Frontend/src/app/services/auditoria.service.ts` | HTTP client |
| `Frontend/src/app/views/admin/auditoria/auditoria.component.ts` | Listado tenant |
| `Frontend/src/app/views/admin/auditoria/auditoria.component.html` | Tabla + filtros |
| `Frontend/src/app/views/super-admin/auditoria/auditoria-platform.component.ts` | Listado cross-tenant |
| `Frontend/src/app/views/super-admin/auditoria/auditoria-platform.component.html` | + filtro empresa |

### Frontend — modificar

| Archivo | Cambio |
|---------|--------|
| `Frontend/src/app/layout/sidebar/sidebar.component.html` | Link `/auditoria` tras Reportes automáticos |
| `Frontend/src/app/layout/sidebar/sidebar-admin/sidebar-admin.component.html` | Link `/admin/auditoria` en Configuración |
| `Frontend/src/app/views/admin/admin.routing.module.ts` | Ruta lazy `/auditoria` |
| `Frontend/src/app/app.routing.module.ts` o módulo super-admin | Ruta `/admin/auditoria` |

---

## PR sugeridos (4)

| PR | Contenido |
|----|-----------|
| **PR-1** | Paquete + migración + trait + modelos fase 1 + tests unit presentation |
| **PR-2** | API tenant + permisos + tests feature |
| **PR-3** | API super-admin + frontend ambas vistas + menús |
| **PR-4** | Comando purge + schedule + doc breve |

---

### Task 1: Instalar Laravel Auditing

**Files:**
- Modify: `Backend/composer.json`

- [ ] **Step 1: Instalar paquete**

```bash
cd Backend && composer require owen-it/laravel-auditing:^14.0
```

- [ ] **Step 2: Publicar config y migración base**

```bash
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider"
```

- [ ] **Step 3: Verificar instalación**

Run: `php artisan about | grep -i audit`  
Expected: sin errores de autoload

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/auditing.php database/migrations/*create_audits*
git commit -m "feat(auditoria): instalar laravel-auditing v14"
```

---

### Task 2: Extender tabla audits (empresa + módulo)

**Files:**
- Create: `Backend/database/migrations/2026_07_03_100000_add_empresa_module_to_audits_table.php`

- [ ] **Step 1: Crear migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('id_empresa')->nullable()->after('user_id');
            $table->string('module', 50)->nullable()->after('id_empresa');
            $table->index(['id_empresa', 'module', 'created_at'], 'audits_empresa_module_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex('audits_empresa_module_created_idx');
            $table->dropColumn(['id_empresa', 'module']);
        });
    }
};
```

- [ ] **Step 2: Ejecutar migración**

Run: `php artisan migrate`  
Expected: `Migrating: 2026_07_03_100000_add_empresa_module_to_audits_table`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_03_100000_add_empresa_module_to_audits_table.php
git commit -m "feat(auditoria): agregar id_empresa y module a audits"
```

---

### Task 3: JWT user resolver + config

**Files:**
- Create: `Backend/app/Resolvers/JwtUserResolver.php`
- Modify: `Backend/config/auditing.php`

- [ ] **Step 1: Crear resolver**

```php
<?php

namespace App\Resolvers;

use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\UserResolver;

class JwtUserResolver implements UserResolver
{
    public static function resolve()
    {
        return Auth::guard('api')->user() ?? Auth::user();
    }
}
```

- [ ] **Step 2: Configurar auditing.php**

```php
'user' => [
    'morph_prefix' => 'user',
    'guards' => ['api'],
    'resolver' => App\Resolvers\JwtUserResolver::class,
],
'purge_months' => (int) env('AUDIT_PURGE_MONTHS', 6),
```

- [ ] **Step 3: Commit**

```bash
git add app/Resolvers/JwtUserResolver.php config/auditing.php
git commit -m "feat(auditoria): resolver JWT para usuario auditor"
```

---

### Task 4: Trait AuditableForEmpresa + modelo Audit

**Files:**
- Create: `Backend/app/Models/Concerns/AuditableForEmpresa.php`
- Create: `Backend/app/Models/Audit/Audit.php`

- [ ] **Step 1: Trait con transformAudit**

```php
<?php

namespace App\Models\Concerns;

use OwenIt\Auditing\Auditable as AuditableTrait;

trait AuditableForEmpresa
{
    use AuditableTrait;

    abstract protected static function auditModule(): string;

    public function transformAudit(array $data): array
    {
        $data['id_empresa'] = $this->id_empresa ?? auth()->user()?->id_empresa;
        $data['module'] = static::auditModule();
        return $data;
    }

    public function getAuditExclude(): array
    {
        return array_merge($this->auditExclude ?? [], ['updated_at', 'created_at']);
    }
}
```

- [ ] **Step 2: Modelo Audit con scope empresa**

```php
<?php

namespace App\Models\Audit;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Models\Audit as BaseAudit;

class Audit extends BaseAudit
{
    protected static function booted(): void
    {
        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 3: Registrar modelo en config/auditing.php**

```php
'implementation' => App\Models\Audit\Audit::class,
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/Concerns/AuditableForEmpresa.php app/Models/Audit/Audit.php config/auditing.php
git commit -m "feat(auditoria): trait multi-tenant y modelo Audit"
```

---

### Task 5: AuditPresentationService + test

**Files:**
- Create: `Backend/app/Services/Audit/AuditPresentationService.php`
- Create: `Backend/tests/Unit/Services/Audit/AuditPresentationServiceTest.php`

- [ ] **Step 1: Test**

```php
<?php

namespace Tests\Unit\Services\Audit;

use App\Services\Audit\AuditPresentationService;
use PHPUnit\Framework\TestCase;

class AuditPresentationServiceTest extends TestCase
{
    public function test_describe_created_venta(): void
    {
        $svc = new AuditPresentationService();
        $text = $svc->describe('created', 'App\\Models\\Ventas\\Venta', ['correlativo' => 'FAC-001'], 'David');
        $this->assertSame('David creó Venta #FAC-001', $text);
    }
}
```

- [ ] **Step 2: Run test (expect fail)**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Audit/AuditPresentationServiceTest.php -v`

- [ ] **Step 3: Implementar servicio**

```php
<?php

namespace App\Services\Audit;

class AuditPresentationService
{
    private const EVENTS = [
        'created' => 'creó',
        'updated' => 'actualizó',
        'deleted' => 'eliminó',
        'restored' => 'restauró',
    ];

    private const TYPE_LABELS = [
        'App\\Models\\Ventas\\Venta' => 'Venta',
        'App\\Models\\Compras\\Compra' => 'Compra',
        'App\\Models\\CotizacionVenta' => 'Cotización',
        'App\\Models\\Ventas\\Orden_Produccion\\OrdenProduccion' => 'Orden de producción',
        'App\\Models\\OrdenCompra' => 'Orden de compra',
        'App\\Models\\Compras\\Gastos\\Gasto' => 'Gasto',
        'App\\Models\\Inventario\\Entradas\\Entrada' => 'Entrada de inventario',
        'App\\Models\\Inventario\\Salidas\\Salida' => 'Salida de inventario',
        'App\\Models\\Inventario\\Ajuste' => 'Ajuste de inventario',
        'App\\Models\\Inventario\\Traslados\\Traslado' => 'Traslado',
        'App\\Models\\Inventario\\Producto' => 'Producto',
    ];

    public function describe(string $event, string $type, array $newValues, ?string $userName): string
    {
        $action = self::EVENTS[$event] ?? $event;
        $label = self::TYPE_LABELS[$type] ?? class_basename($type);
        $ref = $newValues['correlativo'] ?? $newValues['codigo'] ?? $newValues['nombre'] ?? $newValues['id'] ?? '?';
        $who = $userName ?: 'Sistema';
        return sprintf('%s %s %s #%s', $who, $action, $label, $ref);
    }
}
```

- [ ] **Step 4: Run test (expect pass)**

- [ ] **Step 5: Commit**

---

### Task 6: Aplicar trait a modelos fase 1

**Files:** (ejemplo Venta; repetir patrón en los demás)

- Modify: `Backend/app/Models/Ventas/Venta.php`

- [ ] **Step 1: Agregar trait a Venta**

```php
use App\Models\Concerns\AuditableForEmpresa;

class Venta extends Model
{
    use AuditableForEmpresa;

    protected static function auditModule(): string
    {
        return 'ventas';
    }
```

- [ ] **Step 2: Repetir en** Compra (`compras`), CotizacionVenta (`ventas`), OrdenProduccion (`ventas`), OrdenCompra (`compras`), Gasto (`compras`), Entrada/Salida/Ajuste/Traslado (`inventario`), Producto (`inventario`).

- [ ] **Step 3: Smoke manual**

Crear una venta de prueba en ambiente local; verificar fila en `audits` con `id_empresa` y `module=ventas`.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(auditoria): habilitar auditing en modelos fase 1"
```

---

### Task 7: Permisos

**Files:**
- Modify: `Backend/config/permissions.php`
- Modify: seeders de permisos/roles

- [ ] **Step 1: Agregar en permissions.php**

```php
'PERMISSION_AUDITORIA' => [
    'ver' => 'auditoria.ver',
    'plataforma_ver' => 'auditoria.plataforma.ver',
],
```

- [ ] **Step 2: Seeder — permiso a rol admin y super_admin**

- [ ] **Step 3: Run seeder**

Run: `php artisan db:seed --class=PermissionSeeder`

- [ ] **Step 4: Commit**

---

### Task 8: AuditQueryService + AuditResource

**Files:**
- Create: `Backend/app/Services/Audit/AuditQueryService.php`
- Create: `Backend/app/Http/Resources/AuditResource.php`

- [ ] **Step 1: Query service con filtros**

Parámetros: `module`, `user_id`, `fecha_inicio`, `fecha_fin`, `id_empresa` (solo super-admin), `page`, `per_page` (max 50).

```php
public function paginate(array $filters, bool $crossTenant = false)
{
    $query = Audit::query()
        ->with(['user:id,name', 'empresa:id,nombre'])
        ->when(!$crossTenant, fn ($q) => $q) // scope empresa ya aplica
        ->when($crossTenant, fn ($q) => $q->withoutGlobalScope('empresa'))
        ->when($filters['id_empresa'] ?? null, fn ($q, $id) => $q->where('id_empresa', $id))
        ->when($filters['module'] ?? null, fn ($q, $m) => $q->where('module', $m))
        ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
        ->when($filters['fecha_inicio'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
        ->when($filters['fecha_fin'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
        ->orderByDesc('created_at');

    return $query->paginate(min($filters['per_page'] ?? 25, 50));
}
```

- [ ] **Step 2: AuditResource incluye `descripcion` vía AuditPresentationService**

- [ ] **Step 3: Commit**

---

### Task 9: API Admin — AuditoriaController

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Admin/AuditoriaController.php`
- Create: `Backend/routes/modulos/admin/auditoria.php`
- Modify: `Backend/routes/api.php`

- [ ] **Step 1: Controller index**

```php
public function index(Request $request, AuditQueryService $service)
{
    return AuditResource::collection(
        $service->paginate($request->only(['module', 'user_id', 'fecha_inicio', 'fecha_fin', 'page', 'per_page']))
    );
}
```

- [ ] **Step 2: Ruta con middleware**

```php
Route::get('auditoria', [AuditoriaController::class, 'index'])
    ->middleware(['permission:auditoria.ver']);
```

- [ ] **Step 3: Feature test — admin solo ve su empresa**

- [ ] **Step 4: Commit**

---

### Task 10: API SuperAdmin — AuditoriaController

**Files:**
- Create: `Backend/app/Http/Controllers/Api/SuperAdmin/AuditoriaController.php`
- Create: `Backend/routes/modulos/super-admin/auditoria.php`

- [ ] **Step 1: Controller con crossTenant=true**

- [ ] **Step 2: Middleware stack**

```php
Route::get('auditoria', [AuditoriaController::class, 'index'])
    ->middleware(['SuperAdmin', 'permission:auditoria.plataforma.ver']);
```

- [ ] **Step 3: Test — usuario empresa 2 ve audits de otra empresa al filtrar**

- [ ] **Step 4: Commit**

---

### Task 11: Frontend — servicio + vista tenant

**Files:**
- Create: `Frontend/src/app/services/auditoria.service.ts`
- Create: `Frontend/src/app/views/admin/auditoria/auditoria.component.ts`
- Create: `Frontend/src/app/views/admin/auditoria/auditoria.component.html`
- Modify: `Frontend/src/app/views/admin/admin.routing.module.ts`
- Modify: `Frontend/src/app/layout/sidebar/sidebar.component.html`

- [ ] **Step 1: Servicio GET `/auditoria` con query params**

- [ ] **Step 2: Componente — tabla paginada**

Columnas: Fecha, Usuario, Descripción, Módulo, Acción.  
Filtros: select módulo (ventas, compras, inventario, ajustes), select usuario (lista `/usuarios`), date range.

- [ ] **Step 3: Ruta lazy + menú**

Después de línea Reportes automáticos en `sidebar.component.html`:

```html
@if (apiService.hasPermission('auditoria.ver')) {
  <li routerLinkActive="active" [routerLinkActiveOptions]="{exact: true}">
    <a [routerLink]="['/auditoria']" ...>Auditoría</a>
  </li>
}
```

- [ ] **Step 4: Verificar en browser — listado carga**

- [ ] **Step 5: Commit**

---

### Task 12: Frontend — vista super admin

**Files:**
- Create: `Frontend/src/app/views/super-admin/auditoria/auditoria-platform.component.ts/html`
- Modify: routing super-admin + `sidebar-admin.component.html`

- [ ] **Step 1: Mismo layout + filtro empresa** (reutilizar endpoint `/empresas/list` existente)

- [ ] **Step 2: Guard — solo `auditoria.plataforma.ver` o rol super_admin**

- [ ] **Step 3: Menú en sidebar-admin bajo Configuración**

- [ ] **Step 4: Commit**

---

### Task 13: Comando purge + schedule

**Files:**
- Create: `Backend/app/Console/Commands/PurgeAuditsCommand.php`
- Modify: `Backend/app/Console/Kernel.php`

- [ ] **Step 1: Comando**

```php
protected $signature = 'auditoria:purge {--months=}';

public function handle(): int
{
    $months = (int) ($this->option('months') ?? config('auditing.purge_months', 6));
    $cutoff = now()->subMonths($months);
    $deleted = Audit::withoutGlobalScopes()->where('created_at', '<', $cutoff)->limit(5000)->delete();
    $this->info("Eliminados {$deleted} registros anteriores a {$cutoff->toDateString()}");
    return self::SUCCESS;
}
```

- [ ] **Step 2: Schedule mensual**

```php
$schedule->command('auditoria:purge')->monthlyOn(1, '04:00');
```

- [ ] **Step 3: Test manual**

Run: `php artisan auditoria:purge --months=6`

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(auditoria): comando purge y schedule mensual"
```

---

## Verificación final

- [ ] Admin empresa A no ve audits de empresa B (403 o lista vacía).
- [ ] Super admin empresa 2 filtra por empresa correctamente.
- [ ] Crear venta → aparece en UI en <5s.
- [ ] Filtros módulo/fecha/usuario funcionan en conjunto.
- [ ] Purge elimina registros viejos y conserva recientes.
- [ ] Menú visible solo con permiso `auditoria.ver`.

## Notas de implementación

- **Kardex:** no auditar; movimientos de stock ya están en `kardexs`.
- **Campos excluidos en Producto:** `stock`, timestamps si se actualizan por triggers masivos.
- **Bulk imports:** si un flujo no dispara eventos Eloquent, agregar `Audit::create()` manual en el Service (fase 2).
- **Performance:** si la tabla supera ~5M filas, evaluar partición por `created_at` o archivo frío.
