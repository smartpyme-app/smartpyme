# Changelog — Consignaciones v2

> Completar al cerrar el epic. Mientras tanto, anotar commits o PRs relevantes.

## [Unreleased]

### Añadido

- Tabla `consigna_movimientos`, modelo, `ConsignaMovimientoService` (Fase 1 paralela al kardex clásico).
- Funcionalidad Super Admin `modulo-consignaciones`; preferencia Mi cuenta `consignaciones_ledger_activo`.
- Permisos `inventario.consigna_kardex.ver` / `exportar`.
- Hooks compra/venta/liquidación consigna (solo si ledger activo).
- API `GET /productos/consigna-kardex`, UI `/consigna-kardex`.

### Cambiado

- (pendiente)

### Sin cambios para clientes sin módulo

- Con funcionalidad `modulo-consignaciones` desactivada o ledger off: kardex e inventario idénticos al release anterior.
