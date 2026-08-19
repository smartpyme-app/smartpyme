# Diseño: Totales por moneda en resumen fiscal (SP-2126)

**Fecha:** 2026-08-18  
**Estado:** Aprobado  
**Jira:** [SP-2126](https://smartpyme.atlassian.net/browse/SP-2126)

## Objetivo

En el **resumen de libros de IVA (pantalla)**, si la empresa tiene funcionalidad `multimoneda`, mostrar cuánto se movió en cada moneda.

No va a Excel ni PDF.

## UI

Tres tablas, no una sola: **Ventas**, **Compras**, **Gastos**.

Columnas: Moneda | Documentos | Total (moneda) | Total equivalente (CRC / moneda funcional).

Filas solo si hay movimiento en esa moneda.

## Cálculo

Tablas: `ventas`, `compras`, `egresos`, `devoluciones_venta` (`currency_code`, `total`, `equivalent_total`, `exchange_rate`).

- Total nativo: suma de `total`.
- Equivalente: `equivalent_total` si existe; si no, nativo × TC (USD) o nativo (CRC).
- Devoluciones de venta restan en **Ventas** (misma moneda del documento). No cuentan como “Documentos”.
- Devoluciones de compra **no** entran (no tienen `currency_code`).

Mismos filtros del resumen (periodo, sucursal, no anuladas, no cotizaciones).
