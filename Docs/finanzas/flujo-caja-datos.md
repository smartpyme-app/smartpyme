# Análisis de datos — Flujo de caja (Suite Financiera)

Documento de referencia para el backend en `sp-analytics`. Cruza **lo que la UI ya pinta** (`Frontend/src/app/views/dashboard/sections/finanzas/flujo-caja/`) con **los hechos que el warehouse ya tiene**.

**Estado frontend:** la pestaña llama a `GET /api/finanzas/flujo-caja` vía `FinanzasFlujoCajaDataService`. Los sliders vuelven a pedir la proyección. Editar efectivo inicial y configurar salidas siguen sin persistencia en producción.

**Estado backend:** `GET /api/finanzas/flujo-caja` ya está en `sp-analytics`. Las vistas `vw_flujo_cxc_abierta` y `vw_flujo_cxp_abierta` leen la foto del último mes de CXC y CXP. El efectivo inicial, el colchón y las salidas con fecha viven en `flujo_caja_parametros` y `flujo_caja_salidas_fijas`. El esquema se crea con `EJECUCION FINAL/12_flujo_caja.sql` o con `go run . etl flujo` (también entra en el ETL completo, después de rentabilidad). La serie de 12 semanas se calcula en el request (`cobroPct`, `metaPct`); no hay un hecho semanal. Si la empresa no tiene efectivo inicial guardado, la posición de cuentas sí responde y la proyección viene vacía.

Modelado (gasto fijo/variable, presupuesto por área) queda fuera de este corte. El colchón y el efectivo inicial son datos que pone la empresa, no un hecho de ventas.

---

## 1. Qué debe responder la pantalla

Una proyección de efectivo a 12 semanas, más la posición de hoy (qué cobrar, qué pagar, qué sale fijo). Los textos de las tarjetas («S3 - 26 oct – 1 nov», «supuesto 90%») los arma el frontend con números. El API no escribe el párrafo de `resumenEjecutivo`: ese campo existe en el mock y **la plantilla no lo muestra**.

| Bloque | Qué muestra |
|--------|-------------|
| Tarjetas | Punto más bajo a 4 y a 12 semanas, diferencia contra el colchón, % de cobro y de meta que harían falta, cuántas semanas quedan bajo el colchón |
| Barra de contexto | Efectivo inicial y su fecha, colchón, ciclo de efectivo en días |
| Supuestos | Dos sliders: cobro de CXC (50–100) y cumplimiento de la meta de ventas (70–130). Cada uno marca el valor histórico o el ritmo actual |
| Gráfico | 12 semanas: neto positivo, neto negativo, saldo y una banda de escenarios. Línea horizontal del colchón |
| Posición | CXC por antigüedad y a quién llamar; CXP por vencimiento y pagos grandes; lista de salidas fijas e impuestos con fecha |
| Tabla semanal | Por semana: inicial, ventas sin IVA, de contado, cobros, fijos, impuestos, proveedores, otros, neto, final. La celda de ventas se puede editar |
| Desglose | Al tocar una semana: entradas y salidas de esa semana |

Contrato TypeScript: `flujo-caja.model.ts`.

La vista «Mensual» está en el selector y no tiene serie propia. Hasta que exista, el API puede ignorarla y la pantalla sigue en semanas.

---

## 2. Filtros

| Parámetro | Valores | Efecto |
|-----------|---------|--------|
| `empresa` | id | Obligatorio |
| `sucursal` | vacío = consolidado, o id | Misma sucursal que el P&L y Rentabilidad. CXC sí tiene sucursal. CXP no |
| `cobroPct` | 50–100 | Qué parte de la CXC cobrable se supone que entra en el horizonte |
| `metaPct` | 70–130 | Escala las ventas pronosticadas respecto a la meta |

El corte es **hoy**, no el mes del P&L. Las 12 semanas empiezan en la semana en curso.

Base de la proyección: **caja** (dinero que entra y sale). No es el P&L gerencial sin IVA. La columna «Ventas (sin IVA)» del mock es el pronóstico de venta; lo que entra al saldo es «de contado» más «cobros».

---

## 3. Fórmulas que la tabla ya usa

En `flujo-caja-semanal.component.ts`, al editar ventas de una semana:

```
de contado = ventas sin IVA × pctContado
neto       = de contado + cobros − fijos − impuestos − proveedores − otros
final      = inicial + neto
inicial de la semana siguiente = final de esta
```

`pctContado` del mock es 60. `cobros` viene en `null` en todas las filas, así que hoy suma 0. Los sliders de la izquierda **no** entran en esta cuenta: el gráfico se vuelve a dibujar con la misma serie del mock.

