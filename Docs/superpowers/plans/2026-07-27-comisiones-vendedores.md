# Comisiones vendedores (Fase 0 + Fase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Activar el feature `comisiones-vendedores`, persistir % por categoría/subcategoría, escribir un ledger inmutable de comisiones al facturar/devolver, y exponer liquidación + Excel + PDF por vendedor.

**Architecture:** Módulo lateral (patrón fidelización). Config y ledger propios; hooks post-commit en venta pagada y devolución/anulación. No calcular comisión dentro de la matemática de precios de `FacturacionService`. Reportes leen `comision_movimientos`.

**Tech Stack:** Laravel (PHPUnit), Angular, Maatwebsite Excel, DomPDF.

**Spec:** `Docs/superpowers/specs/2026-07-27-comisiones-bonos-gift-cards-design.md`

**Planes hermanos (implementar después):**
- `Docs/superpowers/plans/2026-07-27-gift-cards.md`
- `Docs/superpowers/plans/2026-07-27-bonos-vendedores.md`

## Global Constraints

- Slug exacto: `comisiones-vendedores`.
- Devoluciones: si el período del movimiento original está `pagado`, el ajuste va al siguiente período `abierto`; si no, al mismo período.
- %: categoría default; override opcional por subcategoría.
- Base de cálculo: configurable en `empresa_funcionalidades.configuracion`; default `subtotal_sin_iva`.
- Vendedor efectivo: `COALESCE(NULLIF(detalle.id_vendedor, 0), venta.id_vendedor)`.
- Excluir categoría Gift Cards del ledger de ventas (aunque gift-cards aún no esté activo, respetar `configuracion.id_categoria_gift_cards` / flag `excluir_categoria_gift_cards`).
- No usar `productos.comision` / UI legacy como fuente de verdad.
- No escribir en `planilla_detalles`.
- Fallo del motor de comisiones no debe tumbar la facturación (try/catch + log).
- Histórico consultable con flag apagado (rutas de lectura sin exigir activo, o middleware distinto).

## File map

| File | Responsibility |
|------|----------------|
| `Backend/database/seeders/FuncionalidadesSeeder.php` | Seed slug `comisiones-vendedores` |
| `Backend/app/Models/Admin/Empresa.php` | `tieneFuncionalidad(string $slug): bool` |
| `Backend/database/migrations/2026_07_27_*_create_comision_*_tables.php` | Tablas del módulo |
| `Backend/app/Models/Comisiones/*` | Eloquent models |
| `Backend/app/Services/Comisiones/ComisionPorcentajeResolver.php` | Resolver % categoría/subcategoría |
| `Backend/app/Services/Comisiones/ComisionBaseCalculator.php` | Monto base según config |
| `Backend/app/Services/Comisiones/ComisionPeriodoService.php` | Período abierto / siguiente abierto |
| `Backend/app/Services/Comisiones/ComisionService.php` | Registrar desde venta, redención, ajuste |
| `Backend/app/Observers/Comisiones/VentaComisionObserver.php` | Hook venta pagada (opcional vs llamada directa) |
| `Backend/app/Services/Ventas/FacturacionService.php` | Llamada post-éxito si flag on |
| `Backend/app/Http/Controllers/Api/Ventas/Devoluciones/DevolucionVentasController.php` | Ajuste comisión |
| `Backend/routes/modulos/comisiones.php` | Rutas API |
| `Backend/routes/api.php` | `require` del módulo |
| `Backend/app/Exports/Comisiones/ComisionesVendedorExport.php` | Excel multi-sheet |
| `Backend/resources/views/reportes/comisiones/comprobante.blade.php` | PDF |
| `Frontend/src/app/views/comisiones/**` | Admin % + liquidaciones + reportes |
| `Backend/tests/Unit/Services/Comisiones/*Test.php` | Tests unitarios del motor |

---

### Task 1: Helper `tieneFuncionalidad` + seed del slug

