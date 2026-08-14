# Factura con/sin RTN + formato correlativo HN — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** En Honduras, oferecer Factura con RTN / Factura sin RTN, guardar `numero_emision` (01–20) en documentos fiscales, y mostrar el correlativo como `001-001-{NN}-{8 dígitos}` sin cambiar el valor numérico en BD.

**Architecture:** Columna `documentos.numero_emision` + helpers de formato FE/BE. Catálogo HN actualizado. Display en facturación, PDFs genéricos HN y libros HN vía helper. Prefijo fijo `001-001`.

**Tech Stack:** Laravel migration/request/model, Angular documentos + facturación, PHPUnit + Jasmine.

**Spec:** `Docs/superpowers/specs/2026-08-04-hn-factura-rtn-formato-design.md`

## Global Constraints

- Prefijo fijo `001-001` (no editable).
- `ventas.correlativo` sigue numérico.
- Sin migración automática de series `Factura` existentes.
- Campo/formato solo país HN.
- Operativos sin `numero_emision`: Cotización, Orden de compra, Recibo, Abono de Venta.
- SV/CR sin cambios de formato.
- No tocar `Frontend/src/environments/environment.ts` (cambio local del usuario).

## File map

| File | Role |
|------|------|
| `Backend/database/migrations/*_add_numero_emision_to_documentos_table.php` | Columna |
| `Backend/app/Models/Admin/Documento.php` | fillable |
| `Backend/app/Http/Requests/Admin/Documentos/StoreDocumentoRequest.php` | validación HN |
| `Backend/app/Support/Honduras/FormatoCorrelativoHn.php` | helper PHP |
| `Backend/tests/Unit/Support/Honduras/FormatoCorrelativoHnTest.php` | tests helper |
| `Backend/app/Support/Admin/DocumentosDefaultPorPais.php` | default Factura sin RTN |
| `Frontend/.../documento-nombre-options.ts` | catálogo + helpers fiscales/venta |
| `Frontend/.../documento-nombre-options.spec.ts` | tests |
| `Frontend/.../documentos.component.{ts,html}` | UI dropdown + preview |
| `Frontend/.../historial/*` | mismo campo si crea resolución |
| Facturación v1/v2 | display correlativo formateado |
| Libros HN exports | `factura_no` formateado |

---

### Task 1: Migración + model + helper PHP

**Files:**
- Create: `Backend/database/migrations/2026_08_04_100000_add_numero_emision_to_documentos_table.php`
- Modify: `Backend/app/Models/Admin/Documento.php`
- Create: `Backend/app/Support/Honduras/FormatoCorrelativoHn.php`
- Create: `Backend/tests/Unit/Support/Honduras/FormatoCorrelativoHnTest.php`

**Interfaces:**
- Produces: `Documento::$fillable` includes `numero_emision`
- Produces: `FormatoCorrelativoHn::format(?string $numeroEmision, string|int|null $correlativo): string`
  - If `$numeroEmision` null/empty → return `(string) $correlativo` (no inventar prefijo)
  - Else → `001-001-{pad2}-{pad8}`

- [ ] **Step 1: Write failing PHPUnit for helper**

```php
<?php
namespace Tests\Unit\Support\Honduras;

use App\Support\Honduras\FormatoCorrelativoHn;
use PHPUnit\Framework\TestCase;

final class FormatoCorrelativoHnTest extends TestCase
{
    public function test_format_with_emision(): void
    {
        $this->assertSame('001-001-01-00000439', FormatoCorrelativoHn::format('01', 439));
        $this->assertSame('001-001-20-00000001', FormatoCorrelativoHn::format('20', 1));
    }

    public function test_format_without_emision_returns_raw(): void
    {
        $this->assertSame('439', FormatoCorrelativoHn::format(null, 439));
        $this->assertSame('439', FormatoCorrelativoHn::format('', '439'));
    }
}
```

- [ ] **Step 2: Run → expect FAIL (class missing)**

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Support/Honduras/FormatoCorrelativoHnTest.php
```

- [ ] **Step 3: Implement helper + migration + fillable**

Migration:

```php
Schema::table('documentos', function (Blueprint $table) {
    $table->char('numero_emision', 2)->nullable()->after('correlativo');
});
```

Helper:

```php
final class FormatoCorrelativoHn
{
    public static function format(?string $numeroEmision, string|int|null $correlativo): string
    {
        $corr = (string) ($correlativo ?? '');
        $em = trim((string) ($numeroEmision ?? ''));
        if ($em === '') {
            return $corr;
        }
        $nn = str_pad(preg_replace('/\D/', '', $em) ?: '0', 2, '0', STR_PAD_LEFT);
        $digits = preg_replace('/\D/', '', $corr) ?: '0';
        return '001-001-' . $nn . '-' . str_pad($digits, 8, '0', STR_PAD_LEFT);
    }
}
```

Add `'numero_emision'` to `$fillable`.

- [ ] **Step 4: Run PHPUnit → PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: columna numero_emision y helper formato correlativo HN"
```

---

### Task 2: Validación backend StoreDocumentoRequest + defaults HN

**Files:**
- Modify: `Backend/app/Http/Requests/Admin/Documentos/StoreDocumentoRequest.php`
- Modify: `Backend/app/Support/Admin/DocumentosDefaultPorPais.php`
- Modify: `Backend/tests/Unit/Support/Admin/DocumentosDefaultPorPaisTest.php`

**Interfaces:**
- Consumes: `FacturacionElectronicaCountryResolver`, auth empresa
- Rule: if empresa HN and nombre is fiscal → `numero_emision` required, `Rule::in(['01',…,'20'])`
- Defaults: replace `Factura` with `Factura sin RTN`

