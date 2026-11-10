# Consignaciones v2 — Fase 2

**Ticket:** SP-2344  
**Pre-requisito:** Fase 1 en rama `dc.SP-2344` (ledger + kardex consigna UI).

## Entregables

| # | Item | Estado |
|---|------|--------|
| 1 | `consigna_custodias` + sync desde movimientos cliente | Hecho |
| 2 | API/UI conteo → `ajuste_consigna` | Hecho (`/consigna-custodia`) |
| 3 | Traslados `cantidad_consigna` → `traslado_consigna` ledger | Hecho |
| 4 | Kardex clásico sin “Compra a consigna” si ledger on | Hecho |
| 5 | Pool facturación desde ledger si ledger on | Hecho (`ConsignaDisponibleService`) |
| 6 | Backfill reconstruye custodias | Hecho (`consigna:ledger-backfill`) |

## Deploy

Ver `Docs/consignaciones-v2/MIGRATIONS_AND_SEEDERS.md` — migración `2026_11_12_100000_create_consigna_custodias_table.php`.

Tras backfill histórico, el comando reconstruye custodias automáticamente.

## Fuera de alcance (posible Fase 3)

- Dejar de escribir otras líneas “Venta a consigna” en kardex clásico (solo se ajustó compra consigna).
- Devoluciones cliente automatizadas en ledger (`devolucion_cliente` hook).
- DTE tipo remisión MH.
