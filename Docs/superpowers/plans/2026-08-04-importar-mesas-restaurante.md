# Importar mesas restaurante — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Comando Artisan que importa mesas desde Excel, matchea zona por nombre, soporta `--dry-run`, omite duplicados y aborta si hay errores.

**Architecture:** Un helper puro planifica altas/omisiones/errores a partir de filas + mapa de zonas; el comando lee el Excel, consulta BD, imprime resumen y (si no es dry-run) inserta en transacción.

**Tech Stack:** Laravel Artisan, PhpSpreadsheet (ya vía Maatwebsite), PHPUnit.

**Spec:** `Docs/superpowers/specs/2026-08-04-importar-mesas-restaurante-design.md`

## Global Constraints

- Match zona por `trim(nombre)`, case-sensitive, solo `activo=1`.
- `--empresa=` obligatorio; `id_sucursal` siempre `null`.
- Duplicado = mismo `id_empresa` + `zona_id` + `numero` → omitir.
- Cualquier error → exit 1 y no insertar nada.
- No crear/actualizar zonas; ignorar columna numérica `zona` del Excel.
- No tocar `Frontend/src/environments/environment.ts`.

## File map

| File | Role |
|------|------|
| `Backend/app/Support/Restaurante/MesasImportPlanner.php` | Lógica pura de plan |
| `Backend/tests/Unit/Support/Restaurante/MesasImportPlannerTest.php` | Self-check |
| `Backend/app/Console/Commands/ImportarMesasRestaurante.php` | Artisan + Excel + DB |

---

### Task 1: Planner + unit test

**Files:**
- Create: `Backend/app/Support/Restaurante/MesasImportPlanner.php`
- Create: `Backend/tests/Unit/Support/Restaurante/MesasImportPlannerTest.php`

**Interfaces:**
- Produces: `MesasImportPlanner::indexZonas(iterable $zonas): array{map: array<string,object>, errors: list<string>}`
  - `$zonas` items con `id`, `nombre`
  - Si nombre (trim) duplicado → error en `errors`, no en map
- Produces: `MesasImportPlanner::plan(array $rows, array $zonaMap, array $existingKeys): array{crear: list<array>, omitir: list<array>, errores: list<array>}`
  - `$rows`: `[['fila'=>int,'numero'=>?string,'capacidad'=>mixed,'zona_nombre'=>?string,'orden'=>mixed], ...]`
  - `$existingKeys`: set de `"{$zonaId}|{$numero}"`
  - Item crear: `numero`, `capacidad`, `orden`, `zona_id`, `zona` (nombre)
  - Item error: `fila`, `zona`, `motivo`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\MesasImportPlanner;
use PHPUnit\Framework\TestCase;

final class MesasImportPlannerTest extends TestCase
{
    public function test_plan_crea_omite_y_error_zona(): void
    {
        $zonas = [
            (object) ['id' => 10, 'nombre' => 'Deck 1'],
            (object) ['id' => 11, 'nombre' => ' Terraza '],
        ];
        $indexed = MesasImportPlanner::indexZonas($zonas);
        $this->assertSame([], $indexed['errors']);
        $this->assertArrayHasKey('Deck 1', $indexed['map']);
        $this->assertArrayHasKey('Terraza', $indexed['map']);

        $rows = [
            ['fila' => 2, 'numero' => '1', 'capacidad' => 4, 'zona_nombre' => 'Deck 1', 'orden' => 1],
            ['fila' => 3, 'numero' => '2', 'capacidad' => null, 'zona_nombre' => 'Deck 1', 'orden' => null],
            ['fila' => 4, 'numero' => '1', 'capacidad' => 4, 'zona_nombre' => 'Deck 1', 'orden' => 1],
            ['fila' => 5, 'numero' => '9', 'capacidad' => 4, 'zona_nombre' => 'No Existe', 'orden' => 1],
        ];
        $existing = ['10|1' => true];

        $plan = MesasImportPlanner::plan($rows, $indexed['map'], $existing);

        $this->assertCount(1, $plan['crear']);
        $this->assertSame('2', $plan['crear'][0]['numero']);
        $this->assertSame(4, $plan['crear'][0]['capacidad']);
        $this->assertSame(0, $plan['crear'][0]['orden']);
        $this->assertCount(1, $plan['omitir']);
        $this->assertCount(1, $plan['errores']);
        $this->assertStringContainsString('zona', strtolower($plan['errores'][0]['motivo']));
    }

