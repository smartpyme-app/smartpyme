# Mostrar en restaurante Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ocultar categorías y productos del menú de restaurante con un switch `mostrar_en_restaurante` (default true), sin afectar el POS de facturación.

**Architecture:** Columna boolean en `categorias` y `productos`. `PosMenuCatalog` acepta `$soloMostrarEnRestaurante = false`; `PosMenuController` pasa `true` en todas las queries. Formularios de inventario guardan el flag por los endpoints existentes.

**Tech Stack:** Laravel, Eloquent, Angular standalone, PHPUnit (inspección de query builders, sin RefreshDatabase).

**Spec:** `Docs/superpowers/specs/2026-09-10-mostrar-en-restaurante-design.md`

## Global Constraints

- Texto del switch: `Mostrar en restaurante`.
- Campo: `mostrar_en_restaurante`, boolean, default `true`.
- Filtro solo en menú restaurante. `PosMenuVentasController` no pasa el flag.
- Flags independientes: categoría y producto no se cascaden.
- No tocar `categoria_subcategorias` ni Excel.
- No tocar `Frontend/src/environments/environment.ts`.
- No commitear salvo que el usuario lo pida.

## File map

| File | Role |
|------|------|
| `Backend/tests/Feature/Restaurante/PosMenuTest.php` | Assert filtro en queries restaurante |
| `Backend/tests/Feature/Inventario/PosMenuVentasTest.php` | Assert facturación no filtra |
| `Backend/database/migrations/2026_09_10_210000_add_mostrar_en_restaurante_to_categorias_and_productos.php` | Columnas default true |
| `Backend/app/Support/Inventario/PosMenuCatalog.php` | `where` + withCount condicional |
| `Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php` | Pasa `true` al catálogo |
| Models + `StoreCategoriaRequest` | Persistencia |
| Forms inventario (categoría, crear-categoría, producto, crear-producto) | Switch + default true |

---

### Task 1: Tests del filtro (TDD)

**Files:**
- Modify: `Backend/tests/Feature/Restaurante/PosMenuTest.php`
- Modify: `Backend/tests/Feature/Inventario/PosMenuVentasTest.php`

**Interfaces:**
- Consumes: `PosMenuController::queryCategoriasRaiz|querySubcategorias|queryProductos` (ya existen)
- Consumes: `PosMenuCatalog::queryCategoriasRaiz|queryProductos` (firma actual; el flag se agrega en Task 2)
- Produces: asserts de `mostrar_en_restaurante === true` en restaurante; ausencia de esa columna en facturación

- [x] **Step 1: Write the failing tests**

En `PosMenuTest`, agregar:

```php
    public function test_consultas_restaurante_filtran_mostrar_en_restaurante(): void
    {
        $casos = [
            PosMenuController::queryCategoriasRaiz(self::EMPRESA),
            PosMenuController::querySubcategorias(self::EMPRESA, 42),
            PosMenuController::queryProductos(self::EMPRESA),
            PosMenuController::queryProductosDeCategoria(self::EMPRESA, 42),
            PosMenuController::queryProductosDeSubcategoria(self::EMPRESA, 99),
        ];

        foreach ($casos as $query) {
            $wheres = $this->wheres($query);
            $this->assertTrue($wheres['mostrar_en_restaurante'] ?? false, $query->getModel()->getTable());
        }

        $grammar = PosMenuController::queryCategoriasRaiz(self::EMPRESA)->getQuery()->getGrammar();
        $columnas = collect(PosMenuController::queryCategoriasRaiz(self::EMPRESA)->getQuery()->columns)
            ->map(fn ($c) => $c instanceof Expression ? (string) $c->getValue($grammar) : (string) $c)
            ->implode(' ');
        $this->assertStringContainsString('mostrar_en_restaurante', $columnas);
    }
```

En `PosMenuVentasTest`, agregar:

```php
    public function test_catalogo_facturacion_no_filtra_mostrar_en_restaurante(): void
    {
        $cat = collect(PosMenuCatalog::queryCategoriasRaiz(self::EMPRESA)->getQuery()->wheres)
            ->pluck('column')
            ->all();
        $prod = collect(PosMenuCatalog::queryProductos(self::EMPRESA)->getQuery()->wheres)
            ->pluck('column')
            ->all();

        $this->assertNotContains('mostrar_en_restaurante', $cat);
        $this->assertNotContains('mostrar_en_restaurante', $prod);
    }
```

