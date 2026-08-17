# Restaurante POS — comensales, nombres, presentaciones Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** En el POS de mesa: editar comensales, nombrar pagadores al dividir, y vender presentaciones como en facturación.

**Architecture:** Reutilizar `PUT /sesiones-mesa/{id}`, `solicitarCuenta`/`dividir` y el catálogo `pos-menu`. Helpers puros (`NombresPagadores`, `PresentacionPos`, etiquetas TS) concentran la lógica. Una columna `nombre_pagador` en pre-cuenta y `id_presentacion` en la línea de orden. `genera_comanda` no se mueve del producto.

**Tech Stack:** Laravel (PHPUnit unit + Feature con cleanup HT17-), Angular en `RestauranteModule` (`standalone: false`), Jasmine en `cuenta-mesa/pos/*.spec.ts`.

**Spec:** `Docs/superpowers/specs/2026-08-17-restaurante-pos-comensales-nombres-presentaciones-design.md`

## Global Constraints

- Comensales: stepper `+`/`−` en encabezado POS; rango 1–99; no limitar a `mesa.capacidad`; subir y bajar; persistir con el PUT existente; solo sesión `abierta` o `pre_cuenta`.
- Nombres al dividir: opcionales; vacío → `Persona {n}` (1-based); máximo 80 caracteres; viven en `pre_cuentas_restaurante.nombre_pagador`; `PC-{mesa}-{n}` no cambia.
- Presentaciones: aplanar como facturación (ficha base + una por presentación) solo si `Empresa::isModuloPresentaciones()`.
- `genera_comanda` y destino: solo en el producto; las presentaciones heredan.
- Precio de línea: `producto.precio` o `presentacion.precio_venta`. Stock: `cantidad × factor_conversion` en unidades base.
- No servicio Angular nuevo. No switch de comanda en el formulario de presentación. No nombres en el mapa. No offline.
- No tocar `Frontend/src/environments/environment.ts`.
- Antes de explorar código: `graphify query` / `graphify path`. Tras editar PHP/TS: `graphify update .` al cierre de la última task backend+front.

## File map

| File | Role |
|------|------|
| `Backend/app/Support/Restaurante/NombresPagadores.php` | Normaliza `nombres[]` → etiquetas de pre-cuenta |
| `Backend/app/Support/Restaurante/PresentacionPos.php` | Nombre a mostrar + cantidad en unidades base |
| `Backend/tests/Unit/Support/Restaurante/NombresPagadoresTest.php` | Unit nombres |
| `Backend/tests/Unit/Support/Restaurante/PresentacionPosTest.php` | Unit nombre/stock |
| `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` | Bloquear update si sesión no operable |
| `Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php` | Feature HT17: comensales, nombres, ítem presentación |
| `Frontend/src/app/services/restaurante.service.ts` | `actualizarSesion`; `id_presentacion` en menú e ítems |
| `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.{ts,html,css}` | Stepper comensales; lista pre-cuentas; nombre de línea |
| `Backend/database/migrations/2026_08_17_140000_add_nombre_pagador_to_pre_cuentas_restaurante.php` | Columna nombres |
| `Backend/app/Models/Restaurante/PreCuenta.php` | fillable `nombre_pagador` |
| `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php` | Payload `nombres`; persistir; factura con presentación |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.ts` | `etiquetaPagador`, `ajustarNombresPagadores` |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/*` | Inputs de nombres |
| `Backend/resources/views/restaurante/pre-cuenta-ticket.blade.php` | `Para: {nombre}` |
| `Backend/app/Services/Restaurante/RestauranteTicketHtmlService.php` | Agrupar por presentación; nombre en comanda |
| `Backend/database/migrations/2026_08_17_140100_add_id_presentacion_to_orden_detalle_restaurante.php` | FK línea |
| `Backend/app/Models/Restaurante/OrdenDetalle.php` | `id_presentacion` + relación |
| `Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php` | Aplanar presentaciones |
| `Backend/tests/Feature/Restaurante/PosMenuTest.php` | Shape + expansión |
| `Backend/app/Http/Controllers/Api/Restaurante/OrdenDetalleController.php` | Store/update con presentación y stock base |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.ts` | `trackFichaPos`, `nombreLineaOrden` |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/*` | Track + badge |
| `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/*` | Emitir `id_presentacion` |
| `Backend/resources/views/restaurante/comanda-ticket.blade.php` | Nombre a mostrar |

---

### Task 1: Helpers PHP — nombres y presentación

**Files:**
- Create: `Backend/app/Support/Restaurante/NombresPagadores.php`
- Create: `Backend/app/Support/Restaurante/PresentacionPos.php`
- Create: `Backend/tests/Unit/Support/Restaurante/NombresPagadoresTest.php`
- Create: `Backend/tests/Unit/Support/Restaurante/PresentacionPosTest.php`

**Interfaces:**
- Produces: `NombresPagadores::normalizar(?array $nombres, int $n): array` — exactamente `$n` strings; trim; vacío → `Persona {i+1}`; recorta extras; rellena faltantes; `mb_substr` a 80.
- Produces: `PresentacionPos::nombreMostrar(?string $nombreComercial, ?string $nombreProducto): string` — si comercial vacío, producto (o `'Producto'`); si hay comercial: `{comercial} ({producto})`.
- Produces: `PresentacionPos::cantidadBase(float $cantidad, ?float $factor): float` — `factor` null/≤0 cuenta como 1; round 4 decimales.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\NombresPagadores;
use PHPUnit\Framework\TestCase;

