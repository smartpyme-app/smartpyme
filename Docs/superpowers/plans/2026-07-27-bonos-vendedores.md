# Bonos vendedores + consolidado (Fase 3 + 4) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Motor de bonos por reglas (`meta_fija`, `escalonado`), evaluación job+manual idempotente, flujo pendiente→aprobado→pagado, dashboard de progreso, y vista consolidada por vendedor (comisiones + bonos + redenciones) sin mezclar montos.

**Architecture:** Tablas propias; evaluador lee ventas (excluye emisión gift cards); nunca escribe `ventas` ni `comision_movimientos`. Dashboard solo lectura sobre datos ya calculados.

**Tech Stack:** Laravel (PHPUnit, Scheduler), Angular, Excel/PDF existentes.

**Spec:** `Docs/superpowers/specs/2026-07-27-comisiones-bonos-gift-cards-design.md`  
**Prereq:** Fase 0 slug `bonos-vendedores`; idealmente comisiones (Fase 1) y gift (Fase 2) para consolidado completo.

## Global Constraints

- Slug: `bonos-vendedores`.
- Bonos no son ventas ni generan comisión.
- Estados: `pendiente` → `aprobado` → `pagado` (sin saltar a pagado sin aprobar).
- Evaluación: job + botón manual; unique `(id_empresa, id_vendedor, id_regla, periodo_inicio, periodo_fin)`.
- Metas: excluir ventas de categoría Gift Cards (emisión); incluir ventas de productos canjeados.
- Jobs CLI: fijar `id_empresa` explícitamente (sin depender solo de Auth global scope).
- Total a pagar siempre desglosado (comisión vs bono).

## File map

| File | Responsibility |
|------|----------------|
| `Backend/database/migrations/2026_07_27_180000_create_bono_reglas_table.php` | Reglas |
| `Backend/database/migrations/2026_07_27_180001_create_bono_generados_table.php` | Instancias |
| `Backend/database/migrations/2026_07_27_180002_create_bono_evaluaciones_table.php` | Log corridas (opcional pero incluido) |
| `Backend/app/Models/Bonos/*` | Models |
| `Backend/app/Services/Bonos/BonoMetaCalculator.php` | Suma ventas del período por vendedor |
| `Backend/app/Services/Bonos/BonoReglaEvaluator.php` | Aplica tipo meta_fija / escalonado |
| `Backend/app/Services/Bonos/BonoEvaluationService.php` | Orquesta evaluación empresa/período |
| `Backend/app/Console/Commands/EvaluarBonosVendedoresCommand.php` | Artisan + schedule |
| `Backend/routes/modulos/bonos.php` | API |
| `Frontend/src/app/views/bonos/**` | Admin reglas + aprobación |
| `Frontend/src/app/views/vendedores-incentivos/**` | Dashboard consolidado (Fase 4) |

---

### Task 1: Migraciones y models

- [ ] **Step 1: `bono_reglas`**

```php
Schema::create('bono_reglas', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->string('nombre');
    $table->string('tipo', 32); // meta_fija|escalonado
    $table->string('ventana', 32)->default('mensual');
    $table->json('config'); // meta_fija: {meta: 40000, bono: 100}; escalonado: {tramos:[{meta, bono},...]}
    $table->boolean('activo')->default(true);
    $table->timestamps();
});
```

- [ ] **Step 2: `bono_generados`**

```php
Schema::create('bono_generados', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->unsignedBigInteger('id_vendedor');
    $table->unsignedBigInteger('id_regla');
    $table->date('periodo_inicio');
    $table->date('periodo_fin');
    $table->decimal('monto_ventas_base', 14, 4)->default(0);
    $table->decimal('monto', 14, 4);
    $table->string('estado', 20)->default('pendiente');
    $table->unsignedBigInteger('aprobado_por')->nullable();
    $table->timestamp('aprobado_at')->nullable();
    $table->timestamp('pagado_at')->nullable();
    $table->timestamps();
    $table->unique(
        ['id_empresa', 'id_vendedor', 'id_regla', 'periodo_inicio', 'periodo_fin'],
        'bono_generados_unique_eval'
    );
});
```

