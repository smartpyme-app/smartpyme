# Libros fiscales oficiales de Honduras Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el libro de ventas unificado de Honduras por los libros oficiales de compras, ventas a consumidor final y ventas a contribuyentes, iguales en vista web, Excel y PDF a los formatos entregados.

**Architecture:** Mantener un fork explícito dentro del módulo Honduras: cada libro tendrá un export que concentra consulta, normalización, totales y mapeo Excel; el controlador expondrá el mismo resultado para JSON/PDF. Angular consumirá contratos específicos por libro y conservará los componentes compartidos de filtros y descargas.

**Tech Stack:** Laravel/PHP 8.3, Eloquent, Maatwebsite Excel, DomPDF, PHPUnit 11, Angular, TypeScript, Bootstrap.

## Global Constraints

- Aplicar únicamente cuando `LibroIvaPaisResolver::tipo() === LibroIvaPaisResolver::TIPO_HD`.
- Reproducir exactamente columnas, orden, títulos, agrupaciones y resúmenes de los tres PDF de referencia.
- Mostrar columnas sin fuente persistida con `0` o cadena vacía; no crear migraciones ni nuevos campos.
- Clasificar `Factura` y `Factura de exportación` como consumidor final; `Crédito fiscal` como contribuyente.
- Mantener Retenciones y Resumen Honduras sin cambios funcionales.
- No modificar exports, blades ni lógica fiscal de El Salvador, Costa Rica o General.
- Reusar `LibroIvaMontosHelper`, filtros compartidos y utilidades de descarga existentes.
- Las devoluciones/notas de crédito se presentan con signo negativo.
- No agregar dependencias.

---

## File Map

### Backend

- Modify: `Backend/app/Exports/Contabilidad/Honduras/LibroComprasExport.php` — consulta y filas oficiales de compras.
- Create: `Backend/app/Exports/Contabilidad/Honduras/LibroConsumidoresExport.php` — ventas consumidor, split 15/18 y resumen.
- Create: `Backend/app/Exports/Contabilidad/Honduras/LibroContribuyentesExport.php` — ventas contribuyentes y resumen de operaciones.
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIva/LibrosIvaHdController.php` — JSON, PDF y Excel de los tres libros.
- Modify: `Backend/routes/modulos/contabilidad/libros-iva-hd.php` — endpoints y compatibilidad `/ventas`.
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIva/LibrosIvaLegacyController.php` — consumidores legacy de empresas HN.
- Modify: `Backend/resources/views/reportes/contabilidad/honduras/libro-compras.blade.php` — formato oficial de compras.
- Create: `Backend/resources/views/reportes/contabilidad/honduras/libro-consumidores.blade.php` — formato consumidor.
- Create: `Backend/resources/views/reportes/contabilidad/honduras/libro-contribuyentes.blade.php` — formato contribuyentes.
- Create: `Backend/tests/Unit/Contabilidad/Honduras/LibroComprasExportTest.php`.
- Create: `Backend/tests/Unit/Contabilidad/Honduras/LibroConsumidoresExportTest.php`.
- Create: `Backend/tests/Unit/Contabilidad/Honduras/LibroContribuyentesExportTest.php`.
- Create: `Backend/tests/Feature/Contabilidad/Honduras/LibrosIvaHdCountryGuardTest.php`.

### Frontend

- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/consumidor-final/libro-iva-hd-consumidor-final.component.ts`.
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/consumidor-final/libro-iva-hd-consumidor-final.component.html`.
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/contribuyentes/libro-iva-hd-contribuyentes.component.ts`.
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/contribuyentes/libro-iva-hd-contribuyentes.component.html`.
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-hd/compras/libro-iva-hd-compras.component.ts`.
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-hd/compras/libro-iva-hd-compras.component.html`.
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-hd/libro-iva-hd-nav.component.html`.
- Modify: `Frontend/src/app/views/contabilidad/contabilidad.routing.module.ts`.
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-shared/libro-iva-pais.service.ts`.

---

### Task 1: Contrato y mapeo del libro de compras

**Files:**
- Modify: `Backend/app/Exports/Contabilidad/Honduras/LibroComprasExport.php`
- Test: `Backend/tests/Unit/Contabilidad/Honduras/LibroComprasExportTest.php`

