# Gift Cards (Fase 2) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emitir gift cards al vender SKUs de la categoría Gift Cards, redimir saldo (parcial) como forma de pago en ventas reales, y generar comisión de redención solo si `comisiones-vendedores` está activo.

**Architecture:** Ledger `gift_cards` + `gift_card_redenciones`. Hooks post-venta pagada para emisión y redención. Integra con `ComisionService::registrarDesdeRedencion` vía `FuncionalidadAccess`. Compatible con `config('constants.FORMAS_PAGO_GIFT_CARD')` para el corte.

**Tech Stack:** Laravel (PHPUnit), Angular, DomPDF/Excel existentes (extender reportes de comisiones).

**Spec:** `Docs/superpowers/specs/2026-07-27-comisiones-bonos-gift-cards-design.md`  
**Prereq:** `Docs/superpowers/plans/2026-07-27-comisiones-vendedores.md` (Fase 0 slug `gift-cards` + `ComisionService::registrarDesdeRedencion`)

## Global Constraints

- Slug: `gift-cards`.
- Emisión = venta normal del SKU categoría Gift Cards + fila `gift_cards`.
- Redención = venta real del producto + pago gift card + `gift_card_redenciones`.
- `fecha_vencimiento` nullable; sin lógica de expiración en v1.
- Comisión solo en redención y solo si `comisiones-vendedores` activo.
- Pagos mixtos: comisión `redencion_gift_card` solo sobre monto cubierto por gift; resto de la línea como `origen=venta` (ajustar `ComisionService::registrarVentaPagada` para prorratear).
- No tumbar facturación si falla el ledger gift.
- Mantener sinónimos en `FORMAS_PAGO_GIFT_CARD`.

## File map

| File | Responsibility |
|------|----------------|
| `Backend/database/migrations/2026_07_27_170000_create_gift_cards_table.php` | Cards |
| `Backend/database/migrations/2026_07_27_170001_create_gift_card_redenciones_table.php` | Redenciones |
| `Backend/app/Models/GiftCards/GiftCard.php` | Model |
| `Backend/app/Models/GiftCards/GiftCardRedencion.php` | Model |
| `Backend/app/Services/GiftCards/GiftCardEmitService.php` | Emisión |
| `Backend/app/Services/GiftCards/GiftCardRedeemService.php` | Redención + check comisiones |
| `Backend/app/Services/GiftCards/GiftCardCategoryBootstrap.php` | Crear categoría Gift Cards al activar |
| `Backend/app/Services/Comisiones/ComisionService.php` | Prorrateo pagos mixtos |
| `Backend/routes/modulos/gift-cards.php` | API |
| `Frontend/src/app/views/gift-cards/**` | Consulta saldo, listado, UI redención en POS |

---

### Task 1: Migraciones y models

**Files:**
- Create migrations + models above

- [ ] **Step 1: Tabla `gift_cards`**

```php
Schema::create('gift_cards', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->string('codigo', 64);
    $table->decimal('monto_inicial', 14, 4);
    $table->decimal('saldo', 14, 4);
    $table->timestamp('fecha_emision');
    $table->timestamp('fecha_vencimiento')->nullable();
    $table->unsignedBigInteger('id_vendedor_emisor')->nullable();
    $table->unsignedBigInteger('id_venta_emision');
    $table->unsignedBigInteger('id_detalle_venta_emision')->nullable();
    $table->unsignedBigInteger('id_producto')->nullable();
    $table->string('estado', 20)->default('activa'); // activa|agotada|anulada
    $table->timestamps();
    $table->unique(['id_empresa', 'codigo']);
    $table->unique(['id_empresa', 'id_detalle_venta_emision'], 'gift_card_unique_detalle_emision');
});
```

- [ ] **Step 2: Tabla `gift_card_redenciones`**

