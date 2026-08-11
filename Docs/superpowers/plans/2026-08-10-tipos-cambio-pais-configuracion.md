# Tipos de cambio en pais_configuracion — Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Quitar `bccr_tipos_cambio` y cachear el TC del día en `pais_configuracion` módulo `moneda`, con job multi-país para fuentes API.

**Architecture:** Documentos siguen siendo la fuente del TC usado. `pais_configuracion.rate_del_dia` es cache de un solo día. `CostaRicaTipoCambioService` lee/escribe esa config; un command genérico itera países con `fuente=api`.

**Tech Stack:** Laravel, `PaisConfiguracion`, BCCR client existente.

**Spec:** `Docs/superpowers/specs/2026-08-10-tipos-cambio-pais-configuracion-design.md`

## Global Constraints

- No inventar tasas (sin fallback 520).
- No tabla de historial de TC.
- Borrar migración sin rollback (no prod).
- Cambios mínimos; no CurrencyEngine multi-país.

---

## Task 1: Eliminar tabla bccr + cablear cache en pais_configuracion

**Files:**
- Delete: `Backend/database/migrations/2026_08_06_100000_create_bccr_tipos_cambio_table.php`
- Delete: `Backend/app/Models/FacturacionElectronica/CostaRica/BccrTipoCambio.php`
- Modify: `Backend/app/Models/PaisConfiguracion.php` — `MODULO_MONEDA`
- Create: `Backend/app/Support/Admin/MonedaDefaultPorPais.php`
- Create: `Backend/database/seeders/PaisConfiguracionMonedaSeeder.php`
- Modify: `Backend/database/seeders/DatabaseSeeder.php`
- Modify: `Backend/app/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioService.php`
- Replace: `SyncBccrTipoCambioCommand` → `tipos-cambio:sync-dia`
- Modify: `Backend/app/Console/Kernel.php`
- Modify: `Backend/tests/Unit/Services/FacturacionElectronica/CostaRica/CostaRicaTipoCambioServiceTest.php`
- Modify: `Docs/superpowers/specs/2026-08-03-cr-multimoneda-design.md` — nota supersede §7.1

- [ ] Implementar lo anterior
- [ ] Correr test unitario del servicio TC
- [ ] Commit (solo si el usuario lo pide)