**Interfaces:**
- Produces: `rowsForApi(): array{filas: array<int,array<string,mixed>>, totales: array<string,float>}`
- Produces: cada fila con claves `no`, `fecha_emision`, `numero_documento`, `nrc`, `nit_o_dui`, `nombre_proveedor`, `exentas_internas`, `exentas_internaciones`, `exentas_importaciones`, `gravadas_internas`, `gravadas_internaciones`, `gravadas_importaciones`, `credito_fiscal`, `fovial`, `cotrans`, `cesc`, `anticipo_iva_percibido`, `total`, `retencion_terceros`, `compras_sujetos_excluidos`.

- [ ] **Step 1: Escribir pruebas fallidas del mapeo oficial**

Crear pruebas puras que invoquen mediante Reflection un método `mapItemToAssoc(object $item, int $no): array`. Cubrir:

```php
public function test_mapea_compra_gravada_en_columnas_oficiales(): void
{
    $registro = (object) [
        'fecha' => '2026-07-01',
        'referencia' => 'FAC-001',
        'tipo_documento' => 'Crédito fiscal',
        'nombre_proveedor' => 'Proveedor HN',
        'sub_total' => 100.0,
        'iva' => 15.0,
        'total' => 115.0,
        'percepcion' => 2.0,
        'iva_retenido' => 1.0,
        'proveedor' => (object) ['ncr' => '08011999123456', 'nit' => 'NIT-1', 'dui' => null],
    ];

    $row = $this->invokeMap((object) ['registro' => $registro, 'mult' => 1], 1);

    $this->assertSame('FAC-001', $row['numero_documento']);
    $this->assertSame(100.0, $row['gravadas_internas']);
    $this->assertSame(15.0, $row['credito_fiscal']);
    $this->assertSame(0.0, $row['fovial']);
    $this->assertSame(1.0, $row['retencion_terceros']);
}
```

Agregar casos para `Importación`, `Sujeto excluido` y multiplicador `-1`, comprobando que internaciones/FOVIAL/COTRANS/CESC permanecen en cero.

- [ ] **Step 2: Ejecutar la prueba para comprobar el fallo**

Run desde `Backend`:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras\LibroComprasExportTest.php
```

Expected: FAIL porque la firma y las claves oficiales aún no existen.

- [ ] **Step 3: Implementar el mapeo mínimo**

Conservar las consultas de Compra/Gasto/Devolución actuales y sustituir el mapper por:

```php
private function mapItemToAssoc(object $item, int $no): array
{
    $r = $item->registro;
    $m = (int) $item->mult;
    $proveedor = $r->proveedor ?? $r->proveedor()->first();
    $tipo = (string) ($r->tipo_documento ?? '');
    $esImportacion = $tipo === 'Importación';
    $esSujetoExcluido = $tipo === 'Sujeto excluido';
    $columnas = $esSujetoExcluido
        ? ['compras_exentas' => 0, 'compras_gravadas' => 0, 'credito_fiscal' => 0]
        : LibroIvaMontosHelper::columnasCompra($r, $m);

    return [
        'no' => $no,
        'fecha_emision' => (string) $r->fecha,
        'numero_documento' => (string) ($r->referencia ?? ''),
        'nrc' => (string) ($proveedor->ncr ?? ''),
        'nit_o_dui' => (string) ($proveedor->nit ?? $proveedor->dui ?? ''),
        'nombre_proveedor' => (string) ($r->nombre_proveedor ?? ''),
        'exentas_internas' => $esImportacion ? 0.0 : (float) $columnas['compras_exentas'],
        'exentas_internaciones' => 0.0,
        'exentas_importaciones' => $esImportacion ? (float) $columnas['importaciones_exentas'] : 0.0,
        'gravadas_internas' => $esImportacion ? 0.0 : (float) $columnas['compras_gravadas'],
        'gravadas_internaciones' => 0.0,
        'gravadas_importaciones' => $esImportacion ? (float) $columnas['importaciones_gravadas'] : 0.0,
        'credito_fiscal' => (float) $columnas['credito_fiscal'],
        'fovial' => 0.0,
        'cotrans' => 0.0,
        'cesc' => 0.0,
        'anticipo_iva_percibido' => (float) ($r->percepcion ?? 0) * $m,
        'total' => (float) ($r->total ?? 0) * $m,
        'retencion_terceros' => (float) ($r->iva_retenido ?? 0) * $m,
        'compras_sujetos_excluidos' => $esSujetoExcluido ? (float) $r->total * $m : 0.0,
    ];
}
```

`rowsForApi()` debe numerar con `values()->map(...)`, sumar únicamente claves monetarias y retornar `['filas' => ..., 'totales' => ...]`. `headings()` y `map()` deben usar exactamente el orden de las claves anterior.

- [ ] **Step 4: Ejecutar pruebas**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras\LibroComprasExportTest.php
```

