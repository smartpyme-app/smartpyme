# Tipos de documento fiscal por país (SV / HN / CR) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el catálogo y los filtros de tipos de documento fiscal dependan del país de la empresa (SV / HN / CR), agregando el catálogo SAR de Honduras sin alterar SV ni CR.

**Architecture:** Extender el patrón ya usado por Costa Rica: listas canónicas en `documento-nombre-options.ts`, defaults en `DocumentosDefaultPorPais.php`, resolución de país con `resolveCodigoPaisFe()` / `FacturacionElectronicaCountryResolver`. Whitelists de ventas y compras se centralizan en helpers del mismo archivo TS para no duplicar entre facturación v1/v2.

**Tech Stack:** Angular 20 (Frontend), Laravel/PHPUnit (Backend), strings en `documentos.nombre` / `tipo_documento`.

**Spec:** `Docs/superpowers/specs/2026-08-04-tipos-documento-por-pais-design.md`

## Global Constraints

- Sin migración de series históricas.
- Sin tabla nueva ni endpoint de catálogo.
- CR y SV: catálogos sin cambios de nombres.
- HN: sin Crédito fiscal, Sujeto excluido, Factura de exportación, Factura comercial en dropdowns/crear.
- Con/sin RTN = una sola `Factura`.
- Otros países / desconocido: lista SV (default actual).
- Commit frecuente por tarea; no tocar vendor ni `.env`.

## File map

| File | Responsibility |
|------|----------------|
| `Frontend/src/app/services/facturacion-electronica/fe-pais.util.ts` | Exportar `FE_PAIS_HN` |
| `Frontend/src/app/views/ventas/documentos/documento-nombre-options.ts` | Catálogos SV/HN/CR + helpers whitelist venta/compra |
| `Frontend/src/app/views/ventas/documentos/documento-nombre-options.spec.ts` | Tests unitarios del catálogo |
| `Backend/app/Services/FacturacionElectronica/FacturacionElectronicaCountryResolver.php` | Constante `CODIGO_HONDURAS` |
| `Backend/app/Support/Admin/DocumentosDefaultPorPais.php` | Defaults al crear empresa/sucursal HN |
| `Backend/tests/Unit/Support/Admin/DocumentosDefaultPorPaisTest.php` | Tests defaults |
| `Frontend/.../facturacion-tienda/facturacion.component.ts` | Usar helper venta |
| `Frontend/.../facturacion-tienda-v2/facturacion-v2.component.ts` | Usar helper venta |
| `Frontend/.../facturacion/facturacion-compra.component.ts` | Usar helper compra (rama HN) |
| `Frontend/.../compras/gastos/gasto/gasto.component.ts` | Excluir tipos SV-only en HN |

---

### Task 1: Constante `FE_PAIS_HN` + catálogo HN + helpers de whitelist

**Files:**
- Modify: `Frontend/src/app/services/facturacion-electronica/fe-pais.util.ts`
- Modify: `Frontend/src/app/views/ventas/documentos/documento-nombre-options.ts`
- Create: `Frontend/src/app/views/ventas/documentos/documento-nombre-options.spec.ts`

**Interfaces:**
- Consumes: `resolveCodigoPaisFe`, `FE_PAIS_CR`, `FE_PAIS_SV` from `fe-pais.util.ts`
- Produces:
  - `FE_PAIS_HN: 'HN'`
  - `NOMBRE_DOCUMENTO_HN` (const object)
  - `DOCUMENTO_NOMBRE_OPCIONES_HN: DocumentoNombreOption[]`
  - `documentoNombreOpciones(empresa): DocumentoNombreOption[]` — ramas CR / HN / DEFAULT(SV)
  - `nombresDocumentosVentaNormales(empresa): string[]`
  - `nombresDocumentosCompraPermitidos(empresa): string[]`
  - `nombresDocumentoExcluidosGastoHn(): string[]` (tipos SV que no deben aparecer en gasto HN)

- [ ] **Step 1: Write the failing test**

Create `Frontend/src/app/views/ventas/documentos/documento-nombre-options.spec.ts`:

```typescript
import {
  DOCUMENTO_NOMBRE_OPCIONES_CR,
  DOCUMENTO_NOMBRE_OPCIONES_DEFAULT,
  DOCUMENTO_NOMBRE_OPCIONES_HN,
  NOMBRE_DOCUMENTO_HN,
  documentoNombreOpciones,
  nombresDocumentosCompraPermitidos,
  nombresDocumentosVentaNormales,
} from './documento-nombre-options';

describe('documentoNombreOpciones', () => {
  it('devuelve CR para Costa Rica', () => {
    expect(documentoNombreOpciones({ pais: 'Costa Rica' })).toEqual(DOCUMENTO_NOMBRE_OPCIONES_CR);
  });

  it('devuelve HN para Honduras', () => {
    expect(documentoNombreOpciones({ pais: 'Honduras', cod_pais: 'HN' })).toEqual(
      DOCUMENTO_NOMBRE_OPCIONES_HN
    );
  });

  it('devuelve default SV para El Salvador', () => {
    expect(documentoNombreOpciones({ pais: 'El Salvador' })).toEqual(DOCUMENTO_NOMBRE_OPCIONES_DEFAULT);
  });

  it('HN no incluye Crédito fiscal ni Sujeto excluido', () => {
    const values = DOCUMENTO_NOMBRE_OPCIONES_HN.map((o) => o.value);
    expect(values).not.toContain('Crédito fiscal');
    expect(values).not.toContain('Sujeto excluido');
    expect(values).not.toContain('Factura de exportación');
    expect(values).not.toContain('Factura comercial');
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.boletaCompra);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.reciboHonorarios);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.guiaRemision);
    expect(values).toContain(NOMBRE_DOCUMENTO_HN.comprobanteRetencion);
  });

  it('venta HN incluye Factura/Ticket/Recibo/Guía/Abono y no Crédito fiscal', () => {
    const names = nombresDocumentosVentaNormales({ pais: 'Honduras' });
    expect(names).toContain('Factura');
    expect(names).toContain('Ticket');
    expect(names).toContain('Recibo');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.guiaRemision);
    expect(names).toContain('Abono de Venta');
    expect(names).not.toContain('Crédito fiscal');
  });

  it('compra HN incluye boleta/honorarios/retención y no Crédito fiscal', () => {
    const names = nombresDocumentosCompraPermitidos({ pais: 'Honduras' });
    expect(names).toContain('Factura');
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.boletaCompra);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.reciboHonorarios);
    expect(names).toContain(NOMBRE_DOCUMENTO_HN.comprobanteRetencion);
    expect(names).not.toContain('Crédito fiscal');
  });

  it('compra SV sigue incluyendo Crédito fiscal', () => {
    expect(nombresDocumentosCompraPermitidos({ pais: 'El Salvador' })).toContain('Crédito fiscal');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd Frontend && npx ng test --include=src/app/views/ventas/documentos/documento-nombre-options.spec.ts --browsers=ChromeHeadless --watch=false
```

Expected: FAIL (helpers / lista HN no existen).

- [ ] **Step 3: Export `FE_PAIS_HN`**

In `fe-pais.util.ts`, after `FE_PAIS_CR`:

```typescript
export const FE_PAIS_HN = 'HN';
```

(`resolveCodigoPaisFe` ya devuelve `'HN'` para Honduras; no cambia lógica.)

- [ ] **Step 4: Implement catálogo HN + branch + helpers**

In `documento-nombre-options.ts`:

1. Import `FE_PAIS_HN`, `FE_PAIS_SV` besides `FE_PAIS_CR`.
2. Add:

```typescript
export const NOMBRE_DOCUMENTO_HN = {
  factura: 'Factura',
  ticket: 'Ticket',
  boletaCompra: 'Boleta de compra',
  notaCredito: 'Nota de crédito',
  notaDebito: 'Nota de débito',
  reciboHonorarios: 'Recibo por honorarios profesionales',
  guiaRemision: 'Guía de remisión',
  comprobanteRetencion: 'Comprobante de retención',
} as const;

export const DOCUMENTO_NOMBRE_OPCIONES_HN: DocumentoNombreOption[] = [
  { value: NOMBRE_DOCUMENTO_HN.factura, label: NOMBRE_DOCUMENTO_HN.factura },
  { value: NOMBRE_DOCUMENTO_HN.ticket, label: NOMBRE_DOCUMENTO_HN.ticket },
  { value: NOMBRE_DOCUMENTO_HN.boletaCompra, label: NOMBRE_DOCUMENTO_HN.boletaCompra },
  { value: NOMBRE_DOCUMENTO_HN.notaCredito, label: NOMBRE_DOCUMENTO_HN.notaCredito },
  { value: NOMBRE_DOCUMENTO_HN.notaDebito, label: NOMBRE_DOCUMENTO_HN.notaDebito },
  { value: NOMBRE_DOCUMENTO_HN.reciboHonorarios, label: NOMBRE_DOCUMENTO_HN.reciboHonorarios },
  { value: NOMBRE_DOCUMENTO_HN.guiaRemision, label: NOMBRE_DOCUMENTO_HN.guiaRemision },
  { value: NOMBRE_DOCUMENTO_HN.comprobanteRetencion, label: NOMBRE_DOCUMENTO_HN.comprobanteRetencion },
  { value: 'Cotización', label: 'Cotización' },
  { value: 'Orden de compra', label: 'Orden de compra' },
  { value: 'Recibo', label: 'Recibo' },
  { value: 'Abono de Venta', label: 'Abono de Venta' },
];
```

3. Replace `documentoNombreOpciones`:

```typescript
export function documentoNombreOpciones(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): DocumentoNombreOption[] {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_CR) {
    return DOCUMENTO_NOMBRE_OPCIONES_CR;
  }
  if (cod === FE_PAIS_HN) {
    return DOCUMENTO_NOMBRE_OPCIONES_HN;
  }
  return DOCUMENTO_NOMBRE_OPCIONES_DEFAULT;
}
```

4. Add sale/purchase helpers (SV list = comportamiento actual de los arrays inline; CR mantiene extras actuales; HN = spec):

```typescript
export function nombresDocumentosVentaNormales(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string[] {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_HN) {
    return [
      'Factura',
      'Ticket',
      'Recibo',
      NOMBRE_DOCUMENTO_HN.guiaRemision,
      'Abono de Venta',
    ];
  }
  // SV / CR / otros: lista actual de facturacion.component.ts
  return [
    'Factura',
    'Crédito fiscal',
    'Factura de exportación',
    'Factura comercial',
    'Ticket',
    'Recibo',
    'Sujeto excluido',
    NOMBRE_DOCUMENTO_CR.factura,
    NOMBRE_DOCUMENTO_CR.tiquete,
    'Abono de Venta',
  ];
}

export function nombresDocumentosCompraPermitidos(
  empresa: { cod_pais?: string | null; pais?: string | null } | null | undefined
): string[] {
  const cod = resolveCodigoPaisFe(empresa);
  if (cod === FE_PAIS_HN) {
    return [
      'Factura',
      'Ticket',
      'Recibo',
      NOMBRE_DOCUMENTO_HN.boletaCompra,
      NOMBRE_DOCUMENTO_HN.reciboHonorarios,
      NOMBRE_DOCUMENTO_HN.comprobanteRetencion,
    ];
  }
  const base = [
    'Factura',
    'Crédito fiscal',
    'Ticket',
    'Recibo',
    'Sujeto excluido',
    'Factura de exportación',
    'Factura de remisión',
    'Documento contable de liquidación',
  ];
  if (cod === FE_PAIS_CR) {
    return [
      ...base,
      NOMBRE_DOCUMENTO_CR.factura,
      NOMBRE_DOCUMENTO_CR.tiquete,
      NOMBRE_DOCUMENTO_CR.fecCompra,
      'Compra electrónica',
    ];
  }
  return base;
}

/** Tipos SV que no deben ofrecerse en gastos de empresas HN. */
export function nombresDocumentoExcluidosGastoHn(): string[] {
  return [
    'Crédito fiscal',
    'Sujeto excluido',
    'Factura de exportación',
    'Factura comercial',
  ];
}
```

Note: Admin / historial already call `documentoNombreOpciones()` — no change needed there once Task 1 lands.

