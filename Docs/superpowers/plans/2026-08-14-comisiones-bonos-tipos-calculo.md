# Tipos de cálculo configurables (comisiones y bonos) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Formalizar comisiones y bonos como reglas con `tipo_calculo` × `alcance` (y `momento_devengo` / `salario_base` en comisiones), sin cambiar las cifras del tipo por categoría ni de `meta_fija`/`escalonado`.

**Architecture:** Strategy por tipo (`Calculators/`). `comision_reglas` nueva con backfill a `por_categoria` global. Bonos reusan `bono_reglas` y el facade `BonoReglaEvaluator::calcular()`. Tipos de línea se escriben en el evento; volumen, salario base y ajuste a mínimo se escriben al cerrar el período. Gift cards, Excel, PDF y flags no se reescriben.

**Tech Stack:** Laravel/PHPUnit (Backend), Angular (Frontend).

**Spec:** `Docs/superpowers/specs/2026-08-14-comisiones-bonos-tipos-calculo-design.md`

## Global Constraints

- No reescribir módulos. Extender.
- `por_categoria` + `al_pagar` + alcance `global` = mismas cifras que v1.
- `meta_fija` y `escalonado` = misma semántica que v1.
- “Por cobro efectivo” no es tipo: es `momento_devengo`.
- “Mixta” no es tipo: es `config.salario_base`.
- Combinación: sumar; `reemplaza_global` descarta globales.
- Conservar `ComisionService::registrarVentaPagada($venta)`.
- No dropear `comision_categoria_config` / `comision_subcategoria_config`.
- No reactivar `tipo_comision` legacy de inventario.
- Equipos v1 = `id_vendedores` JSON (sin tabla `vendedor_equipos`).
- Volumen: último tramo que cumple, persistido al cerrar.
- Salario mínimo: post-procesador de liquidación; si no hay config de planilla, no ajustar.
- Tests v1 en `Backend/tests/Unit/Services/Comisiones/` y `Bonos/` deben seguir verdes tras cada tarea que toque esos servicios.

## File map

**Fase 0**
- Create: `Backend/database/migrations/2026_08_14_120000_create_comision_reglas_and_extend_configs.php`
- Create: `Backend/app/Models/Comisiones/ComisionRegla.php`
- Create: `Backend/app/Services/Comisiones/Calculators/ComisionCalculoResultado.php`
- Create: `Backend/app/Services/Comisiones/Calculators/ComisionCalculator.php`
- Create: `Backend/app/Services/Comisiones/Calculators/PorCategoriaCalculator.php`
- Create: `Backend/app/Services/Comisiones/Calculators/ComisionCalculatorFactory.php`
- Create: `Backend/app/Services/Comisiones/ComisionReglaScope.php`
- Modify: `Backend/app/Models/Comisiones/ComisionCategoriaConfig.php`
- Modify: `Backend/app/Models/Comisiones/ComisionSubcategoriaConfig.php`
- Modify: `Backend/app/Services/Comisiones/ComisionConfigService.php`
- Modify: `Backend/app/Models/Comisiones/ComisionMovimiento.php` (`id_regla`, fillable)
- Modify: `Backend/app/Services/Comisiones/ComisionService.php`
- Test: `Backend/tests/Unit/Services/Comisiones/Calculators/PorCategoriaCalculatorTest.php`
- Test: `Backend/tests/Unit/Services/Comisiones/ComisionReglaScopeTest.php`

**Fase 1**
- Create: `Backend/app/Services/Bonos/Calculators/*`
- Modify: `Backend/app/Services/Bonos/BonoReglaEvaluator.php`
- Modify: `Backend/app/Services/Bonos/BonoReglaService.php`
- Modify: `Backend/app/Services/Bonos/BonoEvaluationService.php`
- Modify: `Backend/app/Models/Bonos/BonoRegla.php`
- Modify: `Backend/app/Models/Bonos/BonoGenerado.php`
- Modify: `Backend/app/Http/Controllers/Api/Bonos/BonoReglaController.php`
- Modify: `Backend/app/Http/Controllers/Api/Bonos/BonoGeneradoController.php`
- Modify: `Backend/routes/modulos/bonos.php`
- Modify: `Frontend/src/app/services/bonos.service.ts`
- Modify: `Frontend/src/app/views/bonos/reglas/*`

**Fase 2–4:** ver cada task.

---

### Task 1: Migración `comision_reglas` + backfill + modelos

**Files:**
- Create: `Backend/database/migrations/2026_08_14_120000_create_comision_reglas_and_extend_configs.php`
- Create: `Backend/app/Models/Comisiones/ComisionRegla.php`
- Modify: `Backend/app/Models/Comisiones/ComisionCategoriaConfig.php`
- Modify: `Backend/app/Models/Comisiones/ComisionSubcategoriaConfig.php`
- Modify: `Backend/app/Models/Comisiones/ComisionMovimiento.php`
- Modify: `Backend/app/Models/Comisiones/ComisionLiquidacion.php`
- Modify: `Backend/app/Services/Comisiones/ComisionConfigService.php`
- Test: `Backend/tests/Unit/Services/Comisiones/ComisionReglaModelTest.php`