Expected: PASS.

- [ ] **Step 5: Revisar el diff de la tarea**

Run:

```powershell
git diff -- Backend/app/Exports/Contabilidad/Honduras/LibroComprasExport.php Backend/tests/Unit/Contabilidad/Honduras/LibroComprasExportTest.php
```

Expected: solo mapper, contrato API, headings y pruebas del libro HN.

---

### Task 2: Libro de ventas a consumidor final

**Files:**
- Create: `Backend/app/Exports/Contabilidad/Honduras/LibroConsumidoresExport.php`
- Test: `Backend/tests/Unit/Contabilidad/Honduras/LibroConsumidoresExportTest.php`

**Interfaces:**
- Produces: `rowsForApi(): array{filas: array, resumen: array}`
- Produces filas con `no`, `fecha`, `factura_no`, `cai_no`, `maquina_registradora`, `exentas`, `exoneradas`, `gravadas_15`, `gravadas_18`, `total_ventas`, `cuenta_terceros`.
- Produces resumen con `total_exentas`, `total_exoneradas`, `netas_15`, `netas_18`, `debito_fiscal`, `credito_fiscal`.

- [ ] **Step 1: Escribir pruebas fallidas de clasificación, tasas y devolución**

Crear ventas en memoria con relación `documento` y detalles como colecciones Eloquent. Probar:

```php
public function test_separa_bases_gravadas_15_y_18_por_detalle(): void
{
    $venta = new Venta([
        'fecha' => '2026-07-02',
        'correlativo' => '000-001-01-00000001',
        'exenta' => 10,
        'total' => 143,
        'cuenta_a_terceros' => 0,
        'iva' => 18,
    ]);
    $venta->setRelation('documento', new Documento(['nombre' => 'Factura', 'resolucion' => 'CAI-123']));
    $venta->setRelation('detalles', collect([
        (object) ['porcentaje_impuesto' => 15, 'gravada' => 100],
        (object) ['porcentaje_impuesto' => 18, 'gravada' => 15],
    ]));

    $row = $this->invokeMapVenta($venta, 1);

    $this->assertSame(100.0, $row['gravadas_15']);
    $this->assertSame(15.0, $row['gravadas_18']);
    $this->assertSame('CAI-123', $row['cai_no']);
    $this->assertSame('', $row['maquina_registradora']);
}
```

Añadir pruebas de filtro (`Factura` incluida, `Crédito fiscal` excluido), fallback CAI de empresa y devolución con montos negativos.

- [ ] **Step 2: Ejecutar para comprobar el fallo**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras\LibroConsumidoresExportTest.php
```

Expected: FAIL porque la clase no existe.

- [ ] **Step 3: Implementar export y contrato**

La clase implementará `FromCollection`, `WithMapping`, `WithHeadings`, `WithEvents`. La consulta:

```php
Venta::with(['documento', 'detalles'])
    ->where('estado', '!=', 'Anulada')
    ->where('cotizacion', 0)
    ->whereHas('documento', fn ($q) => $q->whereIn('nombre', ['Factura', 'Factura de exportación']))
    ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
    ->whereBetween('fecha', [$request->inicio, $request->fin]);
```

El split será:

```php
private function basesPorTasa(iterable $detalles, int $mult = 1): array
{
    $bases = ['gravadas_15' => 0.0, 'gravadas_18' => 0.0];
    foreach ($detalles as $detalle) {
        $tasa = (float) ($detalle->porcentaje_impuesto ?? 0);
        $base = (float) ($detalle->gravada ?? 0) * $mult;
        if (abs($tasa - 15.0) < 0.01) {
            $bases['gravadas_15'] += $base;
        } elseif (abs($tasa - 18.0) < 0.01) {
            $bases['gravadas_18'] += $base;
        }
    }
    return $bases;
}
```

Para devoluciones, cargar `venta.documento`, filtrar por tipo de la venta original y mapear con `mult = -1`. Ordenar ventas y devoluciones por fecha/correlativo. CAI: `documento.resolucion` y luego `data_get($empresa->custom_empresa, 'configuraciones.factura_cai', '')`.

- [ ] **Step 4: Ejecutar pruebas**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras\LibroConsumidoresExportTest.php
```