- [ ] **Step 3: `bono_evaluaciones`** — `id_empresa`, `periodo_inicio/fin`, `origen` (`job`|`manual`), `id_usuario`, `resumen` JSON, timestamps.

- [ ] **Step 4: Models + commit**

```bash
git commit -m "$(cat <<'EOF'
feat(bonos): add bonus rules and generated bonus tables

EOF
)"
```

---

### Task 2: Calculadora de meta y evaluator puro

**Files:**
- `BonoMetaCalculator.php`, `BonoReglaEvaluator.php`
- Tests unitarios

- [ ] **Step 1: Test meta_fija**

```php
public function test_meta_fija_alcanzada(): void
{
    $eval = new BonoReglaEvaluator();
    $monto = $eval->calcular('meta_fija', ['meta' => 40000, 'bono' => 100], 40000);
    $this->assertSame(100.0, $monto);
}

public function test_meta_fija_no_alcanzada(): void
{
    $eval = new BonoReglaEvaluator();
    $this->assertSame(0.0, $eval->calcular('meta_fija', ['meta' => 40000, 'bono' => 100], 39999));
}
```

- [ ] **Step 2: Test escalonado — gana el tramo más alto alcanzado**

```php
public function test_escalonado_elige_mayor_tramo(): void
{
    $config = ['tramos' => [
        ['meta' => 20000, 'bono' => 50],
        ['meta' => 40000, 'bono' => 100],
        ['meta' => 60000, 'bono' => 200],
    ]];
    $eval = new BonoReglaEvaluator();
    $this->assertSame(100.0, $eval->calcular('escalonado', $config, 45000));
}
```

- [ ] **Step 3: Implementar con `match` + `default => throw` / `never`**

```php
public function calcular(string $tipo, array $config, float $ventas): float
{
    return match ($tipo) {
        'meta_fija' => $ventas >= (float) $config['meta'] ? (float) $config['bono'] : 0.0,
        'escalonado' => $this->escalonado($config['tramos'] ?? [], $ventas),
        default => throw new \InvalidArgumentException("tipo bono desconocido: {$tipo}"),
    };
}
```

- [ ] **Step 4: `BonoMetaCalculator::ventasVendedorPeriodo`**

SQL/Eloquent: sumar líneas de ventas `Pagada` en rango; vendedor efectivo = `COALESCE(NULLIF(dv.id_vendedor,0), v.id_vendedor)`; excluir productos cuya `id_categoria` = categoría gift cards de la empresa (si existe en config gift-cards o comisiones).

- [ ] **Step 5: PHPUnit PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
feat(bonos): rule evaluator and seller sales base calculator

EOF
)"
```

---

### Task 3: EvaluationService + command + schedule

**Files:**
- `BonoEvaluationService.php`
- `EvaluarBonosVendedoresCommand.php`
- Register in `Kernel` / `routes/console.php` según versión Laravel del repo

- [ ] **Step 1: Test idempotencia — segunda evaluación actualiza monto si sigue `pendiente`, no toca si `aprobado`/`pagado`**

```php
public function test_no_modifica_bono_ya_aprobado(): void
{
    // arrange generated estado=aprobado monto=100
    // evaluate would yield 200
    // assert monto sigue 100
}
```

- [ ] **Step 2: Implementar**

```text
for each empresa with bonos-vendedores activo:
  for each regla activa:
    for each vendedor con ventas en período (o todos tipo vendedor):
      ventas = metaCalculator
      monto = evaluator
      if monto <= 0: skip (o upsert 0 — prefer skip)
      updateOrCreate unique key; if existing estado in (aprobado,pagado): leave
      else set pendiente + monto + monto_ventas_base
  log bono_evaluaciones
```

- [ ] **Step 3: Command**

```bash
php artisan bonos:evaluar {--empresa=} {--desde=} {--hasta=}
```

Schedule diario (o último día del mes — poner en `configuracion` JSON; default diario para progreso, generación formal fin de mes).

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(bonos): evaluation service, artisan command, and schedule

EOF
)"
```

