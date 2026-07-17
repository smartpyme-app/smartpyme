# Ajustes de consignas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir el pool virtual de consigna tras pagos parciales, listar compras en consigna (no productos), permitir ajustar a 0 en revisión, y etiquetar canal “Consigna” en ventas totales sin cambiar `id_canal`.

**Architecture:** Flag durable `compras.es_consigna` para liquidaciones; fórmula `disponible = min(max(0, entrada − max(0, ventas − liquidado)), stock_fisico)` en `ConsignaDisponibleService`; API/UI de consignas-compras reorientada a filas de compra; etiqueta de display en `VentasDetallesExport`.

**Tech Stack:** Laravel (PHPUnit), Angular 15, Maatwebsite Excel.

**Spec:** `Docs/superpowers/specs/2026-07-17-consignas-ajustes-design.md`

## Global Constraints

- No tocar documento de remisión en ventas (fuera de alcance).
- No crear canal de catálogo “Consigna” ni modificar `id_canal` al facturar.
- No rediseñar tab Ventas de `/consignas`.
- No emitir MH DTE `04`.
- Stock físico / kardex en recepción y venta: sin cambios de comportamiento.
- `facturacionConsigna` sigue sin mover inventario físico.

## File map

| File | Responsibility |
|------|----------------|
| `Backend/database/migrations/2026_07_17_120000_add_es_consigna_to_compras_table.php` | Columna + backfill |
| `Backend/app/Models/Compras/Compra.php` | `es_consigna` fillable/cast |
| `Backend/app/Http/Controllers/Api/Compras/ComprasController.php` | Set flag al crear/pagar consigna |
| `Backend/app/Services/Inventario/ConsignaDisponibleService.php` | Fórmula con liquidado |
| `Backend/tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php` | Tests de fórmula |
| `Backend/app/Http/Controllers/Api/Inventario/ConsignasController.php` | `indexCompras` por compra |
| `Backend/app/Exports/ConsignasComprasExport.php` | Export alineado |
| `Frontend/.../consignas-compras/productos-consignas-compras.component.{ts,html}` | UI listado compras |
| `Frontend/.../facturacion-consigna/facturacion-compra-consigna.component.html` | Ajuste a 0 |
| `Backend/app/Exports/VentasDetallesExport.php` | Etiqueta canal |
| `Backend/tests/Unit/Exports/VentasDetallesCanalConsignaTest.php` | Test etiqueta (helper) |

---

### Task 1: Fórmula pura + tests del pool virtual

**Files:**
- Create: `Backend/tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php`
- Modify: `Backend/app/Services/Inventario/ConsignaDisponibleService.php`

**Interfaces:**
- Consumes: componentes numéricos entrada / ventas / liquidado / stock físico
- Produces: `ConsignaDisponibleService::calcularDisponibleDesdeComponentes(float $entradaAbierta, float $ventasConsigna, float $liquidado, float $stockFisico): float`

- [ ] **Step 1: Escribir test fallido**

```php
<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\ConsignaDisponibleService;
use PHPUnit\Framework\TestCase;

class ConsignaDisponibleServiceTest extends TestCase
{
    public function test_tras_vender_4_y_pagar_4_de_10_disponible_es_6(): void
    {
        // entrada abierta 6, ventas 4, liquidado 4, físico 6
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 4, 4, 6);
        $this->assertEquals(6.0, $disponible);
    }

    public function test_vende_4_sin_pagar_disponible_es_6(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(10, 4, 0, 6);
        $this->assertEquals(6.0, $disponible);
    }

    public function test_paga_4_sin_ventas_disponible_es_6(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 0, 4, 10);
        $this->assertEquals(6.0, $disponible);
    }

    public function test_formula_legacy_doble_resta_fallaria_aqui(): void
    {
        // Si alguien restara ventas sin liquidado: max(0, 6-4)=2 — no debe pasar
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(6, 4, 4, 6);
        $this->assertNotEquals(2.0, $disponible);
    }

    public function test_tope_por_stock_fisico(): void
    {
        $disponible = ConsignaDisponibleService::calcularDisponibleDesdeComponentes(10, 0, 0, 3);
        $this->assertEquals(3.0, $disponible);
    }
}
```

