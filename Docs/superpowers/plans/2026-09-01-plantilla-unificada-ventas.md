# Plantilla unificada de importación de ventas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Una sola plantilla Excel de ventas históricas, validada por fila (fila + columna + motivo), sin importación parcial.

**Architecture:** Un validador puro decide errores y agrupación sin BD. `VentasExcelImport` lo usa, crea clientes/ventas y aborta si hay errores. El controller devuelve 422 con `errores[]`. El frontend muestra la tabla y solo cierra el modal en éxito.

**Tech Stack:** Laravel, Maatwebsite Excel, PhpSpreadsheet, PHPUnit, Angular.

**Spec:** `Docs/superpowers/specs/2026-09-01-plantilla-unificada-ventas-design.md`

## Global Constraints

- Discriminar con `tipo_cliente` + `tipo_documento_venta`; nunca inferir por NIT.
- Crédito fiscal exige `nit` y `nrc` aunque el cliente sea Persona.
- `correlativo` obligatorio (histórico); no autogenerar.
- Detalle siempre `Servicio`, `id_producto = 0`; no buscar inventario.
- `forma_pago` por nombre: Efectivo, Tarjeta de crédito/débito, Cheque, Transferencia, Vales, Chivo Wallet, Bitcoin.
- Cualquier error → rollback, `procesadas = 0`, lista estructurada.
- Éxito → cerrar modal + success. Error → modal abierto con tabla Fila | Columna | Error.
- Sin compatibilidad con las dos plantillas viejas.
- No tocar `Frontend/src/environments/environment.ts`.

## File map

| File | Role |
|------|------|
| `Backend/app/Support/Ventas/VentasImportFilaValidador.php` | Validación y agrupación puras |
| `Backend/tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php` | Tests del validador |
| `Backend/app/Imports/VentasExcelImport.php` | Import real; usa el validador |
| `Backend/tests/Unit/Imports/VentasExcelImportConsumidorFinalTest.php` | Ajustar a errores estructurados |
| `Backend/app/Http/Controllers/Api/Ventas/VentasImportController.php` | 200 / 422 con `errores[]` |
| `Backend/tests/Unit/Http/Controllers/Api/Ventas/VentasImportControllerTest.php` | Contrato HTTP |
| `Backend/app/Console/Commands/GenerarPlantillasCommand.php` | Una plantilla `ventas-format.xlsx` |
| `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.ts` | Pintar errores; no cerrar en fallo |
| `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.html` | Un link + tabla de errores |

---

### Task 1: Validador puro

**Files:**
- Create: `Backend/app/Support/Ventas/VentasImportFilaValidador.php`
- Create: `Backend/tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php`

**Interfaces:**
- Produces: `VentasImportFilaValidador`
  - `COLUMNAS_REQUERIDAS_ARCHIVO`: `tipo_cliente`, `tipo_documento_venta`, `correlativo`
  - `validarEncabezados(array $keys): list<array{fila:int,columna:string,mensaje:string}>`
  - `validarFila(array $fila, int $filaExcel): list<array{...}>`
  - `validarAgrupacion(array $filas): list<array{...}>` — `$filas` items con `fila` (excel) + datos
  - `claveAgrupacion(array $fila): string`
  - `tipoItemDetalle(): string` → `'Servicio'`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentasImportFilaValidador;
use PHPUnit\Framework\TestCase;

