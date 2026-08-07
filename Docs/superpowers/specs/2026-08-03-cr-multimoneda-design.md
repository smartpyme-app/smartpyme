# Diseño: Facturación multimoneda Costa Rica (CRC / USD)

**Fecha:** 2026-08-03 (actualizado 2026-08-04)  
**Estado:** Pendiente revisión de usuario del spec escrito  
**Fuente de requisitos:** `CR_Requisitos_Multimoneda.md` (Alejandro Alas — borrador P0 lanzamiento CR Q3 2026) + brainstorming producto 2026-08-04  
**Mercado:** Costa Rica (Mercado 3)  
**Tipo:** Feature de cumplimiento + producto

---

## 1. Contexto y problema

Hoy la moneda en SmartPyme es un atributo de **empresa** (`empresa.moneda`). En facturación electrónica CR, `CostaRicaInvoiceFromVentaMapper` deriva `currency_code` / `exchange_rate` solo desde esa moneda. El tipo de cambio para USD sale de APIs genéricas (`exchangerate.host`, `open.er-api.com`), con override manual `facturacion_fe.tipo_cambio_usd_crc` y **fallback fijo 520** — no del BCCR.

Eso no cumple el modelo fiscal CR: la moneda se define **por documento**, el TC de venta de referencia debe ser el del BCCR vigente al hecho generador (salvo override explícito por empresa), y el IVA / reportes se declaran en colones.

**Riesgo actual:** una empresa con `moneda = USD` puede emitir con TC inventado o desactualizado; Hacienda puede rechazar (0/1) o el dato fiscal queda incorrecto.

---

## 2. Objetivos (Fase 1)

1. Emitir FE/TE/NC/ND (y documentos FE relacionados) en **CRC o USD por documento**, intercambiables el mismo día.
2. Obtener y cachear el **tipo de cambio de venta de referencia BCCR** (indicador 318) como default; **no inventar** tasas (eliminar fallback 520).
3. Persistir montos **nativos** en la moneda del documento y **equivalente CRC** para contabilidad, IVA y dashboards.
4. Llenar `CodigoMoneda` / `TipoCambio` en XML v4.4 desde el documento (la capa XML ya existe).
5. Detectar moneda en ingesta de XML (email/DTE); no asumir CRC.
6. Cubrir **ventas + compras + gastos** en la misma fase de producto.
7. Config por empresa: permitir o no **editar el TC en ventas antes de emitir**.

---

## 3. Fuera de alcance (Fase 2 — no construir)

- Cuentas bancarias / efectivo multimoneda reales en USD.
- Diferencia cambiaria (ganancia/pérdida) en CxC/CxP abiertas entre períodos.
- Monedas distintas de CRC/USD (EUR, etc.).
- Selector de moneda a nivel de dashboard (“ver P&L como si fuera USD”).
- Edición de TC en compras/gastos (manual o XML) — Fase 1 no lo incluye.
- Edición de TC después de emitir FE.

---

## 4. Decisiones de diseño acordadas

| Tema | Decisión |
|------|----------|
| Enfoque | **A — Incremental** sobre FE CR actual (mapper + XML vendor); no abstracción multi-país genérica |
| Alcance docs Fase 1 | **Ventas + compras + gastos** |
| Modelo de montos | **Nativo = lo digitado** en `currency_code`; CRC equivalent derivado (necesario para FE/XML y UX) |
| Fuente TC default | BCCR indicador **318** (venta de referencia) |
| Edición manual de TC | **Configurable por empresa**; solo **ventas**, solo **antes de emitir**; default **off** |
| Compras/gastos TC | XML: viene en el comprobante (readonly). Manual: BCCR propuesto, **sin** edición en Fase 1 |
| Fallback inventado (520) | **Eliminar** del path de emisión |
| Override legacy `tipo_cambio_usd_crc` | **No usar** en emisión FE; deprecar / ignorar (reemplazado por BCCR + flag editar) |
| Sin TC disponible | **Bloquear** emisión/guardado USD (salvo venta con flag on y rate válido digitado) |
| Moneda empresa | Default de UI y moneda funcional de producto; **no** candado de emisión; reportes fiscales CR siempre en CRC equivalent |
| XML `CodigoMoneda`/`TipoCambio` | Ya en `dgt-xml-generator`; cablear desde documento (incluye TC editado si aplica) |
| OCR genérico | Fuera; alcance de ingesta = **parser XML** email/DTE |
| País | Solo empresas CR (`cod_pais` / mercado CR); no cambiar modelo SV |