**Files:**
- Modify: `Backend/app/Models/Admin/Empresa.php` (cerca de `tieneFidelizacionHabilitada`)
- Modify: `Backend/database/seeders/FuncionalidadesSeeder.php`
- Create: `Backend/tests/Unit/Services/Comisiones/EmpresaTieneFuncionalidadTest.php` (o test del helper vía reflection/mock mínimo; preferir unit del query builder si el proyecto ya mockea Eloquent — si no, test puro del seeder array no aplica; usar test de integración ligero solo si hay patrón. Preferir: test unitario de un pequeño `FuncionalidadAccess` class).

**Interfaces:**
- Produces: `Empresa::tieneFuncionalidad(string $slug): bool`
- Produces: slug `comisiones-vendedores` en catálogo

- [ ] **Step 1: Extraer checker testeable + test fallido**

Crear `Backend/app/Services/Funcionalidades/FuncionalidadAccess.php`:

```php
<?php

namespace App\Services\Funcionalidades;

use App\Models\Admin\EmpresaFuncionalidad;

class FuncionalidadAccess
{
    public static function empresaTieneSlug(int $idEmpresa, string $slug): bool
    {
        return EmpresaFuncionalidad::query()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->whereHas('funcionalidad', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }
}
```

Test (PHPUnit + Mockery o SQLite si el proyecto lo usa en Feature). Si solo hay unit tests sin DB, documentar Feature test:

Create `Backend/tests/Unit/Services/Funcionalidades/FuncionalidadAccessLogicTest.php` — para v1, probar que el método existe y el seeder contiene el slug:

```php
<?php

namespace Tests\Unit\Services\Funcionalidades;

use PHPUnit\Framework\TestCase;

class FuncionalidadesSeederSlugTest extends TestCase
{
    public function test_seeder_incluye_comisiones_vendedores(): void
    {
        $path = dirname(__DIR__, 3) . '/../database/seeders/FuncionalidadesSeeder.php';
        $src = file_get_contents(realpath(dirname(__DIR__, 4) . '/database/seeders/FuncionalidadesSeeder.php')
            ?: base_path('database/seeders/FuncionalidadesSeeder.php'));
        // Evitar dependencia de base_path en unit puro:
        $file = __DIR__ . '/../../../../database/seeders/FuncionalidadesSeeder.php';
        $src = file_get_contents($file);
        $this->assertStringContainsString("'slug' => 'comisiones-vendedores'", $src);
    }
}
```

Ajustar path relativo hasta que `file_exists` sea true desde `tests/Unit`.

- [ ] **Step 2: Correr test — FAIL (slug ausente)**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Funcionalidades/FuncionalidadesSeederSlugTest.php -v`  
Expected: FAIL — string no encontrado.

- [ ] **Step 3: Agregar slug al seeder**

En `$funcionalidades` de `FuncionalidadesSeeder.php`, agregar (siguiente `orden` libre):

```php
[
    'nombre' => 'Comisiones de Vendedores',
    'slug' => 'comisiones-vendedores',
    'descripcion' => 'Comisiones por categoría/subcategoría de producto con ledger y liquidación',
    'orden' => 20
],
[
    'nombre' => 'Bonos de Vendedores',
    'slug' => 'bonos-vendedores',
    'descripcion' => 'Motor de bonos por reglas (independiente de comisiones)',
    'orden' => 21
],
[
    'nombre' => 'Gift Cards',
    'slug' => 'gift-cards',
    'descripcion' => 'Emisión y redención de gift cards con saldo parcial',
    'orden' => 22
],
```

(Fase 0 siembra los tres slugs aunque solo se implemente comisiones ahora.)

- [ ] **Step 4: Implementar `Empresa::tieneFuncionalidad`**

```php
public function tieneFuncionalidad(string $slug): bool
{
    return \App\Services\Funcionalidades\FuncionalidadAccess::empresaTieneSlug((int) $this->id, $slug);
}
```

- [ ] **Step 5: Correr test — PASS**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Funcionalidades/FuncionalidadesSeederSlugTest.php -v`

