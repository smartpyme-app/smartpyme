# Impuesto turismo 5% independiente de IVA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que la exención/desactivación del IVA 13% no apague el impuesto de turismo 5% (ni otros no-IVA), con DTE coherente, ficha de cliente `tipo_fiscal` y reporte de ventas afectas al 5%.

**Architecture:** Desacoplar identificación/cálculo de IVA (`codigo_mh=20` / 13%) vs tributos especiales en el util de frontend y en facturación; persistir `venta.iva` solo como IVA; ajustar gates DTE para emitir tributos no-IVA con IVA=0; cliente con `tipo_fiscal`; reporte vía `venta_impuestos` sin hardcode de empresa.

**Tech Stack:** Angular 15 (Jasmine/Karma), Laravel (PHPUnit), Maatwebsite Excel, modelos MH existentes (`BuildsTributosVenta`).

**Spec:** `Docs/superpowers/specs/2026-07-15-impuesto-turismo-iva-exento-design.md`

## Global Constraints

- No hardcodear `id_empresa` ni Hostal Amapola / 770.
- No inventar tipos MH nuevos; mantener `gravada` | `exenta` | `no_sujeta`.
- No modificar `tipo` Persona/Empresa/Extranjero ni `tipo_contribuyente` Pequeño/Mediano/Grande.
- Turismo solo si el producto tiene el impuesto en `detalle.impuestos`; base = gravada o exenta de la línea; no aplicar en `no_sujeta`.
- `venta.iva` = solo monto IVA; montos especiales solo en `venta.impuestos` / `venta_impuestos`.

## File map

| File | Responsibility |
|------|----------------|
| `Frontend/src/app/utils/impuestos-venta.util.ts` | Helpers IVA/no-IVA, cálculo línea, acumulación |
| `Frontend/src/app/utils/impuestos-venta.util.spec.ts` | Unit tests del util (crear) |
| `Frontend/.../facturacion-tienda/facturacion.component.ts` (+ html) | `sumTotal`, switch solo IVA |
| `Frontend/.../facturacion-tienda-v2/facturacion-v2.component.ts` (+ html) | Igual v2 |
| `Frontend/.../facturacion-consigna/facturacion-consigna.component.ts` | Legacy consigna |
| `Backend/app/Models/MH/Concerns/BuildsTributosVenta.php` | Helper `documentoTieneIva` / `documentoTieneTributosNoIva` si hace falta |
| `Backend/app/Models/MH/MHFactura.php` | Gates DTE factura consumidor |
| `Backend/app/Models/MH/MHCCF.php` | Gates DTE CCF |
| `Backend/tests/Unit/MH/BuildsTributosVentaIvaGateTest.php` | Tests helpers/gate (crear) |
| Migración `clientes.tipo_fiscal` | Campo ficha |
| `Backend/app/Models/Ventas/Clientes/Cliente.php` | fillable |
| `Frontend/.../cliente-informacion.component.*` | Select tipo fiscal |
| Facturación `setCliente` | Aplicar Exento → solo IVA off |
| `Backend/routes/modulos/contabilidad/libros-iva.php` | Rutas reporte |
| `LibrosIVAController` | JSON + export turismo |
| `LibroImpuestoTurismoExport.php` | Excel |
| `libro-iva-general.component.*` | UI sección reporte |

---

### Task 1: Helpers y tests unitarios del util (IVA vs no-IVA)

**Files:**
- Create: `Frontend/src/app/utils/impuestos-venta.util.spec.ts`
- Modify: `Frontend/src/app/utils/impuestos-venta.util.ts`

**Interfaces:**
- Consumes: catálogo impuesto con `codigo_mh`, `porcentaje`, `id`
- Produces: `esImpuestoIva(imp)`, `porcentajeSoloIva(detalle|impuestos[], ivaEmpresa, cobrarIva)`, `baseParaImpuestosEspeciales(detalle)`, ampliación de `acumularMontosImpuestosVenta`

- [ ] **Step 1: Escribir tests fallidos**

Crear `impuestos-venta.util.spec.ts` con casos:

```typescript
import {
  esImpuestoIva,
  acumularMontosImpuestosVenta,
  calcularMontosLineaDetalle,
} from './impuestos-venta.util';

describe('impuestos-venta.util — IVA vs especiales', () => {
  it('esImpuestoIva reconoce codigo 20 y 13% sin código', () => {
    expect(esImpuestoIva({ codigo_mh: '20', porcentaje: 13 })).toBe(true);
    expect(esImpuestoIva({ codigo_mh: null, porcentaje: 13 })).toBe(true);
    expect(esImpuestoIva({ codigo_mh: 'C8', porcentaje: 5 })).toBe(false);
    expect(esImpuestoIva({ porcentaje: 5 })).toBe(false);
  });

  it('con cobrarIva=false acumula turismo sobre línea exenta si el detalle tiene el impuesto', () => {
    const ventaImpuestos = [
      { id: 1, porcentaje: 13, codigo_mh: '20', monto: 0 },
      { id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 },
    ];
    const detalles = [{
      tipo_gravado: 'exenta',
      gravada: 0,
      exenta: 100,
      no_sujeta: 0,
      impuestos: [
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 2, porcentaje: 5, codigo_mh: 'C8' },
      ],
    }];
    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);
    expect(ventaImpuestos[0].monto).toBe(0);
    expect(Number(ventaImpuestos[1].monto)).toBeCloseTo(5, 2);
  });

  it('no acumula especial en línea no_sujeta', () => {
    const ventaImpuestos = [{ id: 2, porcentaje: 5, codigo_mh: 'C8', monto: 0 }];
    const detalles = [{
      tipo_gravado: 'no_sujeta',
      gravada: 0,
      exenta: 0,
      no_sujeta: 100,
      impuestos: [{ id: 2, porcentaje: 5, codigo_mh: 'C8' }],
    }];
    acumularMontosImpuestosVenta(ventaImpuestos, detalles, false, 13);
    expect(ventaImpuestos[0].monto).toBe(0);
  });
});
```

Corregir el typo `porcentaje: de 5` → `porcentaje: 5` al pegar.

- [ ] **Step 2: Correr tests y ver fallos**

Run: `cd Frontend && npx ng test --include=**/impuestos-venta.util.spec.ts --browsers=ChromeHeadless --watch=false`  
Expected: FAIL (`esImpuestoIva` no definido / acumulación en 0).

- [ ] **Step 3: Implementar helpers mínimos**

En `impuestos-venta.util.ts` agregar:

```typescript
export function esImpuestoIva(imp: { codigo_mh?: string | null; porcentaje?: number } | null | undefined): boolean {
  if (!imp) return false;
  if (imp.codigo_mh === '20') return true;
  return Number(imp.porcentaje) === 13 && (imp.codigo_mh == null || imp.codigo_mh === '');
}

/** Base para impuestos especiales: gravada o exenta; nunca no_sujeta. */
export function baseParaImpuestosEspeciales(detalle: any): number {
  const tipo = String(detalle?.tipo_gravado || 'gravada').toLowerCase();
  if (tipo === 'no_sujeta') return 0;
  const gravada = parseFloat(detalle?.gravada || 0) || 0;
  if (gravada > 0) return gravada;
  const exenta = parseFloat(detalle?.exenta || 0) || 0;
  return exenta > 0 ? exenta : 0;
}
```

- [ ] **Step 4: Cambiar `acumularMontosImpuestosVenta`**

Comportamiento nuevo:

1. Resetear montos a 0.
2. **No** retornar temprano si `!cobrarImpuestos`.
3. Por cada detalle:
   - Para cada impuesto en `detalle.impuestos` (o legacy):
     - Si `esImpuestoIva(imp)`: solo acumular si `cobrarImpuestos && tipo === 'gravada'` sobre `detalle.gravada`.
     - Si no es IVA: acumular si `baseParaImpuestosEspeciales(detalle) > 0` y el impuesto está en el detalle (o legacy match), sobre esa base; **ignorar** `cobrarImpuestos`.
4. Legacy sin `detalle.impuestos[]`: si el % es IVA (13 / empresa) → misma regla IVA; si no → tratar como especial sobre la base especial.

Firma: mantener `(ventaImpuestos, detalles, cobrarImpuestos, empresaIva)` — `cobrarImpuestos` significa **cobrar IVA**.

- [ ] **Step 5: Ajustar `resolverPorcentajeImpuestoVenta` / `calcularMontosLineaDetalle`**

Para el % usado en precio con IVA / `detalle.iva` de línea:

- El porcentaje efectivo de **IVA embebido** debe ser solo la suma (o el %) de impuestos IVA del detalle, no la suma IVA+turismo.
- Agregar helper:

```typescript
export function porcentajeIvaDetalle(
  detalle: any,
  ivaEmpresa: unknown,
  cobrarIva: boolean
): number {
  if (!cobrarIva) return 0;
  if (Array.isArray(detalle?.impuestos) && detalle.impuestos.length > 0) {
    const ivas = detalle.impuestos.filter((i: any) => esImpuestoIva(i));
    if (ivas.length === 0) return 0;
    return ivas.reduce((s: number, i: any) => s + (Number(i.porcentaje) || 0), 0);
  }
  const pct = Number(detalle?.porcentaje_impuesto ?? ivaEmpresa ?? 0) || 0;
  // Legacy: si el único % es 5 (turismo), no tratarlo como IVA de línea
  if (pct === 5) return 0;
  return pct > 0 ? pct : Number(ivaEmpresa ?? 0) || 0;
}
```

Usar `porcentajeIvaDetalle` dentro de `calcularMontosLineaDetalle` en lugar de `resolverPorcentajeImpuestoVenta` para gravada/precio_iva/`detalle.iva`.

Tras calcular gravada/exenta, opcionalmente setear `detalle.monto_impuestos_especiales` como suma `% * base` de no-IVA (útil para UI); si no se usa, omitir.

- [ ] **Step 6: Correr tests — PASS**

Run: mismo comando del Step 2. Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add Frontend/src/app/utils/impuestos-venta.util.ts Frontend/src/app/utils/impuestos-venta.util.spec.ts
git commit -m "fix: desacoplar cálculo de IVA y tributos especiales en ventas"
```

---

### Task 2: Facturación v1 / v2 / consigna — `sumTotal` y labels

**Files:**
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.ts`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda/facturacion.component.html`
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/facturacion-v2.component.ts`
- Modify: HTML v2 equivalente (buscar “Con IVA”)
- Modify: `Frontend/src/app/views/ventas/facturacion/facturacion-consigna/facturacion-consigna.component.ts` (+ html si aplica)

**Interfaces:**
- Consumes: `acumularMontosImpuestosVenta`, `esImpuestoIva`, `calcularMontosLineaDetalle` actualizados
- Produces: `venta.iva` solo IVA; desglose UI correcto

- [ ] **Step 1: Actualizar `sumTotal` en facturación v1**

En el bloque que hoy hace:

```typescript
if (this.venta.cobrar_impuestos) {
  acumularMontosImpuestosVenta(...);
  this.venta.iva = sum(impuestos.monto);
} else {
  this.venta.iva = 0;
  impuestos.forEach(i => i.monto = 0);
}
```

Reemplazar por:

```typescript
acumularMontosImpuestosVenta(
  this.venta.impuestos,
  this.venta.detalles,
  !!this.venta.cobrar_impuestos,
  empresaIva
);
const montoSoloIva = (this.venta.impuestos || [])
  .filter((i: any) => esImpuestoIva(i))
  .reduce((s: number, i: any) => s + (parseFloat(i.monto) || 0), 0);
this.venta.iva = parseFloat(montoSoloIva.toFixed(4)).toFixed(4);
// total a cobrar: sub_total + suma de TODOS los montos de venta.impuestos (+ retención/percepción según lógica existente)
```

Importar `esImpuestoIva`. Revisar el cálculo de `total` para que sume montos de especiales aunque `cobrar_impuestos` sea false (hoy muchas veces suma `venta.iva` como proxy de todos).

- [ ] **Step 2: Label UI**

Donde diga “Con IVA” / “Con IVA (13%)”, dejar claro:

`Con IVA (13%)`  
hint opcional: `No afecta impuestos especiales (ej. turismo 5%)`.

- [ ] **Step 3: Replicar en v2 y consigna**

Misma lógica `sumTotal` / total / import. En consigna, el `forEach monto=0` al apagar IVA debe eliminarse; llamar acumulación nueva.

- [ ] **Step 4: Verificar edición de venta**

Buscar `cobrar_impuestos = this.venta.iva > 0`. Con `venta.iva` = solo IVA, una venta exenta+turismo abrirá con “Con IVA” off (correcto). Confirmar que al abrir no se resetean montos de especiales al llamar `sumTotal`.

- [ ] **Step 5: Prueba manual checklist**