**Interfaces:**
- Consumes: tablas v1 `comision_categoria_config`, `comision_subcategoria_config`, `empresa_funcionalidades`
- Produces: `ComisionRegla` con constantes `TIPO_*`, `ALCANCE_*`, `MOMENTO_*`; configs con `id_regla`; liquidación con columnas de desglose; movimientos con `id_regla` nullable

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use PHPUnit\Framework\TestCase;

class ComisionReglaModelTest extends TestCase
{
    public function test_constantes_tipo_alcance_momento(): void
    {
        $this->assertSame('por_categoria', ComisionRegla::TIPO_POR_CATEGORIA);
        $this->assertSame('por_volumen', ComisionRegla::TIPO_POR_VOLUMEN);
        $this->assertSame('por_margen', ComisionRegla::TIPO_POR_MARGEN);
        $this->assertSame('global', ComisionRegla::ALCANCE_GLOBAL);
        $this->assertSame('individual', ComisionRegla::ALCANCE_INDIVIDUAL);
        $this->assertSame('equipo', ComisionRegla::ALCANCE_EQUIPO);
        $this->assertSame('al_pagar', ComisionRegla::MOMENTO_AL_PAGAR);
        $this->assertSame('al_facturar', ComisionRegla::MOMENTO_AL_FACTURAR);
        $this->assertSame('por_abono', ComisionRegla::MOMENTO_POR_ABONO);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones/ComisionReglaModelTest.php -v`

Expected: FAIL because class `ComisionRegla` does not exist.

- [ ] **Step 3: Write model + migration**

`Backend/app/Models/Comisiones/ComisionRegla.php`:

```php
<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionRegla extends Model
{
    protected $table = 'comision_reglas';

    public const TIPO_POR_CATEGORIA = 'por_categoria';
    public const TIPO_POR_VOLUMEN = 'por_volumen';
    public const TIPO_POR_MARGEN = 'por_margen';

    public const ALCANCE_GLOBAL = 'global';
    public const ALCANCE_INDIVIDUAL = 'individual';
    public const ALCANCE_EQUIPO = 'equipo';

    public const MOMENTO_AL_PAGAR = 'al_pagar';
    public const MOMENTO_AL_FACTURAR = 'al_facturar';
    public const MOMENTO_POR_ABONO = 'por_abono';

    protected $fillable = [
        'id_empresa',
        'nombre',
        'tipo_calculo',
        'alcance',
        'id_vendedores',
        'momento_devengo',
        'reemplaza_global',
        'config',
        'activo',
    ];

    protected $casts = [
        'id_vendedores' => 'array',
        'reemplaza_global' => 'boolean',
        'config' => 'array',
        'activo' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

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

    public function categoriasConfig()
    {
        return $this->hasMany(ComisionCategoriaConfig::class, 'id_regla');
    }

    public function aplicaAVendedor(int $idVendedor): bool
    {
        if ($this->alcance === self::ALCANCE_GLOBAL) {
            return true;
        }

        $ids = array_map('intval', (array) ($this->id_vendedores ?? []));

        return in_array($idVendedor, $ids, true);
    }
}
```

Migration `Backend/database/migrations/2026_08_14_120000_create_comision_reglas_and_extend_configs.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_reglas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('nombre');
            $table->string('tipo_calculo', 32);
            $table->string('alcance', 32)->default('global');
            $table->json('id_vendedores')->nullable();
            $table->string('momento_devengo', 32)->default('al_pagar');
            $table->boolean('reemplaza_global')->default(false);
            $table->json('config')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['id_empresa', 'activo']);
        });

        Schema::table('comision_categoria_config', function (Blueprint $table) {
            $table->unsignedBigInteger('id_regla')->nullable()->after('id_empresa');
        });

        Schema::table('comision_subcategoria_config', function (Blueprint $table) {
            $table->unsignedBigInteger('id_regla')->nullable()->after('id_empresa');
        });

        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_regla')->nullable()->after('id_periodo');
        });

        Schema::table('comision_liquidaciones', function (Blueprint $table) {
            $table->decimal('salario_base', 14, 4)->default(0)->after('total_comision');
            $table->decimal('ajuste_salario_minimo', 14, 4)->default(0)->after('salario_base');
            $table->decimal('salario_minimo_aplicado', 14, 4)->nullable()->after('ajuste_salario_minimo');
            $table->decimal('total_a_pagar', 14, 4)->default(0)->after('salario_minimo_aplicado');
        });

        $empresaIds = collect()
            ->merge(DB::table('comision_categoria_config')->distinct()->pluck('id_empresa'))
            ->merge(DB::table('comision_subcategoria_config')->distinct()->pluck('id_empresa'))
            ->merge(
                DB::table('empresa_funcionalidades as ef')
                    ->join('funcionalidades as f', 'f.id', '=', 'ef.id_funcionalidad')
                    ->where('f.slug', 'comisiones-vendedores')
                    ->where('ef.activo', true)
                    ->pluck('ef.id_empresa')
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $now = now();
        foreach ($empresaIds as $idEmpresa) {
            $idRegla = DB::table('comision_reglas')->insertGetId([
                'id_empresa' => $idEmpresa,
                'nombre' => 'Por categoría',
                'tipo_calculo' => 'por_categoria',
                'alcance' => 'global',
                'id_vendedores' => null,
                'momento_devengo' => 'al_pagar',
                'reemplaza_global' => false,
                'config' => json_encode(new stdClass()),
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('comision_categoria_config')->where('id_empresa', $idEmpresa)->update(['id_regla' => $idRegla]);
            DB::table('comision_subcategoria_config')->where('id_empresa', $idEmpresa)->update(['id_regla' => $idRegla]);
        }

        Schema::table('comision_categoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_empresa', 'id_categoria']);
            $table->unique(['id_regla', 'id_categoria']);
        });

        Schema::table('comision_subcategoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_empresa', 'id_subcategoria']);
            $table->unique(['id_regla', 'id_subcategoria']);
        });

        DB::table('comision_liquidaciones')->update([
            'total_a_pagar' => DB::raw('total_comision'),
        ]);
    }

    public function down(): void
    {
        Schema::table('comision_categoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_regla', 'id_categoria']);
            $table->unique(['id_empresa', 'id_categoria']);
            $table->dropColumn('id_regla');
        });
        Schema::table('comision_subcategoria_config', function (Blueprint $table) {
            $table->dropUnique(['id_regla', 'id_subcategoria']);
            $table->unique(['id_empresa', 'id_subcategoria']);
            $table->dropColumn('id_regla');
        });
        Schema::table('comision_movimientos', function (Blueprint $table) {
            $table->dropColumn('id_regla');
        });
        Schema::table('comision_liquidaciones', function (Blueprint $table) {
            $table->dropColumn(['salario_base', 'ajuste_salario_minimo', 'salario_minimo_aplicado', 'total_a_pagar']);
        });
        Schema::dropIfExists('comision_reglas');
    }
};
```

Add `id_regla` to fillable of `ComisionCategoriaConfig` and `ComisionSubcategoriaConfig`. Add `id_regla` to `ComisionMovimiento` fillable. Add the four liquidación columns to `ComisionLiquidacion` fillable + casts.

**In the same task**, update `ComisionConfigService::actualizarCategoria` / `actualizarSubcategoria` so `updateOrCreate` keys include `id_regla` (the empresa’s backfilled `por_categoria` global rule). Without this, the unique `(id_regla, id_categoria)` breaks the admin PUT after migrate. Add a private `idReglaCategoriaDefault(int $idEmpresa): int` that loads that rule (create it if missing — same payload as backfill). `listarCategorias` does not need to change yet.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones/ComisionReglaModelTest.php -v`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/database/migrations/2026_08_14_120000_create_comision_reglas_and_extend_configs.php \
  Backend/app/Models/Comisiones/ComisionRegla.php \
  Backend/app/Models/Comisiones/ComisionCategoriaConfig.php \
  Backend/app/Models/Comisiones/ComisionSubcategoriaConfig.php \
  Backend/app/Models/Comisiones/ComisionMovimiento.php \
  Backend/app/Models/Comisiones/ComisionLiquidacion.php \
  Backend/app/Services/Comisiones/ComisionConfigService.php \
  Backend/tests/Unit/Services/Comisiones/ComisionReglaModelTest.php