- [ ] **Step 6: Commit**

```bash
git add Backend/database/seeders/FuncionalidadesSeeder.php \
  Backend/app/Models/Admin/Empresa.php \
  Backend/app/Services/Funcionalidades/FuncionalidadAccess.php \
  Backend/tests/Unit/Services/Funcionalidades/FuncionalidadesSeederSlugTest.php
git commit -m "$(cat <<'EOF'
feat(comisiones): seed feature flags and tieneFuncionalidad helper

EOF
)"
```

---

### Task 2: Migraciones y models del ledger

**Files:**
- Create: `Backend/database/migrations/2026_07_27_160000_create_comision_categoria_config_table.php`
- Create: `Backend/database/migrations/2026_07_27_160001_create_comision_subcategoria_config_table.php`
- Create: `Backend/database/migrations/2026_07_27_160002_create_comision_periodos_table.php`
- Create: `Backend/database/migrations/2026_07_27_160003_create_comision_movimientos_table.php`
- Create: `Backend/database/migrations/2026_07_27_160004_create_comision_liquidaciones_table.php`
- Create: `Backend/app/Models/Comisiones/ComisionCategoriaConfig.php`
- Create: `Backend/app/Models/Comisiones/ComisionSubcategoriaConfig.php`
- Create: `Backend/app/Models/Comisiones/ComisionPeriodo.php`
- Create: `Backend/app/Models/Comisiones/ComisionMovimiento.php`
- Create: `Backend/app/Models/Comisiones/ComisionLiquidacion.php`

**Interfaces:**
- Produces: tablas y models listos para el servicio

- [ ] **Step 1: Escribir migración `comision_categoria_config`**

```php
Schema::create('comision_categoria_config', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->unsignedBigInteger('id_categoria');
    $table->decimal('porcentaje', 8, 4)->default(0);
    $table->timestamps();
    $table->unique(['id_empresa', 'id_categoria']);
});
```

- [ ] **Step 2: Migración `comision_subcategoria_config`**

```php
Schema::create('comision_subcategoria_config', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->unsignedBigInteger('id_subcategoria');
    $table->decimal('porcentaje', 8, 4)->default(0);
    $table->timestamps();
    $table->unique(['id_empresa', 'id_subcategoria']);
});
```

- [ ] **Step 3: Migración `comision_periodos`**

```php
Schema::create('comision_periodos', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->date('fecha_inicio');
    $table->date('fecha_fin');
    $table->string('estado', 20)->default('abierto'); // abierto|cerrado|pagado
    $table->timestamps();
    $table->unique(['id_empresa', 'fecha_inicio', 'fecha_fin']);
});
```

- [ ] **Step 4: Migración `comision_movimientos`**

```php
Schema::create('comision_movimientos', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->unsignedBigInteger('id_vendedor');
    $table->unsignedBigInteger('id_periodo')->nullable();
    $table->string('origen', 32); // venta|redencion_gift_card|ajuste_devolucion
    $table->unsignedBigInteger('id_venta')->nullable();
    $table->unsignedBigInteger('id_detalle_venta')->nullable();
    $table->unsignedBigInteger('id_gift_card_redencion')->nullable();
    $table->unsignedBigInteger('id_categoria')->nullable();
    $table->unsignedBigInteger('id_subcategoria')->nullable();
    $table->decimal('monto_base', 14, 4);
    $table->decimal('porcentaje_aplicado', 8, 4);
    $table->decimal('monto_comision', 14, 4);
    $table->unsignedBigInteger('id_movimiento_origen')->nullable();
    $table->timestamp('fecha_evento')->nullable();
    $table->timestamps();

    $table->unique(
        ['id_empresa', 'origen', 'id_detalle_venta'],
        'comision_mov_unique_detalle'
    );
    // Nota: para origen=ajuste_devolucion, id_detalle_venta puede repetir con sufijo null —
    // usar unique parcial o unique en (origen, id_movimiento_origen) para ajustes.
});
```