- [ ] **Step 2: Correr test y verificar fallo**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php -v`  
Expected: FAIL — método `calcularDisponibleDesdeComponentes` no existe.

- [ ] **Step 3: Implementar método estático y usarlo en `calcularDisponible`**

En `ConsignaDisponibleService.php`, agregar:

```php
public static function calcularDisponibleDesdeComponentes(
    float $entradaAbierta,
    float $ventasConsigna,
    float $liquidado,
    float $stockFisico
): float {
    $salidaEfectiva = max(0, $ventasConsigna - $liquidado);
    $disponible = max(0, $entradaAbierta - $salidaEfectiva);

    return min($disponible, $stockFisico);
}
```

Actualizar `calcularDisponible` para:

```php
public function calcularDisponible(int $idProducto, int $idBodega, ?int $excluirVentaId = null): float
{
    $entrada = $this->sumEntradaComprasConsigna($idProducto, $idBodega);
    $salida = $this->sumSalidaVentasDesdeConsignaCompra($idProducto, $idBodega, $excluirVentaId);
    $liquidado = $this->sumLiquidadoComprasConsigna($idProducto, $idBodega);
    $stockFisico = $this->obtenerStockFisico($idProducto, $idBodega);

    return self::calcularDisponibleDesdeComponentes($entrada, $salida, $liquidado, $stockFisico);
}
```

Agregar stub temporal de `sumLiquidadoComprasConsigna` que retorne `0.0` (se completa en Task 3) para que el archivo compile:

```php
private function sumLiquidadoComprasConsigna(int $idProducto, int $idBodega): float
{
    return 0.0;
}
```

- [ ] **Step 4: Correr tests y verificar pass**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php -v`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/Inventario/ConsignaDisponibleService.php \
  Backend/tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php
git commit -m "$(cat <<'EOF'
fix: add consigna pool formula that subtracts only unsettled sales

EOF
)"
```

---

### Task 2: Migración `es_consigna` + modelo Compra

**Files:**
- Create: `Backend/database/migrations/2026_07_17_120000_add_es_consigna_to_compras_table.php`
- Modify: `Backend/app/Models/Compras/Compra.php`

**Interfaces:**
- Consumes: tabla `compras`, kardex `detalle = 'Compra a consigna'`, `referencia = compras.id`
- Produces: columna `es_consigna` boolean default false; Compra fillable + cast

- [ ] **Step 1: Crear migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->boolean('es_consigna')->default(false)->after('estado');
        });

        DB::table('compras')->where('estado', 'Consigna')->update(['es_consigna' => true]);

        $idsDesdeKardex = DB::table('kardexs')
            ->where('detalle', 'Compra a consigna')
            ->distinct()
            ->pluck('referencia');

        if ($idsDesdeKardex->isNotEmpty()) {
            DB::table('compras')
                ->whereIn('id', $idsDesdeKardex)
                ->where('estado', 'Pagada')
                ->update(['es_consigna' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('es_consigna');
        });
    }
};
```

Nota: confirmar nombre real de tabla kardex (`kardexs` vs `kardex`) en el modelo `Kardex` antes de migrar; usar el nombre de `$table` del modelo.

- [ ] **Step 2: Actualizar modelo Compra**

Agregar `'es_consigna'` a `$fillable` y en `$casts`:

```php
'es_consigna' => 'boolean',
```

- [ ] **Step 3: Commit**

```bash
git add Backend/database/migrations/2026_07_17_120000_add_es_consigna_to_compras_table.php \
  Backend/app/Models/Compras/Compra.php
git commit -m "$(cat <<'EOF'
feat: add es_consigna flag on compras with historical backfill

EOF
)"
```

---