final class NombresPagadoresTest extends TestCase
{
    public function test_vacio_usa_persona_n(): void
    {
        $this->assertSame(['Persona 1', 'Persona 2'], NombresPagadores::normalizar(['', '  '], 2));
    }

    public function test_rellena_y_recorta(): void
    {
        $this->assertSame(['Ana', 'Persona 2'], NombresPagadores::normalizar(['Ana'], 2));
        $this->assertSame(['Ana'], NombresPagadores::normalizar(['Ana', 'Luis'], 1));
    }

    public function test_trim_y_tope_80(): void
    {
        $largo = str_repeat('á', 81);
        $out = NombresPagadores::normalizar(['  Ana  ', $largo], 2);
        $this->assertSame('Ana', $out[0]);
        $this->assertSame(80, mb_strlen($out[1]));
    }
}
```

```php
<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\PresentacionPos;
use PHPUnit\Framework\TestCase;

final class PresentacionPosTest extends TestCase
{
    public function test_nombre_mostrar(): void
    {
        $this->assertSame('Cerveza', PresentacionPos::nombreMostrar(null, 'Cerveza'));
        $this->assertSame('330ml (Cerveza)', PresentacionPos::nombreMostrar('330ml', 'Cerveza'));
        $this->assertSame('Producto', PresentacionPos::nombreMostrar('', ''));
    }