- [x] **Step 2: Run tests to verify they fail**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/PosMenuTest.php --filter test_consultas_restaurante_filtran_mostrar_en_restaurante`

Expected: FAIL — `mostrar_en_restaurante` no está en los wheres.

- [x] **Step 3: Implement filter + persistence**

Migración `Backend/database/migrations/2026_09_10_210000_add_mostrar_en_restaurante_to_categorias_and_productos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            if (! Schema::hasColumn('categorias', 'mostrar_en_restaurante')) {
                $table->boolean('mostrar_en_restaurante')->default(true)->after('enable');
            }
        });
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'mostrar_en_restaurante')) {
                $table->boolean('mostrar_en_restaurante')->default(true)->after('genera_comanda');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            if (Schema::hasColumn('categorias', 'mostrar_en_restaurante')) {
                $table->dropColumn('mostrar_en_restaurante');
            }
        });
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'mostrar_en_restaurante')) {
                $table->dropColumn('mostrar_en_restaurante');
            }
        });
    }
};
```

`Categoria`: agregar `mostrar_en_restaurante` a `$fillable` y `'mostrar_en_restaurante' => 'boolean'` en `$casts`.

`Producto`: igual, junto a `genera_comanda`.

`StoreCategoriaRequest::rules`: `'mostrar_en_restaurante' => ['nullable', 'boolean']`.

`PosMenuCatalog` — añadir `bool $soloMostrarEnRestaurante = false` a `queryCategoriasRaiz`, `querySubcategorias`, `queryProductos`. Aplicar `->where('mostrar_en_restaurante', true)` cuando el flag es true. En el `withCount` de raíces, el closure de subcategorías también filtra si el flag es true.

`queryProductosDeCategoria` / `queryProductosDeSubcategoria` reenvían el bool a `queryProductos`.

`PosMenuController` wrappers pasan `true`:

```php
    public static function queryCategoriasRaiz(int $idEmpresa): Builder
    {
        return PosMenuCatalog::queryCategoriasRaiz($idEmpresa, true);
    }
```

(mismo patrón en subcategorías y productos).

- [x] **Step 4: Run tests to verify they pass**

Run: `cd Backend && ./vendor/bin/phpunit tests/Feature/Restaurante/PosMenuTest.php tests/Feature/Inventario/PosMenuVentasTest.php`

Expected: PASS (todos los tests de ambos archivos).

---

### Task 2: Formularios inventario

**Files:**
- Modify: `Frontend/src/app/views/inventario/categorias/categorias.component.html`
- Modify: `Frontend/src/app/views/inventario/categorias/categorias.component.ts`
- Modify: `Frontend/src/app/shared/modals/crear-categoria/crear-categoria.component.html`
- Modify: `Frontend/src/app/shared/modals/crear-categoria/crear-categoria.component.ts`
- Modify: `Frontend/src/app/views/inventario/productos/producto/informacion/producto-informacion.component.html`
- Modify: `Frontend/src/app/views/inventario/productos/producto/producto.component.ts`
- Modify: `Frontend/src/app/shared/modals/crear-producto/crear-producto.component.html`
- Modify: `Frontend/src/app/shared/modals/crear-producto/crear-producto.component.ts`

**Interfaces:**
- Consumes: campo `mostrar_en_restaurante` persistido por Task 1
- Produces: switch visible; altas arrancan en `true`

- [x] **Step 1: Categoría**

Junto a Activo:

```html
            <div class="form-group col-lg-4 pt-lg-4">
                <div class="form-switch bg-light-info rounded">
                  <label for="mostrar_en_restaurante">Mostrar en restaurante</label>
                  <input class="form-check-input ms-3" type="checkbox" role="switch" id="mostrar_en_restaurante" [(ngModel)]="categoria.mostrar_en_restaurante" name="categoria.mostrar_en_restaurante">
                </div>
            </div>
```

En `initNewItem`: `item.mostrar_en_restaurante = true`.

En el array `keys` del FormData: agregar `'mostrar_en_restaurante'`.

Crear-categoría: mismo switch bajo Activo; en `openModal`: `this.categoria.mostrar_en_restaurante = true`.

- [x] **Step 2: Producto**

En información y crear-producto, en el bloque Restaurante / cocina, **antes** de Genera comanda:

```html
        <div class="form-group col-md-6 col-lg-5 without-label">
            <div class="form-switch bg-light-info rounded">
                <label>Mostrar en restaurante</label>
                <input class="form-check-input float-end" type="checkbox" role="switch" [(ngModel)]="producto.mostrar_en_restaurante" name="mostrar_en_restaurante">
            </div>
        </div>
```

Alta producto (`producto.component.ts` cuando `this.producto = {}`): `this.producto.mostrar_en_restaurante = true`.

Crear-producto `openModal`: `this.producto.mostrar_en_restaurante = true`.

- [x] **Step 3: graphify update**

Run: `graphify update .`

---
