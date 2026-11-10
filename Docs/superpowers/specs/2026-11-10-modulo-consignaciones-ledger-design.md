# Diseño: Módulo consignaciones v2 (ledger aparte)

**Fecha:** 2026-11-10  
**Ticket:** SP-2344  
**Estado:** Aprobado para implementación  
**Plan:** `Docs/superpowers/plans/2026-11-10-modulo-consignaciones-ledger.md`  
**Spec previo (ajustes jul-2026):** `Docs/superpowers/specs/2026-07-17-consignas-ajustes-design.md` — mantener comportamiento legacy cuando ledger **off**.

---

## 1. Objetivo

Ledger de consignación **separado** de `kardexs` / `inventarios`, con trazabilidad de entrada proveedor, ventas por origen económico, consigna salida a cliente, liquidaciones (sin duplicar stock), activación **por empresa** (Super Admin + Mi cuenta).

## 2. Reglas de negocio

1. **Inventario físico:** una sola salida por unidad desde bodega en entrega/venta; liquidación compra/venta consigna **no** mueve `inventarios.stock`.
2. **Ledger consigna:** todos los eventos de propiedad, custodia cliente, pendiente proveedor, liquidación.
3. **Legacy (ledger off):** código actual sin cambios observables (mismos kardex, mismo pool `ConsignaDisponibleService`).
4. **Ledger on (Fase 1):** kardex clásico **igual** + inserts en `consigna_movimientos`; Fase 3+ opcional desacoplar semántica kardex.

## 3. Activación

| Capa | Mecanismo |
|------|-----------|
| Licencia | Super Admin: funcionalidad `modulo-consignaciones` |
| Operación | Mi cuenta: `configuraciones.consignaciones_ledger_activo` (+ sub-opciones) |
| Rol | Permisos existentes + `inventario.consigna_kardex.ver` |
| Backend | `Empresa::isModuloConsignacionesLedgerActivo()` → early return si false |

## 4. Modelo de datos (resumen)

### `consigna_movimientos`

Movimientos append-only. Tipos (string enum):  
`entrada_proveedor`, `venta_regular_propio`, `venta_regular_consigna_proveedor`,  
`entrega_consigna_propio`, `entrega_consigna_proveedor`,  
`liquidacion_proveedor`, `liquidacion_cliente`,  
`devolucion_proveedor`, `devolucion_cliente`, `traslado_consigna`, `ajuste_consigna`.

Campos clave: `id_empresa`, `fecha`, `tipo`, `cantidad` (decimal, signo según convención documentada),  
`id_producto`, `id_bodega`, `id_proveedor`, `id_cliente` (nullable),  
`id_compra`, `id_venta`, `id_detalle_compra`, `id_detalle_venta` (nullable),  
`costo_unitario`, `nota`, `id_usuario`, `idempotency_key` (nullable, unique por empresa).

### Líneas venta (opcional Fase 1)

Persistir en `detalles_venta`: `modo_operacion` (`regular` | `consigna_salida`) — o derivar de `venta.estado` en v1.

## 5. Integraciones (hooks)

| Evento | Servicio |
|--------|----------|
| Compra guardada `estado=Consigna` | `ConsignaMovimientoService::entradaProveedor` |
| Venta facturada | `ConsignaMovimientoService::desdeVentaDetalle` |
| `facturacionConsigna` compra | `liquidacionProveedor` |
| `facturacionConsigna` venta | `liquidacionCliente` |
| Traslado (fase 2) | `trasladoConsigna` si producto con saldo consigna |

## 6. Criterios de aceptación (SP-2344 alineados — Fase 1)

1. Empresa sin funcionalidad: cero filas nuevas, kardex idéntico a antes.
2. Empresa con ledger on: cada recepción consigna genera movimiento ledger; liquidación compra consigna genera `liquidacion_proveedor` sin cambio stock.
3. Venta `origen_stock=consigna_compra` con ledger on: movimiento venta + pendiente proveedor en ledger.
4. UI Kardex consigna: listado filtrable por proveedor, producto, fechas.
5. Manual usuario + deploy documentados al cierre del epic.

## 7. Fuera de alcance Fase 1

- Bodega virtual por cliente (tabla dedicada) — Fase 2
- Conteo físico periódico UI — Fase 2
- Dejar de escribir "Compra a consigna" en `kardexs` — Fase 3
- DTE MH tipo 04 — fuera
- Backfill obligatorio histórico — comando opcional