    public function test_cantidad_base(): void
    {
        $this->assertSame(6.0, PresentacionPos::cantidadBase(2, 3));
        $this->assertSame(2.0, PresentacionPos::cantidadBase(2, null));
        $this->assertSame(2.0, PresentacionPos::cantidadBase(2, 0));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Support/Restaurante/NombresPagadoresTest.php tests/Unit/Support/Restaurante/PresentacionPosTest.php -v`

Expected: FAIL — classes not found.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Support\Restaurante;

final class NombresPagadores
{
    public static function normalizar(?array $nombres, int $n): array
    {
        $n = max(0, $n);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = isset($nombres[$i]) ? trim((string) $nombres[$i]) : '';
            if (mb_strlen($raw) > 80) {
                $raw = mb_substr($raw, 0, 80);
            }
            $out[] = $raw === '' ? ('Persona '.($i + 1)) : $raw;
        }

        return $out;
    }
}
```

```php
<?php

namespace App\Support\Restaurante;

final class PresentacionPos
{
    public static function nombreMostrar(?string $nombreComercial, ?string $nombreProducto): string
    {
        $prod = trim((string) $nombreProducto);
        $com = trim((string) $nombreComercial);
        if ($com === '') {
            return $prod === '' ? 'Producto' : $prod;
        }

        return $prod === '' ? $com : $com.' ('.$prod.')';
    }

    public static function cantidadBase(float $cantidad, ?float $factor): float
    {
        $f = ($factor !== null && $factor > 0) ? $factor : 1.0;

        return round($cantidad * $f, 4);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Support/Restaurante/NombresPagadoresTest.php tests/Unit/Support/Restaurante/PresentacionPosTest.php -v`

Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Support/Restaurante/NombresPagadores.php Backend/app/Support/Restaurante/PresentacionPos.php Backend/tests/Unit/Support/Restaurante/NombresPagadoresTest.php Backend/tests/Unit/Support/Restaurante/PresentacionPosTest.php
git commit -m "$(cat <<'EOF'
feat(restaurante): helpers de nombres de pagador y presentación POS

EOF
)"
```

---

### Task 2: Comensales — bloquear update si la sesión no está operable

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` (método `update`, ~L118)
- Create: `Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php`

**Interfaces:**
- Consumes: `PUT` existente `num_comensales` 1–99.
- Produces: 422 con `error` si `estado` no es `abierta` ni `pre_cuenta`; 200 + sesión fresca si sí.

- [ ] **Step 1: Write the failing feature tests**

Crear `Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php`:

```php
<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ComensalesNombresPresentacionesTest extends TestCase
{
    private User $userA;

    private int $empresaA;

    /** @var list<int> */
    private array $presentacionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('restaurante_mesas')) {
            $this->markTestSkipped('Tablas restaurante no disponibles.');
        }
        $this->userA = User::whereNotNull('id_empresa')->orderBy('id')->first();
        if (! $this->userA) {
            $this->markTestSkipped('No hay usuario con id_empresa.');
        }
        $this->empresaA = (int) $this->userA->id_empresa;
        Auth::login($this->userA);
    }

    protected function tearDown(): void
    {
        $this->cleanupHt17();
        parent::tearDown();
    }

    public function test_update_comensales_en_abierta_persiste(): void
    {
        $mesa = $this->crearMesaLibre('HT17-COM');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $req = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id, 'PUT', [
            'num_comensales' => 5,
        ]);
        $req->setUserResolver(fn () => $this->userA);
        $resp = app(SesionMesaController::class)->update($req, $sesion->id);
        $this->assertSame(200, $resp->getStatusCode(), $resp->getContent());
        $this->assertSame(5, (int) $sesion->fresh()->num_comensales);
    }

    public function test_update_comensales_en_cerrada_422(): void
    {
        $mesa = $this->crearMesaLibre('HT17-CLS');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $sesion->update(['estado' => 'cerrada', 'closed_at' => now()]);
        $req = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id, 'PUT', [
            'num_comensales' => 8,
        ]);
        $req->setUserResolver(fn () => $this->userA);
        $resp = app(SesionMesaController::class)->update($req, $sesion->id);
        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame(2, (int) $sesion->fresh()->num_comensales);
    }

    private function crearMesaLibre(string $prefix): Mesa
    {
        return Mesa::create([
            'id_empresa' => $this->empresaA,
            'id_sucursal' => $this->userA->id_sucursal,
            'numero' => $prefix.'-'.substr(bin2hex(random_bytes(3)), 0, 6),
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
            'orden' => 0,
        ]);
    }

    private function abrirSesion(Mesa $mesa, User $user): SesionMesa
    {
        Auth::login($user);
        $req = Request::create('/api/restaurante/sesiones-mesa', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $req->setUserResolver(fn () => $user);
        $resp = app(SesionMesaController::class)->store($req);
        $this->assertSame(201, $resp->getStatusCode(), $resp->getContent());

        return SesionMesa::findOrFail(json_decode($resp->getContent(), true)['id']);
    }

    private function productoEmpresa(int $empresaId): Producto
    {
        $p = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->where('enable', 1)
            ->orderBy('id')
            ->first();
        if (! $p) {
            $this->markTestSkipped('Sin producto enable para empresa.');
        }

        return $p;
    }

    private function cleanupHt17(): void
    {
        try {
            if ($this->presentacionIds !== []) {
                ProductoPresentacion::whereIn('id', $this->presentacionIds)->delete();
            }
            $mesas = Mesa::where('numero', 'like', 'HT17-%')->get();
            foreach ($mesas as $mesa) {
                $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
                if ($sesionIds->isNotEmpty()) {
                    $pcIds = PreCuenta::whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($pcIds->isNotEmpty()) {
                        PreCuentaOrdenDetalle::whereIn('pre_cuenta_id', $pcIds)->delete();
                        PreCuenta::whereIn('id', $pcIds)->delete();
                    }
                    $comandaIds = Comanda::whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($comandaIds->isNotEmpty()) {
                        ComandaDetalle::whereIn('comanda_id', $comandaIds)->delete();
                        Comanda::whereIn('id', $comandaIds)->delete();
                    }
                    OrdenDetalle::withTrashed()->whereIn('sesion_id', $sesionIds)->forceDelete();
                    SesionMesa::whereIn('id', $sesionIds)->delete();
                }
                $mesa->delete();
            }
        } catch (\Throwable) {
        }
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php --filter test_update_comensales_en_cerrada_422 -v`

Expected: FAIL (hoy el update de sesión cerrada devuelve 200) o skip si no hay tablas/usuario.

- [ ] **Step 3: Guard in `update`**

En `SesionMesaController::update`, después de `findOrFail`:

```php
if (! in_array($sesion->estado, ['abierta', 'pre_cuenta'], true)) {
    return response()->json(['error' => 'La sesión no se puede editar en su estado actual'], 422);
}
```

Dejar la validación `num_comensales` => `nullable|integer|min:1|max:99` como está.

- [ ] **Step 4: Re-run tests**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php --filter test_update_comensales -v`

Expected: PASS (o skip documentado).

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php
git commit -m "$(cat <<'EOF'
fix(restaurante): no editar comensales de una sesión cerrada

EOF
)"
```

---

### Task 3: Comensales — stepper en el encabezado POS

**Files:**
- Modify: `Frontend/src/app/services/restaurante.service.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html` (~L25–26)
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.css`

**Interfaces:**
- Consumes: `PUT /sesiones-mesa/{id}` de Task 2.
- Produces: `RestauranteService.actualizarSesion(id, { num_comensales: number }): Observable<SesionMesa>`

- [ ] **Step 1: Service method**

Junto a `getSesion`:

```typescript
actualizarSesion(id: number, data: { num_comensales?: number; observaciones?: string }): Observable<SesionMesa> {
  return this.api.update(BASE + 'sesiones-mesa', id, data);
}
```

- [ ] **Step 2: Component state + handler**

En `CuentaMesaComponent`:

```typescript
guardandoComensales = false;

cambiarComensales(delta: number): void {
  if (!this.sesion || this.guardandoComensales || !this.puedeOperarOrden) {
    return;
  }
  const actual = Math.max(1, Number(this.sesion.num_comensales) || 1);
  const siguiente = Math.min(99, Math.max(1, actual + delta));
  if (siguiente === actual) {
    return;
  }
  this.guardandoComensales = true;
  this.sesion = { ...this.sesion, num_comensales: siguiente };
  this.cdr.markForCheck();
  this.restauranteService
    .actualizarSesion(this.sesionId, { num_comensales: siguiente })
    .pipe(takeUntilDestroyed(this.destroyRef))
    .subscribe({
      next: (sesion) => {
        this.sesion = { ...this.sesion, ...sesion };
        this.guardandoComensales = false;
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.sesion = { ...this.sesion, num_comensales: actual };
        this.guardandoComensales = false;
        this.alertService.error(err);
        this.cdr.markForCheck();
      }
    });
}
```

Optimistic UI: cambia el número ya; si falla, revierte a `actual`.

- [ ] **Step 3: Template**

Reemplazar `<span>{{ sesion.num_comensales }} comensales</span>` por:

```html
<span class="pos-comensales">
  <button type="button" class="btn btn-sm btn-outline-secondary" [disabled]="guardandoComensales || sesion.num_comensales <= 1 || !puedeOperarOrden"
    (click)="cambiarComensales(-1)" title="Quitar comensal">
    <i class="fa fa-minus"></i>
  </button>
  <span>{{ sesion.num_comensales }} comensales</span>
  <button type="button" class="btn btn-sm btn-outline-secondary" [disabled]="guardandoComensales || sesion.num_comensales >= 99 || !puedeOperarOrden"
    (click)="cambiarComensales(1)" title="Agregar comensal">
    <i class="fa fa-plus"></i>
  </button>
</span>
```

CSS:

```css
.pos-comensales {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.pos-comensales .btn {
  width: 32px;
  height: 32px;
  padding: 0;
}
```

- [ ] **Step 4: Manual check**

Abrir una mesa, `+`/`−` en el header, recargar: el número persiste. Mesa en pre-cuenta también. No hay test Angular de componente (el API ya está cubierto).

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/services/restaurante.service.ts Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.ts Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.css
git commit -m "$(cat <<'EOF'
feat(restaurante): cambiar comensales desde el POS de mesa abierta

EOF
)"
```

---

### Task 4: Nombres al dividir — persistir en pre-cuenta

**Files:**
- Create: `Backend/database/migrations/2026_08_17_140000_add_nombre_pagador_to_pre_cuentas_restaurante.php`
- Modify: `Backend/app/Models/Restaurante/PreCuenta.php`
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php` (`validarPayloadDividir`, `ejecutarDivisionDesdeItems`)
- Modify: `Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php`

**Interfaces:**
- Consumes: `NombresPagadores::normalizar`.
- Produces: cada `PreCuenta` de una división tiene `nombre_pagador`. Payload `dividir.nombres?: string[]`.

- [ ] **Step 1: Migration + fillable**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->string('nombre_pagador', 80)->nullable()->after('numero_pre_cuenta');
        });
    }

    public function down(): void
    {
        Schema::table('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->dropColumn('nombre_pagador');
        });
    }
};
```

Añadir `'nombre_pagador'` a `$fillable` de `PreCuenta`.

Run: `cd Backend && php artisan migrate`

- [ ] **Step 2: Failing test**

En el feature test, abrir mesa, agregar un ítem (mismo patrón que `Phase11SuiteCoverageTest::test_agregar_producto_y_solicitar_cuenta_crea_precuenta_pendiente`), luego:

```php
$req = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id.'/pre-cuenta', 'POST', [
    'dividir' => [
        'tipo' => 'equitativa',
        'num_pagadores' => 2,
        'nombres' => ['Ana', ''],
    ],
]);
$req->headers->set('Idempotency-Key', 'ht17-div-'.bin2hex(random_bytes(8)));
$req->setUserResolver(fn () => $this->userA);
$resp = app(PreCuentaController::class)->generar($req, $sesion->id);
$this->assertSame(201, $resp->getStatusCode(), $resp->getContent());
$pcs = PreCuenta::where('sesion_id', $sesion->id)->orderBy('id')->get();
$this->assertSame('Ana', $pcs[0]->nombre_pagador);
$this->assertSame('Persona 2', $pcs[1]->nombre_pagador);
```

- [ ] **Step 3: Run to see fail**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php --filter test_dividir_equitativa_persiste_nombres -v`

Expected: FAIL (columna null / validación no acepta `nombres`).

- [ ] **Step 4: Wire controller**

`use App\Support\Restaurante\NombresPagadores;`

En `validarPayloadDividir` agregar:

```php
'nombres' => 'nullable|array',
'nombres.*' => 'nullable|string|max:80',
```

Al inicio de `ejecutarDivisionDesdeItems`, después de `$n = $validated['num_pagadores'];`:

```php
$nombres = NombresPagadores::normalizar($validated['nombres'] ?? null, $n);
```

En **ambos** bucles que hacen `PreCuenta::create` (equitativa y por_items), añadir:

```php
'nombre_pagador' => $nombres[$i],
```

(`$i` es 0-based en ambos).

- [ ] **Step 5: Re-run**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php --filter test_dividir_equitativa_persiste_nombres -v`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add Backend/database/migrations/2026_08_17_140000_add_nombre_pagador_to_pre_cuentas_restaurante.php Backend/app/Models/Restaurante/PreCuenta.php Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php
git commit -m "$(cat <<'EOF'
feat(restaurante): guardar nombre del pagador al dividir la cuenta

EOF
)"
```

---

### Task 5: Nombres al dividir — UI del flujo de cuenta

**Files:**
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.spec.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.html`

**Interfaces:**
- Produces: `etiquetaPagador(nombres: string[], index1: number): string`
- Produces: `ajustarNombresPagadores(nombres: string[], n: number): string[]` — slice/pad con `''` (el backend aplica el fallback).
- Produces: payload `dividir.nombres` en `confirmar()`.

- [ ] **Step 1: Failing Jasmine tests**

Añadir al spec:

```typescript
import { etiquetaPagador, ajustarNombresPagadores } from './pos-division';

describe('nombres pagador', () => {
  it('usa Persona N si el input está vacío', () => {
    expect(etiquetaPagador(['', 'Ana'], 1)).toBe('Persona 1');
    expect(etiquetaPagador(['', 'Ana'], 2)).toBe('Ana');
  });

  it('ajusta el array al cambiar N', () => {
    expect(ajustarNombresPagadores(['Ana', 'Luis'], 1)).toEqual(['Ana']);
    expect(ajustarNombresPagadores(['Ana'], 3)).toEqual(['Ana', '', '']);
  });
});
```

- [ ] **Step 2: Run to fail**

Run: `cd Frontend && npx ng test --include='**/cuenta-mesa/pos/*.spec.ts' --browsers=ChromeHeadless --watch=false`

Expected: FAIL — exports missing.

- [ ] **Step 3: Implement helpers**

Al final de `pos-division.ts`:

```typescript
export function etiquetaPagador(nombres: string[], index1: number): string {
  const raw = String(nombres[index1 - 1] ?? '').trim();
  return raw || `Persona ${index1}`;
}

export function ajustarNombresPagadores(nombres: string[], n: number): string[] {
  const next = nombres.slice(0, n);
  while (next.length < n) {
    next.push('');
  }
  return next;
}
```

- [ ] **Step 4: Re-run tests — PASS**

- [ ] **Step 5: Wire `PosFlujoCuentaComponent`**

- Importar `etiquetaPagador`, `ajustarNombresPagadores`.
- Campo `nombresPagadores: string[] = ['', ''];`
- En `ngOnChanges` cuando `visible`: `this.nombresPagadores = ajustarNombresPagadores([], this.numPagadores);`
- En `onNumPagadoresChange`, después de asignar `numPagadores`: `this.nombresPagadores = ajustarNombresPagadores(this.nombresPagadores, this.numPagadores);`
- Getter: `etiquetaPersona(p: number): string { return etiquetaPagador(this.nombresPagadores, p); }`
- En `confirmar`, dentro de `dividir`: `dividir['nombres'] = this.nombresPagadores.map((s) => String(s || '').trim());`

HTML, debajo del stepper de personas (visible en ambos tipos de división):

```html
<div class="mb-3">
  <label class="form-label">Nombres (opcional)</label>
  @for (p of personas; track p) {
    <input type="text" class="form-control mb-2" maxlength="80"
      [placeholder]="'Persona ' + p"
      [(ngModel)]="nombresPagadores[p - 1]"
      [name]="'nombrePagador' + p">
  }
</div>
```

Pestañas: `{{ etiquetaPersona(p) }}` en lugar de `Persona {{ p }}`.

Mini-prompt: `Unidades para {{ etiquetaPersona(personaActiva) }}`.

- [ ] **Step 6: Commit**

```bash
git add Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.ts Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-division.spec.ts Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.ts Frontend/src/app/views/restaurante/cuenta-mesa/pos-flujo-cuenta/pos-flujo-cuenta.component.html
git commit -m "$(cat <<'EOF'
feat(restaurante): nombres opcionales al dividir la cuenta en el POS

EOF
)"
```

---

### Task 6: Nombres en lista POS y ticket

**Files:**
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html` (bloque pre-cuentas ~L159)
- Modify: `Backend/resources/views/restaurante/pre-cuenta-ticket.blade.php`

**Interfaces:**
- Consumes: `PreCuenta.nombre_pagador`.

- [ ] **Step 1: Lista POS**

```html
<span>
  {{ pc.numero_pre_cuenta }}<ng-container *ngIf="pc.nombre_pagador"> · {{ pc.nombre_pagador }}</ng-container>
  — {{ pc.total | currency }}
  <span class="badge bg-success ms-1" *ngIf="pc.estado === 'facturada'">Facturada</span>
</span>
```

- [ ] **Step 2: Ticket**

Después de `<p><strong>PRE-CUENTA: {{ $preCuenta->numero_pre_cuenta }}</strong></p>`:

```blade
@if($preCuenta->nombre_pagador)
<p><strong>Para:</strong> {{ $preCuenta->nombre_pagador }}</p>
@endif
```

Invalidar cache de ticket no hace falta en pre-cuentas nuevas (se generan después del cambio).

- [ ] **Step 3: Commit**

```bash
git add Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html Backend/resources/views/restaurante/pre-cuenta-ticket.blade.php
git commit -m "$(cat <<'EOF'
feat(restaurante): mostrar nombre del pagador en pre-cuenta y ticket

EOF
)"
```

---

### Task 7: Presentaciones en el catálogo POS

**Files:**
- Create: `Backend/database/migrations/2026_08_17_140100_add_id_presentacion_to_orden_detalle_restaurante.php`
- Modify: `Backend/app/Models/Restaurante/OrdenDetalle.php`
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php`
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php` (`show` eager load)
- Modify: `Backend/tests/Feature/Restaurante/PosMenuTest.php`

**Interfaces:**
- Consumes: `PresentacionPos::nombreMostrar`, `Empresa::isModuloPresentaciones()`.
- Produces: `mapProductos(Collection $productos, bool $incluirPresentaciones = false): array` — shape estable con `id_presentacion`. Si `$incluirPresentaciones`, una ficha extra por cada item de la relación `presentaciones`.
- Produces: `OrdenDetalle::presentacion()` belongsTo `ProductoPresentacion`.

- [ ] **Step 1: Migration + model**

```php
Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
    $table->unsignedBigInteger('id_presentacion')->nullable()->after('producto_id');
    $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->nullOnDelete();
});
```

En `OrdenDetalle`: fillable `id_presentacion`; relación:

```php
public function presentacion()
{
    return $this->belongsTo(\App\Models\Inventario\ProductoPresentacion::class, 'id_presentacion');
}
```

`show` de sesión: `'ordenDetalle.producto'` → también `'ordenDetalle.presentacion'`.

Run: `cd Backend && php artisan migrate`

- [ ] **Step 2: Failing PosMenuTest**

Actualizar `test_map_productos_conserva_el_shape_y_no_filtra_por_genera_comanda`: keys esperadas

```php
['id', 'id_presentacion', 'nombre', 'precio', 'img', 'tipo', 'genera_comanda']
```

y `$this->assertNull($mapeados[0]['id_presentacion']);`

Añadir test:

```php
public function test_map_productos_aplana_presentaciones_cuando_se_pide(): void
{
    $plato = $this->producto(1, 'Cerveza', 1.5, true, 'Producto', 'productos/c.jpg');
    $pres = new \App\Models\Inventario\ProductoPresentacion([
        'nombre_comercial' => '330ml',
        'precio_venta' => 2.25,
        'factor_conversion' => 1,
    ]);
    $pres->id = 9;
    $plato->setRelation('presentaciones', new Collection([$pres]));

    $sin = PosMenuController::mapProductos(new Collection([$plato]), false);
    $this->assertCount(1, $sin);

    $con = PosMenuController::mapProductos(new Collection([$plato]), true);
    $this->assertCount(2, $con);
    $this->assertNull($con[0]['id_presentacion']);
    $this->assertSame('Cerveza', $con[0]['nombre']);
    $this->assertSame(9, $con[1]['id_presentacion']);
    $this->assertSame('330ml (Cerveza)', $con[1]['nombre']);
    $this->assertEquals(2.25, $con[1]['precio']);
    $this->assertTrue($con[1]['genera_comanda']);
}
```

- [ ] **Step 3: Run — FAIL** (keys viejas / no aplana)

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/PosMenuTest.php -v`