---

### Task 4: API reglas + aprobación + calcular manual

**Files:**
- Controllers + `routes/modulos/bonos.php`
- Middleware `verificar.funcionalidad:bonos-vendedores`

- [ ] **Step 1: CRUD `bono_reglas`**

- [ ] **Step 2: `POST bonos/evaluar`** — body período; llama `BonoEvaluationService` origen=`manual`

- [ ] **Step 3: `POST bonos/generados/{id}/aprobar`** — solo desde `pendiente`; set `aprobado_por`

- [ ] **Step 4: `POST bonos/generados/{id}/pagar`** — solo desde `aprobado`

- [ ] **Step 5: Listados filtrables por estado/período/vendedor**

- [ ] **Step 6: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(bonos): API for rules, manual evaluate, and approval flow

EOF
)"
```

---

### Task 5: Frontend bonos

- Module `Frontend/src/app/views/bonos/` + `FuncionalidadGuard` slug `bonos-vendedores`
- Pantallas: reglas, listado generados (acciones aprobar/pagar), botón calcular período
- Commit FE

```bash
git commit -m "$(cat <<'EOF'
feat(bonos): Angular UI for rules and bonus approval

EOF
)"
```

---

### Task 6 (Fase 4): Dashboard consolidado + comprobante unificado

**Files:**
- `Backend/app/Http/Controllers/Api/Incentivos/VendedorIncentivosDashboardController.php`
- `Backend/app/Services/Incentivos/VendedorConsolidadoService.php`
- FE vista solo lectura
- Extender PDF comprobante para sección bonos (desglose)

**Interfaces — response shape:**

```json
{
  "id_vendedor": 1,
  "periodo": {"inicio": "2026-07-01", "fin": "2026-07-31"},
  "ventas_por_categoria": [{"id_categoria": 1, "nombre": "Muebles", "monto": 12000}],
  "comisiones": {
    "por_categoria": [],
    "por_redencion_gift": 15.5,
    "total": 200.0
  },
  "bonos": [{"id_regla": 1, "nombre": "Meta mes", "monto": 100, "estado": "pendiente"}],
  "redenciones_gift": [{"codigo": "GC...", "monto": 50}],
  "total_a_pagar": {
    "comisiones": 200.0,
    "bonos_aprobados_o_pagados": 0,
    "desglose": true
  },
  "progreso_bono": [{"regla": "Meta mes", "actual": 32000, "meta": 40000, "faltante": 8000}]
}
```

- [ ] **Step 1: Service ensambla solo si cada flag está on (secciones omitidas si off; histórico comisiones/bonos sí si hay filas)**

- [ ] **Step 2: FE dashboard listado vendedores**

- [ ] **Step 3: PDF comprobante — bloque comisión + bloque bono + firmas**

- [ ] **Step 4: Deprecar UI legacy subcategoría comisión** — ocultar o banner “usar Comisiones de Vendedores”; no borrar datos producto aún

- [ ] **Step 5: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(incentivos): consolidated seller dashboard and voucher breakdown

EOF
)"
```

---

### Task 7: Verificación Fase 3–4

- [ ] Regla meta_fija: bajo meta → no bono; en meta → pendiente.
- [ ] Recalcular no duplica; no pisa `aprobado`.
- [ ] Flujo aprobar → pagar enforced.
- [ ] Bonos no aparecen en exports de ventas/facturación.
- [ ] Dashboard muestra progreso y desglose; total nunca es un solo número opaco.
- [ ] Flags independientes: solo bonos on funciona sin comisiones/gift.

## Self-review

| Spec | Task |
|------|------|
| Reglas configurables | 1, 4, 5 |
| Job + manual idempotente | 3 |
| Estados + aprobación | 4 |
| No ventas / no comisión sobre bono | 2, 3 |
| Dashboard progreso | 6 |
| Consolidado desglosado | 6 |
| Deprecar legacy / fin workaround ficticio (doc) | 6 (+ nota en README módulo gift) |
