# Cierre de caja por bodega Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El reporte Cierre de caja permite elegir criterio Por sucursal (comportamiento actual) o Por bodega (solo operaciones de esa bodega), con PDF alineado.

**Architecture:** Mismo `GET /api/corte` e `Indicador`. El frontend envía `id_sucursal` **o** `id_bodega`. `Indicador` aplica `when(id_bodega)` en queries de ventas; si hay bodega, no carga gastos. `CorteCriterio` fuerza la bodega del usuario Ventas. El PDF lee `id_bodega` por query param.

**Tech Stack:** Angular (Frontend), Laravel (Backend), PHPUnit.

**Spec:** `Docs/superpowers/specs/2026-08-13-cierre-caja-por-bodega-design.md`

## Global Constraints

- Default: criterio **Por sucursal**.
- Criterios exclusivos: nunca enviar `id_sucursal` e `id_bodega` a la vez.
- Sin opción “Todas las bodegas”.
- Gastos en cierre por bodega = 0 (no filtrar `egresos` por `id_bodega`).
- No tocar dashboard (`DashController::index`) ni `caja_cortes`.
- No extraer un helper masivo de ubicación en `Indicador`; solo agregar `when($this->id_bodega)`.
- Canal no se agrega al PDF.
- No cambiar el enforcement de `id_sucursal` en el API.

---

### Task 1: `CorteCriterio` (resolver bodega por rol)

**Files:**
- Create: `Backend/app/Services/Admin/CorteCriterio.php`
- Test: `Backend/tests/Unit/Services/Admin/CorteCriterioTest.php`

**Interfaces:**
- Consumes: objeto usuario con `tipo` e `id_bodega`; el `id_bodega` del request
- Produces: `CorteCriterio::esUsuarioVentas(?string $tipo): bool` y `CorteCriterio::resolverIdBodega(object $usuario, $idBodegaRequest)` (int|string|null)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\CorteCriterio;
use PHPUnit\Framework\TestCase;

class CorteCriterioTest extends TestCase
{
    public function test_usuario_ventas_no_puede_usar_otra_bodega(): void
    {
        $usuario = (object) ['tipo' => 'Ventas', 'id_bodega' => 7];
        $this->assertSame(7, CorteCriterio::resolverIdBodega($usuario, 99));
    }

    public function test_usuario_ventas_limitado_tampoco_puede_usar_otra_bodega(): void
    {
        $usuario = (object) ['tipo' => 'Ventas Limitado', 'id_bodega' => 3];
        $this->assertSame(3, CorteCriterio::resolverIdBodega($usuario, 99));
    }

    public function test_admin_conserva_la_bodega_solicitada(): void
    {
        $usuario = (object) ['tipo' => 'Administrador', 'id_bodega' => 7];
        $this->assertSame(99, CorteCriterio::resolverIdBodega($usuario, 99));
    }

    public function test_supervisor_conserva_la_bodega_solicitada(): void
    {
        $usuario = (object) ['tipo' => 'Supervisor', 'id_bodega' => 1];
        $this->assertSame(5, CorteCriterio::resolverIdBodega($usuario, 5));
    }