git commit -m "$(cat <<'EOF'
feat: add comision_reglas with backward-compatible category backfill

EOF
)"
```

---

### Task 2: Strategy `PorCategoria` + factory

**Files:**
- Create: `Backend/app/Services/Comisiones/Calculators/ComisionCalculoResultado.php`
- Create: `Backend/app/Services/Comisiones/Calculators/ComisionCalculator.php`
- Create: `Backend/app/Services/Comisiones/Calculators/PorCategoriaCalculator.php`
- Create: `Backend/app/Services/Comisiones/Calculators/ComisionCalculatorFactory.php`
- Test: `Backend/tests/Unit/Services/Comisiones/Calculators/PorCategoriaCalculatorTest.php`

**Interfaces:**
- Consumes: `ComisionPorcentajeResolver::resolver(int, ?int, ?int): float`
- Produces: `ComisionCalculator::calcularEnEvento` / `calcularEnCierre`; `ComisionCalculatorFactory::for(string $tipo): ComisionCalculator`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
use App\Services\Comisiones\Calculators\PorCategoriaCalculator;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

class PorCategoriaCalculatorTest extends TestCase
{
    public function test_usa_porcentaje_del_resolver(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn () => 2.0,
            fn () => null
        );
        $calc = new PorCategoriaCalculator($resolver);
        $detalle = (object) ['id' => 1];
        $regla = (object) ['id' => 9, 'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA];

        $ctx = (object) [
            'id_empresa' => 1,
            'regla' => $regla,
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'base' => 100.0,
            'detalle' => $detalle,
        ];

        $r = $calc->calcularEnEvento($ctx);
        $this->assertNotNull($r);
        $this->assertSame(100.0, $r->montoBase);
        $this->assertSame(2.0, $r->porcentaje);
        $this->assertSame(2.0, $r->montoComision);
        $this->assertSame(10, $r->idCategoria);
        $this->assertSame([], $calc->calcularEnCierre($ctx));
    }

    public function test_cero_no_genera_resultado(): void
    {
        $calc = new PorCategoriaCalculator(new ComisionPorcentajeResolver(fn () => 0.0, fn () => null));
        $ctx = (object) [
            'id_empresa' => 1,
            'regla' => (object) ['id' => 1],
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'base' => 100.0,
            'detalle' => new stdClass(),
        ];
        $this->assertNull($calc->calcularEnEvento($ctx));
    }

    public function test_factory_por_categoria(): void
    {
        $factory = new ComisionCalculatorFactory(new ComisionPorcentajeResolver(fn () => null, fn () => null));
        $this->assertInstanceOf(
            PorCategoriaCalculator::class,
            $factory->for(ComisionRegla::TIPO_POR_CATEGORIA)
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones/Calculators/PorCategoriaCalculatorTest.php -v`

