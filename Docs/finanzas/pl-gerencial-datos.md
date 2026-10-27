# Análisis de datos — P&L Gerencial (Suite Financiera)

Documento de referencia para diseño de API y reglas de negocio. Cruza **lo que la UI exige** (`Frontend/src/app/views/dashboard/sections/finanzas/pl-gerencial/`) con **lo que el producto ya puede calcular o exponer** (Go Analytics, Laravel, modelado).

**Estado frontend:** P&L consume solo `GET /api/finanzas/pl-gerencial` (sin mocks; ver `Docs/finanzas/suite-financiera-frontend.md`).

---

## 1. Qué debe responder la pantalla

Tres bloques de salida:

| Bloque | Contenido | Uso |
|--------|-----------|-----|
| **KPIs** | 5 tarjetas con valor, tono y sublínea | Resumen ejecutivo del periodo |
| **Estado de resultados** | Tabla con 6 columnas por concepto | Detalle gerencial |
| **Gastos como % de ventas** | Filas con % mes, promedio 6m, delta, sparkline | Tendencia de estructura de costos |

La tabla no es un listado de transacciones: es un **árbol fijo de conceptos** con montos agregados, porcentajes sobre ventas netas, variación vs comparación y acumulado año (YTD).

---

## 2. Contrato de datos (frontend)

### 2.1 Filtros (toolbar — `finanzas.component.ts`)

| Parámetro | Valores | Efecto esperado |
|-----------|---------|-----------------|
| `plPeriodo` | `mes` \| `anio_completo` | Columna principal = mes seleccionado o suma del año; acumulado coherente con el modo |
| `plBase` | `gerencial` (sin IVA) \| `caja` (con IVA) | Misma base en ventas, COGS y OPEX |
| `plSucursalId` | `consolidado` \| `id_sucursal` | Scope de agregación |
| `plComparacion` | `mes_anterior` \| `mes_anterior_2` \| `presupuesto` | Columna “VS.” y sublíneas de KPIs |

**Faltante en toolbar:** año y mes concretos (los dashboards de ventas/gastos ya usan `anio` / `mes` en `DashboardAnalyticsApiService.params`). Hoy `periodoTitulo` / `comparacionTitulo` se arman en el padre, no desde datos reales.

### 2.2 KPIs (`PlKpi` — `pl-gerencial.model.ts`)

Derivados de líneas clave del P&L + comparación:

- Ventas netas
- Utilidad bruta (+ % de ventas en sublínea)
- Utilidad operativa (+ variación % vs comparación)
- Resultado neto (+ % de ventas)
- Margen bruto (+ delta en puntos vs comparación)

### 2.3 Filas del estado de resultados (`PlEstadoResultadosFila`)

Por cada concepto:

| Campo | Significado |
|-------|-------------|
| `mes`, `acum` | Montos del periodo y YTD |
| `pctVentasMes`, `pctVentasAcum` | Línea ÷ ventas netas (mismo scope) |
| `vsAbs`, `vsPct` | Vs periodo de comparación (misma línea) |
| `concepto`, `detalle?`, `indent?`, `estilo?` | Presentación (`normal` \| `subtotal` \| `total`) |

**Jerarquía de líneas del estado de resultados:**

1. Ventas brutas
2. (-) Devoluciones y descuentos
3. **Ventas netas** (subtotal)
4. (-) Costo de ventas
5. **Utilidad bruta** (total)
6. OPEX indentados: Personal, Local y servicios, Ventas y marketing, Administrativos, Financieros
7. **Utilidad operativa** (total)
8. (+/-) Otros no operativos
9. **Resultado neto** (total)

### 2.4 Gastos % ventas (`PlGastoPctVentaFila`)

Mismos rubros que COGS + OPEX (sin “ventas netas” como fila de gasto). Por rubro:

- `pctMes` = rubro / ventas netas del mes
- `promedio6m`, `deltaPts` (mes vs promedio)
- `tendencia[]` — serie histórica (mock ~9 puntos)
- `barTone`, `tendenciaAlcista` — solo UX

---

## 3. Reglas de cálculo

```
Ventas brutas
    └─► (-) Devoluciones y descuentos
            └─► Ventas netas
                    └─► (-) Costo de ventas
                            └─► Utilidad bruta
                                    └─► (-) OPEX por rubro
                                            └─► Utilidad operativa
                                                    └─► (+/-) Otros no operativos
                                                            └─► Resultado neto
```

| Regla | Fórmula / nota |
|-------|----------------|
| Ventas netas | `ventas_brutas + devoluciones_descuentos` (devoluciones en negativo) |
| Utilidad bruta | `ventas_netas + costo_ventas` (COGS en negativo) |
| Utilidad operativa | `utilidad_bruta + sum(OPEX rubros)` |
| Resultado neto | `utilidad_operativa + otros_no_operativos` |
| % ventas | `monto_línea / ventas_netas × 100`; denominador = ventas netas del mismo scope |
| Acumulado | Suma desde 1 ene del `anio` hasta fin del periodo seleccionado |
| VS absoluto | `mes_actual − mes_comparación` (o vs presupuesto del mismo concepto) |
| VS % | `(mes_actual − comparación) / \|comparación\|` (convención de color según tipo de línea) |
| Base gerencial | Montos **sin IVA** en ingresos y gastos deducibles |
| Base caja | Montos **con IVA** donde aplique |