1. Producto con IVA 13% + turismo 5%; venta gravada → ambos montos.
2. Mismo producto; “Con IVA” off → IVA 0, turismo = base × 5%.
3. Línea marcada exenta con Con IVA on → IVA 0, turismo > 0.
4. Producto solo IVA → sin montos 5%.
5. Producto solo turismo → monto 5% con o sin Con IVA.

- [ ] **Step 6: Commit**

```bash
git add Frontend/src/app/views/ventas/facturacion
git commit -m "fix: facturación aplica solo IVA con el switch Con IVA"
```

---

### Task 3: DTE — gates IVA vs tributos no-IVA

**Files:**
- Modify: `Backend/app/Models/MH/Concerns/BuildsTributosVenta.php`
- Modify: `Backend/app/Models/MH/MHFactura.php`
- Modify: `Backend/app/Models/MH/MHCCF.php`
- Review: `MHNotaCredito.php`, `MHNotaDebito.php` (solo si copian gate `venta->iva > 0` para tributos de línea)
- Create: `Backend/tests/Unit/MH/TributosVentaGateLogicTest.php` (test puro de reglas, sin bootstrap DTE completo si es pesado)

**Interfaces:**
- Consumes: `montoIvaDocumento()`, `montoTributosNoIvaDocumento()`, `filasImpuestosDocumento()`, `esImpuestoIva`
- Produces: `documentoTieneIva(): bool`, `documentoTieneTributosNoIva(): bool`

- [ ] **Step 1: Helpers en el trait**

```php
protected function documentoTieneIva(): bool
{
    return $this->montoIvaDocumento() > 0;
}

protected function documentoTieneTributosNoIva(): bool
{
    return $this->montoTributosNoIvaDocumento() > 0;
}
```

- [ ] **Step 2: `MHFactura` — resumen**

Reemplazar el bloque que fuerza `gravada = sub_total` / `exenta = sub_total` solo con `$this->venta->iva > 0` por lógica basada en montos guardados de la venta (`$this->venta->gravada`, `exenta`, `no_sujeta`) **o**:

- Si `documentoTieneIva()` → mantener comportamiento gravada actual (incl. precios con IVA incluido donde aplique).
- Si no hay IVA pero hay tributos no-IVA → **no** forzar todo a exenta borrando tributos: usar `exenta`/`no_sujeta`/`gravada` ya persistidos; construir `tributosResumen` con `buildTributosResumenFacturaConsumidor()` (ya no filtra por IVA>0).
- `totalGravada` / `totalIva` deben usar `montoIvaDocumento()`, no `$this->venta->iva` como suma legacy.

Revisar líneas ~198–215 y ~353–364: en el `else` (sin IVA):

```php
if ($this->documentoTieneIva()) {
    // ... flujo gravada + factor IVA incluido ...
    $tributos = $this->buildTributosLineaCodesFacturaConsumidor($detalle);
} else {
    $detalle->iva = 0;
    // Si la línea está exenta / no sujeta según detalle:
    // asignar ventaExenta / ventaNoSuj según detalle persistido
    $tributos = $this->documentoTieneTributosNoIva()
        ? $this->buildTributosLineaCodesFacturaConsumidor($detalle)
        : null;
}
```

Ajustar `buildTributosLineaCodesFacturaConsumidor` para que con IVA=0 pero detalle con producto de turismo **no** haga early-return por `(float) $doc->iva <= 0 && gravada <= 0` sin mirar especiales. Cambiar a:

```php
if (!$this->documentoTieneIva() && !$this->documentoTieneTributosNoIva()) {
    return null;
}
// Si no hay IVA pero hay especiales, aún devolver códigos no-IVA del producto/documento
```

- [ ] **Step 3: `MHCCF` — mismo patrón**

En ~173, ~247–258, ~309–313: sustituir `$this->venta->iva > 0` por `documentoTieneIva()` para clasificación IVA; usar `documentoTieneTributosNoIva()` / builders para no anular tributos de línea.

- [ ] **Step 4: Test unitario de helpers**

Si los métodos son `protected`, extraer funciones estáticas/helpers testables **o** testear vía clase stub mínima. Alternativa pragmática: duplicar la regla de identificación en un test del trait con una clase anónima que use el trait.

