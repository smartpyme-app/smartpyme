# SP-2150 — Inventario según tipo de crédito

Kardex de créditos a clientes: el DTE de cada cuota es una **venta normal**. El inventario no puede bajar una vez por cada cuota.

Ticket: [SP-2150](https://smartpyme.atlassian.net/browse/SP-2150). Epic: [SP-1906](https://smartpyme.atlassian.net/browse/SP-1906).

## Objetivo

En un crédito tipo **bien** de N cuotas, el stock sale **una vez** (primera factura). Servicio y préstamo no mueven existencias. No hay desembolso ni gasto automático.

## Decisiones de producto (cerradas)

| Tema | Decisión |
|------|----------|
| Momento del kardex (bien) | Primera factura (cuota `numero == 1`) |
| Producto | Lo elige el facturador en `/venta/crear`, no se guarda en el contrato |
| Cuotas 2..N (bien) | Venta normal; **no** kardex aunque el detalle lleve `id_producto` |
| Servicio / préstamo | Venta normal; **nunca** kardex |
| Caja / desembolso | Fuera. No se crea gasto ni movimiento de caja |
| Anular DTE | Misma regla que al facturar. Cuota 1 bien: el stock **vuelve**. Resto: no mueve stock |
| Desvincular cuota al anular | No. Sigue sin poder facturarse dos veces |

## Regla

Una función pura, misma respuesta al facturar y al anular:

```
debeMoverInventario(tipo, numeroCuota) → bool
```

| `tipo` | `numeroCuota` | Resultado |
|--------|---------------|-----------|
| `bien` | 1 | `true` (motor actual: solo hay kardex si el detalle trae producto no-servicio) |
| `bien` | ≥ 2 | `false` |
| `servicio` | cualquiera | `false` |
| `prestamo` | cualquiera | `false` |
| venta sin cuota de crédito | — | no aplica (flujo actual) |

`true` no fuerza un movimiento: deja el motor de facturación igual. `false` enciende el skip. Si no hay `id_credito_cuota`, no se cambia nada.

## Arquitectura

Unidad: `App\Services\CreditosClientes\KardexCredito` (estático, como `TipoDocumentoCredito`).

Puntos de enganche (no se reescribe el motor de inventario):

1. **Facturar** — `FacturacionService::procesar`. Si la venta trae `id_credito_cuota`, cargar cuota + contrato. Si `debeMoverInventario` es false, poner `$saltarActualizarInventario = true` (mismo flag que pedidos de canal).
2. **Anular / cancelar anulación** — `VentasController` al ajustar stocks. Si la venta está ligada a una cuota (`credito_cuotas.id_venta`) y `debeMoverInventario` es false, no revertir ni volver a descontar.

Las líneas de detalle **no se alteran**. El DTE puede llevar ítem; solo se omite kardex/stock.

Resolver la cuota por `id_credito_cuota` al crear y por `id_venta` al anular.

## Fuera de alcance

- Botón Entregar / desembolso / gasto automático
- Producto, bodega o cantidad en el contrato
- Desvincular `id_venta` al anular
- Recalcular kardex si se anula la cuota 1 y luego se factura la 2 (la 2 sigue sin mover stock)
- Costo/utilidad por cuota, asientos de interés, devoluciones parciales
- UI nueva (el facturador sigue eligiendo producto como hoy)

## Tests

`Backend/tests/Unit/Services/CreditosClientes/KardexCreditoTest.php`:

- bien + cuota 1 → true
- bien + cuota 2 → false
- servicio + cuota 1 → false
- prestamo + cuota 1 → false

No hace falta test de integración de `FacturacionService` en este ticket: el skip de inventario ya existe; esta historia solo decide cuándo encenderlo.

## Criterios de aceptación (mapeo)

1. Bien, 3 cuotas: kardex baja en la cuota 1, no en 2 ni 3.
2. Servicio: 0 movimientos de inventario.
3. Préstamo: no descuenta existencias. El “desembolso” no se automatiza (venta normal).
4. Anular DTE de cuota 2+ no reingresa stock. Anular la cuota 1 (entrega) sí.