Expected: PASS.

- [ ] **Step 5: Revisar contrato y totales**

Run:

```powershell
vendor\bin\phpunit --filter LibroConsumidoresExportTest
```

Expected: PASS y el resumen coincide con la suma de filas.

---

### Task 3: Libro de ventas a contribuyentes

**Files:**
- Create: `Backend/app/Exports/Contabilidad/Honduras/LibroContribuyentesExport.php`
- Test: `Backend/tests/Unit/Contabilidad/Honduras/LibroContribuyentesExportTest.php`

**Interfaces:**
- Produces: `rowsForApi(): array{filas: array, resumen_operaciones: array}`
- Filas: `no`, `fecha`, `correlativo`, `nrc`, `nombre`, `exentas`, `no_sujetas`, `gravadas_locales`, `debito_fiscal`, `cta_terceros`, `debito_cta_terceros`, `iva_percibido`, `iva_retenido`, `total`.

- [ ] **Step 1: Escribir pruebas fallidas**

Probar que `Crédito fiscal` se incluye, `Factura` se excluye, los datos fiscales salen del cliente y el resumen suma filas:

```php
public function test_mapea_credito_fiscal_y_totales(): void
{
    $venta = new Venta([
        'fecha' => '2026-07-03',
        'correlativo' => 'CCF-1',
        'exenta' => 5,
        'no_sujeta' => 2,
        'gravada' => 100,
        'iva' => 15,
        'cuenta_a_terceros' => 3,
        'iva_percibido' => 1,
        'iva_retenido' => 0.5,
        'total' => 126,
    ]);
    $venta->setRelation('cliente', (object) ['ncr' => '0801', 'nombre' => 'Cliente HN']);
    $venta->setRelation('documento', (object) ['nombre' => 'Crédito fiscal']);

    $row = $this->invokeMapVenta($venta, 1);

    $this->assertSame('0801', $row['nrc']);
    $this->assertSame(100.0, $row['gravadas_locales']);
    $this->assertSame(15.0, $row['debito_fiscal']);
    $this->assertSame(0.0, $row['debito_cta_terceros']);
}
```

Agregar devolución negativa y estructura completa de `resumen_operaciones`.

- [ ] **Step 2: Ejecutar para comprobar el fallo**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras\LibroContribuyentesExportTest.php
```

Expected: FAIL porque la clase no existe.

- [ ] **Step 3: Implementar export**

Usar consulta de Venta con `cliente`, `documento`, filtro exacto `Crédito fiscal`; agregar devoluciones cuya venta origen sea Crédito fiscal. El mapeo central:

```php
private function mapVenta(object $venta, int $no, int $mult = 1): array
{
    $cliente = $venta->cliente;
    return [
        'no' => $no,
        'fecha' => (string) $venta->fecha,
        'correlativo' => (string) ($venta->correlativo ?? ''),
        'nrc' => (string) ($cliente->ncr ?? ''),
        'nombre' => (string) ($venta->nombre_cliente ?? $cliente->nombre ?? ''),
        'exentas' => LibroIvaMontosHelper::ventasExentas($venta) * $mult,
        'no_sujetas' => LibroIvaMontosHelper::ventasNoSujetas($venta) * $mult,
        'gravadas_locales' => LibroIvaMontosHelper::ventasGravadas($venta) * $mult,
        'debito_fiscal' => (float) ($venta->iva ?? 0) * $mult,
        'cta_terceros' => (float) ($venta->cuenta_a_terceros ?? 0) * $mult,
        'debito_cta_terceros' => 0.0,
        'iva_percibido' => (float) ($venta->iva_percibido ?? 0) * $mult,
        'iva_retenido' => (float) ($venta->iva_retenido ?? 0) * $mult,
        'total' => (float) ($venta->total ?? 0) * $mult,
    ];
}
```

El resumen tendrá `totales_detalle`, `consumidor_final`, `contribuyentes`, `cta_terceros`, con claves exactas `gravadas`, `exportaciones`, `debito_fiscal`, `iva_percibido`, `iva_retenido`.

- [ ] **Step 4: Ejecutar pruebas**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras\LibroContribuyentesExportTest.php
```