Expected: IVA 0 + fila venta_impuestos 5% → `documentoTieneTributosNoIva() === true`, `documentoTieneIva() === false`.

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/MH/TributosVentaGateLogicTest.php`

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Models/MH Backend/tests/Unit/MH
git commit -m "fix: DTE emite tributos no-IVA aunque el IVA sea cero"
```

---

### Task 4: Ficha de cliente — `tipo_fiscal`

**Files:**
- Create: `Backend/database/migrations/2026_07_15_000001_add_tipo_fiscal_to_clientes_table.php`
- Modify: `Backend/app/Models/Ventas/Clientes/Cliente.php`
- Modify: `Frontend/.../cliente/informacion/cliente-informacion.component.html`
- Modify: `Frontend/.../cliente/informacion/cliente-informacion.component.ts` (default si aplica)
- Modify: `Frontend/.../facturacion.component.ts` y `facturacion-v2.component.ts` (`setCliente`)

**Interfaces:**
- Produces: `cliente.tipo_fiscal`: `'Contribuyente' | 'Consumidor Final' | 'Exento' | null`

- [ ] **Step 1: Migración**

```php
Schema::table('clientes', function (Blueprint $table) {
    $table->string('tipo_fiscal', 40)->nullable()->after('tipo_contribuyente');
});
```

- [ ] **Step 2: Modelo**

Agregar `'tipo_fiscal'` a `$fillable`.

- [ ] **Step 3: UI ficha**

Junto a tipo de contribuyente:

```html
<div class="form-group col-md-6 col-lg-3">
  <label for="tipo_fiscal">Tipo fiscal (IVA):</label>
  <select class="form-select" [(ngModel)]="cliente.tipo_fiscal" name="tipo_fiscal">
    <option value="">Seleccione aquí</option>
    <option value="Contribuyente">Contribuyente</option>
    <option value="Consumidor Final">Consumidor Final</option>
    <option value="Exento">Exento</option>
  </select>
  <small class="form-text text-muted">Exento: no aplica IVA 13%; sí impuestos especiales del producto.</small>
</div>
```

- [ ] **Step 4: Facturación al elegir cliente**

En `setCliente` (v1/v2):

```typescript
if (cliente?.tipo_fiscal === 'Exento') {
  this.venta.cobrar_impuestos = false;
  sincronizarTipoGravadoPorCobroIva(this.venta.detalles, false);
} else if (/* no forzar on si el usuario ya apagó IVA */) {
  // Solo auto-activar IVA si empresa.cobra_iva == 'Si' y el usuario no lo desactivó manualmente
}
this.sumTotal();
```

No poner todos los `impuestos.monto = 0`.

- [ ] **Step 5: Commit**

```bash
git add Backend/database/migrations Backend/app/Models/Ventas/Clientes/Cliente.php Frontend/src/app/views/ventas/clientes Frontend/src/app/views/ventas/facturacion
git commit -m "feat: tipo fiscal del cliente (Exento sin IVA, con impuestos especiales)"
```

---

### Task 5: Reporte impuesto turismo 5%

