# Honduras Default Document Template Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que los documentos fiscales hondureños impriman un formato carta predeterminado cuando la empresa no tenga una plantilla especial.

**Architecture:** Un soporte Honduras concentra el catálogo imprimible, el correlativo y el cálculo fiscal; una única vista Blade consume esos datos. Las tres rutas vigentes de generación conservan primero sus excepciones por empresa y usan el default HN únicamente como fallback de país.

**Tech Stack:** Laravel/PHP, Eloquent, Blade, Dompdf, PHPUnit.

## Global Constraints

- No modificar `ventas.correlativo`; el formato fiscal es solo de presentación.
- El correlativo se genera con `FormatoCorrelativoHn::format`.
- Las plantillas especiales existentes por empresa tienen prioridad.
- No cambiar impresión de empresas de El Salvador o Costa Rica.
- El footer usa `documento.nota`, `documento.resolucion`, `documento.rangos` y `documento.fecha`; los valores vacíos se omiten.
- No agregar dependencias.
- No incluir los cambios locales de Excel ni `Frontend/src/manifest.webmanifest` en commits de esta tarea.

---

### Task 1: Soporte de impresión fiscal Honduras

**Files:**
- Create: `Backend/app/Support/Honduras/DocumentoImpresionHn.php`
- Create: `Backend/tests/Unit/Support/Honduras/DocumentoImpresionHnTest.php`

**Interfaces:**
- Consumes: `Empresa`, `Documento`, `FormatoCorrelativoHn`.
- Produces:
  - `DocumentoImpresionHn::aplica(Empresa $empresa, Documento $documento): bool`
  - `DocumentoImpresionHn::correlativo(Documento $documento, string|int|null $correlativo): string`
  - `DocumentoImpresionHn::totales(iterable $detalles, float $ivaEmpresa): array`
  - `DocumentoImpresionHn::footer(Documento $documento): array`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Support\Honduras;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Support\Honduras\DocumentoImpresionHn;
use PHPUnit\Framework\TestCase;