    public function test_sin_id_bodega_en_request_no_fuerza_bodega(): void
    {
        $usuario = (object) ['tipo' => 'Ventas', 'id_bodega' => 7];
        $this->assertNull(CorteCriterio::resolverIdBodega($usuario, null));
        $this->assertNull(CorteCriterio::resolverIdBodega($usuario, ''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Admin/CorteCriterioTest.php -v`

Expected: FAIL because class `CorteCriterio` does not exist.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Admin;

class CorteCriterio
{
    public static function esUsuarioVentas(?string $tipo): bool
    {
        return in_array($tipo, ['Ventas', 'Ventas Limitado'], true);
    }

    public static function resolverIdBodega(object $usuario, $idBodegaRequest)
    {
        $idBodega = $idBodegaRequest ?: null;
        if ($idBodega && self::esUsuarioVentas($usuario->tipo ?? null)) {
            return $usuario->id_bodega ?: null;
        }

        return $idBodega;
    }
}
```

- [ ] **Step 4: Run tests and make sure they pass**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/Services/Admin/CorteCriterioTest.php -v`

Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Services/Admin/CorteCriterio.php Backend/tests/Unit/Services/Admin/CorteCriterioTest.php
git commit -m "Add CorteCriterio to lock warehouse filter for sales users."
```

---

### Task 2: `Indicador` filtra por `id_bodega` y omite gastos

**Files:**
- Modify: `Backend/app/Models/Indicador.php` (`$fillable`, constructor)
- Test: `Backend/tests/Unit/IndicadorBodegaFillableTest.php`

**Interfaces:**
- Consumes: `id_bodega` en atributos del constructor (string|int|null)
- Produces: colecciones de ventas/devoluciones/abonos filtradas por `id_bodega`; `$this->gastos = collect()` si hay `id_bodega`

- [ ] **Step 1: Write the failing test** (fillable + omisión de gastos sin pegarle a la DB)

```php
<?php

namespace Tests\Unit;

use App\Models\Indicador;
use PHPUnit\Framework\TestCase;

class IndicadorBodegaFillableTest extends TestCase
{
    public function test_fillable_incluye_id_bodega(): void
    {
        $defaults = (new \ReflectionClass(Indicador::class))->getDefaultProperties();
        $this->assertContains('id_bodega', $defaults['fillable']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/IndicadorBodegaFillableTest.php -v`

Expected: FAIL — `id_bodega` not in fillable.

- [ ] **Step 3: Add `id_bodega` to fillable and apply filters in the constructor**

In `$fillable`, after `'id_sucursal'`, add `'id_bodega'`.

In every venta / `whereHas('venta')` block that already has `when($this->id_sucursal, ...)`, add immediately after it:

```php
->when($this->id_bodega, function($q){
    $q->where('id_bodega', $this->id_bodega);
})
```

Places (all inside the constructor):

1. `detalles_metodos_de_pago` `whereHas('venta')` (after sucursal when)
2. `$this->ventas`
3. `$this->ventas_pagadas`
4. `$this->ventas_anuladas`
5. `$this->devoluciones_ventas` `whereHas('venta')`
6. `$this->cxc`
7. `$this->abonos` `whereHas('venta')`

Do **not** add it to `$this->compras`, `$this->devoluciones_compras`, `$this->cxp`, or `getTotalesSalidas`.

Replace the `$this->gastos = Gasto::...` assignment with:

```php
if ($this->id_bodega) {
    $this->gastos = collect();
} else {
    $this->gastos = Gasto::where('id_empresa', $this->id_empresa)
                    ->when($this->id_sucursal, function($q){
                        $q->where('id_sucursal', $this->id_sucursal);
                    })
                    ->when($this->id_usuario, function($q){
                        $q->where('id_usuario', $this->id_usuario);
                    })
                    ->where('estado', '!=', 'Cancelado')
                    ->whereBetween('fecha', [$this->inicio, $this->fin])
                    ->get();
}
```

- [ ] **Step 4: Run fillable test**

Run: `cd Backend && ./vendor/bin/phpunit tests/Unit/IndicadorBodegaFillableTest.php -v`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Backend/app/Models/Indicador.php Backend/tests/Unit/IndicadorBodegaFillableTest.php
git commit -m "Filter cash close indicators by warehouse and skip expenses."
```

---

### Task 3: `DashController` pasa `id_bodega` (JSON y PDF)

**Files:**
- Modify: `Backend/app/Http/Controllers/Api/DashController.php` (`corte`, `cortePdf`)

**Interfaces:**
- Consumes: `CorteCriterio::resolverIdBodega`; request `id_bodega`
- Produces: `Indicador` constructed with exclusive `id_sucursal` or `id_bodega`

- [ ] **Step 1: Add import**

```php
use App\Services\Admin\CorteCriterio;
```

- [ ] **Step 2: Update `corte()`**

Replace the `new Indicador([...])` block with:

```php
$idSucursal = $request->id_sucursal ?: null;
$idBodega = CorteCriterio::resolverIdBodega($usuario, $request->id_bodega);

$indicadores = new Indicador([
    'inicio' => $request->fecha,
    'fin' => $request->fecha,
    'id_empresa' => $usuario->id_empresa,
    'id_sucursal' => $idBodega ? null : $idSucursal,
    'id_bodega' => $idBodega,
    'id_usuario' => $request->id_usuario,
    'id_canal' => $request->id_canal,
]);
```

If both arrive, bodega wins (frontend should not send both; this keeps totals exclusive).

- [ ] **Step 3: Update `cortePdf` signature and body**

```php
public function cortePdf(Request $request, $id_usuario = null, $id_sucursal = null, $fechaDe = null)
{
    if ($id_sucursal == 'null') {
        $id_sucursal = null;
    }

    if ($id_usuario == 'null') {
        $id_usuario = null;
    }

    $usuario = JWTAuth::parseToken()->authenticate();

    if (!$fechaDe)
        $fechaDe = date("Y-m-d");

    $idBodega = CorteCriterio::resolverIdBodega($usuario, $request->query('id_bodega'));

    $indicadores = new Indicador([
        'inicio' => $fechaDe,
        'fin' => $fechaDe,
        'id_empresa' => $usuario->id_empresa,
        'id_sucursal' => $idBodega ? null : $id_sucursal,
        'id_bodega' => $idBodega,
        'id_usuario' => $id_usuario,
    ]);

    $pdf = app('dompdf.wrapper')->loadView('reportes.corte', compact('indicadores'));
    return $pdf->stream();
}
```

Laravel injects `Request` as the first argument; the route path params stay `{id_usuario?}/{id_sucursal?}/{fecha?}`.

- [ ] **Step 4: Commit**

```bash
git add Backend/app/Http/Controllers/Api/DashController.php
git commit -m "Pass warehouse id into cash close JSON and PDF endpoints."
```

---

### Task 4: Selector de criterio en Cierre de caja (UI)

**Files:**
- Modify: `Frontend/src/app/views/reportes/corte/corte.component.ts`
- Modify: `Frontend/src/app/views/reportes/corte/corte.component.html`

**Interfaces:**
- Consumes: `bodegas/list`, `CorteCriterio` rules on the client (same Ventas types)
- Produces: `GET /api/corte` with either `id_sucursal` or `id_bodega`; PDF URL with `?id_bodega=` when criterio is bodega

- [ ] **Step 1: Update `corte.component.ts`**

Replace the component class with:

```typescript
import { Component, OnInit } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
    selector: 'app-corte',
    templateUrl: './corte.component.html'
})
export class CorteComponent implements OnInit {

    public usuario:any = {};
    public indicadores:any = {};
    public sucursales:any = [];
    public bodegas:any = [];
    public usuarios:any = [];
    public canales:any = [];
    public filtros:any = {};

    constructor(public apiService: ApiService, public alertService: AlertService) {}

    ngOnInit(){
        this.usuario = this.apiService.auth_user();

        this.filtros.criterio = 'sucursal';
        this.filtros.id_bodega = '';
        this.filtros.id_canal = '';
        this.filtros.fecha = this.apiService.date();

        if(this.esUsuarioVentas()){
            this.filtros.id_sucursal = this.usuario.id_sucursal;
            this.filtros.id_usuario = this.usuario.id;
        }else{
            this.filtros.id_sucursal = '';
            this.filtros.id_usuario = '';
        }

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
            if(this.filtros.id_sucursal){
                this.sucursales = sucursales.filter((item:any) => item.id == this.filtros.id_sucursal);
            }
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
            if(this.esUsuarioVentas()){
                this.bodegas = bodegas.filter((item:any) => item.id == this.usuario.id_bodega);
            }
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
            if(this.apiService.auth_user().tipo != 'Administrador' && this.apiService.auth_user().tipo != 'Supervisor'){
                this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
            }
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales/list').subscribe(canales => {
            this.canales = canales;
        }, error => {this.alertService.error(error);});

        this.filtrar();
    }