Ajustar índices: unique solo donde `origen IN ('venta','redencion_gift_card')` y `id_detalle_venta` / `id_gift_card_redencion` not null. Si MySQL no soporta unique parcial, idempotencia en servicio con `firstOrCreate`.

- [ ] **Step 5: Migración `comision_liquidaciones`**

```php
Schema::create('comision_liquidaciones', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->unsignedBigInteger('id_periodo');
    $table->unsignedBigInteger('id_vendedor');
    $table->decimal('total_comision', 14, 4)->default(0);
    $table->timestamp('pagado_at')->nullable();
    $table->timestamps();
    $table->unique(['id_empresa', 'id_periodo', 'id_vendedor']);
});
```

- [ ] **Step 6: Models Eloquent** con `$table`, `$fillable`, casts decimal, relaciones `belongsTo` básicas, y global scope `id_empresa` cuando `Auth::check()` (copiar patrón de `Venta` / `Categoria`).

- [ ] **Step 7: `php artisan migrate`** en entorno local y verificar tablas.

- [ ] **Step 8: Commit**

```bash
git add Backend/database/migrations/2026_07_27_16000*.php Backend/app/Models/Comisiones/
git commit -m "$(cat <<'EOF'
feat(comisiones): add commission config, period, and ledger tables

EOF
)"
```

---

### Task 3: Resolver de % y calculadora de base (puro)

**Files:**
- Create: `Backend/app/Services/Comisiones/ComisionPorcentajeResolver.php`
- Create: `Backend/app/Services/Comisiones/ComisionBaseCalculator.php`
- Create: `Backend/tests/Unit/Services/Comisiones/ComisionPorcentajeResolverTest.php`
- Create: `Backend/tests/Unit/Services/Comisiones/ComisionBaseCalculatorTest.php`

**Interfaces:**
- Produces: `ComisionPorcentajeResolver::resolver(int $idEmpresa, ?int $idCategoria, ?int $idSubcategoria): float`
- Produces: `ComisionBaseCalculator::calcular(object $detalle, string $baseCalculo): float`

- [ ] **Step 1: Test resolver — override subcategoría gana**

```php
<?php

namespace Tests\Unit\Services\Comisiones;

use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;

class ComisionPorcentajeResolverTest extends TestCase
{
    public function test_subcategoria_override_gana_sobre_categoria(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c) => 2.0,
            fn (int $e, int $s) => 1.5
        );
        $this->assertSame(1.5, $resolver->resolver(1, 10, 20));
    }

    public function test_usa_categoria_si_no_hay_override(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c) => 2.0,
            fn (int $e, int $s) => null
        );
        $this->assertSame(2.0, $resolver->resolver(1, 10, 20));
    }

    public function test_cero_si_sin_config(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn () => null,
            fn () => null
        );
        $this->assertSame(0.0, $resolver->resolver(1, 10, null));
    }
}
```

- [ ] **Step 2: Implementar resolver con callables inyectables + factory `fromDatabase()`**

```php
public function resolver(int $idEmpresa, ?int $idCategoria, ?int $idSubcategoria): float
{
    if ($idSubcategoria) {
        $override = ($this->findSub)($idEmpresa, $idSubcategoria);
        if ($override !== null) {
            return (float) $override;
        }
    }
    if ($idCategoria) {
        $pct = ($this->findCat)($idEmpresa, $idCategoria);
        if ($pct !== null) {
            return (float) $pct;
        }
    }
    return 0.0;
}
```

- [ ] **Step 3: Test base calculator**

