# Análisis de datos — Cierre de mes (Suite Financiera)

Documento de referencia para el backend. Cruza **lo que la UI ya pinta** (`Frontend/src/app/views/dashboard/sections/finanzas/cierre-mes/`) con **lo que el warehouse y producción ya tienen**.

**Estado frontend:** la pestaña llama a `GET /api/finanzas/cierre-mes` vía `FinanzasCierreMesDataService`. Certificar y «Es normal» siguen sin guardar: no hay endpoint de escritura.

**Estado backend:** `GET /api/finanzas/cierre-mes` ya está en `sp-analytics`. Lee el real en `fact_pl_gerencial_mensuales` (consolidado) y el estado de la barra en `cierre_mes_certificaciones`. El esquema se crea con `EJECUCION FINAL/13_cierre_mes.sql` o con `go run . etl cierre`. El presupuesto por rubro sigue en 0, el checklist va vacío y `puedeCertificar` es false. Los impuestos no se inventan: el bloque llega vacío hasta leer el cumplimiento del contador. Esta pantalla no es el cierre contable de `Frontend/src/app/views/contabilidad/cierre-mes/` (balance de comprobación, partidas, asiento de cierre). Aquí se certifica el mes **gerencial**. Certificar no debe generar partida.

Modelado (mapa categoría → rubro, presupuesto por línea bien partido) sigue fuera. Sin eso, varias filas del mock no se pueden armar de verdad.

---

## 1. Qué debe responder la pantalla

El estado del año y el expediente del mes que se está cerrando.

| Bloque | Qué muestra |
|--------|-------------|
| Barra de meses | Doce pastillas: cerrado, en revisión o pendiente. «N de 12». Un mes pendiente no se abre |
| Checklist | Ítems del mes en ok, error o alerta, con una acción. El conteo de pendientes es lo que no está en ok. Certificar queda bloqueado mientras haya pendientes |
| Impuestos | IVA (F-07) y pago a cuenta (F-14) del mes, con vencimiento |
| Presupuesto vs real | Siete filas fijas: ventas netas, costo de ventas y los cinco rubros de gasto del P&L. Presupuesto, real, variación del mes, variación acumulada y un punto de estado |
| Gastos fuera de rango | Categorías cuyo monto del mes supera el promedio de 6 meses por encima de un umbral. Acciones Revisar y Es normal |

Contrato TypeScript: `cierre-mes.model.ts`. El mock solo trae detalle de septiembre 2026 (`mesActivoId` 8). El resto de meses cerrados no tiene expediente.

Los encabezados de presupuesto y real usan la etiqueta del mes seleccionado.

---

## 2. Filtros

| Parámetro | Valores | Efecto |
|-----------|---------|--------|
| `empresa` | id | Obligatorio |
| `anio` | año de la barra | Los doce meses |
| `mes` | 1–12 | Expediente. Si no viene, el mes en revisión |

No hay filtro de sucursal en esta pestaña. El mock habla de sucursales dentro de un ítem («3 sucursales», «San Miguel»), pero los totales son de empresa. Hasta que la barra de sucursal exista aquí, el corte es consolidado (`id_sucursal = 0` en el P&L).

El mes en revisión del mock es septiembre, no el mes calendario. La regla: el activo es el primer mes del año que aún no está certificado y que ya empezó. Los anteriores certificados se ven. Los que todavía no llegan quedan pendientes y deshabilitados.

Base de los montos: **gerencial, sin IVA**, la misma que el P&L. Los impuestos sí son el IVA y el pago a cuenta a enterar, no el gasto sin IVA.

---

## 3. Fórmulas que la pantalla ya usa