**Convención de signos en UI:** gastos y COGS negativos en `mes`/`acum`; ingresos y utilidades positivos (como el mock).

---

## 4. Mapeo concepto P&L → datos de negocio

### 4.1 Ingresos y COGS

| Línea P&L | Fuente probable | Campos / APIs |
|-----------|-----------------|---------------|
| Ventas brutas | Ventas facturadas del periodo | Go: `/api/ventas/cards` → `ventasConIva`, `ventasSinIva` |
| Devoluciones y descuentos | NC/devoluciones + descuentos en factura | `ventas` + `devoluciones`; descuentos en líneas |
| Costo de ventas | Costo vendido | `detalles.subcosto` / `total_costo`; job `CalcularMetricasSucursales` → `costo_venta_sin_iva`; compras con `es_costo_venta` (referenciado en jobs IA, no en modelo `gastos_categorias`) |

El dashboard **Resultados** (`/api/resultados/cards`, `ventas-gastos-mes`) agrega ventas vs gastos totales — **no** desglosa P&L gerencial.

### 4.2 OPEX por rubro

| Rubro UI | Origen lógico | Gap |
|----------|---------------|-----|
| Personal | Gastos + planilla | Modelado mock: salarios, ISSS, aguinaldo → **Personal**; sin API unificada |
| Local y servicios | Rentas, energía, internet… | Mapeo categoría → rubro (modelado alineado al mock) |
| Ventas y marketing | Publicidad, comisiones | `presupuestos.publicidad`, `comisiones`, `marketing` |
| Administrativos | Gastos admin | `presupuestos.gastos_administrativos`, categorías admin |
| Financieros | Intereses, comisiones bancarias | Préstamos: intereses al P&L; gastos financieros |

Hoy **`gastos_categorias`** (`Categoria.php`) solo tiene `nombre`, `id_empresa`, `id_cuenta_contable` — **no** `rubro_pl` ni `es_costo_venta` en Eloquent. El **Modelado — Paso 1** (clasificación fijo/variable + categoría gerencial) es el lugar natural para persistir el mapeo.

### 4.3 Otros no operativos

No definido en analytics. Opciones: partidas contables fuera de OPEX, ajustes manuales, ingresos extraordinarios. Requiere regla de producto.

### 4.4 Comparación vs presupuesto

- Laravel: `presupuestos` (`Presupuesto` model): `ingresos`, `egresos`, `costo_de_venta`, `planilla`, `servicios`, `marketing`, `gastos_administrativos`, `prestamos`, etc.
- Go: `/api/ventas/vs-presupuesto`, `/api/gastos/vs-presupuesto` (series mensuales, no líneas P&L completas).
- Modelado mock: presupuesto 2026 por mes — puede ser otro store si no se unifica.

Hay que **alinear rubros del mock con columnas de `presupuestos`** o presupuesto mensual del modelado.

---

## 5. APIs existentes (reutilizables, insuficientes solas)

| API (Go Analytics) | Qué da | Para P&L |
|--------------------|--------|----------|
| `/api/ventas/cards`, `por-mes` | Ventas con/sin IVA, series | Ventas brutas/netas parcial |
| `/api/gastos/cards`, `por-mes`, `por-categoria` | Totales y por categoría ERP | OPEX si hay mapeo categoría → rubro |
| `/api/resultados/cards`, `ventas-gastos-mes` | Ventas y egresos con IVA | Vista caja simplificada |
| `/api/ventas/vs-presupuesto`, `/api/gastos/vs-presupuesto` | Real vs presupuesto mensual | Comparación parcial |

Query estándar: `empresa`, `anio`, `mes?`, `sucursal` (`DashboardAnalyticsApiService`).

**Referencia backend Laravel (métricas mensuales):** `CalcularMetricasSucursales` calcula `ventas_sin_iva` / `con_iva`, `egresos_*`, `costo_venta_sin_iva`, comparativas vs mes anterior y `ventas_vs_presupuesto` en `ia_metricas_mensuales_sucursales`.

---

## 6. Checklist de decisiones de negocio

