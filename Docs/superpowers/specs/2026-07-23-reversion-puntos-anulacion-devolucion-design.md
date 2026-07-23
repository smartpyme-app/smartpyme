# Diseño: Reversión de puntos en anulación y devolución

**Fecha:** 2026-07-23  
**Estado:** Implementado  
**Tipo:** Bug fix / feature fidelización  

---

## 1. Problema

- Al **anular** una venta no se revierten puntos ganados ni se restauran puntos canjeados.
- En **devoluciones** el ajuste se calcula por monto × puntos_por_dolar, no por los puntos reales de la venta, y no restaura canje.

## 2. Decisiones

| Tema | Decisión |
|------|----------|
| Anulación | Revertir 100% ganados + restaurar 100% canjeados |
| Devolución | Proporcional (parcial o total) sobre ganados y canjeados |
| Saldo insuficiente al restar ganados | Solo hasta saldo disponible (sin negativo, sin bloquear) |
| Canje | Opción B: también restaurar |
| Enfoque | Servicio `ReversionPuntosService` + hooks en store anulación y sync devolución |

## 3. Diseño técnico

### `ReversionPuntosService`

- `revertirPorAnulacion(Venta $venta)` — idempotente (`ajuste_anulacion_ganancia_{id}`, `ajuste_anulacion_canje_{id}`).
- `syncPorDevolucion(Devolucion $devolucion)` — factor = `min(1, total_dev / total_venta)`; targets = `floor(puntos_* × factor)`; claves `ajuste_devolucion_ganancia_{id}` / `ajuste_devolucion_canje_{id}`.

**Ganancia:** ajuste negativo; tope saldo; marcar `puntos_consumidos` en la ganancia de la venta para no re-canjear esos puntos.

**Canje:** localizar canje por descripción `Canje aplicado en venta #{id}`; deshacer FIFO (`consumo_puntos`) proporcional; ajuste positivo; subir `puntos_disponibles` y bajar `puntos_totales_canjeados`.

### Hooks

- `VentasController::store` al pasar a `Anulada`.
- `DevolucionPuntosService` delega a `ReversionPuntosService` (mismos call sites).

## 4. Fuera de alcance

- Re-acumular si se desanula.
- Shopify/Woo salvo que usen el mismo `store`.