| Campo | Cálculo |
|-------|---------|
| Pendientes | Ítems del checklist con estado distinto de `ok` |
| Certificar | Solo el mes activo, y solo cuando pendientes es 0. El botón del mock está siempre deshabilitado |
| Variación del mes | `(real − presupuesto) / \|presupuesto\|`. Si el presupuesto es 0, la variación va `null` y la UI pinta «—» |
| Tono de esa variación | En ventas, por debajo del presupuesto es negativo. En costo y gastos, por encima es aviso o negativo y por debajo es positivo (se gastó menos). El mock no usa un umbral único: financiero +57% es negativo y local +11% es aviso. Hasta tener regla de Modelado, +10% o más en un gasto es aviso y el resto del signo sigue esta convención |
| Variación acumulada | Real de enero a ese mes menos presupuesto de enero a ese mes, en monto y en %. El mock manda la frase ya armada (`+$1,930 (+1%)`). El API manda los dos números y el frontend arma el texto |
| Estado de la fila | `ok` si hay presupuesto y la variación no es aviso. Ventas netas en el mock van en `na` (raya, no punto). Sin presupuesto, `na` |
| Fuera de rango | `(mes − promedio de los 6 meses anteriores) / promedio × 100`, solo si el promedio es mayor que 0 y la variación es ≥ umbral. El mock usa 20. La tabla solo lista las que superan el umbral |
| Vencimiento de impuestos | Día 14 del mes siguiente. «2 días» es la distancia desde hoy hasta esa fecha, en `America/El_Salvador` |

---

## 4. Fuentes que ya existen

| Necesidad | Dónde está | Qué sí da | Qué no da |
|-----------|------------|-----------|-----------|
| Real del mes y acumulado por línea del P&L | `fact_pl_gerencial_mensuales` | Ventas brutas, devoluciones, descuentos, costo y los cinco OPEX, sin IVA, por empresa, sucursal y mes. Consolidado = `id_sucursal = 0` | No trae presupuesto por línea |
| Ventas netas | Esa misma fact | `ventas brutas − devoluciones − descuentos` | |
| Promedio 6 meses de un rubro | La misma fact, seis meses previos | El monto, no el % sobre ventas. El P&L ya compara % de ventas contra 6 meses; esta tabla compara pesos | No parte el rubro en «Devoluciones en San Miguel» |
| Presupuesto de ingresos y egresos | `fact_presupuestos_mensuales` | Un monto de ingresos y uno de egresos por empresa y mes | Sin costo, planilla, local, marketing, admin ni financieros |
| Presupuesto por rubro en producción | Tabla `presupuestos` | `ingresos`, `costo_de_venta`, `planilla`, `alquiler`, `servicios`, `marketing`, `publicidad`, `comisiones`, `gastos_administrativos`, `prestamos`, `impuestos`, entre otras | El ETL copia a cada mes del rango `fecha_inicio`–`fecha_fin` el monto **entero** del presupuesto, y solo ingresos, egresos, utilidad y margen. Si el presupuesto es anual, el fact no es un presupuesto mensual |
| IVA y pago a cuenta | `ContadorCumplimientoService` (Laravel, módulo contadores) | F-07 `iva_a_pagar`, F-14 `total`, vencimiento el 14 del mes siguiente | No está en el warehouse. No hace falta un segundo cálculo de IVA |
| Cierre contable | `contabilidad/cierre-mes` | Periodo, balance, validación de partidas | No alimenta este checklist |

Mapeo de la tabla de presupuesto a columnas que **sí** existen en producción, si más adelante se copian al fact partidas por mes:

| Fila de la UI | Columnas de `presupuestos` |
|---------------|----------------------------|
| Ventas netas | `ingresos` |
| Costo de ventas | `costo_de_venta` |
| Personal | `planilla` |
| Local y servicios | `alquiler` + `servicios` |
| Ventas y marketing | `marketing` + `publicidad` + `comisiones` |
| Administrativos | `gastos_administrativos` |
| Financieros | `prestamos` |

Esa suma es una aproximación. `varios`, `mantenimiento`, `combustible`, `impuestos` e `insumos` no tienen fila. No se reparte el `egresos` total entre las siete filas.

---

## 5. Lo que falta