### Task 3: Setear `es_consigna` en facturación y completar `sumLiquidado`

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Compras/ComprasController.php`
- Modify: `Backend/app/Services/Inventario/ConsignaDisponibleService.php`

**Interfaces:**
- Consumes: `Compra::$es_consigna`, estado `Consigna` / `Pagada`
- Produces: `sumLiquidadoComprasConsigna` real; flag persistido en create/split/pay

- [ ] **Step 1: En `facturacion()`, tras `fill` y antes de `save`, forzar flag**

Después de `$compra->fill(...)` (aprox. línea 320):

```php
if ($compra->estado === 'Consigna') {
    $compra->es_consigna = true;
}
```

- [ ] **Step 2: En `facturacionConsigna()`, preservar/setear flag**

Al crear remanente (`$consigna = $compra->replicate()`), después de setear estado:

```php
$consigna->es_consigna = true;
$consigna->estado = 'Consigna';
```

Antes de `$compra->estado = 'Pagada'`:

```php
$compra->es_consigna = true; // liquidación; no limpiar
$compra->estado = 'Pagada';
```

(Si la compra original ya tenía `es_consigna=true`, el assign es idempotente.)

- [ ] **Step 3: Implementar `sumLiquidadoComprasConsigna`**

Reemplazar el stub:

```php
private function sumLiquidadoComprasConsigna(int $idProducto, int $idBodega): float
{
    return (float) DetalleCompra::query()
        ->where('id_producto', $idProducto)
        ->whereHas('compra', function ($query) use ($idBodega) {
            $query->where('es_consigna', true)
                ->where('estado', 'Pagada')
                ->where('id_bodega', $idBodega)
                ->where('cotizacion', 0);
        })
        ->sum('cantidad');
}
```

- [ ] **Step 4: Verificar tests de fórmula siguen pasando**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php -v`  
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Compras/ComprasController.php \
  Backend/app/Services/Inventario/ConsignaDisponibleService.php
git commit -m "$(cat <<'EOF'
fix: count paid consigna qty as settled so pool stops double-subtracting

EOF
)"
```

---

### Task 4: API listado consignas-compras por compra + export

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/Inventario/ConsignasController.php` (`indexCompras`)
- Modify: `Backend/app/Exports/ConsignasComprasExport.php`

**Interfaces:**
- Consumes: `Compra` con `estado = Consigna`, `cotizacion = 0`
- Produces: JSON array de compras con `detalles[]`; export una fila por línea de detalle (o por compra — preferir **una fila por detalle** para Excel útil)

- [ ] **Step 1: Reescribir `indexCompras`**

```php
public function indexCompras()
{
    $compras = Compra::query()
        ->where('estado', 'Consigna')
        ->where('cotizacion', 0)
        ->with([
            'proveedor',
            'bodega:id,nombre',
            'sucursal:id,nombre',
            'detalles.producto:id,nombre,codigo,img,id_categoria',
            'detalles.producto.categoria:id,nombre',
        ])
        ->orderByDesc('fecha')
        ->orderByDesc('id')
        ->get();

    $rows = $compras->map(function (Compra $compra) {
        return [
            'id' => $compra->id,
            'uuid' => Crypt::encrypt($compra->id),
            'fecha' => $compra->fecha,
            'proveedor' => $compra->nombre_proveedor,
            'id_proveedor' => $compra->id_proveedor,
            'tipo_documento' => $compra->tipo_documento,
            'referencia' => $compra->referencia,
            'fecha_pago' => $compra->fecha_pago,
            'estado' => $compra->estado,
            'bodega' => $compra->bodega?->nombre ?? '',
            'sucursal' => $compra->sucursal?->nombre ?? '',
            'total' => $compra->total,
            'detalles' => $compra->detalles->map(function ($detalle) {
                return [
                    'id' => $detalle->id,
                    'id_producto' => $detalle->id_producto,
                    'producto' => $detalle->producto?->nombre,
                    'codigo' => $detalle->producto?->codigo,
                    'cantidad' => $detalle->cantidad,
                    'costo' => $detalle->costo,
                    'total' => $detalle->total,
                ];
            })->values(),
        ];
    })->values();

    return response()->json($rows, 200);
}
```

Asegurar `use App\Models\Compras\Compra;` (y quitar lógica `groupBy` producto / `ConsignaDisponibleService` de este método).  
No modificar `disponible`, `ventasConsignaCompra`, ni `index` de consignas ventas.