- [ ] **Step 4: Implement `mapProductos` + callers**

```php
use App\Models\Admin\Empresa;
use App\Support\Restaurante\PresentacionPos;

public static function mapProductos(Collection $productos, bool $incluirPresentaciones = false): array
{
    $out = [];
    foreach ($productos as $p) {
        $out[] = self::mapFichaProducto($p, null, null, null);
        if (! $incluirPresentaciones) {
            continue;
        }
        foreach ($p->presentaciones ?? [] as $pres) {
            $out[] = self::mapFichaProducto(
                $p,
                (int) $pres->id,
                PresentacionPos::nombreMostrar($pres->nombre_comercial ?? null, $p->nombre),
                $pres->precio_venta
            );
        }
    }

    return $out;
}

private static function mapFichaProducto(Producto $p, ?int $idPresentacion, ?string $nombre, $precio): array
{
    return [
        'id' => $p->id,
        'id_presentacion' => $idPresentacion,
        'nombre' => $nombre ?? $p->nombre,
        'precio' => $precio ?? $p->precio,
        'img' => $p->img,
        'tipo' => $p->tipo,
        'genera_comanda' => (bool) $p->genera_comanda,
    ];
}
```

En `queryProductos`, si el caller va a aplanar, eager load `presentaciones`. Añadir helper de instancia:

```php
private function incluirPresentaciones(): bool
{
    $idEmpresa = $this->idEmpresa();
    if (! $idEmpresa) {
        return false;
    }
    $empresa = Empresa::find($idEmpresa);

    return $empresa ? $empresa->isModuloPresentaciones() : false;
}
```

En cada acción que lista productos (`contenidoCategoria` rama productos, `productosSubcategoria`, `buscar`):

```php
$incluir = $this->incluirPresentaciones();
$query = self::queryProductosDeCategoria($idEmpresa, $categoria->id); // o DeSubcategoria / queryProductos+where
if ($incluir) {
    $query->with('presentaciones');
}
$productos = $query->get(); // buscar: ->limit(self::BUSCAR_LIMIT)->get()

return response()->json(['modo' => 'productos', 'items' => self::mapProductos($productos, $incluir)]);
// buscar y subcategoría: return response()->json(self::mapProductos($productos, $incluir));
```

- [ ] **Step 5: Re-run PosMenuTest — PASS**

- [ ] **Step 6: Commit**

```bash
git add Backend/database/migrations/2026_08_17_140100_add_id_presentacion_to_orden_detalle_restaurante.php Backend/app/Models/Restaurante/OrdenDetalle.php Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php Backend/app/Http/Controllers/Api/Restaurante/SesionMesaController.php Backend/tests/Feature/Restaurante/PosMenuTest.php
git commit -m "$(cat <<'EOF'
feat(restaurante): aplanar presentaciones en el catálogo POS

EOF
)"
```