**Alineación internacional:** moneda funcional (empresa) + moneda por documento + TC congelado al guardar = modelo Xero / QBO / IAS 21. Particularidad CR: default BCCR para FE; override opt-in por empresa (práctica de mercado tipo Alegra, pero gated).

---

## 5. Estado actual (baseline código)

| Pieza | Ubicación | Estado |
|-------|-----------|--------|
| TC emisión | `CostaRicaTipoCambioService` | APIs genéricas + fallback 520 + override manual |
| Proxy Hacienda TC | `CostaRicaHaciendaPublicApiService::tipoCambioDolar` + ruta FE-CR | Existe; **no** usado en emisión |
| Mapper FE | `CostaRicaInvoiceFromVentaMapper` | Lee `empresa.moneda` |
| XML | `vendor/dazza-dev/dgt-xml-generator` → `CodigoTipoMoneda` | ✅ Listo |
| Modelos Venta/Compra/Gasto | Sin `currency_code` / TC / CRC equiv. | ❌ |
| UI facturación | Pipe moneda = empresa; sin selector por doc | ❌ |
| Import XML | `CostaRicaXmlDocumentoParser` no lee moneda | ❌ |
| Reportes IVA CR | `ReporteDetalleIvaCrService` hardcodea CRC/1 | ❌ |
| BCCR | — | ❌ No hay integración |

---

## 6. Arquitectura

### 6.1 Flujo objetivo

```
Usuario elige moneda (CRC|USD) en factura / compra / gasto
        │
        ▼
Resolver TC:
  CRC → 1
  USD → BCCR 318 (fecha hecho generador)
        │  └─ cache bccr_tipos_cambio
        │  └─ si falta → SOAP BCCR
        │  └─ venta + flag permitir_editar → UI puede sobrescribir rate
        │  └─ sin rate usable → bloquear
        ▼
Persistir documento:
  total/iva/líneas = nativos
  currency_code, exchange_rate, exchange_rate_date
  crc_equivalent_* = nativo × rate (o = nativo si CRC)
        │
        ├── Emisión FE → mapper lee documento → XML CodigoMoneda/TipoCambio
        │                 (TC congelado; post-emisión no editable)
        └── Reportes/dash/asientos CR → suman crc_equivalent_*
```

### 6.2 Componentes nuevos / tocados

| Componente | Acción |
|------------|--------|
| Tabla `bccr_tipos_cambio` | Nueva |
| Job/command sync BCCR | Nuevo (diario, timezone `America/Costa_Rica`) |
| `BccrTipoCambioClient` (o ampliar servicio) | SOAP indicadores económicos |
| `CostaRicaTipoCambioService` | Reescribir: solo BCCR; sin fallback inventado |
| Config empresa `permitir_editar_tipo_cambio` | Nueva (bool, default false) |
| Migraciones `ventas` / `compras` / `gastos` (+ devoluciones FE) | Columnas moneda |
| Controllers/services de guardado venta-compra-gasto | Validar + calcular CRC equiv. + honrar flag en ventas |
| `CostaRicaInvoiceFromVentaMapper` (+ NC, FEC/gasto) | Leer documento |
| Frontend facturación/gastos/compras CR | Selector + preview TC (+ input TC si flag) |
| `CostaRicaXmlDocumentoParser` / import | Leer moneda XML |
| `ReporteDetalleIvaCrService` + dash CR | Usar CRC equivalent |

`ponytail:` un solo servicio de TC + una tabla; no capa “CurrencyEngine” multi-país.

---

## 7. Modelo de datos

### 7.1 `bccr_tipos_cambio`

