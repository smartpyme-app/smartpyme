# Crédito desde facturación

Al marcar **Venta al crédito**, si la empresa tiene `creditos-clientes`, un botón abre el plan de cuotas. Al confirmar y procesar la venta: se crea el contrato y **esta factura es la cuota 1**.

## Regla de montos

- El total de la venta **antes del plan** = monto del contrato.
- Esta factura se recorta a la **cuota 1** (mismas cantidades de producto; se escala el precio). Kardex de bien: 1 salida por cantidad, no por el precio.
- Cuotas 2..N quedan programadas. Ventas → Créditos sigue para facturarlas.

## Modal

Tipo, N cuotas (≥2), fecha inicio, concepto. Preview. Tasas en 0.

Sin plan configurado, el checkbox sigue siendo el crédito clásico (una factura). Cola (`credito_cuota` en la URL) no muestra el botón.

## Backend

`credito_contrato` en el POST de facturación. Misma transacción: venta + contrato + vincular cuota 1. El total de la venta debe coincidir con la cuota 1. Cupo: no contar esta venta dos veces.

Kardex: `credito_contrato.tipo` + cuota 1 (servicio/préstamo no mueven stock).
