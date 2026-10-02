# Revisión: venta pendiente de pago contabilizada contra banco

Fecha: 2026-10-02  
Alcance: diagnóstico. La corrección quedó aplicada en código el mismo día (ver el final de este documento).

## Síntoma

Al generar la partida contable de una **venta con estado Pendiente** (pendiente de pago), el Debe queda en la cuenta del banco de la forma de pago. En el caso reportado esa cuenta es **Banco Promerica**.

Lo esperado: el Debe de esa venta va a **Cuentas por Cobrar** (`contabilidad_configuracion.id_cuenta_cxc`). El banco entra recién cuando se registra el abono.

El nombre “Banco Promerica” no está escrito en el código. Sale de la cadena forma de pago → banco → cuenta contable del banco. Si esa forma de pago apunta a Promerica, la partida usa esa cuenta.

## Causa

La partida individual de una venta **no mira el estado**. Siempre carga el Debe a la cuenta del banco de `forma_pago`, esté la venta Pagada o Pendiente.

Eso está cerrado a propósito en un helper y en un test que afirma lo contrario del criterio contable esperado.

### 1. Botón que dispara el síntoma

En el listado de ventas, **Generar partida contable** se muestra para cualquier venta editable con contabilidad activa. No distingue Pendiente de Pagada.

- Pantalla: `Frontend/src/app/views/ventas/ventas.component.html` (acción “Generar partida contable”)
- Llamada: `Frontend/src/app/views/ventas/ventas.component.ts` → `POST contabilidad/partida/venta`
- Si la empresa tiene `generar_partidas = Auto`, la facturación llama el mismo endpoint al guardar (`facturacion.component.ts` y `facturacion-v2.component.ts`)

El endpoint carga la venta y llama a `VentasService::crearPartida`:

- `Backend/app/Http/Controllers/Api/Contabilidad/ApiController.php` método `venta`

### 2. Dónde se elige el banco

`Backend/app/Services/Contabilidad/VentasService.php`, método `crearPartida`, partida de ingresos:

1. Exige que `ReglaIngresoVenta::origenCuentaDebe($venta)` sea `'forma_pago'`. Si no, aborta.
2. Busca `FormaDePago` por el nombre de `venta.forma_pago`, con su banco.
3. Toma `banco.id_cuenta_contable` y arma el detalle al **Debe** por `venta.total`.

No hay rama `if ($venta->estado == 'Pendiente')`. El Haber (ingresos por categoría, IVA, propina) sí se arma aparte. El costo de venta va en una segunda partida y no es el origen de este síntoma.

El helper que fuerza esa decisión:

```php
// Backend/app/Services/Contabilidad/Partidas/ReglaIngresoVenta.php
public static function origenCuentaDebe(object $venta): string
{
    return 'forma_pago';
}
```

El comentario del helper dice que contado y crédito usan la forma de pago. El parámetro `$venta` no se usa.

El test que fija ese comportamiento:

- `Backend/tests/Unit/Services/Contabilidad/Partidas/ReglaIngresoVentaTest.php`
- `test_venta_pendiente_usa_forma_de_pago_no_cxc` espera `'forma_pago'` cuando `estado` es `Pendiente`.

Ese test documenta la regla actual del código. No coincide con el criterio de esta revisión (Pendiente → Cuentas por Cobrar).

## Lo que ya hace bien el sistema

Hay otro generador que sí manda la venta pendiente a CxC. No es el que usa el botón de la venta.

`PartidasController::generarCxC` (`POST partidas/generar/cxc`, tipo de partida CxC en el cierre del día):

- Filtra `Venta` con `estado = Pendiente` y la fecha pedida.
- El Debe es `configuracion.id_cuenta_cxc`.
- El Haber es ingreso (e IVA) de la venta.
- Archivo: `Backend/app/Http/Controllers/Api/Contabilidad/Partidas/PartidasController.php`, método `generarCxC`.