Lo que el motor tiene que calcular, y la pantalla solo mostrar:

| Campo | Cálculo |
|-------|---------|
| `puntoBajo4Semanas` / `puntoBajo12Semanas` | Mínimo de `saldo` en las primeras 4 semanas y en las 12. La sublínea es el id y el rango de esa semana |
| `vsColchon` | Ese mínimo de 4 semanas − colchón. Negativo = por debajo |
| `semanasBajoColchon` | Cuántas de las 12 tienen `saldo` < colchón |
| `cobroNecesarioPct` | El menor `cobroPct` con el que el mínimo de 4 semanas deja de estar bajo el colchón. Si ni 100% alcanza, 100 |
| `metaNecesariaPct` | Igual, moviendo solo el cumplimiento de la meta, con el cobro en su valor histórico |
| Banda del gráfico | El mock dice −10% / +12% sobre ventas. `saldoBajo` y `saldoAlto` son el saldo de esa semana con esa banda. No hace falta otra frase: el frontend puede armar la nota |
| `cicloEfectivoDias` | Días de cobro + días de inventario − días de pago. El mock parte 30 + 52 − 32 = 50 |

El eje del gráfico hoy está fijo de −15.000 a 45.000. Con datos reales el mínimo y el máximo salen de la serie, no de esa constante.

---

## 4. Fuentes que ya existen

| Necesidad | Hecho | Qué sí da | Qué no da |
|-----------|--------|-----------|-----------|
| Antigüedad de CXC y saldo por factura | `fact_cuentas_cobrar_mensuales` | Cliente, sucursal, `fecha_venta`, `fecha_pago_programada`, saldo con y sin IVA, `dias_vencimiento`, bucket (`Corriente`, `1-30`, `31-60`, `61-90`, `90+`) | No es un calendario de cobro futuro. Es la foto al cierre del mes |
| Antigüedad de CXP y vencimiento | `fact_cuentas_pagar_mensuales` | Proveedor, compra o gasto, correlativo, `fecha_vencimiento`, saldo, bucket. Sin sucursal | El pago no está partido por semana |
| Ventas del mes | `fact_ventas_netas_mensuales` | Ventas sin IVA y con IVA por mes y sucursal | No separa contado vs crédito por semana |
| Meta del mes | `fact_presupuestos_mensuales` | `presupuesto_ingresos` y `presupuesto_egresos` por empresa y mes | Un monto. Sin calendario semanal |
| Gastos del mes | `fact_gastos_mensuales` | Tipo, fuente, con y sin IVA, por mes | Sin día de pago. No distingue nómina del día 15 vs alquiler del día 1 |
| Días de inventario | Hecho de inventario del ETL `inventario` | Stock y salidas para días de cobertura | No es el ciclo de cobro ni el de pago |

Los buckets de la UI de CXC no son los del hecho. Mapeo hasta que se unifiquen:

| UI | Hecho |
|----|--------|
| Por vencer | `Corriente` y saldo con `fecha_pago_programada` ≥ hoy |
| 1–30 días vencido | `1-30 dias` |
| 31–60 días vencido | `31-60 dias` |
| Más de 60 días (no se proyecta) | `61-90` + `90+`. Ese monto se muestra y **no** entra al pronóstico de cobro |

CXP en la UI: vence en 7 días o menos, vence después, vencido. El hecho trae `fecha_vencimiento` y `dias_vencimiento`, así que esos tres cortes se arman en la consulta. No hace falta un bucket nuevo.

«A quién llamar primero» son las facturas de CXC con saldo, ordenadas por monto o por días vencidos, tope 3. `urgente` si lleva más de 15 días vencida. «Pagos grandes» son las CXP de mayor saldo con fecha de vencimiento dentro de las 12 semanas, tope 3. `urgente` si vence en 7 días o menos.

---

## 5. Lo que falta

