# IVA separado y abonos en cartera (configuración de contabilidad)

Dos reglas opcionales en Configuración de contabilidad. Apagadas por defecto: el comportamiento actual no cambia.

## Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Dónde | Misma pantalla, bloque **Reglas** (switches) encima de Ventas |
| Persistencia | Columnas nuevas en `contabilidad_configuracion` |
| IVA split | Un switch para ventas **y** compras |
| Cuentas actuales | `id_cuenta_iva_ventas` / `id_cuenta_iva_compras` = crédito fiscal cuando el split está on |
| Cuentas nuevas | `id_cuenta_iva_ventas_cf` / `id_cuenta_iva_compras_cf` (consumidor final), opcionales |
| Fallback CF | Si el split está on y falta la cuenta CF, se usa la de crédito fiscal |
| Clasificación | Por tipo de documento del propio registro (no se busca la factura origen) |
| Crédito fiscal | Nombre/tipo que sea CCF o contenga “crédito fiscal” (sin acentos) |
| Resto | Consumidor final (Factura, Ticket, NC, ND, etc.) |
| Abonos | Un switch para ambos lados: ingresos/CXC y egresos/CXP |
| Partidas viejas | No se reprocesan |
| Retenciones | Sin cambios (siguen un solo campo) |

## Qué ve el usuario

**Reglas**

1. Separar cuentas de IVA (consumidor final / crédito fiscal)
2. Registrar abonos en CXC / CXP (no en ingresos / egresos)

**IVA — switch off:** un campo de IVA en Ventas y uno en Compras (labels actuales).

**IVA — switch on:** esos campos se etiquetan “IVA crédito fiscal” y aparecen “IVA consumidor final” en Ventas y en Compras.

## Comportamiento al generar partidas

### Cuenta de IVA

Si `separar_cuentas_iva` es falso → siempre la cuenta actual.

Si es verdadero:

| Tipo de documento | Ventas | Compras / gastos |
|---|---|---|
| Crédito fiscal, CCF, o texto que contiene “crédito fiscal” | `id_cuenta_iva_ventas` | `id_cuenta_iva_compras` |
| Cualquier otro (Factura, Ticket, NC, ND, vacío) | `id_cuenta_iva_ventas_cf` o fallback | `id_cuenta_iva_compras_cf` o fallback |

Aplica en generación de ingresos, CXC, egresos, CXP, partida individual de venta/compra y gastos que ya usan IVA de compras.

NC/ND se clasifican por su propio tipo (“Nota de crédito”), no por la factura origen. En esta versión no se resuelve el documento relacionado.

### Abonos

Hoy ingresos mezclan ventas + abonos y egresos mezclan compras + abonos. CXC/CXP ya agregan cobros/pagos si el abono **no** tiene partida.

- `abonos_en_cartera` off → se mantiene: ingresos/egresos incluyen abonos; CXC/CXP los saltan si ya existen.
- `abonos_en_cartera` on → ingresos = ventas (incluye NC que ya van como venta); egresos = compras. Los abonos quedan para CXC/CXP.

## Datos

Tabla `contabilidad_configuracion`:

- `separar_cuentas_iva` boolean default false
- `abonos_en_cartera` boolean default false
- `id_cuenta_iva_ventas_cf` nullable
- `id_cuenta_iva_compras_cf` nullable

Los flags y las cuentas CF no son requeridos al guardar. Las cuentas de IVA actuales siguen requeridas.

## Arquitectura

Helpers puros (mismo estilo que `ReglaIngresoVenta`):

- `ReglaCuentaIva` — tipo del documento, si es crédito fiscal, id de cuenta ventas/compras
- `ReglaAbonosCartera` — si los abonos van en ingresos/egresos

Los generadores (`PartidasController`, `PartidaIngresosService`, `PartidaEgresosService`, `VentasService`, `ComprasService`, `GastosService`) solo preguntan al helper. No duplicar la clasificación.

## Fuera de alcance

- Recalcular o migrar partidas ya generadas
- Separar IVA retenido / renta
- Switches distintos ventas vs compras
- Resolver NC/ND contra la factura origen
- Libros de IVA / reportes fiscales (siguen agrupando por tipo de documento, no por estas cuentas)

## Prueba mínima

Unitario de los helpers (sin DB):

- Split off → siempre la cuenta general
- Split on + CCF / “Crédito fiscal” → cuenta crédito fiscal
- Split on + Factura / Ticket / NC / vacío → cuenta CF; si CF es null, fallback
- `abonos_en_cartera` off → incluir abonos en resultado; on → no incluir