El cobro posterior también está bien armado. `CXCService::crearPartida` (abono, `POST contabilidad/partida/cxc`):

- Debe: banco de la forma de pago **del abono**.
- Haber: `id_cuenta_cxc`.

Ese asiento solo cuadra si la venta pendiente debitó CxC antes. Si la venta ya debitó el banco, el abono vuelve a debitar banco y acredita una CxC que nunca se cargó.

### Espejo en compras (el patrón que ventas no sigue)

`ComprasService::crearPartida` sí bifurca:

- Compra `Pendiente` → Haber en `id_cuenta_cxp` (cuentas por pagar). Tipo de partida `CxP`.
- Compra pagada → Haber en el banco de la forma de pago.

Archivo: `Backend/app/Services/Contabilidad/ComprasService.php`, alrededor de la decisión `estado == 'Pendiente'`.

La venta individual no tiene el equivalente con `id_cuenta_cxc`. Además deja el tipo de partida siempre en `Ingreso`, también cuando la venta está pendiente.

## Dos caminos que no coinciden

| Camino | Cuándo | Venta Pendiente | Venta Pagada |
|---|---|---|---|
| Partida de la venta (`VentasService`) | Botón o auto al facturar | Debe = banco de la forma de pago | Debe = banco de la forma de pago |
| Ingresos del día (`generarIngresos`) | Partida tipo Ingreso | Incluye la venta (`estado != Anulada`) y también usa el banco de la forma de pago | Banco de la forma de pago |
| CxC del día (`generarCxC`) | Partida tipo CxC | Debe = Cuentas por Cobrar | No entra |

`generarIngresos` y `generarCxC` omiten un documento si ya existe una partida con `referencia = Venta` e `id_referencia` de esa venta (`sinPartidaOrigen`).

Consecuencia: si primero se usa **Generar partida contable** en la venta pendiente, queda el banco y el generador diario de CxC **ya no la toma**. El camino correcto no corrige el incorrecto.

## Asiento que queda vs el que se espera

Venta pendiente, partida de ingresos, hoy:

| | Cuenta | Monto |
|---|---|---|
| Debe | Banco de la forma de pago (p. ej. Banco Promerica) | `venta.total` |
| Haber | Ingresos (categoría o cuenta general) + IVA + propina | según detalle |

Esperado para esa misma venta:

| | Cuenta | Monto |
|---|---|---|
| Debe | Cuentas por cobrar (`id_cuenta_cxc`) | `venta.total` |
| Haber | Ingresos + IVA + propina | igual que hoy |

Cuando el cliente paga, el abono ya hace Debe banco / Haber CxC. Con el asiento esperado, el banco se mueve una sola vez, en el cobro.

## Qué no se revisó

- No se consultó la base de una venta concreta. No se confirmó el nombre de la forma de pago ni el `id` de la cuenta Banco Promerica en datos.
- No se revisaron partidas ya guardadas. Esta revisión no propone reprocesarlas.
- El costo de venta (segunda partida) no depende de Pagada/Pendiente.

## Corrección aplicada

- `ReglaIngresoVenta::origenCuentaDebe`: estado `Pendiente` devuelve `cxc`; el resto, `forma_pago`.
- `VentasService::crearPartida`: si es `cxc`, el Debe es `id_cuenta_cxc` y el tipo de partida es `CxC`. Si está cobrada, el Debe sigue siendo el banco de la forma de pago.
- Ingresos del día (`generarIngresos` y `PartidaIngresosService`) ya no incluyen ventas `Pendiente`. Esas quedan en la partida individual o en `generarCxC`, igual que las compras pendientes quedan fuera de egresos.
- El test pasó a `test_venta_pendiente_usa_cxc`.

La partida 9827 de la factura 302 ya existe. Hay que anularla antes de volver a generar la de esa venta; el sistema no deja duplicar el origen.