```php
public function test_subtotal_sin_iva_usa_gravada_exenta_menos_noop(): void
{
    $detalle = (object) [
        'gravada' => 100.0,
        'exenta' => 0.0,
        'no_sujeta' => 0.0,
        'descuento' => 10.0,
        'total' => 113.0,
        'sub_total' => 100.0,
        'iva' => 13.0,
    ];
    // Convención v1 default: gravada+exenta+no_sujeta (ya net de descuento en líneas SV)
    // Si el detalle guarda gravada post-descuento, usar gravada+exenta+no_sujeta.
    $calc = new ComisionBaseCalculator();
    $this->assertEquals(100.0, $calc->calcular($detalle, 'subtotal_sin_iva'));
}

public function test_total_con_iva(): void
{
    $detalle = (object) ['total' => 113.0, 'gravada' => 100.0, 'exenta' => 0, 'no_sujeta' => 0];
    $calc = new ComisionBaseCalculator();
    $this->assertEquals(113.0, $calc->calcular($detalle, 'total_con_iva'));
}
```

Confirmar en una venta real de staging qué campo es “sin IVA post-descuento”; ajustar la fórmula en el test **antes** de implementar y dejar un comentario `ponytail:` si se simplifica a `gravada + exenta + no_sujeta`.

- [ ] **Step 4: Implementar calculator con `match` exhaustivo**

```php
public function calcular(object $detalle, string $baseCalculo): float
{
    return match ($baseCalculo) {
        'subtotal_sin_iva' => (float) ($detalle->gravada ?? 0) + (float) ($detalle->exenta ?? 0) + (float) ($detalle->no_sujeta ?? 0),
        'total_con_iva' => (float) ($detalle->total ?? 0),
        'bruto_sin_descuento' => (float) ($detalle->sub_total ?? 0),
        default => throw new \InvalidArgumentException("base_calculo desconocida: {$baseCalculo}"),
    };
}
```

- [ ] **Step 5: PHPUnit PASS**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Comisiones/ -v`

- [ ] **Step 6: Commit**

```bash
git add Backend/app/Services/Comisiones/ComisionPorcentajeResolver.php \
  Backend/app/Services/Comisiones/ComisionBaseCalculator.php \
  Backend/tests/Unit/Services/Comisiones/
git commit -m "$(cat <<'EOF'
feat(comisiones): add percentage resolver and base amount calculator

EOF
)"
```

---

### Task 4: Períodos + ComisionService (venta y ajuste)

**Files:**
- Create: `Backend/app/Services/Comisiones/ComisionPeriodoService.php`
- Create: `Backend/app/Services/Comisiones/ComisionService.php`
- Create: `Backend/tests/Unit/Services/Comisiones/ComisionPeriodoServiceTest.php`
- Create: `Backend/tests/Unit/Services/Comisiones/ComisionServiceAjustePeriodoTest.php`

**Interfaces:**
- Produces: `ComisionPeriodoService::periodoParaFecha(int $idEmpresa, Carbon $fecha): ComisionPeriodo`
- Produces: `ComisionPeriodoService::periodoParaAjuste(ComisionMovimiento $original): ComisionPeriodo`
- Produces: `ComisionService::registrarVentaPagada(Venta $venta): void`
- Produces: `ComisionService::registrarAjustePorDevolucion(...): void`
- Produces: `ComisionService::registrarDesdeRedencion(...): void` (stub usado en plan gift-cards; implementar firma ahora)

- [ ] **Step 1: Test regla B de períodos**

```php
public function test_ajuste_usa_mismo_periodo_si_no_pagado(): void
{
    $periodo = (object) ['id' => 1, 'estado' => 'abierto'];
    $svc = new ComisionPeriodoService(/* inject finders */);
    // assert periodoParaAjuste returns same id when estado !== pagado
}

public function test_ajuste_usa_siguiente_abierto_si_pagado(): void
{
    // original periodo pagado → next abierto
}
```

Implementar lógica pura con dependencias inyectadas (find período by id, find next abierto).

- [ ] **Step 2: Implementar `ComisionPeriodoService`**

- Período por defecto: mes calendario de la fecha del evento; `firstOrCreate` estado `abierto`.
- `periodoParaAjuste`: si `estado === 'pagado'`, buscar el abierto con `fecha_inicio` mínima `>` fin del pagado, o crear el mes actual/siguiente.

- [ ] **Step 3: Test `ComisionService` no crea movimiento si % = 0**

Usar stubs del resolver/calculator; assert no llamada a `save` / contador de creates = 0.

- [ ] **Step 4: Implementar `registrarVentaPagada`**

```text
if (!FuncionalidadAccess::empresaTieneSlug($venta->id_empresa, 'comisiones-vendedores')) return;
foreach detalles as detalle:
  load producto categoria/subcategoria
  if categoria es gift-cards (config): skip
  pct = resolver
  if pct == 0: skip
  base = calculator
  vendedor = detalle.id_vendedor ?: venta.id_vendedor
  firstOrCreate movimiento origen=venta, id_detalle_venta=...