- [ ] **Step 5: Run tests to verify they pass**

Same `ng test --include=...` command. Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add Frontend/src/app/services/facturacion-electronica/fe-pais.util.ts \
  Frontend/src/app/views/ventas/documentos/documento-nombre-options.ts \
  Frontend/src/app/views/ventas/documentos/documento-nombre-options.spec.ts
git commit -m "$(cat <<'EOF'
feat: catálogo de documentos fiscales por país (HN)

EOF
)"
```

---

### Task 2: Defaults backend Honduras

**Files:**
- Modify: `Backend/app/Services/FacturacionElectronica/FacturacionElectronicaCountryResolver.php`
- Modify: `Backend/app/Support/Admin/DocumentosDefaultPorPais.php`
- Create: `Backend/tests/Unit/Support/Admin/DocumentosDefaultPorPaisTest.php`

**Interfaces:**
- Consumes: `FacturacionElectronicaCountryResolver::resolveCodigoPaisFe`
- Produces: `CODIGO_HONDURAS = 'HN'`; `DocumentosDefaultPorPais::nombres()` returns HN defaults without Crédito fiscal

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Support\Admin;

use App\Models\Admin\Empresa;
use App\Support\Admin\DocumentosDefaultPorPais;
use PHPUnit\Framework\TestCase;

final class DocumentosDefaultPorPaisTest extends TestCase
{
    public function test_defaults_honduras_sin_credito_fiscal(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'Honduras';
        $empresa->cod_pais = 'HN';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertSame(
            ['Ticket', 'Factura', 'Cotización', 'Orden de compra'],
            $nombres
        );
        $this->assertNotContains('Crédito fiscal', $nombres);
    }

    public function test_defaults_el_salvador_incluye_credito_fiscal(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'El Salvador';
        $empresa->cod_pais = 'SV';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertContains('Crédito fiscal', $nombres);
        $this->assertContains('Factura', $nombres);
    }

    public function test_defaults_costa_rica_electronicos(): void
    {
        $empresa = new Empresa();
        $empresa->pais = 'Costa Rica';
        $empresa->cod_pais = 'CR';

        $nombres = DocumentosDefaultPorPais::nombres($empresa);

        $this->assertContains(DocumentosDefaultPorPais::CR_TIQUETE, $nombres);
        $this->assertContains(DocumentosDefaultPorPais::CR_FACTURA, $nombres);
        $this->assertNotContains('Crédito fiscal', $nombres);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd Backend && ./vendor/bin/phpunit tests/Unit/Support/Admin/DocumentosDefaultPorPaisTest.php
```

Expected: FAIL on Honduras (hoy cae en default SV con Crédito fiscal).

- [ ] **Step 3: Minimal implementation**

In `FacturacionElectronicaCountryResolver.php` add:

```php
public const CODIGO_HONDURAS = 'HN';
```

(opcional: usar la constante en `codigoFromNombrePais` donde hoy retorna `'HN'`).

In `DocumentosDefaultPorPais::nombres()`:

```php
public static function nombres(?Empresa $empresa): array
{
    $cod = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);

    if ($cod === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
        return [
            self::CR_TIQUETE,
            self::CR_FACTURA,
            config('constants.TIPO_DOCUMENTO_COTIZACION', 'Cotización'),
            config('constants.TIPO_DOCUMENTO_ORDEN_COMPRA', 'Orden de compra'),
        ];
    }

    if ($cod === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS) {
        return [
            config('constants.TIPO_DOCUMENTO_TICKET', 'Ticket'),
            config('constants.TIPO_DOCUMENTO_FACTURA', 'Factura'),
            config('constants.TIPO_DOCUMENTO_COTIZACION', 'Cotización'),
            config('constants.TIPO_DOCUMENTO_ORDEN_COMPRA', 'Orden de compra'),
        ];
    }

    return [
        config('constants.TIPO_DOCUMENTO_TICKET', 'Ticket'),
        config('constants.TIPO_DOCUMENTO_FACTURA', 'Factura'),
        config('constants.TIPO_DOCUMENTO_CREDITO_FISCAL', 'Crédito fiscal'),
        config('constants.TIPO_DOCUMENTO_COTIZACION', 'Cotización'),
        config('constants.TIPO_DOCUMENTO_ORDEN_COMPRA', 'Orden de compra'),
    ];
}
```