```php
Schema::create('gift_card_redenciones', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('id_empresa');
    $table->unsignedBigInteger('id_gift_card');
    $table->unsignedBigInteger('id_venta');
    $table->unsignedBigInteger('id_vendedor')->nullable();
    $table->decimal('monto', 14, 4);
    $table->decimal('saldo_resultante', 14, 4);
    $table->unsignedBigInteger('id_categoria')->nullable();
    $table->unsignedBigInteger('id_subcategoria')->nullable();
    $table->unsignedBigInteger('id_comision_movimiento')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3: Models + scopes `id_empresa`**

- [ ] **Step 4: Migrate + commit**

```bash
git commit -m "$(cat <<'EOF'
feat(gift-cards): add gift card and redemption tables

EOF
)"
```

---

### Task 2: Generador de código + bootstrap categoría

**Files:**
- Create: `Backend/app/Services/GiftCards/GiftCardCodeGenerator.php`
- Create: `Backend/app/Services/GiftCards/GiftCardCategoryBootstrap.php`
- Create: `Backend/tests/Unit/Services/GiftCards/GiftCardCodeGeneratorTest.php`

- [ ] **Step 1: Test código único formato**

```php
public function test_genera_codigo_con_prefijo_y_longitud(): void
{
    $gen = new GiftCardCodeGenerator('GC', 12);
    $code = $gen->generate();
    $this->assertMatchesRegularExpression('/^GC[A-Z0-9]{10}$/', $code);
}
```

- [ ] **Step 2: Implementar generator (retry si unique falla en emit)**

- [ ] **Step 3: Bootstrap**

```php
public function ensureForEmpresa(Empresa $empresa): int
{
    // firstOrCreate categoria nombre "Gift Cards"
    // guardar id en empresa_funcionalidades.configuracion del slug gift-cards
    // si comisiones activo: comision_categoria_config porcentaje 0
    return $idCategoria;
}
```

Invocar al activar funcionalidad (hook en `EmpresasFuncionalidadesController` cuando slug=`gift-cards` y `activo=true`) o endpoint admin “Inicializar Gift Cards”.

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(gift-cards): code generator and Gift Cards category bootstrap

EOF
)"
```

---

### Task 3: Emisión post-venta

**Files:**
- Create: `Backend/app/Services/GiftCards/GiftCardEmitService.php`
- Modify: `FacturacionService` (try/catch post Pagada)

**Interfaces:**
- `GiftCardEmitService::emitirDesdeVenta(Venta $venta): void`

- [ ] **Step 1: Test unitario — ignora líneas no gift**

Con stubs: producto cuya `id_categoria` ≠ config gift → 0 cards.

- [ ] **Step 2: Implementar**

```text
if !gift-cards flag: return
foreach detalle where producto.id_categoria == id_categoria_gift_cards:
  firstOrCreate gift_cards by id_detalle_venta_emision
  monto_inicial = total línea (o gravada según política: usar total cobrado de la línea)
  saldo = monto_inicial
  estado = activa
```

- [ ] **Step 3: Hook FacturacionService + commit**

```bash
git commit -m "$(cat <<'EOF'
feat(gift-cards): emit card ledger when Gift Cards SKU is sold

EOF
)"
```

---

### Task 4: Redención + integración comisiones

**Files:**
- Create: `Backend/app/Services/GiftCards/GiftCardRedeemService.php`
- Create: `Backend/tests/Unit/Services/GiftCards/GiftCardRedeemServiceTest.php`
- Modify: `FacturacionService` — tras guardar métodos de pago, si hay pago gift card
- Modify: `ComisionService::registrarVentaPagada` — prorrateo mixto

**Interfaces:**
- `redeem(Venta $venta, string $codigo, float $monto, int $idVendedorAtencion): GiftCardRedencion`
- Runtime: `FuncionalidadAccess::empresaTieneSlug(..., 'comisiones-vendedores')`

- [ ] **Step 1: Test saldo insuficiente lanza**

```php
public function test_saldo_insuficiente(): void
{
    $this->expectException(\DomainException::class);
    // card saldo 10, redeem 15
}
```

- [ ] **Step 2: Test parcial deja activa; redeem exacto → agotada**

- [ ] **Step 3: Test con comisiones on llama registrarDesdeRedencion; off no llama**

Usar mock/spy del `ComisionService`.