---

### Task 8: Agregar ítem con presentación (precio, stock, fusión)

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/OrdenDetalleController.php`
- Modify: `Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php`

**Interfaces:**
- Consumes: `PresentacionPos::cantidadBase`; `ProductoPresentacion` del mismo `producto_id`.
- Produces: `POST items` acepta `id_presentacion` nullable; `precio_unitario` de la presentación; stock en unidades base; no fusiona líneas de distinta presentación.

- [ ] **Step 1: Failing test**

Usar `productoEmpresa`. Crear presentación temporal:

```php
$pres = \App\Models\Inventario\ProductoPresentacion::create([
    'id_producto' => $producto->id,
    'id_unidad_medida' => $producto->medida ?: 1,
    'nombre_comercial' => 'HT17-Pack',
    'factor_conversion' => 2,
    'precio_venta' => 9.5,
]);
```

Guardar `$this->presentacionIds[] = $pres->id` y borrarlas en `cleanupHt17`.

POST item con `id_presentacion`, cantidad 1. Assert `id_presentacion`, `precio_unitario == 9.5`.

Segundo POST igual (sin enviar comanda): misma línea, `cantidad == 2`.

POST con `id_presentacion` de otro producto (crear pres en otro producto de la misma empresa o usar id 0): 422.

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement store**

Validación extra:

```php
'id_presentacion' => 'nullable|integer|exists:producto_presentaciones,id',
```

Tras cargar `$producto`:

```php
$idPres = $validated['id_presentacion'] ?? null;
$presentacion = null;
if ($idPres) {
    $presentacion = \App\Models\Inventario\ProductoPresentacion::where('id', $idPres)
        ->where('id_producto', $producto->id)
        ->first();
    if (! $presentacion) {
        return response()->json(['error' => 'La presentación no pertenece a este producto'], 422);
    }
}
$precioLista = round((float) ($presentacion->precio_venta ?? $producto->precio ?? 0), 2);
$factor = $presentacion ? (float) $presentacion->factor_conversion : 1.0;
```

Fusión: añadir

```php
->where(function ($q) use ($idPres) {
    if ($idPres) {
        $q->where('id_presentacion', $idPres);
    } else {
        $q->whereNull('id_presentacion');
    }
})
```

Stock: pasar `PresentacionPos::cantidadBase($cantidad, $factor)` a `errorStockSiAplica` (tanto fusión como alta).

`OrdenDetalle::create` incluye `'id_presentacion' => $idPres`.

`fresh()->load(['producto', 'presentacion'])`.

En `update`, si el ítem tiene presentación, validar stock con `cantidadBase` usando `$item->presentacion?->factor_conversion`.

Pasar `$idPres`, `$precioLista`, `$factor` al closure de la transacción (use list).

- [ ] **Step 4: Re-run feature test — PASS**

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Restaurante/OrdenDetalleController.php Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php
git commit -m "$(cat <<'EOF'
feat(restaurante): agregar presentaciones a la orden con precio y stock base

EOF
)"
```