```

- [ ] **Step 5: Implementar `registrarAjustePorDevolucion`** (monto negativo proporcional o 100% en anulación)

- [ ] **Step 6: Stub `registrarDesdeRedencion(GiftCardRedencion $r, Detalle $detalle): void`** — misma lógica que venta pero `origen=redencion_gift_card` y set `id_gift_card_redencion`. Si la clase GiftCard aún no existe, aceptar `int $idRedencion` + datos de categoría/vendedor/base.

```php
public function registrarDesdeRedencion(
    int $idEmpresa,
    int $idVendedor,
    int $idVenta,
    int $idDetalleVenta,
    int $idGiftCardRedencion,
    ?int $idCategoria,
    ?int $idSubcategoria,
    object $detalleLinea,
    \DateTimeInterface $fechaEvento
): ?ComisionMovimiento
```

- [ ] **Step 7: PHPUnit PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
feat(comisiones): period service and commission ledger writer

EOF
)"
```

---

### Task 5: Hooks en facturación y devolución/anulación

**Files:**
- Modify: `Backend/app/Services/Ventas/FacturacionService.php` (tras commit exitoso / bloque Pagada, patrón fidelización ~L513)
- Modify: `Backend/app/Http/Controllers/Api/Ventas/Devoluciones/DevolucionVentasController.php` (cerca de sync puntos L241/L434)
- Modify: punto de anulación de venta (buscar `Anulada` / `fecha_anulacion` en `VentasController`)

**Interfaces:**
- Consumes: `ComisionService`

- [ ] **Step 1: Envolver llamada en try/catch**

```php
try {
    app(\App\Services\Comisiones\ComisionService::class)->registrarVentaPagada($venta);
} catch (\Throwable $e) {
    \Log::error('comisiones: fallo al registrar venta', [
        'venta' => $venta->id,
        'error' => $e->getMessage(),
    ]);
}
```

Solo si `$venta->estado === 'Pagada'`.

- [ ] **Step 2: Hook devolución → ajuste**

- [ ] **Step 3: Hook anulación → ajuste 100% de movimientos de esa venta aún no ajustados**

- [ ] **Step 4: Probar manualmente una venta Pagada con % configurado (staging) o Feature test mínimo**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(comisiones): hook ledger after sale payment and returns

EOF
)"
```

---

### Task 6: API admin config + períodos + liquidación

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Comisiones/ComisionConfigController.php`
- Create: `Backend/app/Http/Controllers/Api/Comisiones/ComisionPeriodoController.php`
- Create: `Backend/app/Http/Controllers/Api/Comisiones/ComisionLiquidacionController.php`
- Create: `Backend/app/Http/Controllers/Api/Comisiones/ComisionReporteController.php`
- Create: `Backend/routes/modulos/comisiones.php`
- Modify: `Backend/routes/api.php` — require módulo

**Interfaces:**
- CRUD config categoría/subcategoría
- Listar movimientos filtrables
- Cerrar período / marcar liquidación pagada
- Export Excel / PDF

- [ ] **Step 1: Rutas**

