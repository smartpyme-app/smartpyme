# Changelog — Consignaciones v2

> Completar al cerrar el epic. Mientras tanto, anotar commits o PRs relevantes.

## [Unreleased]

### Añadido

- Tabla `consigna_movimientos`, modelo, `ConsignaMovimientoService` (Fase 1 paralela al kardex clásico).
- Funcionalidad Super Admin `modulo-consignaciones`; preferencia Mi cuenta `consignaciones_ledger_activo`.
- Permisos `inventario.consigna_kardex.ver` / `exportar`.
- Hooks compra/venta/liquidación consigna (solo si ledger activo).
- API `GET /productos/consigna-kardex`, export Excel, UI `/consigna-kardex`.
- `ConsignaDisponibleService::assertConsistenteConLedger` (solo `APP_DEBUG`, Fase 1 diagnóstico).

### Cambiado

- `FuncionalidadesSeeder`: nueva funcionalidad `modulo-consignaciones`.
- Mi cuenta: switch **Kardex de consignaciones** (`consignaciones_ledger_activo`).
- Menú compras: enlace **Kardex consigna** (con permiso).

### Fase 2.1

- Kardex clásico: ventas consigna salida con ledger on → detalle `Venta` (no “Venta a consigna”).
- Ledger en devoluciones venta (cliente consigna / pool proveedor) y devolución compra consigna.
- **P1:** Documento «Devolución de consigna» (auto al activar ledger), devolución parcial reduce remisión `Consigna`, UI solo mercancía, movimiento ledger con `lote_id` cuando aplica.
- **P2:** Productos en consigna (ventas) agrupados por producto+lote si ledger y lotes activos; columnas lote/vencimiento en UI y Excel; detalle compras muestra lote/vencimiento.
- **P3:** Custodia consigna: pestaña devoluciones, filtro cliente, export Excel por cliente (ventas y devoluciones) con columnas de lote opcionales.
- DTE MH 04: documento impreso “Factura de remisión” vigente; transmisión MH → epic **FE-SV-5B** (respuesta API explícita).

### Lotes

No requisito en spec SP-2344 ni texto extraíble del PDF ejecutivo; inventario por lote sigue en capa física.

### Fase 2

- Tabla `consigna_custodias` sincronizada con entregas/liquidaciones/ajustes del ledger.
- UI `/consigna-custodia` con conteo físico → movimiento `ajuste_consigna`.
- Traslados: campo `cantidad_consigna` por línea → `traslado_consigna` en ledger.
- Con ledger activo, compras consigna registran kardex clásico como `Compra` (sin duplicar “Compra a consigna”).

### Comandos

- `php artisan consigna:ledger-backfill --empresa=ID [--dry-run]` — backfill histórico opcional.

### Sin cambios para clientes sin módulo

- Con funcionalidad `modulo-consignaciones` desactivada o ledger off: kardex e inventario idénticos al release anterior.