| Dato de la UI | Por qué no sale hoy | Qué hacer |
|---------------|---------------------|-----------|
| Mes certificado o en revisión | No hay tabla de certificación gerencial | Guardar por empresa, año y mes: certificado o no, y cuándo. No reutilizar el asiento de cierre contable |
| Checklist (nómina el 15 y el 30, ISSS, energía, rentas, día sin ventas, compras sin costo, gasto nuevo) | El mock es una lista escrita a mano. `fact_gastos_mensuales` no tiene día ni «esto se esperaba» | Plantilla que configura la empresa (concepto, día esperado, rubro). Cada ítem se marca ok si hay un gasto o un hecho que lo cubre en esa ventana. Vacío si no hay plantilla: no se inventan los 12 ítems de septiembre |
| Día sin ventas por sucursal | Los hechos de ventas son mensuales | Hace falta grano diario (o una lectura a producción por fecha). Con el total del mes no se sabe si el 15 o el 22 hubo venta |
| Compras sin costo de producto | No hay hecho de líneas de compra sin costo | Conteo en producción (detalle de compra con costo 0). Fuera del warehouse actual |
| Gasto recurrente sin clasificar | El mapeo categoría → rubro P&L es Modelado, todavía no persistido | Hasta entonces este ítem no se emite. No se marca alerta con un nombre inventado |
| Presupuesto de la fila | El fact solo tiene ingresos y egresos, y el monto no está partido por mes | Copiar las columnas de rubro y dividir el rango entre los meses que cubre. Mientras tanto la columna presupuesto va 0 y la variación `null` |
| «Es normal» | Decisión de la persona, no un hecho | Guardar el descarte por empresa, año, mes y categoría para que no vuelva a salir |
| Acciones «Registrar gasto», «Revisar día», «Asignar costos», «Clasificar» | Navegación | El API manda un código (`registrar_gasto`, `revisar_dia`, `asignar_costos`, `clasificar`). El texto del botón lo pone el frontend |

El umbral de fuera de rango puede quedar fijo en 20 hasta que Modelado lo configure. No se publica la frase «umbral +20%» como si saliera de un cálculo: es el parámetro.

«Devoluciones en San Miguel» del mock no es un rubro del P&L. Si se arma la tabla con los rubros que ya existen, esa fila no aparece. Una desviación por sucursal espera grano de gasto por sucursal **y** nombre de categoría, que el fact mensual no guarda (guarda sucursal y tipo, no el concepto «Seguridad privada»).

---

## 6. API

Un endpoint de lectura. Certificar y descartar son escrituras aparte, cuando el expediente exista.

```http
GET /api/finanzas/cierre-mes?empresa=&anio=&mes=
```

Header: `Authorization: Bearer {API_SECRET}`

```json
{
  "anio": 2026,
  "mesActivoId": 8,
  "mesesCerrados": 0,
  "totalMeses": 12,
  "meses": [
    { "id": 0, "etiqueta": "Ene", "estado": "pendiente" }
  ],
  "detalle": {
    "mesTitulo": "",
    "mesCertificar": "",
    "mesEtiquetaCorto": "",
    "checklist": [],
    "puedeCertificar": false,
    "presupuesto": [],
    "impuestos": { "vencimiento": "", "items": [] },
    "gastosFueraRango": { "umbralPct": 20, "filas": [] }
  }
}
```

`id` del mes es 0–11, como el mock. `estado`: `cerrado` | `activo` | `pendiente`.

Cada fila de presupuesto:

| Campo | Tipo |
|-------|------|
| `categoria` | texto fijo de las siete filas |
| `presupuesto`, `real` | montos a dos decimales |
| `varMesPct` | número o `null` |
| `varMesTono` | `neutral` \| `pos` \| `neg` \| `warn` |
| `varAcumMonto`, `varAcumPct` | números. El frontend arma «+$1,930 (+1%)» |
| `estado` | `ok` \| `na` \| `warn` |

Cada fila fuera de rango: `categoria`, `mes`, `promedio6m`, `variacionPct`. Sin lista de acciones: los dos botones ya están en la plantilla.

Checklist: `estado`, `texto`, `accion` opcional con el código de arriba. Listas vacías en `[]`, no `null`.

Impuestos, si se incluyen en este mismo JSON: dos ítems `IVA (F-07)` y `Pago a cuenta (F-14)` con el monto que ya calcula el cumplimiento del contador, y `vencimiento` como fecha (`2026-10-14`) más los días que faltan. El frontend arma «14 oct · 2 días». Si ese servicio no está disponible para la empresa, `items` va `[]` y no se inventa el monto.

### Orden

1. Tabla de certificación y la barra de meses. Sin filas, ningún mes está cerrado y el activo es el mes calendario anterior si ya empezó el actual.
2. Presupuesto vs real: la columna real desde `fact_pl_gerencial_mensuales`. La de presupuesto cuando el fact tenga rubro y monto mensual. Fuera de rango con los mismos rubros y umbral 20.
3. Impuestos desde el cumplimiento que ya existe, sin recalcular el libro IVA.
4. Checklist cuando exista la plantilla de lo esperado. Hasta entonces la lista va vacía y certificar no se habilita con ítems ficticios.

No se copia el cierre contable a esta pestaña.