- [ ] **Step 4: Run tests to verify they pass**

Same phpunit command. Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/FacturacionElectronica/FacturacionElectronicaCountryResolver.php \
  Backend/app/Support/Admin/DocumentosDefaultPorPais.php \
  Backend/tests/Unit/Support/Admin/DocumentosDefaultPorPaisTest.php
git commit -m "$(cat <<'EOF'
feat: defaults de documentos al crear empresa Honduras

EOF
)"
```

---

### Task 3: Wire whitelists en facturación ventas (v1 + v2)

**Files:**
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts`
- Modify (solo si rompen por acceso a la propiedad privada):  
  `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.spec.ts`  
  `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.spec.ts`

**Interfaces:**
- Consumes: `nombresDocumentosVentaNormales(empresa)` from Task 1
- Produces: filtro de documentos de venta según país de `auth_user().empresa`

- [ ] **Step 1: Replace static array in v1**

Remove the field:

```typescript
private readonly nombresDocumentosVentaNormales = [ ... ];
```

Import `nombresDocumentosVentaNormales` from `documento-nombre-options` (alias if needed to avoid clash):

```typescript
import {
  NOMBRE_DOCUMENTO_CR,
  nombresDocumentosVentaNormales as nombresVentaPorPais,
} from '../../documentos/documento-nombre-options';
// adjust relative path to match each file
```

Where the filter uses `this.nombresDocumentosVentaNormales.includes(...)`, change to:

```typescript
nombresVentaPorPais(this.apiService.auth_user()?.empresa).includes(String(doc.nombre || '').trim())
```

Search the component for every `nombresDocumentosVentaNormales` reference (filter ~line 640 in v1) and update. Spec files that assign `component.nombresDocumentosVentaNormales = [...]` must switch to stubbing empresa país or testing the helper (prefer leave those specs assigning nothing and stub `auth_user().empresa` if the property disappears).

- [ ] **Step 2: Same change in v2** (`facturacion-v2.component.ts`, filter ~line 480).

- [ ] **Step 3: Smoke-run affected specs if they exist**

```bash
cd Frontend && npx ng test --include=src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.spec.ts --browsers=ChromeHeadless --watch=false
cd Frontend && npx ng test --include=src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.spec.ts --browsers=ChromeHeadless --watch=false
```

Fix only breaks caused by removing the private field.

- [ ] **Step 4: Commit**

```bash
git add Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts \
  Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts \
  Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.spec.ts \
  Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.spec.ts
git commit -m "$(cat <<'EOF'
feat: filtrar documentos de venta según país de la empresa

EOF
)"
```

---

### Task 4: Wire whitelist compras + exclusión gastos HN

**Files:**
- Modify: `Frontend/src/app/views/compras/facturacion/facturacion-compra.component.ts` (`cargarDocumentos`, ~289–339)
- Modify: `Frontend/src/app/views/compras/gastos/gasto/gasto.component.ts` (`cargarDocumentos`, ~478–508)

**Interfaces:**
- Consumes: `nombresDocumentosCompraPermitidos`, `nombresDocumentoExcluidosGastoHn`, `FE_PAIS_HN`, `resolveCodigoPaisFe`

- [ ] **Step 1: Compras — replace inline `documentosPermitidos`**

In `cargarDocumentos()`:

```typescript
import {
  NOMBRE_DOCUMENTO_CR,
  esTipoFacturaElectronicaCompraCr,
  nombresDocumentosCompraPermitidos,
} from '../../ventas/documentos/documento-nombre-options';
// adjust path as needed from compras/facturacion/

public cargarDocumentos() {
  const empresa = this.apiService.auth_user()?.empresa;
  const documentosPermitidos = nombresDocumentosCompraPermitidos(empresa);

  this.sharedDataService.getDocumentos()
    .pipe(this.untilDestroyed())
    .subscribe({
      next: (documentos) => {
        this.documentos = documentos;
        this.documentos = this.documentos.filter(
          (x: any) => x.id_sucursal == this.compra.id_sucursal
        );
        if (this.compra.cotizacion == 1) {
          this.documentos = this.documentos.filter((x: any) => x.nombre == 'Orden de compra');
          const documento = this.documentos.find((x: any) => x.nombre == 'Orden de compra');
          if (documento) {
            this.compra.tipo_documento = documento.nombre;
            this.compra.referencia = documento.correlativo;
          }
        } else {
          this.documentos = this.documentos.filter(
            (x: any) =>
              documentosPermitidos.includes(x.nombre) &&
              x.nombre != 'Nota de crédito' &&
              x.nombre != 'Nota de débito' &&
              x.nombre != NOMBRE_DOCUMENTO_CR.notaCredito &&
              x.nombre != NOMBRE_DOCUMENTO_CR.notaDebito
          );
        }
        this.cdr.markForCheck();
      },
      // ... error unchanged
    });
}
```

Remove the old CR `if (resolveCodigoPaisFe(...) === FE_PAIS_CR) { push(...) }` block — the helper already includes CR extras.

- [ ] **Step 2: Gastos — excluir tipos SV-only cuando país es HN**

In `gasto.component.ts` `cargarDocumentos()`, after the existing exclude filter for Cotización/OC/NC/ND:

```typescript
import {
  NOMBRE_DOCUMENTO_CR,
  nombresDocumentoExcluidosGastoHn,
} from '../../../ventas/documentos/documento-nombre-options';
import { FE_PAIS_HN, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

// inside filter chain:
this.documentos = this.documentos.filter(
  (x: any) =>
    x.nombre != 'Cotización' &&
    x.nombre != 'Orden de compra' &&
    x.nombre != 'Nota de crédito' &&
    x.nombre != NOMBRE_DOCUMENTO_CR.notaCredito &&
    x.nombre != 'Nota de débito' &&
    x.nombre != NOMBRE_DOCUMENTO_CR.notaDebito
);

if (resolveCodigoPaisFe(this.apiService.auth_user()?.empresa) === FE_PAIS_HN) {
  const excluidos = new Set(nombresDocumentoExcluidosGastoHn());
  this.documentos = this.documentos.filter((x: any) => !excluidos.has(x.nombre));
}
```

- [ ] **Step 3: Manual verification checklist**

1. Empresa **SV**: Admin Documentos dropdown = lista actual (con Crédito fiscal).  
2. Empresa **CR**: dropdown = electrónica (sin cambios).  
3. Empresa **HN**: dropdown = catálogo HN; no Crédito fiscal / Sujeto excluido / exportación / comercial.  
4. Crear serie HN `Boleta de compra` y verla en compras; no en ventas normales.  
5. Crear serie HN `Guía de remisión` y verla en ventas.  
6. Gasto HN: no listar Crédito fiscal aunque exista série histórica (opcional verificar exclusión). Admin tabla sigue mostrando series históricas.

- [ ] **Step 4: Commit**

```bash
git add Frontend/src/app/views/compras/facturacion/facturacion-compra.component.ts \
  Frontend/src/app/views/compras/gastos/gasto/gasto.component.ts
git commit -m "$(cat <<'EOF'
feat: filtrar tipos de documento en compras y gastos por país

EOF
)"
```

---

## Self-review

| Spec requirement | Task |
|------------------|------|
| Catálogo Admin por país | Task 1 (`documentoNombreOpciones`) — documentos/historial ya lo consumen |
| Defaults empresa/sucursal HN | Task 2 |
| Filtros ventas | Task 3 |
| Filtros compras/gastos | Task 4 |
| CR/SV sin cambios de nombres | Tasks 1–2 preserve lists |
| Sin migración | No task de migración |
| Tests mínimos catálogo | Task 1 + Task 2 |

Placeholder scan: none.  
Type consistency: `NOMBRE_DOCUMENTO_HN.*` strings match between catalog and whitelists; `FE_PAIS_HN` / `CODIGO_HONDURAS` both `'HN'`.

---

## Execution handoff

Plan complete and saved to `Docs/superpowers/plans/2026-08-04-tipos-documento-por-pais.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — execute tasks in this session with checkpoints  

Which approach?