---

### Task 9: POS front — fichas, sheet, nombre en la orden

**Files:**
- Modify: `Frontend/src/app/services/restaurante.service.ts` (`PosMenuProducto`, `agregarItem`)
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.spec.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/pos-catalogo.component.html`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/pos-sheet-agregar.component.ts`
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.ts` (`onConfirmarAgregar`)
- Modify: `Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html` (celdas de nombre de ítem)

**Interfaces:**
- Produces: `trackFichaPos(p): string` = `` `${p.id}:${p.id_presentacion || 0}` ``
- Produces: `nombreLineaOrden(item): string` — `PresentacionPos` equivalente en TS: comercial + ` (producto)` o `producto.nombre`.
- Produces: sheet emite `{ producto_id, id_presentacion: number | null, cantidad, notas }`.

- [ ] **Step 1: Failing specs**

```typescript
import { trackFichaPos, nombreLineaOrden } from './pos-menu-nav';

it('distingue fichas del mismo producto', () => {
  expect(trackFichaPos({ id: 1, id_presentacion: null })).toBe('1:0');
  expect(trackFichaPos({ id: 1, id_presentacion: 9 })).toBe('1:9');
});

it('nombra la línea con la presentación', () => {
  expect(nombreLineaOrden({ producto: { nombre: 'Cerveza' } })).toBe('Cerveza');
  expect(nombreLineaOrden({
    producto: { nombre: 'Cerveza' },
    presentacion: { nombre_comercial: '330ml' }
  })).toBe('330ml (Cerveza)');
});
```

- [ ] **Step 2: Run — FAIL**

Run: `cd Frontend && npx ng test --include='**/cuenta-mesa/pos/*.spec.ts' --browsers=ChromeHeadless --watch=false`

- [ ] **Step 3: Implement + wire**

```typescript
export function trackFichaPos(p: { id: number; id_presentacion?: number | null }): string {
  return `${p.id}:${p.id_presentacion || 0}`;
}

export function nombreLineaOrden(item: {
  producto?: { nombre?: string } | null;
  presentacion?: { nombre_comercial?: string } | null;
}): string {
  const prod = String(item?.producto?.nombre || '').trim();
  const com = String(item?.presentacion?.nombre_comercial || '').trim();
  if (!com) {
    return prod;
  }
  return prod ? `${com} (${prod})` : com;
}
```

`PosMenuProducto`:

```typescript
id_presentacion?: number | null;
```

Catálogo: `@for (p of productos; track trackFichaPos(p))` y lo mismo en búsqueda. Exponer `trackFichaPos` en el component. Badge:

```html
<span class="badge bg-success text-dark" *ngIf="p.id_presentacion" style="font-size:0.65rem">Presentación</span>
```

Sheet: emit `id_presentacion: this.producto.id_presentacion ?? null`.

`onConfirmarAgregar` reenvía `id_presentacion` en el payload.

`agregarItem` acepta `id_presentacion?: number | null`.

En la tabla de orden (las dos celdas de nombre, consumo y si hay duplicado más abajo): `{{ nombreLineaOrden(item) }}` — método del component que delega al helper.

- [ ] **Step 4: Re-run Jasmine — PASS**

- [ ] **Step 5: Commit**

```bash
git add Frontend/src/app/services/restaurante.service.ts Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.ts Frontend/src/app/views/restaurante/cuenta-mesa/pos/pos-menu-nav.spec.ts Frontend/src/app/views/restaurante/cuenta-mesa/pos-catalogo/pos-catalogo.component.html Frontend/src/app/views/restaurante/cuenta-mesa/pos-sheet-agregar/pos-sheet-agregar.component.ts Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.ts Frontend/src/app/views/restaurante/cuenta-mesa/cuenta-mesa.component.html
git commit -m "$(cat <<'EOF'
feat(restaurante): mostrar y pedir presentaciones en el catálogo POS