| Campo | Tipo | Notas |
|-------|------|--------|
| `id` | bigint PK | |
| `date` | date | **unique** — día del indicador |
| `venta_reference_rate` | decimal(18,5) | Indicador 318 |
| `fetched_at` | timestamp | |
| `created_at` / `updated_at` | timestamps | |

### 7.2 Documentos transaccionales

Agregar a **ventas**, **compras**, **gastos** (y tabla de devoluciones usada en NC FE CR, si aplica):

| Campo | Tipo | Notas |
|-------|------|--------|
| `currency_code` | char(3) / enum | `CRC` \| `USD`; default `CRC`; obligatorio |
| `exchange_rate` | decimal(18,5) | CRC: `1`; USD: BCCR o editado (venta + flag) |
| `exchange_rate_date` | date | Fecha del hecho generador / TC aplicado |
| `crc_equivalent_total` | decimal(18,5) | `total × rate` (CRC: = `total`) |
| `crc_equivalent_iva` | decimal(18,5) | `iva × rate` (CRC: = `iva`) |

Opcional útil (auditoría): `exchange_rate_source` = `bccr` \| `manual` \| `xml` — no bloqueante en Fase 1; si se omite, se infiere (manual solo si flag on y rate ≠ BCCR del día).

**Semántica de montos existentes:** `total`, `iva`, `subtotal`, precios de línea = **nativos** en `currency_code`. No renombrar columnas legacy. No duplicar cada línea en CRC en Fase 1.

**Migración de datos existentes:**

- `currency_code = CRC`
- `exchange_rate = 1`
- `exchange_rate_date = fecha del documento` (o `created_at` date)
- `crc_equivalent_total = total`, `crc_equivalent_iva = iva`

### 7.3 Config empresa

| Clave | Tipo | Default | Efecto |
|-------|------|---------|--------|
| `facturacion_fe.permitir_editar_tipo_cambio` | bool | `false` | Si true: en **ventas** USD pre-emisión el TC es editable; el valor digitado se congela al guardar/emitir y va al XML |

### 7.4 Reglas de validación (servidor)

- `currency_code ∈ {CRC, USD}` (Fase 1).
- Si `CRC`: forzar `exchange_rate = 1`, equivalents = nativos.
- Si `USD` en **venta**:
  - Default: `exchange_rate` = BCCR de `exchange_rate_date`; **ignorar** rate del body si flag off.
  - Si flag on: aceptar rate del body si `> 0` y `≠ 1`; recalcular `crc_equivalent_*`.
  - Sin BCCR y flag off → 422. Sin rate válido y flag on → 422.
- Si `USD` en **compra/gasto**:
  - Manual: rate = BCCR (cliente no puede override en Fase 1).
  - Import XML: ver §12.
- Rechazar `exchange_rate ≤ 0`. Para USD rechazar `exchange_rate == 1` (CRC-only semantics).
- Solo empresas de mercado CR usan selector USD en UI; otros mercados sin cambio.
- Documento ya emitido (FE aceptada/enviada): moneda y TC **inmutables**.

---

## 8. Integración BCCR

### 8.1 Configuración

- Suscripción gratuita al WS de indicadores (correo + token) — **prerrequisito de cuenta**, no de código.
- Env (ejemplo): `BCCR_WS_EMAIL`, `BCCR_WS_TOKEN`, `BCCR_WS_NAME` (y URL del asmx si hace falta override).
- Indicador: **318** (venta).

### 8.2 Comportamiento

1. Job diario temprano (CR): fetch del día → upsert `bccr_tipos_cambio`.
2. Al resolver TC para una fecha:
   - leer tabla;
   - si falta, consultar BCCR por esa fecha (o rango corto) y cachear;
   - si aún falla → excepción de dominio / HTTP 422 (salvo venta con flag on y rate manual válido ya presente).
3. Reintentos del job ante caída del WS; no usar tasa de otro día “por silencio”.

### 8.3 Relación con API Hacienda TC

El proxy `GET /api/fe-cr/tipo-cambio-dolar` puede quedar como utilidad de catálogo/UI, pero **la fuente de verdad default de emisión y persistencia es BCCR 318**, no Hacienda ni exchangerate.host.

---