- [ ] **Step 2: Reescribir `ConsignasComprasExport`**

```php
public function headings(): array
{
    return [
        'Fecha',
        'Proveedor',
        'Documento',
        'Referencia',
        'Fecha pago',
        'Bodega',
        'Sucursal',
        'Producto',
        'Código',
        'Cantidad',
        'Costo',
        'Total línea',
        'Total compra',
    ];
}

public function map($row): array
{
    return [
        $row['fecha'],
        $row['proveedor'],
        $row['tipo_documento'],
        $row['referencia'],
        $row['fecha_pago'],
        $row['bodega'],
        $row['sucursal'],
        $row['producto'],
        $row['codigo'],
        $row['cantidad'],
        $row['costo'],
        $row['total_linea'],
        $row['total_compra'],
    ];
}

public function collection()
{
    $compras = \App\Models\Compras\Compra::query()
        ->where('estado', 'Consigna')
        ->where('cotizacion', 0)
        ->with(['detalles.producto', 'bodega', 'sucursal'])
        ->orderByDesc('fecha')
        ->get();

    $rows = collect();
    foreach ($compras as $compra) {
        foreach ($compra->detalles as $detalle) {
            $rows->push([
                'fecha' => $compra->fecha,
                'proveedor' => $compra->nombre_proveedor,
                'tipo_documento' => $compra->tipo_documento,
                'referencia' => $compra->referencia,
                'fecha_pago' => $compra->fecha_pago,
                'bodega' => $compra->bodega?->nombre ?? '',
                'sucursal' => $compra->sucursal?->nombre ?? '',
                'producto' => $detalle->producto?->nombre,
                'codigo' => $detalle->producto?->codigo,
                'cantidad' => $detalle->cantidad,
                'costo' => $detalle->costo,
                'total_linea' => $detalle->total,
                'total_compra' => $compra->total,
            ]);
        }
    }

    return $rows;
}
```

- [ ] **Step 3: Commit**

```bash
git add Backend/app/Http/Controllers/Api/Inventario/ConsignasController.php \
  Backend/app/Exports/ConsignasComprasExport.php
git commit -m "$(cat <<'EOF'
feat: list consigna purchases by compra instead of product aggregate

EOF
)"
```

---

### Task 5: Frontend listado consignas tab Compras

**Files:**
- Modify: `Frontend/src/app/views/inventario/consignas-compras/productos-consignas-compras.component.ts`
- Modify: `Frontend/src/app/views/inventario/consignas-compras/productos-consignas-compras.component.html`
- Modify: `Frontend/src/app/views/inventario/consignas-compras/productos-consignas-compras.component.spec.ts` (si rompe por propiedades)

**Interfaces:**
- Consumes: `GET productos/consignas-compras` → array de compras con `detalles`
- Produces: tabla tipo compras; Revisar → `/compra/consigna/revisar/:id`; Detalles → modal productos

- [ ] **Step 1: Actualizar componente TS**

```typescript
export class ProductosConsignasComprasComponent implements OnInit {
  public compras: any[] = [];
  public buscador: string = '';
  public loading: boolean = false;
  public downloading: boolean = false;
  public compra: any = {};
  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadAll();
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('productos/consignas-compras').subscribe(
      (compras) => {
        this.compras = compras;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public openDetalles(template: TemplateRef<any>, compra: any) {
    this.compra = compra;
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('productos/consignas-compras/exportar', {}).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'consignas-compras.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloading = false;
      }
    );
  }
}
```

Quitar filtros de categoría / modales de ventas-por-producto / `isShopifyActive` si ya no se usan.

- [ ] **Step 2: Reescribir HTML**

Columnas: Fecha | Proveedor | Documento | Fecha pago | Estado | Total | Acciones.

Acciones:
- Revisar: `routerLink="/compra/consigna/revisar/{{ compra.id }}"` (mismo patrón que `compras.component.html`).
- Detalles: `(click)="openDetalles(mdetalles, compra)"`.

Modal `#mdetalles`: tabla de `compra.detalles` con Producto, Código, Cantidad, Costo, Total.

Mantener tabs Compras / Ventas en la cabecera.

