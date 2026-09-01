# Auxiliar de cuentas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Tab *Auxiliar de cuentas* en el modal de reportes de Partidas: detalle del mayor de una cuenta, o del árbol si se elige un padre.

**Architecture:** Helper puro `ArbolCuentas` resuelve el árbol. `construirLibroDiarioMayor` acepta un conjunto de ids (`whereIn`). El mayor sigue pasando un solo id. Ruta nueva reutiliza las vistas del mayor con `titulo = Libro auxiliar`.

**Tech Stack:** Laravel, DomPDF, Maatwebsite Excel, Angular (partidas modal).

## Global Constraints

- Cuenta obligatoria; `all` o vacío → 422.
- Padre = id elegido ∪ descendientes por `id_cuenta_padre` (recursivo).
- Una tabla por cuenta (no saldo mezclado).
- Mayor/diario/balance no cambian de comportamiento.
- Sin auxiliar de terceros. Sin blades/export nuevos. Sin permiso nuevo.
- `{cuenta}` es el `id` de `catalogo_cuentas`.

## File map

- Create: `Backend/app/Services/Contabilidad/ArbolCuentas.php`
- Create: `Backend/tests/Unit/Contabilidad/ArbolCuentasTest.php`
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/Reportes/GenerarReportesController.php` (`construirLibroDiarioMayor`, PDF/Excel mayor, método auxiliar)
- Modify: `Backend/routes/modulos/contabilidad/reportes.php`
- Modify: `Backend/resources/views/reportes/contabilidad/libro_diario_mayor.blade.php`
- Modify: `Backend/resources/views/reportes/contabilidad/excel/libro_diario_mayor_excel.blade.php`
- Modify: `Backend/app/Exports/Contabilidad/DiarioMayorExport.php`
- Modify: `Backend/tests/Unit/Contabilidad/ReportesLibroDiarioMayorBladeTest.php`
- Modify: `Frontend/src/app/views/contabilidad/partidas/cuenta-select.util.ts`
- Modify: `Frontend/src/app/views/contabilidad/partidas/cuenta-select.util.spec.ts`
- Modify: `Frontend/src/app/views/contabilidad/partidas/partidas.component.ts`
- Modify: `Frontend/src/app/views/contabilidad/partidas/partidas.component.html`

---

### Task 1: Árbol de cuentas (TDD)

**Files:**
- Create: `Backend/app/Services/Contabilidad/ArbolCuentas.php`
- Test: `Backend/tests/Unit/Contabilidad/ArbolCuentasTest.php`

**Interfaces:**
- Produces: `ArbolCuentas::idRequerido(mixed $cuenta): ?int` — `null` si vacío/`all`/no numérico positivo.
- Produces: `ArbolCuentas::idsDelArbol(iterable $cuentas, int $raizId): array` — `[]` si la raíz no está en `$cuentas`; si está, `[raiz, ...descendientes]` (BFS/DFS por `id_cuenta_padre`). Cada ítem de `$cuentas` es array u objeto con `id` e `id_cuenta_padre`.

- [x] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\ArbolCuentas;
use PHPUnit\Framework\TestCase;

class ArbolCuentasTest extends TestCase
{
    private array $catalogo = [
        ['id' => 1, 'id_cuenta_padre' => null],
        ['id' => 2, 'id_cuenta_padre' => 1],
        ['id' => 3, 'id_cuenta_padre' => 2],
        ['id' => 4, 'id_cuenta_padre' => 1],
        ['id' => 9, 'id_cuenta_padre' => null],
    ];

    public function test_id_requerido_rechaza_all_y_vacio(): void
    {
        $this->assertNull(ArbolCuentas::idRequerido('all'));
        $this->assertNull(ArbolCuentas::idRequerido(''));
        $this->assertNull(ArbolCuentas::idRequerido(null));
        $this->assertSame(12, ArbolCuentas::idRequerido('12'));
        $this->assertSame(12, ArbolCuentas::idRequerido(12));
    }

    public function test_hoja_es_solo_ese_id(): void
    {
        $this->assertSame([3], ArbolCuentas::idsDelArbol($this->catalogo, 3));
    }

    public function test_padre_incluye_hijas_y_nietas(): void
    {
        $ids = ArbolCuentas::idsDelArbol($this->catalogo, 1);
        sort($ids);
        $this->assertSame([1, 2, 3, 4], $ids);
    }

    public function test_raiz_inexistente_es_vacio(): void
    {
        $this->assertSame([], ArbolCuentas::idsDelArbol($this->catalogo, 99));
    }
}
```

- [x] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Contabilidad/ArbolCuentasTest.php`
Expected: FAIL (class not found)

- [x] **Step 3: Write minimal implementation**

`Backend/app/Services/Contabilidad/ArbolCuentas.php` — `idRequerido` + `idsDelArbol` (índice por padre, cola, incluir raíz).

- [x] **Step 4: Run test to verify it passes**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Contabilidad/ArbolCuentasTest.php`
Expected: PASS

---

### Task 2: Mayor acepta conjunto de ids + ruta auxiliar + título