## 9. Facturación electrónica

### 9.1 Mapper

En `CostaRicaInvoiceFromVentaMapper` (y paths NC / compra / gasto):

```
currency.currency_code  ← documento.currency_code
currency.exchange_rate  ← documento.exchange_rate   // ya redondeado a 5 decimales
```

Dejar de usar `empresa.moneda` para el payload FE (salvo default al crear documento sin moneda explícita).

### 9.2 Pre-validación pre-envío

Antes de firmar/enviar:

- CRC ⇒ rate == 1.
- USD ⇒ rate > 0 && rate != 1 && rate = el persistido en el documento (BCCR o manual si flag lo permitió al guardar).
- Fallo ⇒ no enviar; mensaje accionable (“No hay tipo de cambio BCCR para la fecha X” / “Tipo de cambio inválido”).

### 9.3 XML / PDF

- Generación XML: sin cambios de schema; vendor ya emite `CodigoTipoMoneda`.
- PDF CR: ya lee `CodigoMoneda`/`TipoCambio` del XML; preview pre-emisión muestra nativo + TC + CRC equivalent.

### 9.4 Criterio de aceptación FE

Factura de prueba USD aceptada en sandbox Hacienda sin rechazo por moneda/TC (caso BCCR default y, si aplica, caso con flag + rate manual).

---

## 10. Producto (UI)

### 10.1 Ventas (empresas CR)

1. Selector **CRC | USD** (default = `empresa.moneda` si es CRC/USD; si no, CRC).
2. Al elegir USD: mostrar TC (prefill BCCR), fecha del TC, equivalente CRC del total (e IVA si aplica).
3. Si `permitir_editar_tipo_cambio`: input TC editable; si no: readonly.
4. Sin TC usable: deshabilitar emitir / guardar con mensaje claro.
5. Detalle de documento: moneda, monto nativo, TC, conversión (cálculo visible).
6. Tras emitir: todo readonly.

### 10.2 Compras / gastos (empresas CR)

1. Selector CRC | USD + preview TC BCCR (manual) o datos del XML (import).
2. TC **no** editable en Fase 1.
3. Detalle: misma visibilidad nativo / TC / CRC equivalent.

### 10.3 Listados / dashboards

Montos agregados en CRC (`crc_equivalent_*`); el detalle puede mostrar nativo.

WhatsApp/móvil futuros: misma regla de API (servidor resuelve TC; honra flag en ventas).

---

## 11. Contabilidad y reportería CR

- `ReporteDetalleIvaCrService` y resúmenes que hoy suman `venta.total` / `iva` deben usar `crc_equivalent_total` / `crc_equivalent_iva` (o fallback: si null y CRC, usar nativo).
- Dashboards financieros CR: mismos campos.
- Asientos que se generen desde ventas/compras/gastos en CR deben asentar el **equivalente CRC** (Fase 1 no introduce P&L cambiario).
- Export detalle IVA: `cod_moneda` / `tipo_cambio` desde el documento real, no hardcode CRC/1.

---

## 12. Ingesta XML (email / DTE)

1. Extender parser de resumen XML CR para leer `CodigoMoneda` y `TipoCambio`.
2. Al crear compra/gasto/import desde XML:
   - set `currency_code` del XML (normalizar a CRC/USD; otras monedas → error o diferir a Fase 2);
   - `exchange_rate_date` = fecha del comprobante;
   - preferir rate BCCR de esa fecha para `exchange_rate` y CRC equivalent;
   - si el XML trae rate distinto al BCCR: **usar BCCR** para contabilidad interna y loguear discrepancia (el XML original se conserva); opcional warning en UI.
3. No asumir CRC solo porque la empresa es CR.
4. No aplicar el flag de edición de ventas a compras XML.

---

## 13. Fases de implementación (subtareas)

