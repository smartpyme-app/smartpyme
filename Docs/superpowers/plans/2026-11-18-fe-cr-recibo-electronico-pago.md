# FE CR — Recibo electrónico de pago (REP) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Emitir Recibo Electrónico de Pago (tipo comprobante **10**, código referencia **17**) desde un abono de venta, con botón manual como en ventas.

**Architecture:** Mapper arma el payload; `CostaRicaFeEmitService` firma/envía; disparo solo por `POST emitirFeCrReciboPago`. Parche del generador XML para `payment-receipt`. UI solo en `abonos-ventas`.

**Tech Stack:** Laravel, Angular, `dazza-dev/dgt-cr` + parches `dgt-xml-generator`.

**Spec:** `Docs/superpowers/specs/2026-11-18-fe-cr-recibo-electronico-pago-design.md`

## Global Constraints

- Tipo de **comprobante emitido** = **10** (Nota 3). Tipo de **documento de referencia** **20** solo al referenciar otro REP (Nota 10).
- Código de referencia **17** exclusivo del REP.
- Emisión **manual**; no emitir al guardar el abono.
- Solo abonos de **venta**.
- No tocar `Frontend/src/environments/environment.ts`.
- PHPUnit sin `RefreshDatabase` / migrate fresh.

---

### Task 1: Catálogo + mapper + test

**Files:**
- Modify: `Backend/app/Support/FacturacionElectronica/CostaRica/ReferenciaComprobanteCr.php`
- Create: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaReciboPagoMapper.php`
- Test: `Backend/tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaReciboPagoMapperTest.php`

- [x] Constantes `TIPO_COMPROBANTE_REP = '10'`, `TIPO_RECIBO_PAGO = '20'`
- [x] Mapper: referencia 17, validación tipos, medio de pago, build
- [x] Tests unitarios

### Task 2: Parche XML generator + emisión + API

- [x] Parche `dgt-xml-generator-recibo-electronico-pago.patch` + entrada en `composer.json`
- [x] `emitirReciboPagoDesdeAbono` + `POST emitirFeCrReciboPago`
- [x] Migración FE en `abonos_ventas` + modelo `Abono`

### Task 3: Frontend abonos-ventas

- [x] Servicio + botón Emitir DTE + regresión HTML
