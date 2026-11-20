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

## Fase 2.1 (hecho)

- Kardex “Venta a consigna” → `Venta` con ledger on.
- `ConsignaDevolucionLedgerService` en devoluciones compra/venta.
- DTE 04 MH: **FE-SV-5B** (`MHNotaRemision`); consigna usa impreso hasta cablear MH.

## Fuera de alcance

- Ledger/custodia **por lote** (no figura en PDF/spec; pedirlo aparte).
- Anulación de devolución reverso ledger (ponytail: idempotency manual si hace falta).