    public function test_zona_ambigua(): void
    {
        $indexed = MesasImportPlanner::indexZonas([
            (object) ['id' => 1, 'nombre' => 'Salon'],
            (object) ['id' => 2, 'nombre' => 'Salon'],
        ]);
        $this->assertNotEmpty($indexed['errors']);
        $this->assertSame([], $indexed['map']);
    }
}
```

- [ ] **Step 2: Run → expect FAIL**

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Support/Restaurante/MesasImportPlannerTest.php
```

- [ ] **Step 3: Implement planner**

```php
<?php

namespace App\Support\Restaurante;

final class MesasImportPlanner
{
    public static function indexZonas(iterable $zonas): array
    {
        $map = [];
        $errors = [];
        $seen = [];
        foreach ($zonas as $zona) {
            $nombre = trim((string) $zona->nombre);
            if ($nombre === '') {
                continue;
            }
            if (isset($seen[$nombre])) {
                $errors[] = "Zona ambigua: \"{$nombre}\"";
                unset($map[$nombre]);
                continue;
            }
            $seen[$nombre] = true;
            $map[$nombre] = $zona;
        }
        if ($errors !== []) {
            $map = [];
        }
        return compact('map', 'errors');
    }

    public static function plan(array $rows, array $zonaMap, array $existingKeys): array
    {
        $crear = [];
        $omitir = [];
        $errores = [];

        foreach ($rows as $row) {
            $fila = (int) ($row['fila'] ?? 0);
            $numero = trim((string) ($row['numero'] ?? ''));
            $zonaNombre = trim((string) ($row['zona_nombre'] ?? ''));

            if ($numero === '') {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Número de mesa vacío'];
                continue;
            }

            if ($zonaNombre === '') {
                $errores[] = ['fila' => $fila, 'zona' => '', 'motivo' => 'Nombre de zona vacío'];
                continue;
            }

            if (! isset($zonaMap[$zonaNombre])) {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Zona no encontrada'];
                continue;
            }

            $capacidad = $row['capacidad'];
            if ($capacidad === null || $capacidad === '') {
                $capacidad = 4;
            }
            if (! is_numeric($capacidad) || (int) $capacidad < 1) {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Capacidad inválida'];
                continue;
            }

            $orden = $row['orden'];
            if ($orden === null || $orden === '') {
                $orden = 0;
            }
            if (! is_numeric($orden) || (int) $orden < 0) {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Orden inválido'];
                continue;
            }

            $zona = $zonaMap[$zonaNombre];
            $key = $zona->id . '|' . $numero;
            $payload = [
                'numero' => $numero,
                'capacidad' => (int) $capacidad,
                'orden' => (int) $orden,
                'zona_id' => (int) $zona->id,
                'zona' => $zonaNombre,
                'fila' => $fila,
            ];

            if (isset($existingKeys[$key])) {
                $omitir[] = $payload;
                continue;
            }

            // Evitar duplicados dentro del mismo Excel
            $existingKeys[$key] = true;
            $crear[] = $payload;
        }

        return compact('crear', 'omitir', 'errores');
    }
}
```

- [ ] **Step 4: Run tests → PASS**

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Support/Restaurante/MesasImportPlannerTest.php
```

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Support/Restaurante/MesasImportPlanner.php Backend/tests/Unit/Support/Restaurante/MesasImportPlannerTest.php
git commit -m "feat: planificador de importación de mesas restaurante"
```

---

### Task 2: Artisan command

**Files:**
- Create: `Backend/app/Console/Commands/ImportarMesasRestaurante.php`

**Interfaces:**
- Consumes: `MesasImportPlanner::indexZonas`, `MesasImportPlanner::plan`
- Signature: `restaurante:importar-mesas {archivo} {--empresa=} {--dry-run}`

- [ ] **Step 1: Implement command**