Buscador: filtrar por `proveedor` / `referencia` (pipe `filter` existente o `*ngFor` con método simple).

Empty state: “No tiene compras en consigna”.

- [ ] **Step 3: Ajustar spec del componente**

Si el spec solo hace `should create`, asegurar que `ApiService` / `AlertService` / `BsModalService` estén mockeados como en otros componentes del módulo, o dejar el smoke test compilando.

- [ ] **Step 4: Commit**

```bash
git add Frontend/src/app/views/inventario/consignas-compras/
git commit -m "$(cat <<'EOF'
feat: show consigna purchases list with review and product details

EOF
)"
```

---

### Task 6: Permitir ajustar cantidad a 0 en revisión

**Files:**
- Modify: `Frontend/src/app/views/compras/facturacion/facturacion-consigna/facturacion-compra-consigna.component.html`

**Interfaces:**
- Consumes: `cantidadVendidaDetalle` (puede ser 0)
- Produces: botón “Ajustar cantidad” habilitado; `ajustarCantidadVendida()` ya asigna 0

- [ ] **Step 1: Quitar disable**

Cambiar:

```html
<button type="button" class="btn btn-primary" (click)="ajustarCantidadVendida()" [disabled]="cantidadVendidaDetalle <= 0">
    Ajustar cantidad
</button>
```

Por:

```html
<button type="button" class="btn btn-primary" (click)="ajustarCantidadVendida()">
    Ajustar cantidad
</button>
```

No hace falta cambiar el método TS (`detalleVendido.cantidad = this.cantidadVendidaDetalle` ya acepta 0). El backend en `facturacionConsigna` ya omite líneas con `cantidad > 0` al armar la Pagada y mueve el resto al remanente.

- [ ] **Step 2: Commit**

```bash
git add Frontend/src/app/views/compras/facturacion/facturacion-consigna/facturacion-compra-consigna.component.html
git commit -m "$(cat <<'EOF'
fix: allow adjusting consigna review qty to zero when nothing sold

EOF
)"
```

---

### Task 7: Etiqueta canal “Consigna” en ventas totales

**Files:**
- Modify: `Backend/app/Exports/VentasDetallesExport.php`
- Create: `Backend/tests/Unit/Exports/VentasDetallesCanalConsignaTest.php` (helper estático o método protegido testeable)

**Interfaces:**
- Consumes: venta con relación `detalles` (o el detalle actual + hermanos) y `origen_stock`
- Produces: string canal display `"Consigna"` | nombre canal | null

- [ ] **Step 1: Extraer helper y test**

Agregar método estático o de instancia en el export (preferible estático puro para unit test sin Excel):

```php
public static function nombreCanalParaExport(?\App\Models\Ventas\Venta $venta): ?string
{
    if (!$venta) {
        return null;
    }

    $detalles = $venta->relationLoaded('detalles')
        ? $venta->detalles
        : $venta->detalles()->get(['id', 'id_venta', 'origen_stock']);

    foreach ($detalles as $detalle) {
        if (\App\Constants\OrigenStockVentaConstants::esConsignaCompra($detalle->origen_stock ?? null)) {
            return 'Consigna';
        }
    }

    return $venta->canal?->nombre;
}
```

Test:

```php
<?php

namespace Tests\Unit\Exports;

use App\Constants\OrigenStockVentaConstants;
use App\Exports\VentasDetallesExport;
use PHPUnit\Framework\TestCase;

class VentasDetallesCanalConsignaTest extends TestCase
{
    public function test_devuelve_consigna_si_alguna_linea_tiene_origen_consigna_compra(): void
    {
        $venta = new class {
            public $canal;
            public $detalles;
            public function relationLoaded($r) { return true; }
        };
        $venta->canal = (object) ['nombre' => 'Tienda'];
        $venta->detalles = collect([
            (object) ['origen_stock' => OrigenStockVentaConstants::NORMAL],
            (object) ['origen_stock' => OrigenStockVentaConstants::CONSIGNA_COMPRA],
        ]);

        $this->assertSame('Consigna', VentasDetallesExport::nombreCanalParaExport($venta));
    }

    public function test_devuelve_canal_normal_si_no_hay_origen_consigna(): void
    {
        $venta = new class {
            public $canal;
            public $detalles;
            public function relationLoaded($r) { return true; }
        };
        $venta->canal = (object) ['nombre' => 'Tienda'];
        $venta->detalles = collect([
            (object) ['origen_stock' => OrigenStockVentaConstants::NORMAL],
        ]);

        $this->assertSame('Tienda', VentasDetallesExport::nombreCanalParaExport($venta));
    }
}
```