Expected: FAIL because calculator classes do not exist.

- [ ] **Step 3: Write minimal implementation**

`ComisionCalculoResultado.php`:

```php
<?php

namespace App\Services\Comisiones\Calculators;

class ComisionCalculoResultado
{
    public function __construct(
        public float $montoBase,
        public float $porcentaje,
        public float $montoComision,
        public ?int $idCategoria = null,
        public ?int $idSubcategoria = null,
        public string $origen = 'venta',
    ) {
    }
}
```

`ComisionCalculator.php`:

```php
<?php

namespace App\Services\Comisiones\Calculators;

interface ComisionCalculator
{
    public function calcularEnEvento(object $ctx): ?ComisionCalculoResultado;

    /** @return list<ComisionCalculoResultado> */
    public function calcularEnCierre(object $ctx): array;
}
```

`PorCategoriaCalculator.php`:

```php
<?php

namespace App\Services\Comisiones\Calculators;

use App\Services\Comisiones\ComisionPorcentajeResolver;

class PorCategoriaCalculator implements ComisionCalculator
{
    public function __construct(private ComisionPorcentajeResolver $resolver)
    {
    }

    public function calcularEnEvento(object $ctx): ?ComisionCalculoResultado
    {
        $pct = $this->resolver->resolver(
            (int) $ctx->id_empresa,
            isset($ctx->id_categoria) ? (int) $ctx->id_categoria : null,
            isset($ctx->id_subcategoria) ? (int) $ctx->id_subcategoria : null
        );
        if ($pct == 0.0) {
            return null;
        }
        $base = (float) $ctx->base;
        if ($base <= 0) {
            return null;
        }

        return new ComisionCalculoResultado(
            $base,
            $pct,
            round($base * ($pct / 100), 4),
            isset($ctx->id_categoria) ? (int) $ctx->id_categoria : null,
            isset($ctx->id_subcategoria) ? (int) $ctx->id_subcategoria : null,
        );
    }

    public function calcularEnCierre(object $ctx): array
    {
        return [];
    }
}
```

`ComisionCalculatorFactory.php` — fase 0 solo mapea `por_categoria`; otros tipos lanzan `InvalidArgumentException` (todavía no implementados):

```php
<?php

namespace App\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use InvalidArgumentException;

class ComisionCalculatorFactory
{
    public function __construct(private ComisionPorcentajeResolver $resolver)
    {
    }

    public function for(string $tipo): ComisionCalculator
    {
        return match ($tipo) {
            ComisionRegla::TIPO_POR_CATEGORIA => new PorCategoriaCalculator($this->resolver),
            default => throw new InvalidArgumentException("tipo_calculo desconocido: {$tipo}"),
        };
    }
}
```

- [ ] **Step 4: Run tests**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones/Calculators/PorCategoriaCalculatorTest.php tests/Unit/Services/Comisiones/ComisionPorcentajeResolverTest.php tests/Unit/Services/Comisiones/ComisionBaseCalculatorTest.php -v`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/Comisiones/Calculators Backend/tests/Unit/Services/Comisiones/Calculators
git commit -m "$(cat <<'EOF'
feat: add por_categoria commission calculator behind a factory

EOF
)"
```

---

### Task 3: `ComisionReglaScope` + cablear `ComisionService` sin cambiar cifras

**Files:**
- Create: `Backend/app/Services/Comisiones/ComisionReglaScope.php`
- Modify: `Backend/app/Services/Comisiones/ComisionService.php`
- Test: `Backend/tests/Unit/Services/Comisiones/ComisionReglaScopeTest.php`
- Test: keep `ComisionServiceOrchestrationTest.php` green (constructor gana deps opcionales al final)

**Interfaces:**
- Consumes: reglas con `aplicaAVendedor`, `alcance`, `reemplaza_global`
- Produces: `ComisionReglaScope::aplicables(array $reglas, int $idVendedor): array` ya combinadas

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionReglaScope;
use PHPUnit\Framework\TestCase;

class ComisionReglaScopeTest extends TestCase
{
    private function regla(array $over): object
    {
        return (object) array_merge([
            'id' => 1,
            'alcance' => ComisionRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
            'reemplaza_global' => false,
            'activo' => true,
        ], $over);
    }

    public function test_suma_global_e_individual(): void
    {
        $scope = new ComisionReglaScope();
        $out = $scope->aplicables([
            $this->regla(['id' => 1]),
            $this->regla([
                'id' => 2,
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [5],
            ]),
        ], 5);
        $this->assertSame([1, 2], array_map(fn ($r) => $r->id, $out));
    }

    public function test_reemplaza_global_descarta_globales(): void
    {
        $scope = new ComisionReglaScope();
        $out = $scope->aplicables([
            $this->regla(['id' => 1]),
            $this->regla([
                'id' => 2,
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [5],
                'reemplaza_global' => true,
            ]),
        ], 5);
        $this->assertSame([2], array_map(fn ($r) => $r->id, $out));
    }