Fiscal names (mirror FE):

```
Factura con RTN, Factura sin RTN, Ticket, Boleta de compra,
Nota de crédito, Nota de débito, Recibo por honorarios profesionales,
Guía de remisión, Comprobante de retención
```

- [ ] **Step 1: Update DocumentosDefaultPorPaisTest** to expect `Factura sin RTN` instead of `Factura` for HN — run FAIL.

- [ ] **Step 2: Change HN defaults array to use `Factura sin RTN`.**

- [ ] **Step 3: In `StoreDocumentoRequest::rules()`, load empresa by `id_empresa`; if HN and nombre in fiscal list, require `numero_emision` in `01`–`20`; else `nullable`. Always allow `numero_emision` as nullable string for non-HN.**

- [ ] **Step 4: PHPUnit defaults → PASS. Manual/optional FormRequest test not required.**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: validar numero_emision HN y default Factura sin RTN"
```

---

### Task 3: Catálogo FE Factura con/sin RTN + helper formato FE

**Files:**
- Modify: `Frontend/src/app/views/ventas/documentos/documento-nombre-options.ts`
- Modify: `Frontend/src/app/views/ventas/documentos/documento-nombre-options.spec.ts`

**Interfaces:**
- `NOMBRE_DOCUMENTO_HN.facturaConRtn = 'Factura con RTN'`
- `NOMBRE_DOCUMENTO_HN.facturaSinRtn = 'Factura sin RTN'`
- Remove single `factura: 'Factura'` (or keep alias only if something still needs it — prefer remove and update all HN references)
- Update `DOCUMENTO_NOMBRE_OPCIONES_HN`, `nombresDocumentosVentaNormales` (both facturas), compra whitelist (both facturas + existing)
- `esDocumentoFiscalHn(nombre): boolean`
- `NUMERO_EMISION_OPCIONES_HN = ['01'..'20']`
- `formatoCorrelativoHn(numeroEmision, correlativo): string` — same rules as PHP

- [ ] **Step 1: Update/extend specs** — assert options include con/sin RTN, exclude bare `Factura`; assert `formatoCorrelativoHn('01', 439) === '001-001-01-00000439'`; venta whitelist includes both facturas. Run RED if needed.

- [ ] **Step 2: Implement catalog + helpers.**

- [ ] **Step 3: Specs GREEN (isolated tsconfig if Karma full suite broken).**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: catálogo HN Factura con/sin RTN y formato FE"
```

---

### Task 4: UI Admin Documentos (crear/editar + historial)

**Files:**
- Modify: `Frontend/src/app/views/ventas/documentos/documentos.component.ts`
- Modify: `Frontend/src/app/views/ventas/documentos/documentos.component.html`
- Modify: historial component html/ts if it clones the form

**Behavior:**
- `esHonduras` via `resolveCodigoPaisFe === FE_PAIS_HN`
- Show select `numero_emision` (01–20) when HN and `esDocumentoFiscalHn(documento.nombre)`
- Required on save client-side when visible
- Preview text: `formatoCorrelativoHn(documento.numero_emision, documento.correlativo)`
- On open create: default `numero_emision = '01'` for fiscal HN
- List table: optional column “Nº emisión” for HN only — skip if noisy; preview in modal is enough (YAGNI: no new list column)

- [ ] **Step 1: Wire TS getters + opciones.**

- [ ] **Step 2: Add form controls in `#mdetalle` and `#mresolucion` / historial.**

- [ ] **Step 3: Manual smoke: create Factura con RTN with 01, see preview.**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: dropdown numero_emision en documentos Honduras"
```

---

### Task 5: Display en facturación + libros HN

**Files:**
- Modify: facturacion-tienda v1/v2 (where `venta.correlativo` shown)
- Modify: `Backend/app/Exports/Contabilidad/Honduras/LibroConsumidoresExport.php` (and Contribuyentes/Ventas if they output factura_no)
- Prefer one Blade-friendly use: only where a shared partial exists; else call helper in export classes. Do **not** rewrite all empresa-specific PDF hardcodes (Accesorios/Lilian) unless a single shared ticket template is clearly shared — YAGNI: libros + UI first; if `GenerarDocumentosController` has a central place for HN display, hook there.

**Behavior:**
- UI: if HN and selected documento has `numero_emision`, display formatted string in the correlativo label; **do not** write formatted value into `venta.correlativo` before save.
- Libros: `FormatoCorrelativoHn::format($documento->numero_emision ?? null, $venta->correlativo)`.

- [ ] **Step 1: Locate correlativo display bindings in v1/v2; add getter `correlativoDisplay`.**

- [ ] **Step 2: Wire libros exports.**

- [ ] **Step 3: Smoke / confirm save still increments numeric correlativo.**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: mostrar correlativo formato HN en ventas y libros"
```

---

## Self-review

| Spec item | Task |
|-----------|------|
| Factura con/sin RTN catalog | 3 |
| Defaults Factura sin RTN | 2 |
| Columna numero_emision | 1 |
| Validación request | 2 |
| UI dropdown + preview | 4 |
| Helper FE/BE | 1, 3 |
| Display only (no store formatted) | 5 |
| Libros | 5 |
| Fiscal vs operativo | 2, 3, 4 |

Placeholder scan: none.

---

## Execution handoff

Plan saved to `Docs/superpowers/plans/2026-08-04-hn-factura-rtn-formato.md`.

**1. Subagent-Driven (recommended)**  
**2. Inline Execution**  

Which approach?