    public esUsuarioVentas(): boolean {
        return this.usuario.tipo == 'Ventas' || this.usuario.tipo == 'Ventas Limitado';
    }

    public onCriterioChange(){
        if(this.filtros.criterio === 'bodega'){
            this.filtros.id_sucursal = '';
            if(this.esUsuarioVentas()){
                if(!this.usuario.id_bodega){
                    this.filtros.id_bodega = '';
                    this.alertService.warning('Cierre de caja', 'No tienes una bodega asignada para consultar el cierre por bodega.');
                    return;
                }
                this.filtros.id_bodega = this.usuario.id_bodega;
            }else{
                this.filtros.id_bodega = this.usuario.id_bodega || '';
            }
        }else{
            this.filtros.id_bodega = '';
            if(this.esUsuarioVentas()){
                this.filtros.id_sucursal = this.usuario.id_sucursal;
            }else{
                this.filtros.id_sucursal = '';
            }
        }
        this.filtrar();
    }

    public paramsCorte(): any {
        const params:any = {
            fecha: this.filtros.fecha,
            id_usuario: this.filtros.id_usuario,
            id_canal: this.filtros.id_canal
        };
        if(this.filtros.criterio === 'bodega'){
            params.id_bodega = this.filtros.id_bodega;
        }else{
            params.id_sucursal = this.filtros.id_sucursal;
        }
        return params;
    }