```php
Route::middleware(['jwt.auth', 'verificar.funcionalidad:comisiones-vendedores'])->group(function () {
    Route::get('comisiones/config/categorias', ...);
    Route::put('comisiones/config/categorias/{id_categoria}', ...);
    Route::put('comisiones/config/subcategorias/{id_subcategoria}', ...);
    Route::get('comisiones/periodos', ...);
    Route::post('comisiones/periodos/{id}/cerrar', ...);
    Route::post('comisiones/liquidaciones/{id}/pagar', ...);
    Route::get('comisiones/movimientos', ...);
});

// Lectura histórica (flag off): opcional segundo grupo sin middleware de funcionalidad
// pero con auth + permiso admin, solo GET reportes.
```

- [ ] **Step 2: Controllers delgados que delegan a services**

- [ ] **Step 3: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(comisiones): API for config, periods, and settlements

EOF
)"
```

---

### Task 7: Excel multi-hoja + PDF comprobante

**Files:**
- Create: `Backend/app/Exports/Comisiones/ComisionesPorVendedorSheetsExport.php` (`WithMultipleSheets`)
- Create: `Backend/app/Exports/Comisiones/ComisionVendedorSheet.php`
- Create: `Backend/resources/views/reportes/comisiones/comprobante.blade.php`
- Modify: `ComisionReporteController`

**Interfaces:**
- `GET comisiones/export?desde=&hasta=` → xlsx
- `GET comisiones/comprobante/{id_vendedor}?periodo_id=` → PDF stream

- [ ] **Step 1: Sheet columns** — correlativo, fecha, categoría, origen, monto_base, %, comisión; fila total.

- [ ] **Step 2: Marcar `origen=redencion_gift_card` en columna origen (aunque Fase 2 aún no genere filas).**

- [ ] **Step 3: Blade PDF** — datos vendedor, período, tabla resumen, espacio firma.

```php
$pdf = PDF::loadView('reportes.comisiones.comprobante', compact('vendedor', 'periodo', 'movimientos', 'total'));
$pdf->setPaper('US Letter', 'portrait');
return $pdf->stream('comprobante-comision-'.$vendedor->id.'.pdf');
```

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(comisiones): Excel export by seller and printable PDF voucher

EOF
)"
```

---

### Task 8: Frontend Angular — admin y reportes

**Files:**
- Create module under `Frontend/src/app/views/comisiones/`
- Routing con `FuncionalidadGuard` + `data: { funcionalidadSlug: 'comisiones-vendedores' }`
- Wire menú solo si `verificarAcceso('comisiones-vendedores')`

**Screens (mínimo v1):**
1. Config % por categoría (lista + input) y override subcategoría
2. Períodos / liquidaciones
3. Botón descargar Excel (date range)
4. Botón comprobante PDF por vendedor

- [ ] **Step 1: Scaffold module + routing** (copiar estructura de `fidelizacion-routing.module.ts`)

- [ ] **Step 2: Service HTTP** `comisiones.service.ts`

- [ ] **Step 3: Pantallas CRUD config**

- [ ] **Step 4: Reportes Excel/PDF**

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(comisiones): Angular admin for rates, settlements, and reports

EOF
)"
```

---

### Task 9: Verificación de cierre Fase 1

- [ ] **Step 1:** Activar funcionalidad en una empresa de staging vía Super Admin.

- [ ] **Step 2:** Configurar 2% en una categoría; vender producto; verificar fila en `comision_movimientos`.

- [ ] **Step 3:** Devolver con período abierto → ajuste mismo período; marcar período pagado; devolver otra → ajuste en período nuevo abierto.

- [ ] **Step 4:** Excel y PDF generan sin error.

- [ ] **Step 5:** Con flag off, no se crean movimientos nuevos; GET histórico (si se implementó) sigue respondiendo.

---

## Self-review (plan vs spec)

| Requisito spec | Task |
|----------------|------|
| Feature flag `comisiones-vendedores` | 1 |
| Config categoría + override subcategoría | 2, 6, 8 |
| Ledger al evento | 4, 5 |
| Regla B devoluciones | 4, 5 |
| Base configurable default sin IVA | 3 |
| Excel / PDF | 7, 8 |
| Excluir gift category | 4 |
| `registrarDesdeRedencion` listo para gift | 4 |
| Bonos / gift UI | Planes hermanos |