EOF
)"
```

---

### Task 10: Comanda, ticket y factura con presentación

**Files:**
- Modify: `Backend/app/Services/Restaurante/RestauranteTicketHtmlService.php` (`lineasAgrupadasParaVista`, `renderComandaHtml` eager load)
- Modify: `Backend/resources/views/restaurante/comanda-ticket.blade.php`
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php` (`prepararFactura`)
- Modify: `Backend/app/Http/Controllers/Api/Restaurante/ComandaController.php` (eager `presentacion` en pendientes)
- Modify: `Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php` (producto con `genera_comanda=false` + presentación → enviar comanda no crea cocina/barra; restaurar el flag en cleanup)

**Interfaces:**
- Consumes: `PresentacionPos::nombreMostrar`.
- Produces: agrupación `producto_id|id_presentacion|precio|notas`; `prepararFactura.detalles[].id_presentacion` y `descripcion`; comanda imprime nombre a mostrar. `genera_comanda` sigue leyéndose del producto.

- [ ] **Step 1: Grouping + factura**

Clave:

```php
return $i->producto_id.'|'.(int) ($i->id_presentacion ?? 0).'|'.round((float) $i->precio_unitario, 2).'|'.$nk;
```

En el objeto agrupado incluir `id_presentacion` y `presentacion` del first.

`prepararFactura` map:

```php
'id_presentacion' => $i->id_presentacion ?? null,
'descripcion' => PresentacionPos::nombreMostrar(
    $i->presentacion->nombre_comercial ?? null,
    $i->producto->nombre ?? ''
),
```

Eager load `ordenDetalles.presentacion` / `ordenDetalle.presentacion` donde ya se carga `producto`.

- [ ] **Step 2: Comanda blade**

```blade
{{ \App\Support\Restaurante\PresentacionPos::nombreMostrar(
    $linea->presentacion->nombre_comercial ?? null,
    $prod->nombre ?? null
) }}
```

`renderComandaHtml`: with `detalles.ordenDetalle.presentacion`.

`ComandaController` pendientes: `->with(['producto', 'presentacion'])`.

- [ ] **Step 3: Test comanda hereda flag del producto**

```php
public function test_presentacion_no_genera_comanda_si_el_producto_no_lo_hace(): void
{
    $producto = $this->productoEmpresa($this->empresaA);
    $prev = $producto->genera_comanda;
    $producto->genera_comanda = false;
    $producto->save();
    try {
        $pres = ProductoPresentacion::create([
            'id_producto' => $producto->id,
            'id_unidad_medida' => $producto->medida ?: 1,
            'nombre_comercial' => 'HT17-NoCmd',
            'factor_conversion' => 1,
            'precio_venta' => 1,
        ]);
        $this->presentacionIds[] = $pres->id;
        $mesa = $this->crearMesaLibre('HT17-CMD');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $add = Request::create('/x', 'POST', [
            'producto_id' => $producto->id,
            'id_presentacion' => $pres->id,
            'cantidad' => 1,
        ]);
        $add->headers->set('Idempotency-Key', 'ht17-cmd-'.bin2hex(random_bytes(8)));
        $add->setUserResolver(fn () => $this->userA);
        $this->assertSame(201, app(OrdenDetalleController::class)->store($add, $sesion->id)->getStatusCode());
        $cmd = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id.'/comandas', 'POST', []);
        $cmd->headers->set('Idempotency-Key', 'ht17-send-'.bin2hex(random_bytes(8)));
        $cmd->setUserResolver(fn () => $this->userA);
        $resp = app(\App\Http\Controllers\Api\Restaurante\ComandaController::class)->store($cmd, $sesion->id);
        $body = json_decode($resp->getContent(), true);
        $this->assertSame(0, Comanda::where('sesion_id', $sesion->id)->whereIn('destino', ['cocina', 'barra', 'ambos'])->count(), json_encode($body));
    } finally {
        $producto->genera_comanda = $prev;
        $producto->save();
    }
}
```

- [ ] **Step 4: Run**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php tests/Feature/Restaurante/PosMenuTest.php tests/Unit/Support/Restaurante/NombresPagadoresTest.php tests/Unit/Support/Restaurante/PresentacionPosTest.php -v`

Expected: PASS (o skips de BD documentados).

Run: `cd Frontend && npx ng test --include='**/cuenta-mesa/pos/*.spec.ts' --browsers=ChromeHeadless --watch=false`

Expected: PASS.

- [ ] **Step 5: graphify + commit**

```bash
graphify update .
git add Backend/app/Services/Restaurante/RestauranteTicketHtmlService.php Backend/resources/views/restaurante/comanda-ticket.blade.php Backend/app/Http/Controllers/Api/Restaurante/PreCuentaController.php Backend/app/Http/Controllers/Api/Restaurante/ComandaController.php Backend/tests/Feature/Restaurante/ComensalesNombresPresentacionesTest.php
git commit -m "$(cat <<'EOF'
feat(restaurante): presentaciones en comanda, ticket y preparación de factura

EOF
)"
```

---

## Verificación manual (después de Task 10)

1. Mesa abierta: `+`/`−` comensales; recargar; el número sigue.
2. Solicitar cuenta → dividir → nombres `Ana` y vacío → pre-cuentas `Ana` y `Persona 2`; ticket `Para: Ana`.
3. Empresa con módulo presentaciones: catálogo muestra ficha extra; agregar pack; orden muestra `330ml (Cerveza)`; enviar comanda solo si el producto tiene `genera_comanda`.
4. Módulo apagado: catálogo igual que antes.