final class DocumentoImpresionHnTest extends TestCase
{
    public function test_aplica_a_los_nueve_documentos_fiscales_hn(): void
    {
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);
        foreach (DocumentoImpresionHn::NOMBRES_FISCALES as $nombre) {
            $this->assertTrue(DocumentoImpresionHn::aplica($empresa, new Documento(['nombre' => $nombre])));
        }
    }

    public function test_no_aplica_a_otro_pais_ni_documento_operativo(): void
    {
        $hn = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);
        $sv = new Empresa(['pais' => 'El Salvador', 'cod_pais' => 'SV']);
        $factura = new Documento(['nombre' => 'Factura sin RTN']);

        $this->assertFalse(DocumentoImpresionHn::aplica($hn, new Documento(['nombre' => 'Cotización'])));
        $this->assertFalse(DocumentoImpresionHn::aplica($sv, $factura));
    }

    public function test_formatea_correlativo_y_footer_sin_inventar_valores(): void
    {
        $documento = new Documento([
            'numero_emision' => '01',
            'nota' => "Línea 1\nLínea 2",
            'resolucion' => 'CAI-123',
            'rangos' => '001-001-01-00000001 A 001-001-01-00003000',
            'fecha' => '2027-05-23',
        ]);

        $this->assertSame('001-001-01-00000439', DocumentoImpresionHn::correlativo($documento, 439));
        $this->assertSame('CAI-123', DocumentoImpresionHn::footer($documento)['cai']);
        $this->assertNull(DocumentoImpresionHn::footer(new Documento())['cai']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd Backend && php artisan test --filter=DocumentoImpresionHnTest
```

Expected: FAIL because `DocumentoImpresionHn` does not exist.

- [ ] **Step 3: Implement the minimal support class**

```php
<?php

namespace App\Support\Honduras;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Carbon\Carbon;

final class DocumentoImpresionHn
{
    public const NOMBRES_FISCALES = [
        'Factura con RTN',
        'Factura sin RTN',
        'Ticket',
        'Boleta de compra',
        'Nota de crédito',
        'Nota de débito',
        'Recibo por honorarios profesionales',
        'Guía de remisión',
        'Comprobante de retención',
    ];

    public static function aplica(Empresa $empresa, Documento $documento): bool
    {
        return FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa)
                === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS
            && in_array($documento->nombre, self::NOMBRES_FISCALES, true);
    }

    public static function correlativo(Documento $documento, string|int|null $correlativo): string
    {
        return FormatoCorrelativoHn::format($documento->numero_emision, $correlativo);
    }

    public static function footer(Documento $documento): array
    {
        return [
            'nota' => trim((string) $documento->nota) ?: null,
            'cai' => trim((string) $documento->resolucion) ?: null,
            'rango' => trim((string) $documento->rangos) ?: null,
            'fecha_limite' => $documento->fecha ? Carbon::parse($documento->fecha)->format('d/m/Y') : null,
        ];
    }

    public static function totales(iterable $detalles, float $ivaEmpresa): array
    {
        $totales = [
            'exonerado' => 0.0,
            'exento' => 0.0,
            'gravado_15' => 0.0,
            'gravado_18' => 0.0,
            'isv_15' => 0.0,
            'isv_18' => 0.0,
            'descuento' => 0.0,
        ];

        foreach ($detalles as $detalle) {
            $tipo = (string) ($detalle->tipo_gravado ?? 'gravada');
            $tasa = (float) ($detalle->porcentaje_impuesto ?? $ivaEmpresa);
            $base = (float) ($detalle->gravada ?? $detalle->sub_total ?? $detalle->total ?? 0);
            $impuesto = (float) ($detalle->iva ?? 0);
            $totales['descuento'] += (float) ($detalle->descuento ?? 0);

            if ($tipo === 'exonerada') {
                $totales['exonerado'] += $base;
            } elseif ($tipo === 'exenta' || abs($tasa) < 0.01) {
                $totales['exento'] += (float) ($detalle->exenta ?? $base);
            } elseif (abs($tasa - 18) < 0.01) {
                $totales['gravado_18'] += $base;
                $totales['isv_18'] += $impuesto;
            } else {
                $totales['gravado_15'] += $base;
                $totales['isv_15'] += $impuesto;
            }
        }

        return $totales;
    }
}
```

- [ ] **Step 4: Extend the test with 15%, 18%, exempt and exonerated lines**

Use anonymous objects for four lines and assert every key returned by `totales`; blanks and absent optional properties must remain zero.

- [ ] **Step 5: Run focused tests**

Run:

```bash
cd Backend && php artisan test --filter='DocumentoImpresionHnTest|FormatoCorrelativoHnTest'
```

Expected: PASS.

---

### Task 2: Shared US Letter Blade template

**Files:**
- Create: `Backend/resources/views/reportes/facturacion/formatos_pais/default-honduras.blade.php`
- Test: `Backend/tests/Feature/Ventas/DefaultHondurasViewTest.php`

**Interfaces:**
- Consumes: `$venta`, `$empresa`, `$cliente`, `$documento`, `$dolares`, `$centavos`.
- Uses: `DocumentoImpresionHn::correlativo`, `::totales`, `::footer`.
- Produces: a renderable HTML document compatible with Dompdf.

- [ ] **Step 1: Write a failing render test**

Create a Laravel feature test that builds unsaved `Empresa`, `Documento`, `Venta`, `Cliente` and an Eloquent collection of anonymous-compatible detail models, then calls:

```php
$html = view(
    'reportes.facturacion.formatos_pais.default-honduras',
    compact('venta', 'empresa', 'cliente', 'documento', 'dolares', 'centavos')
)->render();

$this->assertStringContainsString('001-001-01-00000439', $html);
$this->assertStringContainsString('FACTURA SIN RTN', $html);
$this->assertStringContainsString('CAI-123', $html);
$this->assertStringContainsString('RANGO AUTORIZADO', $html);
```

Add a second case with `nota`, `resolucion`, `rangos` and `fecha` empty and assert no empty `CAI:` or `RANGO AUTORIZADO:` labels are rendered.

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd Backend && php artisan test --filter=DefaultHondurasViewTest
```

Expected: FAIL because the Blade view does not exist.

- [ ] **Step 3: Implement the template**

Build one semantic Blade view based on the supplied SANTRE reference and the existing Accesorios/Vilorio layouts:

- header with logo, company, RTN, address, phone and email;
- dynamic `strtoupper($documento->nombre)` title;
- formatted number from `DocumentoImpresionHn`;
- sale and customer information;
- products table;
- SAR totals from `DocumentoImpresionHn::totales`;
- total in letters followed by `LEMPIRAS`;
- optional order-exempt, exoneration and SAG references;
- conditional footer values from `DocumentoImpresionHn::footer`;
- fiscal and copy legends.

Use only inline/local CSS supported by Dompdf and `@page { size: letter portrait; }`. Escape configurable text and use `nl2br(e($footer['nota']))`.

- [ ] **Step 4: Run render and support tests**

Run:

```bash
cd Backend && php artisan test --filter='DefaultHondurasViewTest|DocumentoImpresionHnTest'
```

Expected: PASS.

---

### Task 3: Canonical print controller fallback

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/GenerarDocumentosController.php:104-431`
- Test: `Backend/tests/Unit/Support/Honduras/DocumentoImpresionHnTest.php`

**Interfaces:**
- Consumes: `DocumentoImpresionHn::aplica`.
- Produces: the same response types as today, with the HN default inserted after special-company selection and before the no-template response.

- [ ] **Step 1: Add a failing classification assertion**

Add assertions that `Factura con RTN`, `Factura sin RTN`, `Guía de remisión` and `Nota de débito` return true for HN and that legacy `Factura` is not included in the new fiscal fallback.

- [ ] **Step 2: Run the test and confirm failure if classification differs**

```bash
cd Backend && php artisan test --filter=DocumentoImpresionHnTest
```

- [ ] **Step 3: Wire the canonical controller**

Import `DocumentoImpresionHn`. Keep the existing Ticket/Recibo branch first. Expand the invoice/document branch to enter when:

```php
$documento->nombre === 'Factura'
    || $documento->nombre === DocumentosDefaultPorPais::CR_FACTURA
    || DocumentoImpresionHn::aplica($empresa, $documento)
```

Preserve every current company-specific branch. In the final fallback inside this branch select:

```php
if (DocumentoImpresionHn::aplica($empresa, $documento)) {
    $venta->loadMissing('detalles.producto', 'sucursal');
    $viewImpresion = 'reportes.facturacion.formatos_pais.default-honduras';
    $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento');
} else {
    $viewImpresion = 'reportes.facturacion.formatos_empresas.factura';
    $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento');
}
$configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
```

The long special-company chain must remain before this fallback.

- [ ] **Step 4: Run syntax and focused tests**

```bash
php -l Backend/app/Http/Controllers/Api/Ventas/GenerarDocumentosController.php
cd Backend && php artisan test --filter='DocumentoImpresionHnTest|DefaultHondurasViewTest'
```

Expected: no syntax errors; tests PASS.

---

### Task 4: Keep legacy and asynchronous print paths synchronized

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/VentasController.php:832-976`
- Modify: `Backend/app/Services/Ventas/DocumentoService.php:40-157`
- Test: `Backend/tests/Unit/Support/Honduras/DocumentoImpresionHnTest.php`

**Interfaces:**
- Consumes: the same support class and Blade view from Tasks 1–2.
- Produces: matching HN fallback behavior for legacy HTTP printing and queued PDF generation.

- [ ] **Step 1: Confirm current paths reject a new HN name**

Document the current branch predicates (`Factura` only in both files) in the test name or test comment, then run the focused test suite before modification.

- [ ] **Step 2: Update `VentasController`**

Import `DocumentoImpresionHn`, retain the Ticket/Recibo branch first, and expand the Factura branch with `DocumentoImpresionHn::aplica($empresa, $documento)`. Keep all company mappings first; in the final `else`, load `default-honduras` for HN and the existing generic `factura` for all others. Pass `$documento` and set US Letter portrait.

- [ ] **Step 3: Update `DocumentoService`**

Before the `switch`, route HN fiscal documents other than the already-specialized Ticket through:

```php
if (
    $documento->nombre !== 'Ticket'
    && DocumentoImpresionHn::aplica($empresa, $documento)
) {
    return $this->generarDocumentoHonduras($venta, $empresa, $documento);
}
```

Add `generarDocumentoHonduras` to load cliente and details, convert total to words, render `reportes.facturacion.formatos_pais.default-honduras`, set US Letter portrait, and stream the PDF. Do not alter existing service mappings for `Factura`, CR or SV.

- [ ] **Step 4: Run syntax checks**

```bash
php -l Backend/app/Http/Controllers/Api/Ventas/VentasController.php
php -l Backend/app/Services/Ventas/DocumentoService.php
```

Expected: no syntax errors.

- [ ] **Step 5: Run all focused Honduras tests**

```bash
cd Backend && php artisan test --filter='DocumentoImpresionHnTest|FormatoCorrelativoHnTest|DefaultHondurasViewTest|DocumentosDefaultPorPaisTest'
```

Expected: PASS.

---

### Task 5: Final verification

**Files:**
- Verify only; no new files.

- [ ] **Step 1: Check edited files for lint errors**

Read IDE diagnostics for the support class, Blade, controllers, service and tests. Fix only errors introduced by this task.

- [ ] **Step 2: Run the focused regression suite**

```bash
cd Backend && php artisan test --filter='Honduras|DocumentoImpresionHn|DocumentosDefaultPorPais'
```

Expected: all selected tests PASS.

- [ ] **Step 3: Render smoke check**

Use the feature render test as the runnable check. Verify that its HTML includes the dynamic title, formatted correlativo, 15%/18% totals and configured footer.

- [ ] **Step 4: Review the final diff**

```bash
git diff --check
git diff -- Backend/app/Support/Honduras/DocumentoImpresionHn.php Backend/resources/views/reportes/facturacion/formatos_pais/default-honduras.blade.php Backend/app/Http/Controllers/Api/Ventas/GenerarDocumentosController.php Backend/app/Http/Controllers/Api/Ventas/VentasController.php Backend/app/Services/Ventas/DocumentoService.php Backend/tests
```

Confirm that unrelated Excel files and `Frontend/src/manifest.webmanifest` are untouched by this implementation.

