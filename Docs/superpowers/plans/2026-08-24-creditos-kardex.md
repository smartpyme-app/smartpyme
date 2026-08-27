# SP-2150 Kardex créditos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El inventario de un crédito tipo bien sale solo en la cuota 1; servicio y préstamo nunca mueven stock; anular usa la misma regla.

**Architecture:** Función pura `KardexCredito::debeMoverInventario`. `FacturacionService` enciende `$saltarActualizarInventario` cuando es false. `VentasController` omite revertir/redescontar stock en anulación cuando es false. No se tocan las líneas del DTE ni caja.

**Tech Stack:** PHP 8 / Laravel, PHPUnit 11 (tests unitarios puros, sin DB).

## Global Constraints

- TDD: test que falle antes de cada cambio de producción.
- No gasto/caja automático. No desvincular cuota al anular. No UI nueva.
- No commit salvo que el usuario lo pida.
- Spec: `Docs/superpowers/specs/2026-08-24-creditos-kardex-design.md`

---

### Task 1: KardexCredito

**Files:**
- Create: `Backend/tests/Unit/Services/CreditosClientes/KardexCreditoTest.php`
- Create: `Backend/app/Services/CreditosClientes/KardexCredito.php`

**Interfaces:**
- Consumes: nada
- Produces: `KardexCredito::debeMoverInventario($tipo, $numeroCuota): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\KardexCredito;
use PHPUnit\Framework\TestCase;

class KardexCreditoTest extends TestCase
{
    public function test_bien_cuota_1_mueve_inventario(): void
    {
        $this->assertTrue(KardexCredito::debeMoverInventario('bien', 1));
    }

    public function test_bien_cuota_2_no_mueve_inventario(): void
    {
        $this->assertFalse(KardexCredito::debeMoverInventario('bien', 2));
    }

    public function test_servicio_nunca_mueve_inventario(): void
    {
        $this->assertFalse(KardexCredito::debeMoverInventario('servicio', 1));
    }

    public function test_prestamo_nunca_mueve_inventario(): void
    {
        $this->assertFalse(KardexCredito::debeMoverInventario('prestamo', 1));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Unit/Services/CreditosClientes/KardexCreditoTest.php --no-coverage`

Expected: FAIL class `KardexCredito` not found

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\CreditosClientes;

class KardexCredito
{
    public static function debeMoverInventario($tipo, $numeroCuota): bool
    {
        return $tipo === 'bien' && (int) $numeroCuota === 1;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Unit/Services/CreditosClientes/KardexCreditoTest.php --no-coverage`

Expected: PASS 4 tests

---

### Task 2: Skip al facturar

**Files:**
- Modify: `Backend/app/Services/Ventas/FacturacionService.php` (tras el bloque `$saltarActualizarInventario` de pedido canal, ~línea 133)

**Interfaces:**
- Consumes: `KardexCredito::debeMoverInventario`, `CreditoCuota` + `contrato`
- Produces: `$saltarActualizarInventario = true` cuando la cuota no debe mover stock

- [ ] **Step 1: Hook after pedido-canal skip**

Si `$request->filled('id_credito_cuota')`, cargar `CreditoCuota::with('contrato')->find(...)`. Si existe y `!KardexCredito::debeMoverInventario($cuota->contrato?->tipo, $cuota->numero)`, set `$saltarActualizarInventario = true`.

- [ ] **Step 2: php -l the file**

Run: `php -l app/Services/Ventas/FacturacionService.php`

Expected: No syntax errors

---

### Task 3: Skip al anular

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/VentasController.php` (ajuste de stocks al anular / cancelar anulación, ~472–576)

**Interfaces:**
- Consumes: cuota por `id_venta`, `KardexCredito::debeMoverInventario`
- Produces: no revertir ni redescontar inventario si la regla es false; abonos y paquetes siguen igual

- [ ] **Step 1: Resolver cuota y saltar solo inventario**

Antes del `foreach ($venta->detalles)`:

```php
$cuotaCredito = \App\Models\CreditosClientes\CreditoCuota::with('contrato')
    ->where('id_venta', $venta->id)
    ->first();
$saltarInventarioCredito = $cuotaCredito
    && !\App\Services\CreditosClientes\KardexCredito::debeMoverInventario(
        $cuotaCredito->contrato?->tipo,
        $cuotaCredito->numero
    );
```

Envolver el revert/redescuento de stock (producto + compuestos) en `if (!$saltarInventarioCredito)`. No envolver abonos ni paquetes.

- [ ] **Step 2: php -l**

Run: `php -l app/Http/Controllers/Api/Ventas/VentasController.php`

Expected: No syntax errors

- [ ] **Step 3: Run all créditos unit tests**

Run: `php vendor/bin/phpunit tests/Unit/Services/CreditosClientes --no-coverage`

Expected: all pass including the 4 new ones