    public descargar(){
        if(this.filtros.criterio === 'bodega' && !this.filtros.id_bodega){
            this.alertService.warning('Cierre de caja', 'Seleccione una bodega para descargar el cierre.');
            return;
        }
        const idUsuario = this.filtros.id_usuario ? this.filtros.id_usuario : null;
        const idSucursal = this.filtros.criterio === 'sucursal' && this.filtros.id_sucursal ? this.filtros.id_sucursal : null;
        let url = this.apiService.baseUrl + '/api/corte/documento/' + idUsuario + '/' + idSucursal + '/' + this.filtros.fecha + '?token=' + this.apiService.auth_token();
        if(this.filtros.criterio === 'bodega' && this.filtros.id_bodega){
            url += '&id_bodega=' + this.filtros.id_bodega;
        }
        window.open(url, 'Impresión', 'width=400');
    }

    public filtrar(){
        if(this.filtros.criterio === 'bodega' && !this.filtros.id_bodega){
            this.indicadores = {};
            return;
        }
        this.apiService.getAll('corte', this.paramsCorte()).subscribe(indicadores => {
            this.indicadores = indicadores;
        }, error => {this.alertService.error(error); });
    }

    public onUsuarioClear(){
        this.filtros.id_usuario = '';
        this.filtrar();
    }

}
```

- [ ] **Step 2: Update the filter bar in `corte.component.html`**

Replace the inner `d-flex gap-2` block (download + filters) with:

```html
        <div class="d-flex gap-2">
            <button tooltip="Descargar" (click)="descargar()" class="btn btn-default tcla-F6"><img src="/assets/icons/download.png" class="icon"></button>
            <input type="date" name="fecha" (change)="filtrar()" [(ngModel)]="filtros.fecha" class="form-control">
            <ng-select [(ngModel)]="filtros.id_usuario" (change)="filtrar()" (clear)="onUsuarioClear()" [clearable]="filtros.id_usuario !== ''" class="form-select  p-0" name="filtros.id_usuario">
                <ng-option value="">Todos los usuarios</ng-option>
                <ng-option *ngFor="let usuario of usuarios" [value]="usuario.id">
                    {{ usuario.name }}
                </ng-option>
            </ng-select>
            <select name="criterio" (change)="onCriterioChange()" [(ngModel)]="filtros.criterio" class="form-select">
                <option value="sucursal">Por sucursal</option>
                <option value="bodega">Por bodega</option>
            </select>
            <select *ngIf="filtros.criterio !== 'bodega'" name="id_sucursal" (change)="filtrar()" [(ngModel)]="filtros.id_sucursal" class="form-select">
                <option value="" *ngIf="usuario.tipo != 'Ventas' && usuario.tipo != 'Ventas Limitado'">Todas las sucursal</option>
                <option *ngFor="let sucursal of sucursales" [value]="sucursal.id">
                    {{sucursal.nombre}}
                </option>
            </select>
            <select *ngIf="filtros.criterio === 'bodega'" name="id_bodega" (change)="filtrar()" [(ngModel)]="filtros.id_bodega" class="form-select">
                <option value="" *ngIf="!esUsuarioVentas()">Seleccione una bodega</option>
                <option *ngFor="let bodega of bodegas" [value]="bodega.id">
                    {{ bodega.nombre }} ({{ bodega.nombre_sucursal }})
                </option>
            </select>
            <select name="id_canal" (change)="filtrar()" [(ngModel)]="filtros.id_canal" class="form-select">
                <option value="">Todos los canales</option>
                <option *ngFor="let canal of canales" [value]="canal.id">
                    {{ canal.nombre }}
                </option>
            </select>
        </div>
```

Leave the rest of the template (cards and tables) unchanged.

- [ ] **Step 3: Commit**

```bash
git add Frontend/src/app/views/reportes/corte/corte.component.ts Frontend/src/app/views/reportes/corte/corte.component.html
git commit -m "Add warehouse criterion selector to cash close report."
```

---

## Self-review

- Spec coverage: selector sucursal/bodega → Task 4. Filtro Indicador y gastos 0 → Task 2. Roles Ventas → Task 1 + 3 + 4. PDF `id_bodega` → Task 3 + 4. Dashboard / caja_cortes / canal en PDF / helper masivo → no hay tasks.
- Placeholder scan: none.
- Types: `criterio` is `'sucursal' | 'bodega'`; `CorteCriterio::resolverIdBodega` used in `corte` and `cortePdf`.