Expected: PASS.

- [ ] **Step 5: Ejecutar todos los tests de exports HN**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras
```

Expected: PASS.

---

### Task 4: Endpoints, guard de país y compatibilidad

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIva/LibrosIvaHdController.php`
- Modify: `Backend/routes/modulos/contabilidad/libros-iva-hd.php`
- Modify: `Backend/app/Http/Controllers/Api/Contabilidad/LibrosIva/LibrosIvaLegacyController.php`
- Test: `Backend/tests/Feature/Contabilidad/Honduras/LibrosIvaHdCountryGuardTest.php`

**Interfaces:**
- Produces: `GET /api/libro-iva-hd/{compras|consumidores|contribuyentes}`
- Produces: `GET /api/libro-iva-hd/{libro}/descargar-libro`
- Produces: `GET /api/libro-iva-hd/ventas` como redirect a contribuyentes.

- [ ] **Step 1: Escribir prueba fallida del guard**

Probar los tres listados con una empresa no HN y verificar 403/mensaje. Reusar autenticación y factory/patrón feature existente del repo:

```php
foreach (['compras', 'consumidores', 'contribuyentes'] as $libro) {
    $this->getJson("/api/libro-iva-hd/{$libro}?inicio=2026-07-01&fin=2026-07-31")
        ->assertForbidden()
        ->assertJsonFragment(['message' => 'Esta operación solo está disponible para empresas de Honduras.']);
}
```

- [ ] **Step 2: Ejecutar para comprobar el fallo**

Run:

```powershell
vendor\bin\phpunit tests\Feature\Contabilidad\Honduras\LibrosIvaHdCountryGuardTest.php
```

Expected: FAIL para rutas todavía inexistentes.

- [ ] **Step 3: Implementar métodos del controlador**

Cada listado:

```php
public function consumidores(BaseLibroIVARequest $request)
{
    $this->assertHonduras();
    $export = new LibroConsumidoresExport();
    $export->filter($request);

    if ($request->formato === 'pdf') {
        $data = $export->rowsForApi();
        return app('dompdf.wrapper')
            ->loadView('reportes.contabilidad.honduras.libro-consumidores', [
                'filas' => $data['filas'],
                'resumen' => $data['resumen'],
                'request' => $request,
            ])
            ->setPaper('legal', 'landscape')
            ->stream('libro-consumidores.pdf');
    }

    return response()->json($export->rowsForApi());
}
```

Añadir explícitamente estas firmas al controlador, todas precedidas por `assertHonduras()`:

```php
public function consumidores(BaseLibroIVARequest $request);
public function consumidoresLibroExport(BaseLibroIVARequest $request);
public function contribuyentes(BaseLibroIVARequest $request);
public function contribuyentesLibroExport(BaseLibroIVARequest $request);
public function compras(BaseLibroIVARequest $request);
public function comprasLibroExport(BaseLibroIVARequest $request);
```

`contribuyentes()` carga `reportes.contabilidad.honduras.libro-contribuyentes` con `filas`, `resumen_operaciones` y `request`; `compras()` carga `reportes.contabilidad.honduras.libro-compras` con `filas`, `totales` y `request`. Las tres descargas instancian su export, llaman `filter($request)` y retornan:

```php
return Excel::download($export, 'Libro-consumidores.xlsx');
```

- [ ] **Step 4: Registrar rutas y legacy**

Registrar rutas antes de cualquier catch-all. En `LibrosIvaLegacyController::consumidores`, el branch HN debe llamar al nuevo `consumidores()`; no al `ventas()` unificado. Mantener `/ventas` con redirect de ruta:

```php
Route::redirect('/ventas', '/api/libro-iva-hd/contribuyentes');
```

Si el prefijo global ya agrega `/api`, usar redirect relativo `libro-iva-hd/contribuyentes` para evitar `/api/api`.

- [ ] **Step 5: Ejecutar pruebas y listar rutas**

Run:

```powershell
vendor\bin\phpunit tests\Feature\Contabilidad\Honduras\LibrosIvaHdCountryGuardTest.php
php artisan route:list --path=libro-iva-hd
```

Expected: tests PASS y aparecen consumidores, contribuyentes, compras, retenciones y descargas.

---

### Task 5: PDF y Excel fieles a los formatos