    public function test_individual_de_otro_vendedor_no_aplica(): void
    {
        $scope = new ComisionReglaScope();
        $out = $scope->aplicables([
            $this->regla([
                'id' => 2,
                'alcance' => ComisionRegla::ALCANCE_INDIVIDUAL,
                'id_vendedores' => [9],
            ]),
        ], 5);
        $this->assertSame([], $out);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones/ComisionReglaScopeTest.php -v`

Expected: FAIL because `ComisionReglaScope` does not exist.

- [ ] **Step 3: Implement scope + wire service**

`ComisionReglaScope.php`:

```php
<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;

class ComisionReglaScope
{
    /**
     * @param  array<int, object>  $reglas
     * @return array<int, object>
     */
    public function aplicables(array $reglas, int $idVendedor): array
    {
        $filtradas = [];
        foreach ($reglas as $regla) {
            if ($this->cubre($regla, $idVendedor)) {
                $filtradas[] = $regla;
            }
        }

        $reemplaza = false;
        foreach ($filtradas as $regla) {
            $alcance = (string) ($regla->alcance ?? ComisionRegla::ALCANCE_GLOBAL);
            if ($alcance !== ComisionRegla::ALCANCE_GLOBAL && ! empty($regla->reemplaza_global)) {
                $reemplaza = true;
                break;
            }
        }

        if (! $reemplaza) {
            return array_values($filtradas);
        }

        return array_values(array_filter(
            $filtradas,
            fn ($r) => ($r->alcance ?? ComisionRegla::ALCANCE_GLOBAL) !== ComisionRegla::ALCANCE_GLOBAL
        ));
    }

    private function cubre(object $regla, int $idVendedor): bool
    {
        $alcance = (string) ($regla->alcance ?? ComisionRegla::ALCANCE_GLOBAL);
        if ($alcance === ComisionRegla::ALCANCE_GLOBAL) {
            return true;
        }
        $ids = array_map('intval', (array) ($regla->id_vendedores ?? []));

        return in_array($idVendedor, $ids, true);
    }
}
```

In `ComisionService`:

1. Add constructor deps (after existing ones, all optional with defaults): `?ComisionCalculatorFactory $calculatorFactory = null`, `?ComisionReglaScope $reglaScope = null`, `?Closure $obtenerReglasActivas = null`.
2. Default `$obtenerReglasActivas` = load `ComisionRegla::withoutGlobalScope('empresa')->where id_empresa and activo`.
3. Default factory = `new ComisionCalculatorFactory($this->resolver)`.
4. Default scope = `new ComisionReglaScope()`.
5. In `registrarLineaVenta`, after gift-card skip and vendedor efectivo:
   - `$reglas = ($this->obtenerReglasActivas)($idEmpresa);`
   - if empty: keep current single-resolver path (fallback v1).
   - else: `$aplicables = $this->reglaScope->aplicables($reglas->all(), $idVendedor)` then filter `momento_devengo === al_pagar` (fase 0 only handles pagada).
   - foreach aplicable: `$calc = $this->calculatorFactory->for($regla->tipo_calculo)` then `calcularEnEvento` with ctx `{id_empresa, regla, id_categoria, id_subcategoria, base, detalle}`. Persist one movement per regla with `id_regla`. If several reglas, unique key cannot stay only `(empresa, origen, id_detalle_venta)` — add `id_regla` to `$where` when not null.

**Idempotencia:** `$where` pasa a incluir `'id_regla' => $regla->id` cuando hay regla. Fallback v1 (sin reglas) conserva `$where` actual.

Apply the same dispatch in `registrarDesdeRedencion` (gift): do not keep a second hardcoded `$this->resolver->resolver` path.

Update `ComisionServiceOrchestrationTest::makeService` to still construct: the new params are optional at the end, so existing calls compile.

- [ ] **Step 4: Run tests**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones -v`

Expected: PASS (orchestration + resolver + base + new tests)

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/Comisiones/ComisionReglaScope.php \
  Backend/app/Services/Comisiones/ComisionService.php \
  Backend/tests/Unit/Services/Comisiones/ComisionReglaScopeTest.php \
  Backend/tests/Unit/Services/Comisiones/ComisionServiceOrchestrationTest.php
git commit -m "$(cat <<'EOF'
feat: dispatch commission line events through rules without changing category math

EOF
)"
```

Fase 0 termina aquí. Verificar a mano: una venta de prueba con % de categoría produce el mismo `monto_comision` que antes del backfill.

---

### Task 4: Bonos — extraer calculators y agregar excedente + cualitativo

**Files:**
- Create: `Backend/app/Services/Bonos/Calculators/BonoCalculator.php`
- Create: `Backend/app/Services/Bonos/Calculators/MetaFijaCalculator.php`
- Create: `Backend/app/Services/Bonos/Calculators/EscalonadoCalculator.php`
- Create: `Backend/app/Services/Bonos/Calculators/PorcentajeExcedenteCalculator.php`
- Create: `Backend/app/Services/Bonos/Calculators/CualitativoManualCalculator.php`
- Create: `Backend/app/Services/Bonos/Calculators/BonoCalculatorFactory.php`
- Modify: `Backend/app/Services/Bonos/BonoReglaEvaluator.php`
- Modify: `Backend/app/Models/Bonos/BonoRegla.php` (nuevas constantes de tipo)
- Test: `Backend/tests/Unit/Services/Bonos/BonoReglaEvaluatorTest.php` (extender; no borrar los 3 tests v1)

**Interfaces:**
- Consumes: firma actual `BonoReglaEvaluator::calcular(string $tipo, array $config, float $ventas): float`
- Produces: mismos resultados para `meta_fija`/`escalonado`; `porcentaje_excedente`; `cualitativo_manual` → 0

- [ ] **Step 1: Write the failing tests (append to existing file)**

```php
    public function test_porcentaje_excedente_solo_sobre_el_exceso(): void
    {
        $eval = new BonoReglaEvaluator();
        $monto = $eval->calcular('porcentaje_excedente', ['meta' => 40000, 'porcentaje' => 10], 50000);
        $this->assertSame(1000.0, $monto);
    }