| Dato de la UI | Por qué no sale hoy | Qué hacer |
|---------------|---------------------|-----------|
| Efectivo inicial | No hay hecho de bancos ni de caja | Lo captura la empresa (el botón «editar»). Se guarda por empresa, no se inventa con el saldo de CXC |
| Colchón | No hay parámetro en analytics | Lo mismo: un monto guardado por empresa. El mock usa 4.800 |
| Salidas fijas con fecha (nómina, renta, IVA, préstamo) | `fact_gastos_mensuales` no tiene día ni recurrencia | Lista que configura la empresa (el botón «configurar»), o fechas reales de egresos futuros si producción las tiene. Vacío si no hay lista |
| Ventas de contado de la semana que viene | El hecho de ventas es mensual e histórico | Pronóstico: meta del mes × `metaPct`, repartida en las semanas de ese mes. La parte de contado es un % histórico (ventas cobradas el mismo mes / ventas), no el 60% fijo del mock |
| Cobros de las próximas semanas | La CXC es una foto mensual | Cada factura cobrable (no el bucket de más de 60 días) cae en la semana de `fecha_pago_programada`, escalada por `cobroPct`. Si no hay fecha, no se reparte a ojo |
| Compras y proveedores por semana | CXP tiene vencimiento, el gasto mensual no | Usar `fecha_vencimiento` de `fact_cuentas_pagar_mensuales` para lo ya comprometido. No prorratear el gasto del mes entre 4 semanas |
| Banda −10% / +12% | Es una regla del mock | Dejarla fija en el API hasta que Modelado la configure. Dos corridas del mismo motor |
| Precisión «±6% en 4 semanas» | No hay pronóstico guardado contra el saldo real | No se publica. La nota al pie no inventa ese porcentaje |
| Vista mensual | El selector no tiene datos | Fuera de este corte |

El ciclo de efectivo se puede aproximar sin hecho nuevo:

- Días de cobro: promedio de (`fecha` de cobro − `fecha_venta`) en CXC pagada reciente. Si el hecho no guarda la fecha de cobro, usar `dias_vencimiento` solo como plazo pactado y dejar el ciclo en blanco.
- Días de inventario: el mismo cálculo de cobertura que Rentabilidad.
- Días de pago: promedio contra `fecha_vencimiento` en CXP. Si no hay fecha de pago real, no restar un 32 inventado.

Si falta una pata, `cicloEfectivoDias` va `null`. La barra muestra «—», no 50.

---

## 6. API

Un endpoint. Los sliders vuelven a pedir la serie. No se recalcula el pronóstico solo en el navegador.

```http
GET /api/finanzas/flujo-caja?empresa=&sucursal=
  &cobroPct=90&metaPct=100
```

Header: `Authorization: Bearer {API_SECRET}`

Cuerpo, alineado a los mocks que la pantalla ya parte en cuatro piezas (`FLUJO_CAJA_MOCK`, `FLUJO_POSICION_MOCK`, `FLUJO_SEMANAL_DETALLE_MOCK`, desglose por semana):

```json
{
  "kpis": {
    "puntoBajo4Semanas": 0,
    "semanaBajo4": "S3",
    "rangoBajo4": "26 oct – 1 nov",
    "vsColchon": 0,
    "puntoBajo12Semanas": 0,
    "semanaBajo12": "S3",
    "rangoBajo12": "26 oct – 1 nov",
    "cobroNecesarioPct": 0,
    "metaNecesariaPct": 0,
    "semanasBajoColchon": 0,
    "totalSemanasProyeccion": 12
  },
  "efectivoInicial": 0,
  "efectivoInicialFecha": "",
  "colchon": 0,
  "cicloEfectivoDias": null,
  "cobroCxCHistoricoPct": 0,
  "metaRitmoPct": 0,
  "pctContado": 0,
  "semanas": {
    "labels": [],
    "neto": [],
    "saldo": [],
    "saldoBajo": [],
    "saldoAlto": []
  },
  "posicion": {
    "cuentasCobrar": { "total": 0, "segmentos": [], "llamarPrimero": [] },
    "cuentasPagar": { "total": 0, "segmentos": [], "pagosGrandes": [] },
    "salidasFijas": { "items": [] }
  },
  "detalle": { "filas": [], "notaPie": "" },
  "desgloses": []
}
```

`kpis.*Sub` del mock no viaja como frase. Viajan `semanaBajo4` y `rangoBajo4`, y el frontend arma «S3 - 26 oct – 1 nov». La sublínea «supuesto N%» sale del slider, no del API.

Montos con dos decimales. Listas vacías en `[]`, no `null`.

Si no hay efectivo inicial guardado, las tarjetas de proyección y el gráfico van vacíos y la posición de CXC/CXP sí se muestra: esa parte no depende del saldo de caja.

### Orden

1. Posición de CXC y CXP sobre los hechos actuales. Ya se puede ver sin pronóstico.
2. Efectivo inicial y colchón guardados por empresa, más la lista de salidas fijas.
3. Motor de 12 semanas con `cobroPct` y `metaPct`. Ahí entran gráfico, tabla, desglose y tarjetas de punto bajo.

El motor de las 12 semanas responde en el API y la pestaña de Suite Financiera ya lo consume. No se publica una serie semanal inventada a partir del gasto del mes.
