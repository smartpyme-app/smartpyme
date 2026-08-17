# QA checklist — Gift Cards (Fase 2)

**Branch:** `feat/gift-cards`  
**Entorno:** staging (empresa de prueba)  
**Automated:** `php vendor/bin/phpunit tests/Unit/Services/GiftCards/ tests/Unit/Services/Comisiones/` → 38/38 PASS

---

## Step 0 — Preparación

- [ ] Super Admin → activar **Gift Cards** (`gift-cards`) para la empresa staging.
- [ ] Confirmar categoría **Gift Cards** bootstrap y al menos un SKU de prueba en esa categoría.
- [ ] (Escenarios comisiones) Activar también **Comisiones de Vendedores** (`comisiones-vendedores`) y asignar **2%** a la categoría del producto que se canjeará (no la categoría Gift Cards).

## Step 1 — Emisión (sin comisión)

- [ ] Facturar venta **Pagada** vendiendo SKU categoría Gift Cards (p. ej. $50).
- [ ] Verificar fila en `gift_cards`:
  - `codigo` generado, `monto_inicial = saldo`, `estado = activa`
  - `id_venta_emision` / `id_detalle_venta_emision` apuntan a la venta/línea
  - `fecha_vencimiento` nullable (puede ser null)
- [ ] Confirmar **no** existe fila en `comision_movimientos` para `id_detalle_venta_emision` de esa línea gift.

## Step 2 — Redención parcial (dos veces + fallo saldo)

- [ ] Canjear tarjeta emitida en Step 1 en venta real **Pagada** (producto de otra categoría):
  - Forma de pago gift + `codigo_gift_card` en request
  - Monto gift **menor** al saldo (p. ej. $20 de $50)
- [ ] Verificar `gift_card_redenciones` (monto, `saldo_resultante`), `gift_cards.saldo` actualizado, estado `activa`.
- [ ] Segunda redención parcial (p. ej. $15) → saldos coherentes.
- [ ] Tercer intento por monto **mayor** al saldo restante → facturación rechazada / error saldo insuficiente; saldo sin cambio.

## Step 3 — Comisiones ON → `redencion_gift_card`

- [ ] Con `comisiones-vendedores` activo, redimir gift card sobre producto con categoría al **2%** configurado.
- [ ] Verificar fila en `comision_movimientos`:
  - `origen = redencion_gift_card`
  - `id_gift_card_redencion` = redención recién creada
  - `porcentaje_aplicado = 2`, `monto_comision = monto_base × 0.02`
- [ ] **Comisiones → Reportes** Excel: fila con origen **Redención Gift Card (redencion_gift_card)**.

## Step 4 — Comisiones OFF → redención sin comisión

- [ ] Desactivar `comisiones-vendedores` (mantener `gift-cards` on).
- [ ] Redimir otra gift card activa → venta **Pagada** ok.
- [ ] Verificar `gift_card_redenciones.id_comision_movimiento` **null** y **sin** fila nueva `origen = redencion_gift_card`.

## Step 5 — Pago mixto (no doble comisión)

- [ ] Reactivar comisiones; facturar venta **Pagada** $100 con pago mixto: **$40 Gift Card** + **$60 Efectivo** (mismo código gift).
- [ ] Para la línea canjeada, sumar bases de comisión `origen=venta` + `origen=redencion_gift_card`:
  - La parte gift (~40%) debe ir a `redencion_gift_card`
  - El resto (~60%) a `origen=venta`
  - **Total comisionado sobre la línea ≈ 100% del subtotal**, no 2× el monto completo.

## Step 6 — Corte de caja (excluye gift por nombre)

- [ ] Registrar ventas Pagadas con forma de pago **Gift Card** / **Tarjeta de regalo** (sinónimos en `FORMAS_PAGO_GIFT_CARD`).
- [ ] Abrir **Corte de caja** del periodo → el monto pagado con gift **no** suma al total de ventas del corte (misma regla que antes de Fase 2).
- [ ] Probar al menos un sinónimo alterno (p. ej. `Giftcard`) si la empresa lo usa en catálogo de formas de pago.

---

## Sign-off

| Rol | Nombre | Fecha | Resultado |
|-----|--------|-------|-----------|
| QA / Product | | | ☐ Pass ☐ Fail |
| Dev | | | Automated unit tests OK |

**Notas / incidencias:**