    public function test_porcentaje_excedente_sin_exceso_es_cero(): void
    {
        $eval = new BonoReglaEvaluator();
        $this->assertSame(0.0, $eval->calcular('porcentaje_excedente', ['meta' => 40000, 'porcentaje' => 10], 40000));
    }

    public function test_cualitativo_manual_siempre_cero_en_job(): void
    {
        $eval = new BonoReglaEvaluator();
        $this->assertSame(0.0, $eval->calcular('cualitativo_manual', [], 99999));
    }
```

- [ ] **Step 2: Run tests to verify new ones fail**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Bonos/BonoReglaEvaluatorTest.php -v`

Expected: FAIL on `tipo bono desconocido: porcentaje_excedente`. The three v1 tests still PASS.

- [ ] **Step 3: Implement calculators; evaluator becomes facade**

Keep `BonoReglaEvaluator::calcular` as the public API. Body:

```php
public function calcular(string $tipo, array $config, float $ventas): float
{
    return (new BonoCalculatorFactory())->for($tipo)->calcular($config, $ventas);
}
```

`MetaFijaCalculator`: copy current `meta_fija` branch.  
`EscalonadoCalculator`: copy current `escalonado()` private method.  
`PorcentajeExcedenteCalculator`:

```php
$meta = (float) ($config['meta'] ?? 0);
$pct = (float) ($config['porcentaje'] ?? 0);
if ($ventas <= $meta) {
    return 0.0;
}
return round(($ventas - $meta) * ($pct / 100), 4);
```

`CualitativoManualCalculator::calcular` returns `0.0`.

Factory match: the five types; `default` throws `InvalidArgumentException` like today.

Add constants on `BonoRegla`: `TIPO_PORCENTAJE_EXCEDENTE`, `TIPO_GRUPAL`, `TIPO_CUALITATIVO_MANUAL`. Add `ALCANCE_INDIVIDUAL`, `ALCANCE_EQUIPO` (keep `ALCANCE_VENDEDORES` as alias).

- [ ] **Step 4: Run tests**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Bonos -v`

Expected: PASS including the three original evaluator tests.

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/Bonos Backend/app/Models/Bonos/BonoRegla.php Backend/tests/Unit/Services/Bonos
git commit -m "$(cat <<'EOF'
feat: extract bono calculators and add excedente and qualitative types

EOF
)"
```

---

### Task 5: Bonos — alcance individual/equipo, grupal, manual, `reemplaza_global`

**Files:**
- Create: `Backend/database/migrations/2026_08_14_130000_extend_bono_reglas_alcance.php`
- Create: `Backend/app/Services/Bonos/Calculators/GrupalCalculator.php`
- Modify: `Backend/app/Services/Bonos/BonoReglaService.php`
- Modify: `Backend/app/Services/Bonos/BonoEvaluationService.php`
- Modify: `Backend/app/Services/Bonos/BonoGeneradoService.php`
- Modify: `Backend/app/Http/Controllers/Api/Bonos/BonoReglaController.php`
- Modify: `Backend/app/Http/Controllers/Api/Bonos/BonoGeneradoController.php`
- Modify: `Backend/routes/modulos/bonos.php`
- Modify: `Backend/app/Models/Bonos/BonoGenerado.php` (`origen`)
- Modify: `Frontend/src/app/services/bonos.service.ts`
- Modify: `Frontend/src/app/views/bonos/reglas/reglas.component.ts`
- Modify: `Frontend/src/app/views/bonos/reglas/reglas.component.html`
- Test: `Backend/tests/Unit/Services/Bonos/GrupalCalculatorTest.php`
- Test: extend `BonoEvaluationServiceTest.php` with alcance + skip cualitativo

Migration:

```php
Schema::table('bono_reglas', function (Blueprint $table) {
    $table->boolean('reemplaza_global')->default(false)->after('alcance');
});
Schema::table('bono_generados', function (Blueprint $table) {
    $table->string('origen', 32)->default('evaluacion')->after('estado');
});
```

No rewrite of `alcance=vendedores` rows. Normalize in `BonoReglaService` / evaluation:

- read `vendedores` + 1 id → treat as `individual`
- read `vendedores` + N ids → treat as `equipo`
- writes accept `global|individual|equipo` (and still `vendedores` for old UI until Task 8)

`GrupalCalculator` (unit, no DB):

```php
public function repartir(array $config, array $ventasPorVendedor): array
{
    $meta = (float) ($config['meta'] ?? 0);
    $bono = (float) ($config['bono'] ?? 0);
    $reparto = $config['reparto'] ?? 'equitativo';
    $total = array_sum($ventasPorVendedor);
    if ($total < $meta) {
        return array_fill_keys(array_keys($ventasPorVendedor), 0.0);
    }
    $ids = array_keys($ventasPorVendedor);
    if ($reparto === 'proporcional') {
        $out = [];
        foreach ($ventasPorVendedor as $id => $v) {
            $out[$id] = $total > 0 ? round($bono * ($v / $total), 4) : 0.0;
        }
        return $out;
    }
    $cada = round($bono / max(1, count($ids)), 4);
    return array_fill_keys($ids, $cada);
}
```

`BonoEvaluationService::evaluarEmpresa`: skip `cualitativo_manual`. For `grupal`, group by regla, compute team sales via `BonoMetaCalculator` per member, call `repartir`, persist per vendedor. Apply `ComisionReglaScope`-equivalent combination (extract a shared `ReglaAlcance` in `Backend/app/Services/Incentivos/ReglaAlcance.php` if both modules need it — only if it stays < 40 lines; otherwise duplicate the 20-line filter in a `BonoReglaScope`).

`BonoGeneradoService::crearManual(...)`: validate regla tipo, alcance, unique; set `origen=manual`, `estado=pendiente`.

Route: `POST bonos/generados/manual`.

Controller validation `tipo` in: `meta_fija,escalonado,porcentaje_excedente,grupal,cualitativo_manual`. `alcance` in: `global,vendedores,individual,equipo`. `grupal` requires `alcance=equipo` and `id_vendedores` not empty.

Frontend form: add the three tipos and alcance options; for `porcentaje_excedente` show meta + %; for `grupal` show meta, bono, reparto; for `cualitativo_manual` hide meta fields. Map old `vendedores` in `alcanceLabel`.

- [ ] **Step 4: Run**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Bonos -v`

Expected: PASS

- [ ] **Step 5: Commit** with message `feat: add bono alcance, team split, and manual qualitative awards`

---

### Task 6: Comisiones de línea — `por_margen` y `momento_devengo`

**Files:**
- Create: `Backend/app/Services/Comisiones/Calculators/PorMargenCalculator.php`
- Modify: `Backend/app/Services/Comisiones/Calculators/ComisionCalculatorFactory.php`
- Modify: `Backend/app/Services/Comisiones/ComisionService.php` (filtrar momento vs evento)
- Modify: `Backend/app/Services/Ventas/FacturacionService.php` (llamar registro también si hay reglas `al_facturar` y estado Pendiente — **nuevo método** `registrarVentaFacturada`, no cambiar la firma de `registrarVentaPagada`)
- Modify: `Backend/app/Services/Ventas/AbonoVentaService.php` (evento `por_abono` en cada abono, no solo al saldar)
- Test: `Backend/tests/Unit/Services/Comisiones/Calculators/PorMargenCalculatorTest.php`

Costo línea: `cantidad * (costo_promedio > 0 ? costo_promedio : costo)` del producto. Base de margen = `max(0, base_calculo - costo_linea)`. `%` = `(float) ($regla->config['porcentaje'] ?? 0)`.

`por_abono`: monto comisión de la línea completa × (`abono.monto` / `venta.total`). Persist `origen=abono`, `$where` incluye `id_abono` (nullable column on `comision_movimientos` — add in this task’s migration `2026_08_14_140000_add_abono_origen_to_comision_movimientos.php`). Add `ComisionMovimiento::ORIGEN_ABONO = 'abono'`.

`al_facturar`: `FacturacionService` after creating venta, if estado !== Pagada, `try { registrarVentaFacturada($venta) }`. Internally same as pagada but evento=`facturada` and only reglas `momento_devengo=al_facturar`. Do **not** double-write if the same venta later becomes Pagada for those reglas (idempotency on detalle+regla).

Reglas `al_pagar` must **not** fire on `registrarVentaFacturada`.

- [ ] **Step 4: Run**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones tests/Unit/Services/Ventas/AbonoVentaServiceTest.php -v`

If `AbonoVentaServiceTest` does not exist, only run Comisiones tests. Do not invent a full HTTP test.

Expected: PASS

- [ ] **Step 5: Commit** `feat: add margin commission type and configurable accrual timing`

---

### Task 7: Comisiones de período — volumen, salario base, salario mínimo

**Files:**
- Create: `Backend/app/Services/Comisiones/Calculators/PorVolumenCalculator.php`
- Create: `Backend/app/Services/Comisiones/ComisionSalarioMinimo.php`
- Modify: `Backend/app/Services/Comisiones/ComisionLiquidacionService.php`
- Modify: `Backend/app/Services/Comisiones/Calculators/ComisionCalculatorFactory.php`
- Modify: `Backend/app/Models/Comisiones/ComisionMovimiento.php` (orígenes `ajuste_periodo`, `salario_base`, `ajuste_salario_minimo`)
- Test: `Backend/tests/Unit/Services/Comisiones/Calculators/PorVolumenCalculatorTest.php`
- Test: `Backend/tests/Unit/Services/Comisiones/ComisionSalarioMinimoTest.php`

`PorVolumenCalculator::calcularEnEvento` → null.  
`calcularEnCierre`: `$ventas` from ctx; walk `config.tramos` sorted by `umbral` asc; last matching `porcentaje`; result one `ComisionCalculoResultado` with `origen=ajuste_periodo`, `montoBase=ventas`.

Ventas del período para volumen: reusar la misma base que `BonoMetaCalculator` (excluir categoría gift). Inject a small `ComisionVentasPeriodo` wrapper around that query rather than duplicating SQL if the copy would be > 15 lines; otherwise duplicate the query next to the calculator (ponytail: techo = query copiada; upgrade = extraer shared).

`ComisionSalarioMinimo::ajuste(float $comisionMasBase, ?float $minimo): float` = `max(0, $minimo - $comisionMasBase)` when `$minimo !== null`, else `0`.

`cerrarPeriodo`:

1. Existing lock + assert abierto.
2. Collect vendedor ids from movimientos **union** vendedores cubiertos por reglas período/base.
3. For each vendedor: run volume calculators; `updateOrCreate` movimiento unique `(id_empresa, origen, id_periodo, id_vendedor, id_regla)`.
4. For each aplicable regla with `config.salario_base > 0`: persist `origen=salario_base`.
5. `total_comision` = SUM ledger excluding `salario_base` and `ajuste_salario_minimo`.
6. Read minimo: `EmpresaConfiguracionPlanilla::obtenerConfiguracion($idEmpresa)?->configuracion['salario_minimo'] ?? null`.
7. `ajuste = ComisionSalarioMinimo::ajuste($total_comision + $salario_base, $minimo)`.
8. Persist movimiento `ajuste_salario_minimo` if > 0 (idempotent updateOrCreate).
9. Liquidación: `total_comision`, `salario_base`, `ajuste_salario_minimo`, `salario_minimo_aplicado`, `total_a_pagar`.

Excel/PDF already sum `monto_comision` of listed movements — include the new origens so the sheet total matches `total_a_pagar`. Add labels in `ComisionReporteService::etiquetaOrigen`.

Periodo abierto: do not persist volume. Preview is Task 8.

- [ ] **Step 4: Run**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones -v`

Expected: PASS

- [ ] **Step 5: Commit** `feat: add volume commissions and minimum-wage top-up at period close`

---

### Task 8: UI admin (extender pantallas actuales)

**Files:**
- Create: `Backend/app/Http/Controllers/Api/Comisiones/ComisionReglaController.php`
- Create: `Backend/app/Services/Comisiones/ComisionReglaService.php` (CRUD + validar config por tipo, espejo de `BonoReglaService`)
- Modify: `Backend/routes/modulos/comisiones.php`
- Modify: `Backend/app/Services/Comisiones/ComisionConfigService.php` (aceptar `id_regla` opcional; default = regla `por_categoria` global de la empresa)
- Modify: `Frontend/src/app/services/comisiones.service.ts`
- Modify: `Frontend/src/app/views/comisiones/config-categorias/config-categorias.component.ts`
- Modify: `Frontend/src/app/views/comisiones/config-categorias/config-categorias.component.html`
- Modify: `Frontend/src/app/views/comisiones/periodo-detalle/*` (mostrar `salario_base`, `ajuste_salario_minimo`, `total_a_pagar`)
- Modify: `Frontend/src/app/views/bonos/generados/*` (alta manual)
- Modify: `Frontend/src/app/views/bonos/reglas/*` if anything left from Task 5

Rutas nuevas (mismo middleware `verificar.funcionalidad:comisiones-vendedores`):

```
GET    comisiones/config/reglas
POST   comisiones/config/reglas
PUT    comisiones/config/reglas/{id}
```

Pantalla configuración:

1. Listado de reglas (nombre, tipo, alcance, activo).
2. Form: tipo, alcance, momento, reemplaza_global, salario_base, config del tipo.
3. Si la regla seleccionada es `por_categoria`, mostrar la grilla actual de categorías (pasar `id_regla` al GET/PUT existentes).

Periodo abierto + reglas `por_volumen`: etiqueta “Estimado (se confirma al cerrar)” usando un campo `estimado` opcional en `GET periodos/{id}` — calcular en vivo con `PorVolumenCalculator::calcularEnCierre` sin persistir. If this bloats `cerrarPeriodo`, add `ComisionLiquidacionService::previewVolumen(idEmpresa, idPeriodo)` instead.

No new Angular module. No new route besides the existing `comisiones/configuracion`.

- [ ] **Step 4: Compile check**

Run: `cd Frontend && npx ng build --configuration=development` is too heavy if the project usually uses a lighter check. Prefer: `cd Frontend && npx tsc -p src/tsconfig.app.json --noEmit` if that config exists; otherwise skip and rely on the IDE. Do not add a new test runner.

- [ ] **Step 5: Commit** `feat: extend commission and bonus admin screens for calculation types`

After code files change: `graphify update .` from repo root (AST only).

---

## Execution notes

- Fase 0 (Tasks 1–3) can land on `dc.SP-1888` **before** merging to main.
- Fases 1–4 are follow-up PRs. Do not inflate the first merge with margen/volumen/UI.
- After Task 3, run the full v1 suite: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Comisiones tests/Unit/Services/Bonos tests/Unit/Services/GiftCards -v`