class VentasImportFilaValidadorTest extends TestCase
{
    private VentasImportFilaValidador $v;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v = new VentasImportFilaValidador();
    }

    private function fila(array $over = []): array
    {
        return array_merge([
            'tipo_cliente' => 'Persona',
            'tipo_documento_venta' => 'Factura',
            'correlativo' => 10,
            'nombre' => 'Juan Perez',
            'tipo_documento' => 'DUI',
            'num_documento' => '05027470-7',
            'fecha' => '2025-02-03',
            'descripcion' => 'Servicio A',
            'forma_pago' => 'Efectivo',
            'total' => 113,
            'condicion' => 'Contado',
        ], $over);
    }

    public function test_encabezados_viejos_error_de_archivo(): void
    {
        $err = $this->v->validarEncabezados(['nombre', 'nit', 'fecha']);
        $this->assertNotEmpty($err);
        $this->assertSame('tipo_cliente', $err[0]['columna']);
        $this->assertSame(1, $err[0]['fila']);
    }

    public function test_persona_factura_valida(): void
    {
        $this->assertSame([], $this->v->validarFila($this->fila(), 2));
    }

    public function test_persona_ccf_sin_nit_nrc(): void
    {
        $err = $this->v->validarFila($this->fila([
            'tipo_documento_venta' => 'Crédito fiscal',
            'nit' => '',
            'nrc' => '',
        ]), 12);
        $cols = array_column($err, 'columna');
        $this->assertContains('nit', $cols);
        $this->assertContains('nrc', $cols);
        $this->assertSame(12, $err[0]['fila']);
        $this->assertStringContainsString('Crédito fiscal', $err[0]['mensaje']);
    }

    public function test_persona_ccf_con_nit_nrc_ok(): void
    {
        $this->assertSame([], $this->v->validarFila($this->fila([
            'tipo_documento_venta' => 'Crédito fiscal',
            'nit' => '0614-010190-001-1',
            'nrc' => '123456-7',
        ]), 3));
    }

    public function test_sin_correlativo(): void
    {
        $err = $this->v->validarFila($this->fila(['correlativo' => '']), 5);
        $this->assertSame('correlativo', $err[0]['columna']);
        $this->assertSame(5, $err[0]['fila']);
    }

    public function test_forma_pago_invalida(): void
    {
        $err = $this->v->validarFila($this->fila(['forma_pago' => 'PayPal']), 4);
        $this->assertSame('forma_pago', $err[0]['columna']);
    }

    public function test_agrupa_mismo_correlativo(): void
    {
        $a = $this->fila(['descripcion' => 'A']);
        $b = $this->fila(['descripcion' => 'B']);
        $this->assertSame($this->v->claveAgrupacion($a), $this->v->claveAgrupacion($b));
        $this->assertSame([], $this->v->validarAgrupacion([
            array_merge($a, ['fila' => 2]),
            array_merge($b, ['fila' => 3]),
        ]));
    }

    public function test_mismo_correlativo_dos_clientes(): void
    {
        $err = $this->v->validarAgrupacion([
            array_merge($this->fila(), ['fila' => 2]),
            array_merge($this->fila(['num_documento' => '11111111-1', 'nombre' => 'Otro']), ['fila' => 3]),
        ]);
        $this->assertNotEmpty($err);
        $this->assertSame('correlativo', $err[0]['columna']);
    }

    public function test_tipo_item_siempre_servicio(): void
    {
        $this->assertSame('Servicio', $this->v->tipoItemDetalle());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php`

Expected: FAIL (class not found).

- [ ] **Step 3: Implement the validador**

Create `Backend/app/Support/Ventas/VentasImportFilaValidador.php`:

- Normalizar celdas con `trim`; vacío = `''`.
- Encabezados: cada clave de `COLUMNAS_REQUERIDAS_ARCHIVO` debe existir en `$keys` (comparar en lowercase). Error `fila=1`.
- `validarFila`:
  - Siempre: `tipo_cliente` ∈ Persona|Empresa, `tipo_documento_venta` ∈ Factura|Ticket|Crédito fiscal|Factura de exportación, `correlativo` no vacío, `nombre`, `fecha`, `descripcion`, `total` (numérico), `forma_pago` ∈ lista (obligatorio).
  - Si documento es Crédito fiscal (comparar sin importar acentos: `credito fiscal`): `nit` y `nrc` no vacíos.
  - Si Persona y no CCF: si `nombre` no es "Consumidor Final" (case-insensitive), exigir `tipo_documento` y `num_documento`.
  - Si `condicion` es Crédito/Credito: exigir `fecha_pago`.
  - `tipo_cliente` / documento / forma_pago / condicion fuera de lista → error en esa columna.
- `claveAgrupacion`: `{correlativo}|{tipo_documento_venta normalizado}|{identidad}` donde identidad = nit/nrc si CCF o Empresa, si no `num_documento` o `nombre`.
- `validarAgrupacion`: agrupar por correlativo+tipo_documento_venta; si hay más de una identidad → error en cada fila del grupo, columna `correlativo`. Si mismo correlativo con tipo_documento_venta distinto → error igual.
- `tipoItemDetalle()`: `return 'Servicio';`

- [ ] **Step 4: Re-run tests**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php`

Expected: PASS.

---

### Task 2: Wiring en VentasExcelImport

**Files:**
- Modify: `Backend/app/Imports/VentasExcelImport.php`
- Modify: `Backend/tests/Unit/Imports/VentasExcelImportConsumidorFinalTest.php`

**Interfaces:**
- Consumes: `VentasImportFilaValidador`
- Produces: `getErrores(): list<array{fila:int,columna:string,mensaje:string}>`
- Cambia `determinarTipoDocumento`: usa `tipo_cliente` / `tipo_documento_venta`, no NIT.
- Si el validador reporta errores, no crear ventas; rollback.

- [ ] **Step 1: Update existing test + add structured-error test**

In `VentasExcelImportConsumidorFinalTest.php`:

- `getErrores()[0]` ya no es string. Ajustar `test_descripcion_faltante_se_reporta_como_campo_obligatorio`:
  - Fila ahora incluye `tipo_cliente`, `tipo_documento_venta`, `correlativo`.
  - Assert `$err[0]['columna'] === 'descripcion'` y `$err[0]['fila']` int.
- Añadir test: `validarFilaRequeridos` / flujo de validador: Persona + CCF sin nit acumula error columna nit.

- [ ] **Step 2: Run tests — expect fail on string asserts**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Imports/VentasExcelImportConsumidorFinalTest.php`

- [ ] **Step 3: Wire import**

In `VentasExcelImport`:

1. Construct `protected VentasImportFilaValidador $validador`.
2. `errores` es `array` de `{fila,columna,mensaje}`. Helper `agregarError(int $fila, string $col, string $msg)`.
3. Al inicio de `collection`, tomar keys de la primera fila no vacía (o de `$rows->first()`) y `validarEncabezados`. Si fallan, throw con esos errores.
4. Primera pasada: por cada fila no vacía, `$excelRow = $index + 2`, `$this->errores = array_merge(..., $this->validador->validarFila($rowArr, $excelRow))`.
5. Si `errores` no vacío: rollback, throw `VentasImportValidationException` (o Exception cuyo message sea JSON/lista; el controller leerá `getErrores()`).
6. Segunda pasada: `validarAgrupacion`; si errores, igual.
7. `determinarTipoDocumento($fila)`: si `tipo_documento_venta` normalizado es credito fiscal → `credito_fiscal`; si no → `consumidor_final`. `crearCliente`: `tipo` = `tipo_cliente` (Persona/Empresa), no inferido.
8. `generarClienteKey`: usar `$this->validador->claveAgrupacion($row)`.
9. `obtenerDatosDetalle`: `tipo_item` = `$this->validador->tipoItemDetalle()`; no buscar producto.
10. `extraerCorrelativoFila`: si falta, no autogenerar (el validador ya lo bloqueó).
11. Actualizar class docblock.

No importar hojas 2+.

- [ ] **Step 4: Re-run import unit tests**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Imports/VentasExcelImportConsumidorFinalTest.php tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php`

Expected: PASS.

---

### Task 3: Controller 422

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Ventas/VentasImportController.php`
- Create: `Backend/app/Exceptions/VentasImportValidationException.php` (si no se usó Exception genérica con getErrores)
- Create: `Backend/tests/Unit/Http/Controllers/Api/Ventas/VentasImportControllerTest.php`

**Interfaces:**
- 422: `{ message, procesadas: 0, errores: [...] }`
- 200: `{ message: "Se importaron N ventas correctamente.", procesadas: N, errores: [] }`

- [ ] **Step 1: Test del payload**

Test que instancia el controller con un Import mock es pesado. En su lugar: test unitario de un método estático o del exception:

```php
public function test_payload_error_estructurado(): void
{
    $payload = VentasImportController::payloadErrores([
        ['fila' => 12, 'columna' => 'nit', 'mensaje' => 'obligatorio porque tipo_documento_venta es Crédito fiscal'],
    ]);
    $this->assertSame(0, $payload['procesadas']);
    $this->assertSame(12, $payload['errores'][0]['fila']);
    $this->assertStringContainsString('3 errores', str_replace('1 error', '1 errores', '')); // no
    $this->assertStringContainsString('1 error', $payload['message']);
}
```

Use singular/plural: `Hay 1 error.` / `Hay 3 errores.`

- [ ] **Step 2: Implement `payloadErrores` + cambiar `importar`**

```php
if (count($errores) > 0) {
    return response()->json(self::payloadErrores($errores), 422);
}
return response()->json([
    'message' => "Se importaron {$ventasExitosas} ventas correctamente.",
    'procesadas' => $ventasExitosas,
    'errores' => [],
], 200);
```

Catch: si el import lanza y `getErrores()` no está vacío, 422 con esa lista. Si es otra excepción, 400 con `{ message, procesadas: 0, errores: [{ fila: 0, columna: 'archivo', mensaje: $e->getMessage() }] }`.

- [ ] **Step 3: Run controller + validador tests**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Http/Controllers/Api/Ventas/VentasImportControllerTest.php tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php tests/Unit/Imports/VentasExcelImportConsumidorFinalTest.php`

Expected: PASS.

---

### Task 4: Plantilla Excel única

**Files:**
- Modify: `Backend/app/Console/Commands/GenerarPlantillasCommand.php`

**Interfaces:**
- `php artisan ventas:generar-plantillas` escribe `public/docs/ventas-format.xlsx` (no las dos viejas).
- Encabezados según spec. Dropdowns según spec. Hoja Instrucciones con reglas CCF/Persona/correlativo/formas de pago.
- Hoja Valores: si hay DB, volcar nombres de departamentos, municipios, distritos, giros; si no, dejar columnas vacías y los dropdowns enum igual funcionan.

- [ ] **Step 1: Cambiar handle() a un solo `generarPlantillaUnificada()`**

Encabezados exactos del spec (orden). Validaciones:

- tipo_cliente: `"Persona,Empresa"`
- tipo_documento_venta: `"Factura,Ticket,Crédito fiscal,Factura de exportación"`
- tipo_documento: `"DUI,NIT,Pasaporte,Carnet de residente,Otro"`
- estado_factura: `"Pagada,Pendiente,Anulada"`
- tipo_item: `"Servicio"`
- forma_pago: `"Efectivo,Tarjeta de crédito/débito,Cheque,Transferencia,Vales,Chivo Wallet,Bitcoin"`
- condicion: `"Contado,Crédito"`

Instrucciones: correlativo obligatorio; CCF pide nit/nrc aunque Persona; detalle siempre Servicio; formas de pago de la lista.

Borrar o no generar `ventas-credito-fiscal-format.xlsx` y `ventas-consumidor-final-format.xlsx`.

- [ ] **Step 2: Run command**

Run: `cd Backend && php artisan ventas:generar-plantillas`

Expected: archivo en `Backend/public/docs/ventas-format.xlsx`. Verificar encabezado fila 1 con Python/PhpSpreadsheet o `php -r` leyendo la primera fila.

---

### Task 5: Frontend — un link, errores visibles, success cierra

**Files:**
- Modify: `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.html`
- Modify: `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.ts`

**Interfaces:**
- Ventas: un `<a href=".../docs/ventas-format.xlsx">Descargar plantilla de ventas</a>`
- `ventasErrores: {fila,columna,mensaje}[]`
- Error HTTP: asignar `error.error.errores`, no cerrar modal, no success.
- Success ventas: `alertService.success`, cerrar modal, `loadAll.emit()`.

- [ ] **Step 1: HTML**

Reemplazar el bloque de dos links por uno. Debajo del file input, si `ventasErrores.length`:

```html
<div class="table-responsive validation-errors" *ngIf="nombre === 'ventas' && ventasErrores.length">
  <p class="text-danger mb-2">No se importó ninguna venta. Corrija el Excel y vuelva a subirlo.</p>
  <table class="table table-sm table-striped">
    <thead><tr><th>Fila</th><th>Columna</th><th>Error</th></tr></thead>
    <tbody>
      <tr *ngFor="let e of ventasErrores">
        <td>{{ e.fila }}</td>
        <td>{{ e.columna }}</td>
        <td>{{ e.mensaje }}</td>
      </tr>
    </tbody>
  </table>
</div>
```

- [ ] **Step 2: TS**

- `ventasErrores: {fila:number,columna:string,mensaje:string}[] = []`
- Al `setFile` / al inicio de `onSubmit`: `ventasErrores = []`
- En success de ventas: success + close (como ahora).
- En error de ventas: `ventasErrores = error?.error?.errores ?? []`; si viene `error.error.error` string viejo, un solo item `{fila:0,columna:'archivo',mensaje}`. `alertService.modal = true`. No `modalRef.hide()`.

- [ ] **Step 3: `calcularPlantillaUrl` para ventas**

`this.plantillaUrl = ${baseUrl}/docs/ventas-format.xlsx` para poder reutilizar el link único.

---

## Verificación final

```bash
cd Backend && php vendor/bin/phpunit tests/Unit/Support/Ventas/VentasImportFilaValidadorTest.php tests/Unit/Imports/VentasExcelImportConsumidorFinalTest.php tests/Unit/Http/Controllers/Api/Ventas/VentasImportControllerTest.php
ls -la public/docs/ventas-format.xlsx
```

Frontend: abrir modal Importar ventas, ver un link, subir Excel malo, tabla de errores, modal abierto; Excel bueno, modal cierra y success.
