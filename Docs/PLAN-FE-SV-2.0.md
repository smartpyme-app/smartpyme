# Plan de desarrollo — Facturación electrónica 2.0 El Salvador

**Sistema:** SmartPyme SaaS  
**Alcance acordado:** **Opción C — los 11 tipos DTE del MH + normativa cumplimiento v2.0**  
**Normativa:** Normativa de Cumplimiento DTE v2.0 (700-DGII-MN-2023-002 y actualizaciones), Catálogos MH v1.1  
**Deadline regulatorio:** 1 de diciembre de 2026 (Sistema de Transmisión DTE)  
**Fecha del documento:** 10 de junio de 2026

> Documento listo para copiar a Confluence y desglosar en Jira (Initiative → Epics → Stories).

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Decisión de alcance](#2-decisión-de-alcance)
3. [Estado actual SmartPyme](#3-estado-actual-smartpyme)
4. [Cambios normativos FE 2.0](#4-cambios-normativos-fe-20)
5. [Matriz de brechas](#5-matriz-de-brechas)
6. [Los 11 tipos DTE — inventario y plan](#6-los-11-tipos-dte--inventario-y-plan)
7. [Fases de implementación](#7-fases-de-implementación)
8. [Estructura Jira](#8-estructura-jira)
9. [Cronograma](#9-cronograma)
10. [Riesgos y dependencias](#10-riesgos-y-dependencias)
11. [Definición de hecho](#11-definición-de-hecho)
12. [Referencias](#12-referencias)

---

## 1. Resumen ejecutivo

SmartPyme opera hoy como **Sistema de Transmisión DTE** para El Salvador: generación JSON, firma digital, transmisión MH, invalidación, contingencia, PDF/JSON, libros IVA y pruebas masivas. Cubre **6 de 11 tipos DTE** en flujo productivo end-to-end.

La **Normativa de Cumplimiento DTE v2.0** exige:

- Nuevas **versiones estructurales JSON** en todos los DTE y en el evento de invalidación.
- Dos **eventos nuevos**: Retorno y Operaciones Especiales.
- Reglas ampliadas de **invalidación** (plazos, entrega JSON + representación gráfica).
- **Catálogos MH v1.1** actualizados.
- Campos obligatorios adicionales (p. ej. **FEXE v2** con `ventaTercero`).

**Objetivo:** cumplimiento v2.0 completo con **los 11 tipos DTE** antes del **1-dic-2026**.

**Estimación orientativa:** 7–9 meses calendario (desarrollo + QA/certificación MH), con paralelización backend/frontend.

---

## 2. Decisión de alcance

| Opción | Descripción | Decisión |
|--------|-------------|----------|
| A | Mínimo legal v2.0 (schemas + eventos + FEXE v2 + invalidación + catálogos) | — |
| B | A + Comprobante de Retención (07) | — |
| **C** | **Los 11 tipos DTE del MH + normativa v2.0 completa** | **✅ Aprobado** |

Implicación: la Fase 5 deja de ser opcional; los tipos **04, 07, 08, 09 y 15** son entregables obligatorios del proyecto, no backlog diferido.

---

## 3. Estado actual SmartPyme

### 3.1 Arquitectura

| Capa | Ubicación principal |
|------|---------------------|
| Generación DTE | `Backend/app/Models/MH/*`, `ElSalvadorDteService` |
| API | `MHDTEController`, rutas `routes/modulos/admin/MH.php` |
| Transmisión MH | `MhDteProxyController` (`/dte/enviar`, `/dte/anular`, etc.) |
| Firma | Servicio externo (`firmador.smartpyme.site`) |
| Frontend | `MHService`, `ElSalvadorFacturacionElectronicaService` |
| Contabilidad | `LibrosIvaSvController`, exports anexos SV |
| QA MH | `MHPruebasMasivasService` |
| Recepción DTE | Módulo descarga automatizada (`Docs/PLAN-MODULO-DTES.md`) |

### 3.2 Cobertura por tipo DTE (hoy)

| Código | Documento | JSON | Envío | PDF | UI | Schema `version` |
|--------|-----------|------|-------|-----|----|--------------------|
| 01 | Factura electrónica | ✅ | ✅ | ✅ | ✅ | 1 |
| 03 | Crédito fiscal (CCF) | ✅ | ✅ | ✅ | ✅ | 3 |
| 04 | Nota de remisión | ❌ | ❌ | ❌ | ❌ | — |
| 05 | Nota de crédito | ✅ | ✅ | ✅ | ✅ | 3 |
| 06 | Nota de débito | ✅ | ✅ | ✅ | ✅ | 3 |
| 07 | Comprobante retención | ⚠️ modelo | ❌ | ❌ | ❌ | 1 |
| 08 | Comprobante liquidación | ❌ | ❌ | ❌ | ❌ | — |
| 09 | Doc. contable liquidación | ❌ | ❌ | ❌ | ❌ | — |
| 11 | Factura exportación | ✅ | ✅ | ✅ | ✅ | 1 ⚠️ |
| 14 | Sujeto excluido | ✅ | ✅ | ✅ | ✅ | 1 |
| 15 | Comprobante donación | ❌ | ❌ | ❌ | ❌ | — |

**Eventos:** Invalidación ✅ (estructura v2 antigua) | Contingencia ✅ | Retorno ❌ | Operaciones Especiales ❌

**Transversal:** `ventaTercero` siempre `NULL` en builders; catálogos en `MHTableSeeder` posiblemente pre-v1.1.

---

## 4. Cambios normativos FE 2.0

### 4.1 Cambios generales

| ID | Cambio | Acción SmartPyme |
|----|--------|------------------|
| G1 | Nueva versión estructural DTE + invalidación | Migrar todos los builders a schemas v2.0 |
| G2 | Fecha generación vs fecha transmisión | Persistir `dte_generado_at`, `dte_transmitido_at`; validar reglas |
| G3 | Emisión con fecha posterior: **1 → 5 días** | Validación UI/backend + periodo tributario |
| G4 | Entrega JSON + representación gráfica al receptor | Email, descarga y registro manual; incluir eventos |
| G5 | UUID v4 en eventos | Verificar en Retorno, Operaciones Especiales, Invalidación |

### 4.2 Eventos nuevos

| Evento | Función |
|--------|---------|
| **Retorno** | Afectar FE / FEXE / FSEE sin invalidar documento origen |
| **Operaciones Especiales** | Operaciones con control interno / factura simplificada |
| **Invalidación v2.0** | Invalidar también eventos; plazos ampliados; entrega obligatoria |

### 4.3 Hitos con fecha

| Fecha | Obligación |
|-------|------------|
| Feb 2026 | Invalidación hasta **90 días** (FE, FEXE, FSEE) desde sello MH |
| 17 feb 2026 | **FEXE v2** + `ventaTercero` en exportación por cuenta de terceros |
| Dic 2025 | Catálogos MH **v1.1** |
| Oct 2026 | Posible cambio **número de control** (confirmar con MH) |
| **1 dic 2026** | **Normativa v2.0 plena** (Sistema de Transmisión) |

---

## 5. Matriz de brechas

| Área | Estado | Prioridad |
|------|--------|-----------|
| Schemas v2.0 (01, 03, 05, 06, 11, 14) | Parcial | Alta |
| FEXE v2 + ventaTercero | Falta | **Crítica (vencida)** |
| Catálogos v1.1 | Parcial | Alta |
| Invalidación v2.0 + 90 días | Parcial | Alta |
| Evento Retorno | Falta | Alta |
| Evento Operaciones Especiales | Falta | Alta |
| DTE 04 Nota remisión | Falta | Media-Alta |
| DTE 07 Retención | Parcial (solo modelo) | Alta |
| DTE 08 Liquidación | Falta | Media |
| DTE 09 Doc. contable liquidación | Falta | Media |
| DTE 15 Donación | Falta | Media |
| Pruebas masivas v2.0 | Parcial | Alta |
| Deprecar `MH.php` legacy vs modelos dedicados | Deuda técnica | Media |

---

## 6. Los 11 tipos DTE — inventario y plan

Por cada tipo pendiente o a migrar, el entregable mínimo es:

1. Builder JSON (`App\Models\MH\MH*`) conforme schema v2.0  
2. Endpoints en `MHDTEController` / `ElSalvadorDteService`  
3. Integración firma + transmisión (`MhDteProxyController`)  
4. Plantilla PDF representación gráfica  
5. Pantalla UI (emisión / consulta / reenvío)  
6. Casos en `MHPruebasMasivasService`  
7. Impacto libros IVA / anexos si aplica  

### 6.1 Tipos ya operativos — migración v2.0

| Tipo | Archivo actual | Trabajo |
|------|----------------|---------|
| 01 | `MHFactura.php` | Schema v2.0, validaciones nuevas, PDF |
| 03 | `MHCCF.php` | Schema v2.0, tributos, PDF |
| 05 | `MHNotaCredito.php` | Schema v2.0, documento relacionado |
| 06 | `MHNotaDebito.php` | Schema v2.0 |
| 11 | `MHFacturaExportacion.php` | **v2 + ventaTercero** (urgente) |
| 14 | `MHSujetoExcluidoCompra/Gasto.php` | Schema v2.0 |

### 6.2 Tipos a implementar desde cero

| Tipo | Módulo negocio sugerido | Notas |
|------|-------------------------|-------|
| **04** Nota remisión | Inventario / traslados | Sin efecto fiscal directo; traslado de bienes |
| **07** Retención | Ventas/compras con retención IVA | Modelo `MHComprobanteRetencion` existe; cablear E2E |
| **08** Liquidación | Compras agro / liquidaciones | Resumen operaciones liquidación |
| **09** Doc. contable liquidación | Contabilidad / inventario consignación | Complemento de 08 |
| **15** Donación | Módulo donaciones o ventas especiales | Ingresos deducibles por donación |

### 6.3 Modelo de datos sugerido (nuevos tipos)

- Tablas o columnas polimórficas según tipo (p. ej. `documentos_dte` con `tipo_dte`, `origen_type`, `origen_id`).  
- Tabla `dte_eventos` para Retorno, Operaciones Especiales e invalidaciones vinculadas.  
- Campos en `ventas`: `venta_tercero_doc`, `venta_tercero_nombre`, `dte_transmitido_at`.  

---

## 7. Fases de implementación

### Fase 0 — Baseline (2 semanas)

**Epic:** `FE-SV-0`

| ID | Story | Entregable |
|----|-------|------------|
| 0.1 | Descargar normativa v2.0, schemas JSON, catálogos v1.1 | Carpeta `Backend/docs/fe-sv/` versionada |
| 0.2 | Diff campo a campo v1 → v2 por tipo DTE | Matriz en Confluence |
| 0.3 | Suite regresión ambiente MH `00` | Tests automatizados tipos actuales |
| 0.4 | Deprecación plan `MH.php` vs modelos `MH*` | ADR interno |

---

### Fase 1 — FEXE v2 + catálogos v1.1 (3 semanas) ⚠️ CRÍTICA

**Epic:** `FE-SV-1`

| ID | Story | Componentes |
|----|-------|-------------|
| 1.1 | FEXE schema v2 | `MHFacturaExportacion`: `version=2`, nodo `ventaTercero` |
| 1.2 | UI exportación tercero | Form venta exportación + validación sin guiones |
| 1.3 | Migración BD | `venta_tercero_doc`, `venta_tercero_nombre` |
| 1.4 | Catálogos v1.1 | Migración CAT-012, 013, 014, 017, 019, 020, 027 |
| 1.5 | Alertas códigos obsoletos | Pre-emisión en clientes/proveedores/productos |

---

### Fase 2 — Invalidación v2.0 (3 semanas)

**Epic:** `FE-SV-2`

| ID | Story |
|----|-------|
| 2.1 | `MHAnulacion` → estructura normativa v2.0 |
| 2.2 | Validación plazo **90 días** (01, 11, 14) |
| 2.3 | UX reemplazo (`codigoGeneracionR`) |
| 2.4 | Entrega JSON + PDF obligatoria (email o registro manual) |
| 2.5 | PDF invalidación según MH v2.0 |

---

### Fase 3 — Schemas v2.0 tipos core (6 semanas)

**Epic:** `FE-SV-3`

| ID | Story |
|----|-------|
| 3.1 | `DteSchemaVersionResolver` + feature flag por empresa |
| 3.2 | Migrar 01 Factura |
| 3.3 | Migrar 03 CCF |
| 3.4 | Migrar 05 / 06 notas |
| 3.5 | Migrar 14 sujeto excluido |
| 3.6 | Emisión fecha posterior (hasta 5 días) |
| 3.7 | `dte_transmitido_at` + reglas generación/transmisión |
| 3.8 | Contingencia v2.0 (`MHContingencia`) |
| 3.9 | Número de control (si MH confirma cambio oct-2026) |

---

### Fase 4 — Eventos v2.0 (4 semanas)

**Epic:** `FE-SV-4`

| ID | Story |
|----|-------|
| 4.1 | Servicio Evento **Retorno** (JSON, firma, transmisión, BD) |
| 4.2 | UI Retorno sobre FE / FEXE / FSEE |
| 4.3 | Servicio Evento **Operaciones Especiales** |
| 4.4 | UI Operaciones Especiales |
| 4.5 | Invalidación de eventos hijos |
| 4.6 | PDF/JSON eventos + entrega receptor |

---

### Fase 5 — Tipos DTE faltantes (8–10 semanas) ✅ ALCANCE C

**Epic:** `FE-SV-5`

#### 5A — Comprobante de Retención (07) — 2 semanas

| ID | Story |
|----|-------|
| 5A.1 | Integrar `MHComprobanteRetencion` en `ElSalvadorDteService` |
| 5A.2 | Rutas API + requests validación |
| 5A.3 | UI emisión desde venta/compra con retención |
| 5A.4 | PDF CRE + envío |
| 5A.5 | Libro IVA / F07 si aplica |

#### 5B — Nota de Remisión (04) — 2 semanas

| ID | Story |
|----|-------|
| 5B.1 | Modelo `MHNotaRemision` + builder v2.0 |
| 5B.2 | Módulo traslado inventario → emisión 04 |
| 5B.3 | PDF + transmisión |
| 5B.4 | Pruebas masivas |

#### 5C — Comprobante Liquidación (08) — 2 semanas

| ID | Story |
|----|-------|
| 5C.1 | Modelo `MHComprobanteLiquidacion` |
| 5C.2 | UI liquidación compras agropecuarias |
| 5C.3 | PDF + libros contables |

#### 5D — Documento Contable Liquidación (09) — 1.5 semanas

| ID | Story |
|----|-------|
| 5D.1 | Modelo `MHLiquidacionContable` |
| 5D.2 | Vínculo con 08 y consignación |
| 5D.3 | PDF + transmisión |

#### 5E — Comprobante Donación (15) — 2 semanas

| ID | Story |
|----|-------|
| 5E.1 | Modelo `MHComprobanteDonacion` |
| 5E.2 | UI captura donante/donatario/montos deducibles |
| 5E.3 | PDF + transmisión |
| 5E.4 | Integración contable |

#### 5F — Consolidación 11 tipos

| ID | Story |
|----|-------|
| 5F.1 | Matriz permisos / tipos documento por empresa |
| 5F.2 | Selector unificado tipo DTE en UI |
| 5F.3 | Importación DTE recibidos: mapeo 04/07/08/09/15 |
| 5F.4 | Pruebas masivas **todos los tipos** |

---

### Fase 6 — Certificación y rollout (4 semanas)

**Epic:** `FE-SV-6`

| ID | Story |
|----|-------|
| 6.1 | Actualizar `MHPruebasMasivasService` (11 tipos + eventos) |
| 6.2 | Certificación ambiente `00` → `01` |
| 6.3 | Feature flag rollout gradual |
| 6.4 | Documentación Confluence usuario + soporte |
| 6.5 | Monitoreo rechazos MH post-deploy |
| 6.6 | Freeze código **15 nov 2026** |

---

## 8. Estructura Jira

```
Initiative: FE El Salvador 2.0 — 11 tipos DTE
│
├── FE-SV-0  Baseline y normativa
├── FE-SV-1  FEXE v2 + Catálogos v1.1          [Highest]
├── FE-SV-2  Invalidación v2.0
├── FE-SV-3  Schemas v2.0 (tipos core)
├── FE-SV-4  Eventos Retorno + Operaciones Especiales
├── FE-SV-5  Tipos DTE faltantes (04,07,08,09,15) + consolidación
│   ├── FE-SV-5A  Retención 07
│   ├── FE-SV-5B  Remisión 04
│   ├── FE-SV-5C  Liquidación 08
│   ├── FE-SV-5D  Doc. contable 09
│   ├── FE-SV-5E  Donación 15
│   └── FE-SV-5F  Consolidación 11 tipos
└── FE-SV-6  Certificación y go-live
```

**Labels:** `fe-sv`, `dte`, `mh`, `compliance`, `v2.0`, `11-dte`  
**Components:** Backend-MH, Frontend-Ventas, Frontend-Compras, Frontend-Inventario, Infra-Firmador, QA-MH, Contabilidad-SV

### Estimación por epic (story points orientativos)

| Epic | SP |
|------|-----|
| FE-SV-0 | 13 |
| FE-SV-1 | 21 |
| FE-SV-2 | 18 |
| FE-SV-3 | 55 |
| FE-SV-4 | 34 |
| FE-SV-5 | 89 |
| FE-SV-6 | 21 |
| **Total** | **~251 SP** |

---

## 9. Cronograma

```
Jun 2026          Jul-Ago           Sep-Oct           Nov              1 Dic 2026
|---- F0 ---------|-- F1+F2 --------|-- F3 + F4 ------|-- F5 + F6 -------| GO-LIVE
                  FEXE v2           Schemas v2.0      04,07,08,09,15
                  Catálogos         Eventos           Certificación
```

| Hito | Fecha |
|------|-------|
| FEXE v2 + catálogos en producción | Jul 2026 |
| Invalidación v2.0 | Ago 2026 |
| Schemas core v2.0 + eventos | Oct 2026 |
| 11 tipos DTE completos | 15 nov 2026 |
| Go-live obligatorio MH | **1 dic 2026** |

> FEXE v2 (feb 2026) ya está vencido en calendario real: iniciar Fase 1 de inmediato.

---

## 10. Riesgos y dependencias

| Riesgo | Mitigación |
|--------|------------|
| Schemas MH cambian antes de dic-2026 | Feature flags; schemas versionados en repo |
| Catálogos obsoletos → rechazos masivos | Fase 1 + validación pre-emisión |
| Firmador no soporta nuevos JSON | Pruebas tempranas; actualizar firmador EC2 |
| 11 tipos amplían scope UI/contabilidad | Epics 5A–5E en paralelo por equipo |
| `MH.php` legacy diverge de modelos | Deprecar en Fase 0; un solo path de generación |
| Oct-2026 cambio número control | Story 3.9 condicionada a confirmación MH |

**Dependencias externas:** Portal MH (factura.gob.sv), firmador SmartPyme, certificados vigentes por empresa.

---

## 11. Definición de hecho

Por story / tipo DTE:

- [ ] JSON validado contra schema MH v2.0 (ambiente `00`)
- [ ] Transmisión con sello en `01` o evidencia de prueba
- [ ] PDF representación gráfica conforme
- [ ] Entrega JSON + PDF al receptor documentada
- [ ] Caso en pruebas masivas MH
- [ ] Sin regresión libros IVA SV
- [ ] Documentación Confluence actualizada

Por proyecto (Initiative):

- [ ] **11/11 tipos DTE** operativos E2E
- [ ] Eventos Retorno + Operaciones Especiales operativos
- [ ] Invalidación v2.0 + plazos 90 días
- [ ] Catálogos v1.1 en producción
- [ ] Certificación MH completada antes del 1-dic-2026

---

## 12. Referencias

- Normativa cumplimiento DTE: 700-DGII-MN-2023-002 (MH / factura.gob.sv)
- Catálogos MH v1.1 (dic 2025)
- Código SmartPyme: `Backend/app/Models/MH/`, `ElSalvadorDteService.php`
- Plan módulo recepción DTE: `Docs/PLAN-MODULO-DTES.md`
- Plan FE Costa Rica (referencia arquitectura multi-país): `Docs/FE-COSTA-RICA.md`

---

*Aprobación de alcance: Opción C (11 tipos DTE) — 10 jun 2026*

---

## Anexos

- **Plan de implementación detallado (archivos, migraciones, PRs):** [`docs/superpowers/plans/2026-06-10-fe-sv-2.0.md`](../docs/superpowers/plans/2026-06-10-fe-sv-2.0.md)
- **Import Jira (CSV):** [`Docs/FE-SV-2.0-JIRA-STORIES.csv`](FE-SV-2.0-JIRA-STORIES.csv)
