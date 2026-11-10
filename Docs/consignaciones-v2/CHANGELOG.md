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

### Fase 2

- Tabla `consigna_custodias` sincronizada con entregas/liquidaciones/ajustes del ledger.
- UI `/consigna-custodia` con conteo físico → movimiento `ajuste_consigna`.
- Traslados: campo `cantidad_consigna` por línea → `traslado_consigna` en ledger.
- Con ledger activo, compras consigna registran kardex clásico como `Compra` (sin duplicar “Compra a consigna”).

### Comandos

- `php artisan consigna:ledger-backfill --empresa=ID [--dry-run]` — backfill histórico opcional.

### Sin cambios para clientes sin módulo

- Con funcionalidad `modulo-consignaciones` desactivada o ledger off: kardex e inventario idénticos al release anterior.