**Files:**
- Modify: `GenerarReportesController.php` (`construirLibroDiarioMayor` ~L265–278: `whereIn` si el filtro es array o id; `all`/null sin filtro)
- Modify: `generarRepLibroDiarioMayorPDF` / `Excel`: 4º arg `$titulo = 'Reporte Libro Diario Mayor'`
- Add: `generarRepLibroAuxiliar($fecha_inicio, $fecha_fin, $cuenta, $type)`
- Modify: `Backend/routes/modulos/contabilidad/reportes.php`
- Modify: blades mayor PDF/Excel + `DiarioMayorExport` (`titulo` opcional)
- Test: assert blades usan `{{ $titulo ?? 'Reporte Libro Diario Mayor' }}`

**Interfaces:**
- Consumes: `ArbolCuentas::idRequerido`, `ArbolCuentas::idsDelArbol`
- `construirLibroDiarioMayor(..., $cuentaFilter = null)`: `null|'all'` = todas; `int|string` = `[id]`; `array` = esos ids.
- Auxiliar: id inválido/`all` → JSON 422. Catálogo de la empresa; árbol vacío → 422. Llama PDF/Excel mayor con `$ids` y `$titulo = 'Libro auxiliar'`.
- Mayor público sigue pasando el `{cuenta}` de la ruta (un id o `all`), no el árbol.

```php
public function generarRepLibroAuxiliar($fecha_inicio, $fecha_fin, $cuenta, $type)
{
    $id = ArbolCuentas::idRequerido($cuenta);
    if ($id === null) {
        return response()->json(['error' => 'Debe seleccionar una cuenta.'], 422);
    }
    $empresa_id = auth()->user()->id_empresa;
    $catalogo = Cuenta::where('id_empresa', $empresa_id)->get(['id', 'id_cuenta_padre']);
    $ids = ArbolCuentas::idsDelArbol($catalogo, $id);
    if ($ids === []) {
        return response()->json(['error' => 'Cuenta no encontrada.'], 422);
    }
    if ($type === 'pdf') {
        return $this->generarRepLibroDiarioMayorPDF($fecha_inicio, $fecha_fin, $ids, 'Libro auxiliar');
    }
    return $this->generarRepLibroDiarioMayorExcel($fecha_inicio, $fecha_fin, $ids, 'Libro auxiliar');
}
```

Route (mismo grupo `reports.no_cache`):

```php
Route::get('/reportes/libro/auxiliar/{fecha_inicio}/{fecha_fin}/{cuenta}/{type}', [GenerarReportesController::class, 'generarRepLibroAuxiliar']);
```

En `construirLibroDiarioMayor`, reemplazar el `where('id_cuenta', $cuentaFilter)` por:

```php
if ($cuentaFilter && $cuentaFilter !== 'all') {
    $ids = is_array($cuentaFilter) ? $cuentaFilter : [$cuentaFilter];
    $detallesQuery->whereIn('id_cuenta', $ids);
}
```

- [x] **Step 1: Failing blade assertion** — `test_plantilla_usa_titulo_configurable` busca `$titulo ?? 'Reporte Libro Diario Mayor'` en PDF y excel.
- [x] **Step 2: Run** `./vendor/bin/phpunit tests/Unit/Contabilidad/ReportesLibroDiarioMayorBladeTest.php` — FAIL
- [x] **Step 3: Implement controller, route, blades, export**
- [x] **Step 4: Run ArbolCuentasTest + ReportesLibroDiarioMayorBladeTest** — PASS

---

### Task 3: Modal Angular

**Files:**
- Modify: `cuenta-select.util.ts` — `armarOpcionesCuentaAuxiliar` = opciones del mayor sin `{ value: 'all' }`
- Modify: `cuenta-select.util.spec.ts`
- Modify: `partidas.component.ts` — `cuenta_auxiliar: ''`, `opcionesCuentaAuxiliar`, `imprimirAuxiliarCuentas()`
- Modify: `partidas.component.html` — tab después de Libro diario mayor

URL: `/api/reportes/libro/auxiliar/{fecha_inicio}/{fecha_fin}/{cuenta_auxiliar}/{tipo_descarga}` vía `buildReportDownloadUrl`. Submit deshabilitado si no hay `cuenta_auxiliar`.

- [x] **Step 1: Failing spec** — `armarOpcionesCuentaAuxiliar` no incluye “Todas las cuentas”
- [x] **Step 2: Run** `cd Frontend && npx ng test --watch=false --include='**/cuenta-select.util.spec.ts'`
- [x] **Step 3: util + tab + método**
- [x] **Step 4: Re-run spec** — PASS

---

### Task 4: Verificar

- [x] `cd Backend && ./vendor/bin/phpunit tests/Unit/Contabilidad/ArbolCuentasTest.php tests/Unit/Contabilidad/ReportesLibroDiarioMayorBladeTest.php tests/Unit/Contabilidad/LibroDiarioMayorSaldoTest.php tests/Unit/Contabilidad/ReportesContabilidadViewsExistTest.php`
- [x] `cd Frontend && npx ng test --watch=false --include='**/cuenta-select.util.spec.ts'`
- [x] `graphify update .` (AST-only)