**Files:**
- Modify: `Backend/routes/modulos/contabilidad/libros-iva.php`
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php`
- Create: `Backend/app/Exports/Contabilidad/ElSalvador/LibroImpuestoTurismoExport.php`
- Modify: `Frontend/src/app/views/contabilidad/libro-iva/libro-iva-general/libro-iva-general.component.ts`
- Modify: `Frontend/src/app/views/contabilidad/libro-iva/libro-iva-general/libro-iva-general.component.html`
- Modify: `Frontend/src/app/views/contabilidad/contabilidad.routing.module.ts` solo si se agrega ruta dedicada (preferir sección en general)

**Interfaces:**
- API `GET /libro-iva/impuesto-turismo` → filas + totales
- API `GET /libro-iva/impuesto-turismo/descargar-libro` → Excel
- Filtros: `inicio`, `fin`, `id_sucursal`, opcional `id_impuesto`

- [ ] **Step 1: Query reutilizable en controller**

```php
private function ventasImpuestoTurismoQuery(Request $request)
{
    return Venta::query()
        ->with(['cliente', 'documento', 'impuestos.impuesto'])
        ->where('estado', '!=', 'Anulada')
        ->where('cotizacion', 0)
        ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
        ->whereBetween('fecha', [$request->inicio, $request->fin])
        ->whereHas('impuestos', function ($q) use ($request) {
            $q->where('monto', '>', 0)
              ->whereHas('impuesto', function ($qi) use ($request) {
                  $qi->where('porcentaje', 5)
                     ->where(function ($q2) {
                         $q2->whereNull('codigo_mh')
                            ->orWhere('codigo_mh', '!=', '20');
                     });
                  if ($request->filled('id_impuesto')) {
                      $qi->where('impuestos.id', $request->id_impuesto);
                  }
              });
        })
        ->orderBy('fecha')
        ->orderBy('correlativo');
}
```

**Sin** filtrar por `id_empresa` hardcodeado (el scope de autenticación / global de empresa ya aplica).

- [ ] **Step 2: Endpoint JSON**

Mapear cada venta:

```php
'fecha' => $v->fecha,
'correlativo' => $v->correlativo,
'numero_control' => $v->numero_control,
'cliente' => $v->nombre_cliente,
'base' => (float) $v->sub_total, // o suma de bases de líneas afectas si se quiere más fino
'monto_turismo' => (float) $v->impuestos->filter(...5% no IVA...)->sum('monto'),
```

Incluir `total_monto_turismo` en la respuesta (suma).

- [ ] **Step 3: Export Excel**

Copiar estructura de `LibroRetencion1Export`: título “IMPUESTO DE TURISMO 5%”, empresa, mes/año, columnas Fecha | Documento | Cliente | Base | Monto 5% | Total período en footer o última fila.

- [ ] **Step 4: Rutas**

```php
Route::get('/libro-iva/impuesto-turismo', [LibrosIVAController::class, 'impuestoTurismoList']);
Route::get('/libro-iva/impuesto-turismo/descargar-libro', [LibrosIVAController::class, 'impuestoTurismoLibroExport']);
```

- [ ] **Step 5: UI**

En `libro-iva-general`: nueva sección/botón “Impuesto turismo 5%”, tabla, total, botón descargar Excel (mismo patrón retenciones).

- [ ] **Step 6: Prueba manual**

Con ventas de prueba (tras Fase 1): rango de fechas muestra solo las con monto 5% > 0; total coincide con suma de `venta_impuestos`.

- [ ] **Step 7: Commit**

```bash
git add Backend/routes/modulos/contabilidad/libros-iva.php Backend/app/Http/Controllers/Api/Contabilidad/LibrosIVAController.php Backend/app/Exports/Contabilidad/ElSalvador/LibroImpuestoTurismoExport.php Frontend/src/app/views/contabilidad/libro-iva
git commit -m "feat: reporte de impuesto de turismo 5%"
```

---

### Task 6: Verificación end-to-end y checklist de aceptación

**Files:** ninguno nuevo (checklist)

- [ ] **Step 1: Correr unitarios**

```bash
cd Frontend && npx ng test --include=**/impuestos-venta.util.spec.ts --browsers=ChromeHeadless --watch=false
cd Backend && php vendor/bin/phpunit tests/Unit/MH/TributosVentaGateLogicTest.php
```

- [ ] **Step 2: Checklist ticket**

| Criterio | OK? |
|----------|-----|
| Apagar IVA 13% deja de calcular solo ese impuesto | |
| Turismo 5% sigue cuando el producto lo tiene | |
| Venta/línea exenta de IVA puede llevar turismo | |
| Cliente Exento: sin IVA, con especiales | |
| Exención no afecta especiales | |
| Reporte muestra ventas + monto 5% + total | |
| DTE: IVA 0 + tributo turismo en resumen/línea | |
| Cero hardcode empresa 770 | |

- [ ] **Step 3: Commit vacío no requerido** — si hubo fixes de bug en verificación, commit `fix: ajustes post-verificación impuesto turismo`.

---

## Self-review (plan vs spec)

| Spec | Task |
|------|------|
| Desacoplar IVA / turismo (regla B base) | Task 1–2 |
| DTE gates | Task 3 |
| tipo_fiscal Contribuyente/CF/Exento | Task 4 |
| Reporte 5% | Task 5 |
| Sin hardcode empresa | Task 5 query + Global Constraints |
| CA ticket | Task 6 |

**Placeholders:** ninguno intencional; el `codigo_mh` de turismo en tests usa ejemplo `C8` — en producción debe usarse el código real CAT-015 configurado en el impuesto de la empresa (el cálculo no depende del código salvo identificación IVA=`20`).
