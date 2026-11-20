# Consigna v2 — Devoluciones, productos por lote, historial (P1–P5)

> **For agentic workers:** Implementar en orden P1→P5; cada hito con tests, commit en `dc.SP-2344`, luego siguiente.

**Goal:** Completar operación consigna ledger según reunion cliente (12/Nov): devolución documentada, filas producto+lote, tabs/export e historial ventas+compras.

**Decisiones usuario:**

1. Documento «Devolución de consigna» auto al activar ledger.
2. Solo mercancía (no NC/ND como flujo principal en consigna).
3. Productos en consigna: agrupar producto + lote.
4. Historial/reporte en ventas y compras.
5. Custodia/historial por lote si lotes activos; si no, sin lote.

---

## P1 — Devolución consigna parcial (ventas) — EN CURSO

| Paso | Entrega |
|------|---------|
| 1 | `DocumentoConstants::DEVOLUCION_CONSIGNA`, `ConsignaDocumentosService` + hook `consignaciones_ledger_activo` |
| 2 | `DevolucionConsignaVentaService::reducirRemanenteDesdeDevolucion` |
| 3 | `DevolucionVentasController::facturacion`: ledger + remanente; validación cantidades |
| 4 | Front `devolucion-nueva`: doc «Devolución de consigna», tipo mercancía |
| 5 | Tests unitarios + `php artisan test` filtro |

---

## P2 — Productos en consigna (producto + lote, vencimiento) — HECHO

- `ConsignaProductosVentaListadoService`, `ConsignasExport`, UI + export compras detalle lote.

## P3 — Tab devoluciones custodia + export por cliente — HECHO

- Tab devoluciones, `ConsignaCustodiaDevolucionesExport`, export exige `id_cliente`.

## P4 — PDFs con lote/vencimiento — HECHO

- Partial `detalle-lote-impresion`; ticket, 80mm, devolución/compra PDF; remisión en impresión ticket.

## P5 — Historial según PDF raíz — HECHO

- `ConsignaHistorialService`, API `consigna-historial`, Excel (resumen + movimientos).
- UI `/consigna-historial` (tabs ventas/cliente y compras/proveedor); export exige tercero.
- Enlaces desde productos en consigna, consignas compras y custodia.

---

**Manual QA:** Tras P1–P5, usuario prueba en empresa 855 (FIREX / PCI2510).