**Files:**
- Modify: `Backend/resources/views/reportes/contabilidad/honduras/libro-compras.blade.php`
- Create: `Backend/resources/views/reportes/contabilidad/honduras/libro-consumidores.blade.php`
- Create: `Backend/resources/views/reportes/contabilidad/honduras/libro-contribuyentes.blade.php`
- Modify: los tres exports HN para estilos `BeforeSheet`/`AfterSheet`

**Interfaces:**
- Consumes: contratos `rowsForApi()` de Tasks 1–3.
- Produces: PDF legal horizontal y XLSX con igual orden de columnas, encabezado, totales, resumen y firma.

- [ ] **Step 1: Crear encabezado común dentro de cada blade**

Cada blade debe obtener empresa una sola vez e imprimir:

```blade
@php $empresa = Auth::user()->empresa()->first(); @endphp
<h1>{{ $empresa->nombre }}</h1>
<h2>LIBRO DE VENTAS A CONSUMIDOR FINAL</h2>
<div class="meta">
  <span>MES: {{ \Carbon\Carbon::parse($request->inicio)->translatedFormat('F') }}</span>
  <span>AÑO: {{ \Carbon\Carbon::parse($request->inicio)->format('Y') }}</span>
  <span>NIT: {{ $empresa->nit }}</span>
  <span>NRC: {{ $empresa->ncr }}</span>
</div>
```

Cambiar únicamente el título por libro.

- [ ] **Step 2: Implementar tablas con agrupaciones exactas**

Usar dos filas de `<thead>` cuando el PDF agrupe columnas, `colspan`/`rowspan`, y recorrer las claves contractuales sin recalcular montos en Blade. Añadir fila `TOTAL`, bloques `Resumen`/`Resumen Operaciones` y:

```blade
<div class="firma">__________________________<br>Nombre y Firma de Contador</div>
```

- [ ] **Step 3: Configurar Excel**

En cada export, `BeforeSheet` inserta filas para empresa/título/mes/año/NIT/NRC. `AfterSheet` aplica negrita, bordes, wrap y formato `#,##0.00` a columnas monetarias. No mezclar celdas que impidan filtrar datos salvo títulos superiores.

- [ ] **Step 4: Ejecutar tests backend**

Run:

```powershell
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras tests\Feature\Contabilidad\Honduras
```

Expected: PASS.

- [ ] **Step 5: Generar muestras manuales**

Con sesión HN válida, abrir cada URL con `formato=pdf` y descargar cada XLSX. Comparar contra:

```text
formato-compras-honduras.pdf
formato-ventas-consumidores-honduras.pdf
formato-ventas-contribuyentes-honduras.pdf
```

Expected: mismo título, orden de columnas, agrupaciones, totales, resumen y firma; columnas sin datos visibles con 0/vacío.

---

### Task 6: Componentes Angular de los tres libros

**Files:**
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/consumidor-final/libro-iva-hd-consumidor-final.component.ts`
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/consumidor-final/libro-iva-hd-consumidor-final.component.html`
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/contribuyentes/libro-iva-hd-contribuyentes.component.ts`
- Create: `Frontend/src/app/views/contabilidad/libro-iva-hd/contribuyentes/libro-iva-hd-contribuyentes.component.html`
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-hd/compras/libro-iva-hd-compras.component.ts`
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-hd/compras/libro-iva-hd-compras.component.html`

**Interfaces:**
- Consumes: responses de Tasks 1–4.
- Produces: tablas web con todos los encabezados oficiales, filtros y descargas.

- [ ] **Step 1: Definir interfaces locales exactas**

En cada TS declarar su response; ejemplo consumidor:

```ts
interface LibroConsumidoresHnResponse {
  filas: Array<{
    no: number; fecha: string; factura_no: string; cai_no: string;
    maquina_registradora: string; exentas: number; exoneradas: number;
    gravadas_15: number; gravadas_18: number; total_ventas: number;
    cuenta_terceros: number;
  }>;
  resumen: {
    total_exentas: number; total_exoneradas: number; netas_15: number;
    netas_18: number; debito_fiscal: number; credito_fiscal: number;
  };
}
```

- [ ] **Step 2: Implementar carga y descarga**

Reusar la inicialización de filtros del componente HN actual. URLs:

```ts
this.apiService.get('libro-iva-hd/consumidores', this.filtros)
this.apiService.export('libro-iva-hd/consumidores/descargar-libro', this.filtros)
```

PDF debe usar el helper/forma actual con `formato=pdf`; repetir para contribuyentes y compras.

- [ ] **Step 3: Implementar tablas**

Cada HTML usa `<div class="table-responsive">`, encabezados con `rowspan`/`colspan` equivalentes al PDF, pipes `date:'dd/MM/yyyy'` y `currency`/`number:'1.2-2'`. Renderizar la fila total y los bloques resumen bajo la tabla.

- [ ] **Step 4: Compilar frontend**

Run desde `Frontend`:

```powershell
npm run build
```

Expected: build exitoso sin errores TypeScript/template.

- [ ] **Step 5: Verificar estados de UI**

Comprobar loading, vacío, error de API, scroll horizontal y botón Descargas deshabilitado mientras descarga para cada componente.

---

### Task 7: Navegación, rutas y regresión final

**Files:**
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-hd/libro-iva-hd-nav.component.html`
- Modify: `Frontend/src/app/views/contabilidad/contabilidad.routing.module.ts`
- Modify: `Frontend/src/app/views/contabilidad/libro-iva-shared/libro-iva-pais.service.ts`
- Optional delete after zero references: `Frontend/src/app/views/contabilidad/libro-iva-hd/ventas/libro-iva-hd-ventas.component.ts`
- Optional delete after zero references: `Frontend/src/app/views/contabilidad/libro-iva-hd/ventas/libro-iva-hd-ventas.component.html`