| Fase | Entregable | Dependencia |
|------|------------|-------------|
| **0** | Suscripción BCCR + env en ambientes | Cuenta |
| **1** | Tabla + client + job + reescritura `CostaRicaTipoCambioService` + test | Fase 0 |
| **2** | Columnas en ventas/compras/gastos + validación al guardar + backfill | Fase 1 |
| **3** | Config empresa `permitir_editar_tipo_cambio` (API + UI settings) | Fase 2 |
| **4** | Mapper FE + pre-validación + sandbox USD | Fase 2 |
| **5** | UI ventas: selector, preview, edición condicional, detalle TC/conversión | Fase 2–3 |
| **6** | UI/API compras-gastos + parser/import XML moneda | Fase 2 |
| **7** | Reportes IVA / dash CRC equivalent | Fase 2 |

Estimación orientativa: **~4–6 semanas-persona** (~3–4 semanas calendario con 2 seniors), asumiendo BCCR listo.

---

## 14. Criterios de aceptación

- [ ] Cliente CR emite factura aceptada por Hacienda en CRC y en USD (sandbox).
- [ ] Default USD: TC desde BCCR; nunca fallback inventado (520 eliminado).
- [ ] Empresa con flag off: no puede editar TC en ventas.
- [ ] Empresa con flag on: puede editar TC en ventas pre-emisión; valor queda en documento y XML; post-emisión inmutable.
- [ ] Compras/gastos soportan moneda por documento; XML no asume CRC; TC no editable en Fase 1.
- [ ] Toda transacción tiene CRC equivalent; reportes/dash CR agregan en CRC.
- [ ] Fecha atrasada usa TC histórico de esa fecha, no “hoy” (salvo override manual en venta con flag).
- [ ] Sin feed BCCR → bloqueo claro al emitir/guardar USD (salvo rate manual válido con flag).
- [ ] UI de detalle muestra moneda, TC, valor de conversión y cálculo de forma visible.

---

## 15. Riesgos y dependencias

| Riesgo | Mitigación |
|--------|------------|
| Suscripción BCCR demorada | Fase 0 bloqueante; escalar a ops/CEO |
| WS BCCR caído | Reintentos job; bloqueo USD (no inventar); flag manual solo si empresa lo habilitó |
| TC manual rechazado por Hacienda | Flag default off; documentar riesgo en settings; CS educa al cliente |
| Reportes olvidan CRC equivalent | Checklist Fase 7 + grep de sumas `venta.total` en servicios CR |
| Empresas ya en `moneda=USD` | Emisión pasa a documento; backfill CRC en históricos; UI default USD |
| Confusión SV vs CR | Feature gated por país CR |

---

## 16. Preguntas abiertas del requisito original — resolución

| # | Pregunta | Resolución en este diseño |
|---|----------|---------------------------|
| 1 | ¿XML ya tiene CodigoMoneda/TipoCambio? | **Sí** — vendor + mapper setean `currency`; falta cablear por documento y TC BCCR |
| 2 | ¿Cuántos clientes necesitan USD? | Producto: Fase 1 igual (P0 doc); Fase 2 condicionada a pipeline |
| 3 | ¿Clientes bloqueados hoy? | Operativo CS; no cambia diseño técnico |
| 4 | ¿Editar TC? | Sí, **configurable por empresa**, solo ventas pre-emisión |
| 5 | ¿Montos nativos? | **Sí** — requeridos para FE/XML y UX |
| 6 | ¿Alcance docs? | Ventas + compras + gastos en Fase 1 |

---

## 17. Referencias

- Requisitos: `CR_Requisitos_Multimoneda.md`
- Hacienda Anexos v4.4 — nodo Código y Tipo de Moneda; USD = TC venta BCCR; CRC ⇒ TipoCambio = 1
- BCCR WS indicadores: indicador 318
- Código baseline: `CostaRicaTipoCambioService`, `CostaRicaInvoiceFromVentaMapper`, `ReporteDetalleIvaCrService`
- Práctica de mercado: Alegra CR — moneda por documento + TC BCCR; nosotros: TC readonly por default + override opt-in por empresa en ventas

---

## 18. Próximo paso

1. Usuario revisa este spec.
2. Plan de implementación en `Docs/superpowers/plans/2026-08-04-cr-multimoneda.md`.
3. Subtareas Jira bajo el issue padre (cuando MCP Atlassian esté visible en la sesión Agent + clave del issue).