- [ ] **Step 4: Implementar `GiftCardRedeemService`** dentro de DB transaction propia o la de facturación:

```php
$card = GiftCard::where('codigo', $codigo)->lockForUpdate()->firstOrFail();
if ($card->saldo < $monto) throw new \DomainException('Saldo insuficiente');
$card->saldo -= $monto;
if ($card->saldo <= 0) { $card->saldo = 0; $card->estado = 'agotada'; }
$card->save();
$redencion = GiftCardRedencion::create([...]);
if (FuncionalidadAccess::empresaTieneSlug($idEmpresa, 'comisiones-vendedores')) {
    $mov = app(ComisionService::class)->registrarDesdeRedencion(...);
    $redencion->id_comision_movimiento = $mov?->id;
    $redencion->save();
}
```

- [ ] **Step 5: Detección forma de pago**

Reutilizar `Indicador::esFormaPagoGiftCard` o `in_array` de `config('constants.FORMAS_PAGO_GIFT_CARD')`. El request de facturación debe enviar `codigo_gift_card` (o lista) junto al método de pago.

- [ ] **Step 6: Prorrateo en `registrarVentaPagada`**

```text
fraccion_gift = monto_pagado_gift / total_venta (cap 1)
para cada línea elegible:
  base_venta = base * (1 - fraccion_gift)   // o prorrateo por línea si hay mapping explícito
  // parte gift no genera origen=venta; la genera redeem
```

Documentar en código la política v1: **prorrateo proporcional por total de venta** (`ponytail: ceiling = no line-level payment allocation; upgrade = per-line gift application`).

- [ ] **Step 7: PHPUnit PASS + commit**

```bash
git commit -m "$(cat <<'EOF'
feat(gift-cards): redeem balance and optional commission on redemption

EOF
)"
```

---

### Task 5: Reverso en anulación/devolución de venta de canje

**Files:**
- Modify: hooks anulación / `DevolucionVentasController`
- Create: `GiftCardReverseService.php`

- [ ] **Step 1:** Si se anula venta con redenciones: sumar montos de vuelta al saldo; estado `activa` si saldo > 0; anular/ajustar comisión ligada (regla B vía `ComisionService`).

- [ ] **Step 2:** Idempotencia — no restaurar dos veces (`reversed_at` en redención o unique flag).

- [ ] **Step 3: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(gift-cards): reverse redemption balance on voided redemption sales

EOF
)"
```

---

### Task 6: API + Frontend POS

**Files:**
- `Backend/routes/modulos/gift-cards.php` + require en `api.php`
- Controllers: consulta por código, listado, (opcional) anulación admin
- FE: campo código gift card en facturación cuando flag on; pantalla consulta saldo
- Guard: `funcionalidadSlug: 'gift-cards'`

- [ ] **Step 1: `GET gift-cards/by-codigo/{codigo}`** — saldo, estado (auth + flag)

- [ ] **Step 2: UI facturación — input código + monto al elegir forma de pago gift**

- [ ] **Step 3: Extender Excel comisiones** — ya tiene columna origen; verificar filas `redencion_gift_card` aparecen

- [ ] **Step 4: Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(gift-cards): API lookup and POS redemption UI

EOF
)"
```

---

### Task 7: Verificación Fase 2

- [ ] Emitir: vender SKU Gift Cards → fila `gift_cards`, **sin** `comision_movimientos` de esa línea.
- [ ] Redimir parcial dos veces → saldos correctos; tercera por encima de saldo falla.
- [ ] Comisiones on → movimiento `redencion_gift_card` con % de categoría del producto canjeado.
- [ ] Comisiones off → redención ok, `id_comision_movimiento` null.
- [ ] Pago mixto → no doble comisión sobre el mismo monto.
- [ ] Corte de caja sigue excluyendo forma de pago gift por nombre.

## Self-review

| Spec | Task |
|------|------|
| Emisión A + categoría 0% | 2, 3 |
| Redención A parcial | 4 |
| Check runtime comisiones | 4 |
| vencimiento nullable | 1 |
| Reverso | 5 |
| Reportes identifican redención | 6 |