1. **Periodo de corte** — ¿Ventas por fecha de factura o contabilización? ¿Gastos por egreso, pago o registro?
2. **Base gerencial vs caja** — ¿Sin IVA = subtotal en ventas y gastos sin impuesto en todas las líneas? ¿Propinas, retenciones, NC en brutas o solo netas?
3. **COGS** — ¿Solo `subcosto` en ventas o también compras `es_costo_venta`? ¿Consignación, producción, servicios sin inventario?
4. **Rubros P&L** — ¿Fijos (mock) o configurables por empresa? ¿Gasto sin clasificación → Administrativos, excluido o alerta?
5. **Personal** — ¿Solo módulo Gastos o también Planillas + provisión aguinaldo?
6. **Financieros** — ¿Solo intereses o también comisiones, diferencial cambiario?
7. **Consolidado** — ¿Multi-moneda por sucursal?
8. **Presupuesto** — ¿Tabla `presupuestos`, modelado mensual, o ambos?
9. **Otros no operativos** — ¿Catálogo contable, cero en MVP, manual?
10. **Estados de documentos** — ¿Mismos filtros que dashboard Ventas (anuladas, borrador, consigna)?

---

## 7. Propuesta de contrato API

Un endpoint agregado evita inconsistencias entre KPIs, tabla y sparklines.

**Request:**

**Implementado en** repositorio `sp-analytics` (`GET /api/finanzas/pl-gerencial`, ETL `pl_gerencial`, tabla `fact_pl_gerencial_mensuales`).

```http
GET /api/finanzas/pl-gerencial?empresa=&anio=&mes=
  &periodo=mes|anio_completo
  &base=gerencial|caja
  &sucursal=consolidado|{id}
  &comparacion=mes_anterior|mes_anterior_2|presupuesto
```

(`mes` omitido o ignorado cuando `periodo=anio_completo`, según regla de producto.)

**Response (esquema lógico):**

```ts
{
  meta: {
    periodoTitulo: string;
    comparacionTitulo: string;
    anioAcumulado: number;
    base: 'gerencial' | 'caja';
    sucursal: string;
  };
  kpis: PlKpi[];
  estadoResultados: PlEstadoResultadosFila[]; // recomendado: lineId estable por concepto
  gastosPctVentas: { filas: PlGastoPctVentaFila[] };
  warnings?: string[]; // ej. "N gastos sin rubro P&L"
}
```

**Pipeline de cálculo (backend):**

1. Ventas brutas, devoluciones/descuentos, netas (misma base IVA).
2. COGS periodo y YTD.
3. Gastos agregados por `rubro_pl` (clasificación modelado o tabla nueva).
4. Subtotales y totales derivados.
5. % ventas y VS (actual vs comparación o presupuesto por línea).
6. Sparklines: últimos N meses de `%` por rubro (N = 6–9 según diseño).

---

## 8. Fases sugeridas

| Fase | Alcance | Dependencias |
|------|---------|--------------|
| **MVP** | Ventas netas, COGS por costo vendido, OPEX en una línea “Gastos operativos”, utilidad bruta/operativa simplificada, sin IVA, sucursal, vs mes anterior, YTD | Agregador Go o Laravel; lógica tipo `CalcularMetricasSucursales` |
| **MVP+** | OPEX en 5 rubros vía mapeo categoría → rubro | Config persistida (Modelado paso 1) |
| **Completo** | Personal (planilla + aguinaldo), financieros (préstamos), otros no operativos, vs presupuesto por línea, sparklines 6m | Planillas, préstamos, presupuesto unificado |
| **Paridad mock** | Detalles bajo concepto, comparación -2 meses, año completo en columna principal | Cableado filtros toolbar → `PlGerencialComponent` |

---

## 9. Resumen: qué definir antes de desarrollo backend

1. Fuente de verdad por línea (ventas, devoluciones, COGS, cada rubro OPEX, otros).
2. Mapeo **categorías de gasto → rubros P&L** (y si COGS es solo ventas o también compras).
3. Definición exacta **sin IVA / con IVA** por módulo.
4. Si **nómina y provisiones** entran en Personal sin pasar por Gastos.
5. Qué **presupuesto** usa “vs presupuesto” y prorrateo mensual.
6. Política para **datos sin clasificar** (modelado incompleto).
7. **Un endpoint agregado** (recomendado) vs ensamblar en frontend.

---

## 10. Archivos de referencia en el repo

| Área | Ruta |
|------|------|
| Modelos UI | `Frontend/.../finanzas/pl-gerencial/pl-gerencial.model.ts` |
| Servicio API | `Frontend/.../dashboard/services/finanzas-pl-data.service.ts` |
| Componente | `Frontend/.../finanzas/pl-gerencial/pl-gerencial.component.*` |
| Filtros toolbar | `Frontend/.../finanzas/finanzas.component.ts` |
| Analytics cliente | `Frontend/.../dashboard/services/dashboard-analytics-api.service.ts` |
| Ventas / gastos / resultados | `ventas-dashboard-data.service.ts`, `gastos-dashboard-data.service.ts`, `resultados-dashboard-data.service.ts` |
| Modelado (mapeo rubros mock) | `Frontend/.../finanzas/modelado/modelado.mock.ts` |
| Presupuesto Laravel | `Backend/app/Models/Contabilidad/Presupuesto.php` |
| Métricas mensuales | `Backend/app/Console/Commands/CalcularMetricasSucursales.php` |

---

*Última actualización: octubre 2026 — alineado a mocks P&L gerencial v4 y suite Finanzas en dashboard.*