```php
<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\ZonaRestaurante;
use App\Support\Restaurante\MesasImportPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportarMesasRestaurante extends Command
{
    protected $signature = 'restaurante:importar-mesas
                            {archivo : Ruta al archivo xlsx}
                            {--empresa= : ID de empresa (obligatorio)}
                            {--dry-run : Solo validar y reportar, sin escribir}';

    protected $description = 'Importa mesas de restaurante desde Excel y las asigna a zonas existentes por nombre';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $empresaId = (int) $this->option('empresa');
        $archivo = $this->argument('archivo');

        if ($empresaId < 1) {
            $this->error('Debes indicar --empresa=ID');
            return 1;
        }

        if (! Empresa::where('id', $empresaId)->exists()) {
            $this->error("Empresa {$empresaId} no existe.");
            return 1;
        }

        if (! is_file($archivo)) {
            $this->error("Archivo no encontrado: {$archivo}");
            return 1;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirán cambios.');
        }

        $rows = $this->leerFilas($archivo);
        if ($rows === []) {
            $this->warn('No hay filas de datos en el Excel.');
            return 0;
        }

        $zonas = ZonaRestaurante::where('id_empresa', $empresaId)
            ->where('activo', true)
            ->get(['id', 'nombre']);

        $indexed = MesasImportPlanner::indexZonas($zonas);
        if ($indexed['errors'] !== []) {
            foreach ($indexed['errors'] as $err) {
                $this->error($err);
            }
            return 1;
        }

        $existingKeys = [];
        Mesa::where('id_empresa', $empresaId)
            ->whereNotNull('zona_id')
            ->get(['zona_id', 'numero'])
            ->each(function ($m) use (&$existingKeys) {
                $existingKeys[$m->zona_id . '|' . $m->numero] = true;
            });

        $plan = MesasImportPlanner::plan($rows, $indexed['map'], $existingKeys);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['A crear', count($plan['crear'])],
                ['A omitir', count($plan['omitir'])],
                ['Errores', count($plan['errores'])],
            ]
        );

        if ($plan['errores'] !== []) {
            $this->error('Errores encontrados:');
            $this->table(
                ['Fila', 'Zona', 'Motivo'],
                array_map(fn ($e) => [$e['fila'], $e['zona'], $e['motivo']], $plan['errores'])
            );
            return 1;
        }

        if ($plan['crear'] !== []) {
            $sample = array_slice($plan['crear'], 0, 10);
            $this->info('Muestra a crear (máx. 10):');
            $this->table(
                ['Fila', 'Número', 'Capacidad', 'Zona', 'Orden'],
                array_map(fn ($r) => [$r['fila'], $r['numero'], $r['capacidad'], $r['zona'], $r['orden']], $sample)
            );
        }

        if ($dryRun) {
            $this->warn(sprintf(
                '[Dry-run] Se crearían %d mesas; se omitirían %d.',
                count($plan['crear']),
                count($plan['omitir'])
            ));
            return 0;
        }

        DB::transaction(function () use ($plan, $empresaId) {
            foreach ($plan['crear'] as $row) {
                Mesa::create([
                    'id_empresa' => $empresaId,
                    'id_sucursal' => null,
                    'numero' => $row['numero'],
                    'capacidad' => $row['capacidad'],
                    'zona_id' => $row['zona_id'],
                    'zona' => $row['zona'],
                    'orden' => $row['orden'],
                    'estado' => 'libre',
                    'activo' => true,
                ]);
            }
        });

        $this->info(sprintf(
            'Importación completa: %d mesas creadas, %d omitidas.',
            count($plan['crear']),
            count($plan['omitir'])
        ));

        return 0;
    }

    private function leerFilas(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);
        $rows = [];

        foreach ($raw as $i => $cols) {
            if ($i === 0) {
                continue; // header
            }
            $numero = $cols[0] ?? null;
            $capacidad = $cols[1] ?? null;
            // $cols[2] = zona numérica (ignorar)
            $zonaNombre = $cols[3] ?? null;
            $orden = $cols[4] ?? null;

            if (($numero === null || $numero === '')
                && ($zonaNombre === null || $zonaNombre === '')
            ) {
                continue;
            }

            $rows[] = [
                'fila' => $i + 1,
                'numero' => $numero,
                'capacidad' => $capacidad,
                'zona_nombre' => $zonaNombre,
                'orden' => $orden,
            ];
        }

        return $rows;
    }
}
```

- [ ] **Step 2: Verify command registers**

```bash
cd Backend && php artisan list | grep importar-mesas
```

Expected: `restaurante:importar-mesas`

- [ ] **Step 3: Dry-run smoke (optional if DB available)**

```bash
cd Backend && php artisan restaurante:importar-mesas "plantilla de mesas.xlsx" --empresa=1 --dry-run
```

- [ ] **Step 4: Commit**

```bash
git add Backend/app/Console/Commands/ImportarMesasRestaurante.php
git commit -m "feat: comando artisan para importar mesas desde Excel"
```

---

## Spec coverage

| Spec item | Task |
|-----------|------|
| Excel + Maatwebsite/PhpSpreadsheet | 2 |
| Match zona por nombre | 1+2 |
| `--empresa`, sucursal null | 2 |
| dry-run | 2 |
| omitir duplicados | 1 |
| abortar si zona faltante | 1+2 |
| zona ambigua / inactivas | 1+2 |
| self-check | 1 |