**Interfaces:**
- Produces: 5 pestañas HN y `/libro-iva-hd/ventas` redirigido.

- [ ] **Step 1: Actualizar nav**

Orden:

```html
<button class="btn" routerLinkActive="btn-primary" routerLink="/libro-iva-hd/contribuyentes">Contribuyentes</button>
<button class="btn" routerLinkActive="btn-primary" routerLink="/libro-iva-hd/consumidor-final">Consumidor Final</button>
<button class="btn" routerLinkActive="btn-primary" routerLink="/libro-iva-hd/compras">Compras</button>
<button class="btn" routerLinkActive="btn-primary" routerLink="/libro-iva-hd/retenciones">Retenciones</button>
<button class="btn" routerLinkActive="btn-primary" routerLink="/libro-iva-hd/resumen">Resumen</button>
```

- [ ] **Step 2: Registrar rutas lazy**

Crear rutas `contribuyentes`, `consumidor-final`, conservar compras/retenciones/resumen y convertir `ventas` en:

```ts
{ path: 'libro-iva-hd/ventas', redirectTo: 'libro-iva-hd/contribuyentes', pathMatch: 'full' }
```

Actualizar `rutaInicioLibroIva()` para Honduras a `/libro-iva-hd/contribuyentes`.

- [ ] **Step 3: Buscar referencias obsoletas**

Run desde raíz:

```powershell
rg "libro-iva-hd/ventas|LibroIvaHdVentasComponent|LibroVentasExport" Frontend Backend
```

Expected: solo redirect/deprecación intencional; eliminar componentes/export viejo únicamente si no quedan consumidores.

- [ ] **Step 4: Ejecutar verificación completa**

Run:

```powershell
cd Backend
vendor\bin\phpunit tests\Unit\Contabilidad\Honduras tests\Feature\Contabilidad\Honduras
cd ..\Frontend
npm run build
```

Expected: PHPUnit PASS y Angular build exitoso.

- [ ] **Step 5: Smoke manual de no regresión**

Con empresa HN: recorrer las 5 pestañas, cambiar periodo/sucursal y generar 6 archivos (3 PDF + 3 XLSX). Con empresas SV/CR/general: abrir sus libros y confirmar rutas/columnas existentes. Con empresa no HN: endpoints HN responden 403.

- [ ] **Step 6: Revisar diff final**

Run:

```powershell
git status --short
git diff --stat
git diff -- Backend/app/Exports/Contabilidad/Honduras Backend/resources/views/reportes/contabilidad/honduras Backend/app/Http/Controllers/Api/Contabilidad/LibrosIva Backend/routes/modulos/contabilidad/libros-iva-hd.php Frontend/src/app/views/contabilidad/libro-iva-hd Frontend/src/app/views/contabilidad/contabilidad.routing.module.ts
```

Expected: cambios limitados a Honduras, integración de rutas/controlador y tests; los tres PDF de referencia permanecen sin trackear salvo instrucción explícita del usuario.