Nota: si el type-hint estricto de `Venta` rompe el stub anónimo, tipar el helper como `object|Venta|null` o usar un DTO mínimo. Preferir tipar `?object` / sin type-hint estricto solo en el parámetro del helper si hace falta para el test.

- [ ] **Step 2: Correr test — debe fallar**

Run: `cd Backend && php vendor/bin/phpunit tests/Unit/Exports/VentasDetallesCanalConsignaTest.php -v`  
Expected: FAIL (método no existe).

- [ ] **Step 3: Implementar helper + usarlo en `map()`**

Reemplazar línea del canal:

```php
($venta && $venta->canal) ? $venta->canal->nombre : null,
```

Por:

```php
self::nombreCanalParaExport($venta),
```

En `query()` / eager load del export, asegurar que la venta cargue detalles con `origen_stock`. Buscar el `with([...])` existente y agregar algo como:

```php
'venta.detalles:id,id_venta,origen_stock',
```

(ajustar columnas según el `with` actual para no romper select restringidos).

Alternativa más barata si ya se itera por detalle: además del `$row->origen_stock`, chequear si **cualquier** detalle de esa venta tiene consigna — para eso hace falta la colección de hermanos vía `venta.detalles`.

- [ ] **Step 4: Correr tests**

Run:

```bash
cd Backend && php vendor/bin/phpunit \
  tests/Unit/Exports/VentasDetallesCanalConsignaTest.php \
  tests/Unit/Services/Inventario/ConsignaDisponibleServiceTest.php -v
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Exports/VentasDetallesExport.php \
  Backend/tests/Unit/Exports/VentasDetallesCanalConsignaTest.php
git commit -m "$(cat <<'EOF'
feat: label sales channel as Consigna when line uses consigna_compra stock

EOF
)"
```

---

### Task 8: Verificación manual / checklist de aceptación

**Files:** ninguno (QA)

- [ ] **Step 1: Migrar local**

```bash
cd Backend && php artisan migrate
```

- [ ] **Step 2: Checklist**

1. `/consignas` tab Compras muestra compras `estado=Consigna`; Revisar abre revisión; Detalles muestra productos.
2. Recibir 10 en consigna → vender 4 con origen consigna → pagar 4 → API `productos/consigna-disponible` (o UI) muestra **6**.
3. Revisión: producto con vendido 0 → Ajustar cantidad pone 0 → al guardar queda en remanente consigna.
4. Export detalle ventas totales: venta con `origen_stock=consigna_compra` → columna Canal = `Consigna`; `id_canal` en BD sin cambio.
5. Remisión en ventas sin regresiones (smoke: marcar consigna en facturación sigue eligiendo remisión).

- [ ] **Step 3: Commit docs si aún no están**

```bash
git add Docs/superpowers/specs/2026-07-17-consignas-ajustes-design.md \
  Docs/superpowers/plans/2026-07-17-consignas-ajustes.md
git commit -m "$(cat <<'EOF'
docs: add consignas adjustments design and implementation plan

EOF
)"
```

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Listado compras en consigna + Revisar + Detalles | 4, 5 |
| Fix stock liquidado / escenario 10→4→4=6 | 1, 2, 3 |
| Ajuste a 0 | 6 |
| Canal etiqueta Consigna sin tocar `id_canal` | 7 |
| Remisión omitida | Global Constraints |
| Tests fórmula | 1 |
| Flag `es_consigna` + backfill kardex | 2 |

Sin placeholders TBD. Tipos alineados: `calcularDisponibleDesdeComponentes`, `nombreCanalParaExport`, `es_consigna`.
