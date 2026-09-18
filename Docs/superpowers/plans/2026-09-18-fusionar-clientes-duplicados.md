# Fusionar clientes duplicados Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Comando Artisan que, en una empresa, fusiona clientes inhabilitados hacia el habilitado con el mismo NIT o DUI y luego los borra.

**Architecture:** Un service puro para llave/clasificación (testeable sin DB) y la reasignación/delete en transacción con `DB::table`. El command solo parsea flags e imprime.

**Tech Stack:** Laravel Artisan, PHPUnit (tests/Unit, sin RefreshDatabase).

## Global Constraints

- `--empresa` obligatorio; no hardcodear 799.
- Dry-run por defecto; escribir solo con `--ejecutar`.
- Emparejar solo NIT/DUI normalizado (sin espacios/guiones, mayúsculas).
- Fusionar solo si hay exactamente 1 habilitado y ≥1 inhabilitado.
- Reasignar con `DB::table`; no disparar observers de venta.
- Hard delete del origen. No tocar `empresas.id_cliente`.
- Un par falla → rollback de ese par, seguir.
- Spec: `Docs/superpowers/specs/2026-09-18-fusionar-clientes-duplicados-design.md`

---

### Task 1: Clasificación y normalización (unit tests + service)

**Files:**
- Create: `Backend/tests/Unit/Services/Ventas/FusionarClientesDuplicadosServiceTest.php`
- Create: `Backend/app/Services/Ventas/FusionarClientesDuplicadosService.php`

**Interfaces:**
- Produces: `normalizarDocumento(?string): string`, `claveGrupo(?string $nit, ?string $dui): ?string`, `clasificarGrupo(array $clientes): array`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\FusionarClientesDuplicadosService;
use PHPUnit\Framework\TestCase;

class FusionarClientesDuplicadosServiceTest extends TestCase
{
    public function test_normaliza_nit_sin_guiones_ni_espacios(): void
    {
        $this->assertSame('06141234560010', FusionarClientesDuplicadosService::normalizarDocumento('0614-123456-001-0'));
        $this->assertSame('012345678', FusionarClientesDuplicadosService::normalizarDocumento('01234567-8'));
        $this->assertSame('', FusionarClientesDuplicadosService::normalizarDocumento('  '));
        $this->assertSame('', FusionarClientesDuplicadosService::normalizarDocumento(null));
    }

    public function test_clave_usa_nit_si_hay_si_no_dui(): void
    {
        $this->assertSame('nit:06141234560010', FusionarClientesDuplicadosService::claveGrupo('0614-123456-001-0', '01234567-8'));
        $this->assertSame('dui:012345678', FusionarClientesDuplicadosService::claveGrupo(null, '01234567-8'));
        $this->assertNull(FusionarClientesDuplicadosService::claveGrupo('', ''));
    }

    public function test_un_habilitado_y_un_inhabilitado_fusiona(): void
    {
        $r = FusionarClientesDuplicadosService::clasificarGrupo([
            ['id' => 10, 'enable' => true],
            ['id' => 11, 'enable' => false],
        ]);
        $this->assertSame('fusionar', $r['accion']);
        $this->assertSame(10, $r['destino']);
        $this->assertSame([11], $r['origenes']);
    }

    public function test_dos_habilitados_salta(): void
    {
        $r = FusionarClientesDuplicadosService::clasificarGrupo([
            ['id' => 10, 'enable' => true],
            ['id' => 12, 'enable' => 1],
            ['id' => 11, 'enable' => false],
        ]);
        $this->assertSame('saltar', $r['accion']);
    }

    public function test_solo_inhabilitados_salta(): void
    {
        $r = FusionarClientesDuplicadosService::clasificarGrupo([
            ['id' => 11, 'enable' => 0],
        ]);
        $this->assertSame('saltar', $r['accion']);
        $this->assertNull($r['destino']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Ventas/FusionarClientesDuplicadosServiceTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Write minimal implementation**

Service with the three static methods and the table constants from the spec. `clasificarGrupo` treats `enable` truthy as habilitado.

- [ ] **Step 4: Run test to verify it passes**

Same phpunit command. Expected: PASS (5 tests).

---

### Task 2: Reasignar + comando

**Files:**
- Modify: `Backend/app/Services/Ventas/FusionarClientesDuplicadosService.php`
- Create: `Backend/app/Console/Commands/FusionarClientesDuplicadosCommand.php`
- Modify: `Backend/tests/Unit/Services/Ventas/FusionarClientesDuplicadosServiceTest.php` (constantes de tablas)

**Interfaces:**
- Consumes: métodos de Task 1
- Produces: `planear(int $empresaId): array`, `ejecutarPlan(array $plan, bool $ejecutar): array`
- Command: `clientes:fusionar-duplicados {--empresa=} {--ejecutar}`

- [ ] **Step 1: Add test that table lists match the spec**

Assert `TABLAS_REASIGNAR` contains `ventas` and `devoluciones_venta`, and does not contain `empresas`. Assert `TABLAS_SNAPSHOT` contains `cliente_ventas_mensuales`.

- [ ] **Step 2: Implement `planear` / `ejecutarPlan` and the command**

`planear`: carga `Cliente::withoutGlobalScope('empresa')->where('id_empresa', $empresaId)`, agrupa por `claveGrupo`, clasifica, cuenta ventas del origen con `DB::table('ventas')->where('id_cliente', $id)->count()`.

`ejecutarPlan`: si `$ejecutar` es false, no escribe. Si true, por cada par fusionable `DB::transaction`: reasignar tablas existentes, merge `puntos_cliente`, borrar snapshots, verificar que no queden filas, delete cliente. Catch → marcar error, no tumbar el resto.

Command: sin `--empresa` → error y exit 1. Imprime tabla y resumen.

- [ ] **Step 3: Run unit tests**

`cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Ventas/FusionarClientesDuplicadosServiceTest.php`

Expected: PASS.

- [ ] **Step 4: `graphify update .`**
